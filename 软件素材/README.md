# 虚拟主机CDN

虚拟主机可用的轻量级 CDN 源码，纯 PHP 原生实现，无需数据库、无 Composer 依赖，**可一行代码植入任意 PHP 源码**，并内置可扩展的插件系统。

## 特性

- **纯 PHP**：PHP 5.6+ / 7.x / 8.x 均可运行，仅依赖 `curl` 扩展（虚拟主机通常自带）
- **文件缓存**：本地文件存储，带 TTL 过期与原子写入，无需数据库
- **可植入**：任意 PHP 页面头部 `require` 一行即可整页缓存，二次访问不再执行原脚本
- **独立模式**：配合 `.htaccess` / Nginx rewrite 把所有请求交给 CDN，未命中自动回源
- **资源分流**：按正则规则分流图片 / 静态页面走 CDN，动态页面穿透回源，原站继续在用
- **后台管理**：清缓存 / 命中率统计 / 插件可视化开关，Web 界面操作
- **插件系统**：6 个钩子可自由扩展，内置 3 个开箱即用的插件
- **精细分片**：按 URL + 查询串 + 移动/PC 分别缓存，避免串站与响应式错位

## 目录结构

```
虚拟主机CDN/
├── cdn.php               # 核心入口（植入模式 / 独立模式）
├── router.php            # 内置服务器路由（仅本地 php -S 测试用）
├── config.default.php    # 默认配置
├── config.example.php    # 用户配置模板（复制为 config.php 使用）
├── .htaccess             # Apache 重写规则（独立模式）
├── .gitignore
├── Cache/
│   └── FileCache.php     # 文件缓存实现
├── Plugin/
│   ├── Hooks.php         # 钩子名常量
│   ├── BasePlugin.php    # 插件基类
│   └── PluginManager.php # 插件注册与分发（支持运行时启用/禁用）
├── Plugins/
│   ├── UAFilter.php      # UA 分类/过滤插件
│   ├── CacheControl.php  # Cache-Control 注入插件
│   └── Gzip.php          # gzip 压缩插件
├── lib/
│   ├── Auth.php          # 后台登录认证
│   └── Stats.php         # 命中率统计（按日分片 JSON 存储）
├── admin/
│   ├── index.php         # 后台登录 + 仪表盘
│   ├── stats.php         # 命中率统计页面
│   ├── cache.php         # 清缓存页面
│   ├── plugins.php       # 插件开关页面
│   └── action.php        # 后台动作处理（清缓存/切换开关/登出）
├── runtime-cache/        # 运行时缓存目录（须 Web 用户可写，勿提交到版本库）
├── stats/                # 统计数据目录（自动生成）
└── example/
    ├── index.php         # 植入示例
    └── origin_root/      # 模拟源站（本地测试用）
```

> 注意：`runtime-cache/` 与 `Cache/` 目录名不可只差大小写。Windows 文件系统不区分大小写，
> 若把缓存目录命名为 `cache/`，会与 `Cache/` 源码目录冲突，导致缓存文件混入源码目录。

## 快速开始

### 方式 1：植入到任意现有 PHP 项目（推荐）

在需要缓存的页面**最前面**插入：

```php
<?php
require_once __DIR__ . '/虚拟主机CDN/cdn.php';

\SimpleCdn\Cdn::enable([
    'cache_dir' => __DIR__ . '/runtime-cache',
    'ttl'       => 3600,
    'cdn_rules' => [
        ['pattern' => '\.(jpg|jpeg|png|gif|webp|svg|ico)$', 'action' => 'cdn'],
        ['pattern' => '^/admin/',                            'action' => 'bypass'],
    ],
    'plugins'   => [
        'SimpleCdn\Plugins\UAFilter',
        'SimpleCdn\Plugins\CacheControl',
        'SimpleCdn\Plugins\Gzip',
    ],
]);

// 原始源码继续编写，首次访问会被捕获并缓存
```

首次访问：脚本正常执行 → 输出被捕获 → 写入缓存 → 输出给用户。
二次访问：直接读缓存输出 → **原脚本完全不执行**（响应头 `X-SimpleCDN: HIT1` 可验证）。

