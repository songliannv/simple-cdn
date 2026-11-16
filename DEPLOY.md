# Simple CDN 部署指南

> **GitHub 源码仓库**：https://github.com/songliannv/simple-cdn  
> **功能加强配套**：https://github.com/songliannv/media-library（媒体库功能加强，本 CDN 为其配套源码）

---

## 一、环境要求

| 项 | 要求 | 说明 |
|---|---|---|
| Web 服务器 | Apache（支持 `.htaccess`）或 Nginx | 需配置伪静态重写 |
| PHP 版本 | 5.6 / 7.x / 8.x | 推荐 7.4+ |
| PHP 扩展 | curl | 必须，用于回源抓取 |
| 数据库 | 不需要 | 纯文件缓存，零数据库依赖 |
| 目录权限 | `runtime-cache/`、`stats/`、`purge_logs/`、`health_checks/`、`warm/` | 需对 Web 运行用户可写 |

---

## 二、快速部署

### 1. 上传文件

将仓库中的所有文件上传到虚拟主机站点根目录：

```text
你的站点根目录/
├── cdn.php               # CDN 核心入口
├── router.php            # 内置服务器路由（本地测试用）
├── config.default.php    # 默认配置
├── config.example.php    # 用户配置模板（复制为 config.php 使用）
├── .htaccess             # Apache 重写规则
├── Cache/
├── Plugin/
├── Plugins/
├── lib/
├── admin/                # 后台管理
├── README.md
├── 安装说明.md
├── 功能说明.md
└── nodes.json            # 节点配置示例
```

### 2. 创建配置文件

```bash
cp config.example.php config.php
```

### 3. 修改关键配置

打开 `config.php`，按需修改：

```php
return [
    // 回源站点地址（必填）
    'origin'      => 'https://www.your-site.com',

    // 缓存目录（确保可写）
    'cache_dir'   => __DIR__ . '/runtime-cache',

    // 统计数据目录
    'stats_dir'   => __DIR__ . '/stats',

    // 是否启用命中率统计
    'stats_enable' => true,

    // 缓存有效期（秒），默认 3600 秒 = 1 小时
    'ttl'         => 3600,

    // 后台登录密码（必填，部署时务必修改为强密码）
    'admin_password' => '你的强密码',

    // CDN 分流规则
    'cdn_rules' => [
        ['pattern' => '\.(jpg|jpeg|png|gif|webp|svg|ico|bmp)$',  'action' => 'cdn'],
        ['pattern' => '\.(html|htm|css|js|json|xml|txt)$',       'action' => 'cdn'],
        ['pattern' => '^/admin/',                                'action' => 'bypass'],
    ],

    // 启用的插件
    'plugins'     => [
        'SimpleCdn\Plugins\UAFilter',
        'SimpleCdn\Plugins\CacheControl',
        'SimpleCdn\Plugins\Gzip',
    ],
];
```

### 4. 验证部署

1. **访问首页**：`http://你的域名/`
   - 首次访问应返回源站内容，响应头含 `X-SimpleCDN: HIT0`
   - 第二次访问应命中缓存，响应头含 `X-SimpleCDN: HIT1`

2. **访问后台**：`http://你的域名/admin/`
   - 使用 `config.php` 中设置的 `admin_password` 登录
   - 后台可查看命中率、清缓存、切换插件开关

3. **检查缓存目录**：`runtime-cache/` 下应自动生成 `.cache` 文件

---

## 三、Nginx 伪静态（如不用 Apache）

如果你的虚拟主机使用 Nginx，需要手动添加伪静态规则：

```nginx
location / {
    try_files $uri $uri/ /cdn.php?$query_string;
}

# 禁止直接访问敏感目录
location ~ /(runtime-cache|stats|purge_logs|health_checks|warm|lib|Plugin|Plugins|Cache) {
    deny all;
    return 404;
}
```

---

## 四、多节点 CDN 配置（可选）

如需搭建多节点 CDN，在 `config.php` 中启用多节点模式：

```php
'multi_node_enable' => true,
'main_domain'       => 'https://cdn.your-site.com',
```

然后在后台 `/admin/nodes.php` 添加节点，每个节点配置：
- 节点 ID、名称、域名、回源站点
- 覆盖地区（如 CN-BJ、CN、* 等）
- 权重、启用/禁用状态

---

## 五、缓存清理（Purge）配置

如需通过外部 HTTP 接口触发缓存清理，在 `config.php` 中配置：

```php
'purge_enable'  => true,
'purge_token'   => '你的强随机密钥',
```

清理接口示例：

```bash
# 仅清理主站
curl "https://cdn.your-site.com/?purge=1&token=你的密钥"

# 清理主站 + 通知所有节点
curl "https://cdn.your-site.com/?purge=1&token=你的密钥&all_nodes=1"
```

---

## 六、升级说明

升级时仅覆盖以下文件/目录，**不要覆盖 `config.php`**：

```
cdn.php
router.php
config.default.php
Cache/
Plugin/
Plugins/
lib/
admin/
.htaccess
```

`config.php` 是你的用户配置，保留即可。

---

## 七、安全加固

1. **修改后台密码**：部署后务必修改 `config.php` 中的 `admin_password`
2. **设置 purge_token**：生产环境务必设置强随机清理密钥
3. **目录权限**：仅 `runtime-cache/`、`stats/`、`purge_logs/`、`health_checks/`、`warm/` 需可写，其他目录保持只读
4. **eval() 限制**：极少数虚拟主机可能禁用了 `eval()`，如部署后页面空白，请联系主机商开启，或使用明文版本

---

## 八、版本

| 版本 | 日期 | 主要变更 |
|------|------|----------|
| v1.2.4 | 2026-11-16 | 免费源码加密混淆，仓库扁平化，JS/CSS 分离，多重缓存 |
| v1.2.3 | 2026-11-16 | 缓存预热同步、清理日志CSV/JSON导出、多节点负载均衡调度 |
| v1.2.2 | 2026-11-16 | 节点健康检查、节点间缓存同步、清理日志审计 |
| v1.2.1 | 2026-11-16 | 主站一键清理缓存 + 节点联动清理，HTTP purge 接口 |
| v1.2.0 | 2026-11-16 | 多节点 CDN + 地区智能调度 |
| v1.1.0 | 2026-11-16 | 资源分流规则、后台管理、统计无数据库存储 |
| v1.0.0 | 2026-11-16 | 基础缓存功能，植入模式与独立模式 |

---

© 2026 虚拟主机CDN