### 方式 2：作为独立 CDN 站点

1. 将 `虚拟主机CDN/` 部署到虚拟主机站点根目录
2. 复制 `config.example.php` 为 `config.php`，填写 `origin` 为回源站点地址
3. 确保 `runtime-cache/` 对 Web 运行用户可写
4. Apache 自动读取 `.htaccess`；Nginx 需手动加 rewrite（见下文）

未命中时自动回源 `origin + 请求路径`，缓存后输出。

## 配置

配置优先级：**环境变量 > `config.php` > `config.default.php`**

| 配置项 | 默认值 | 说明 |
|--------|--------|------|
| `origin` | `https://your-origin.com` | 回源站点地址（独立模式必填） |
| `cache_dir` | `runtime-cache` | 缓存目录，须可写 |
| `stats_dir` | `stats` | 统计数据目录（无数据库，按日期分片 JSON） |
| `stats_enable` | `true` | 是否启用命中率统计 |
| `ttl` | `3600` | 缓存有效期（秒） |
| `cache_key_salt` | `simple-cdn` | 缓存键盐值，一机多站时改成不同值 |
| `separate_ua` | `true` | 是否按 移动端 / PC 端 分别缓存 |
| `path_prefix` | `''` | CDN 自身路径前缀，部署在 `/cdn/` 下时填 `/cdn` |
| `exclude_deprecated` | `true` | 屏蔽 Deprecated 警告，防止污染缓存内容 |
| `origin_proxy` | `''` | 回源代理，默认空 = 直连源站 |
| `admin_password` | `''` | 后台登录密码（必填，否则后台无法访问） |
| `plugins` | `[]` | 启用的插件类名列表 |

### CDN 分流规则（`cdn_rules`）

按顺序匹配，先匹配的动作生效：

| 动作 | 说明 |
|------|------|
| `cdn` | 走 CDN 缓存（命中则输出，未命中回源后缓存） |
| `origin` | 强制穿透回源，不缓存 |
| `bypass` | 直接放行（如后台），不缓存 |

示例配置：

```php
'cdn_rules' => [
    ['pattern' => '\.(jpg|jpeg|png|gif|webp|svg|ico|bmp)$',  'action' => 'cdn'],
    ['pattern' => '\.(html|htm|css|js|json|xml|txt)$',       'action' => 'cdn'],
    ['pattern' => '^/admin/',                                'action' => 'bypass'],
],
```

上面的规则表示：图片和静态页面走 CDN 缓存，`/admin/` 下的请求直接放行（不缓存）。

环境变量覆盖（便于本地测试与一机多站）：

```bash
SIMPLE_CDN_ORIGIN=http://127.0.0.1:8898 \
SIMPLE_CDN_CACHE_DIR=/path/to/cache \
SIMPLE_CDN_TTL=300 \
SIMPLE_CDN_ADMIN_PASSWORD=test123 \
SIMPLE_CDN_STATS_ENABLE=1 \
SIMPLE_CDN_PLUGINS="SimpleCdn\Plugins\UAFilter,SimpleCdn\Plugins\CacheControl,SimpleCdn\Plugins\Gzip" \
php -S 127.0.0.1:8899 -t . router.php
```

| 环境变量 | 对应配置 |
|----------|----------|
| `SIMPLE_CDN_ORIGIN` | `origin` |
| `SIMPLE_CDN_CACHE_DIR` | `cache_dir` |
| `SIMPLE_CDN_STATS_DIR` | `stats_dir` |
| `SIMPLE_CDN_STATS_ENABLE` | `stats_enable` |
| `SIMPLE_CDN_TTL` | `ttl` |
| `SIMPLE_CDN_PATH_PREFIX` | `path_prefix` |
| `SIMPLE_CDN_SALT` | `cache_key_salt` |
| `SIMPLE_CDN_ADMIN_PASSWORD` | `admin_password` |
| `SIMPLE_CDN_PLUGINS` | `plugins`（逗号分隔） |

## Nginx 重写配置

```nginx
server {
    listen 80;
    server_name cdn.example.com;
    root /www/wwwroot/cdn;

    location / {
        try_files $uri $uri/ /cdn.php?$query_string;
    }

    location ~ \.cache$ {
        deny all;   # 禁止直接下载缓存文件
    }
}
```

## 插件开发

可用钩子：`request_start`、`cache_hit`、`cache_miss`、`before_output`、`after_write`、`error`

```php
<?php
namespace SimpleCdn\Plugins;

use SimpleCdn\Plugin\BasePlugin;
use SimpleCdn\Plugin\Hooks;

class MyPlugin extends BasePlugin
{
    public function getListens(): array
    {
        return [Hooks::BEFORE_OUTPUT, Hooks::ERROR];
    }

    public function onBeforeOutput(array $params): void
    {
        // 可修改 $params['content']（按引用传入）
    }

    public function onError(array $params): void
    {
        // 处理回源错误
    }
}
```

放入 `Plugins/` 目录后，在配置的 `plugins` 数组中写 `'SimpleCdn\Plugins\MyPlugin'` 即可启用。

后台可通过 Web 界面随时启用/禁用已注册的插件，无需重启 Web 服务器。

内置插件说明：

| 插件 | 作用 |
|------|------|
| `UAFilter` | 命中爬虫/命令行工具 UA 时加 `X-CDN-Bypass: 1` 标记 |
| `CacheControl` | 注入 `Cache-Control` 与 `X-SimpleCDN: HIT0/1`（0=回源，1=命中） |
| `Gzip` | 客户端支持时压缩输出；自动跳过 `cli-server` SAPI、已开启 zlib 自动压缩、已有 `Content-Encoding` 三种情况 |

## 后台管理

访问 `/admin/` 进入管理界面，需先设置 `admin_password`。

| 页面 | 功能 |
|------|------|
| `/admin/` | 仪表盘：总请求、命中数、回源数、命中率、插件状态 |
| `/admin/stats.php` | 命中率统计：按路径分片的详细数据 |
| `/admin/cache.php` | 清缓存：清空全部 `.cache` 文件 |
| `/admin/plugins.php` | 插件开关：启用/禁用已注册插件 |

## 部署注意事项

1. **缓存目录权限**：`runtime-cache/` 须对 Web 运行用户可写（虚拟主机面板通常可设置）。
2. **CDN 节点必须直连源站**：若源站前面有广告过滤、注入脚本的代理，这些中间层改写后的内容会被缓存下来。
   生产环境请确保 CDN 回源不经过任何改写层（`origin_proxy` 默认已置空）。
3. **只缓存 200 响应**：非 200 状态码（如回源失败的 502、源站 404）不会写入缓存。
4. **不缓存个性化页面**：登录态、含 CSRF token、含用户数据的页面不应整页缓存。
5. **PHP 内置服务器限制**：`cli-server` SAPI 会强制剥离 `Content-Encoding` 与 `Content-Length` 头，
   且不支持 `.htaccess`。本地测试用 `router.php`，生产请用 Apache/Nginx。

## 本地测试

```bash
bash start-local-test.sh     # Git Bash / Linux
start-local-test.bat         # Windows 双击运行
```

脚本会启动两个 PHP 内置服务器：

| 服务 | 地址 | 说明 |
|------|------|------|
| 模拟源站 | `http://127.0.0.1:8898/index.php` | 回源目标 |
| CDN 站点 | `http://127.0.0.1:8899/` | 植入模式 `example/index.php`、独立模式 `index.php` |
| 后台管理 | `http://127.0.0.1:8899/admin/` | 需设置 `SIMPLE_CDN_ADMIN_PASSWORD` |

验证：

```bash
curl -i http://127.0.0.1:8899/example/index.php   # 首次 X-SimpleCDN: HIT0
curl -i http://127.0.0.1:8899/example/index.php   # 再次 X-SimpleCDN: HIT1
```

语法检查：

```bash
php -l cdn.php && php -l config.default.php && php -l router.php
```
