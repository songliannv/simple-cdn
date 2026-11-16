<?php
/**
 * Simple CDN 用户配置（复制为 config.php 后生效）
 *
 * 部署时把本文件复制为 config.php 并按需修改即可，
 * 升级覆盖 config.default.php 时不会影响这里的设置。
 */
return [
    // 回源站点地址（独立模式必填）
    'origin'      => 'https://your-origin.com',

    // 缓存目录，必须对 Web 运行用户可写
    'cache_dir'   => __DIR__ . '/runtime-cache',

    // 统计数据目录（无数据库，按日期分片 JSON 存储）
    'stats_dir'   => __DIR__ . '/stats',

    // 是否启用命中率统计
    'stats_enable' => true,

    // 缓存有效期（秒）
    'ttl'         => 3600,

    // 缓存键盐值：同一台主机部署多个站点时改成不同值，避免缓存串站
    'cache_key_salt' => 'simple-cdn',

    // 是否按 移动端 / PC 端 分别缓存
    'separate_ua' => true,

    // CDN 站点自身的路径前缀，例如部署在 /cdn/ 下则填 '/cdn'，回源时自动剥离
    'path_prefix' => '',

    // 后台登录密码（必填，否则后台无法访问）
    'admin_password' => 'admin123',   // 部署时请务必修改为强密码

    // CDN 分流规则：按顺序匹配，先匹配的动作生效
    //   cdn   = 走 CDN 缓存（命中则输出，未命中回源后缓存）
    //   origin = 强制穿透回源，不缓存
    //   bypass = 直接放行（如后台），不缓存
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

    // ==================== 多节点 CDN + 地区智能调度 ====================

    // 是否启用多节点智能调度（主站模式）
    'multi_node_enable' => false,

    // 主站域名（用于生成重定向链接，如 https://cdn.your-site.com）
    'main_domain' => '',

    // CDN 节点列表：每个节点可独立作为一个 CDN 站点使用
    'cdn_nodes' => [
        [
            'id'      => 'node-bj',
            'name'    => '北京节点',
            'domain'  => 'https://bj.cdn.your-site.com',
            'origin'  => 'https://origin-bj.your-site.com',
            'regions' => ['CN-BJ', 'CN'],
            'weight'  => 100,
            'enabled' => true,
        ],
        [
            'id'      => 'node-sg',
            'name'    => '新加坡节点',
            'domain'  => 'https://sg.cdn.your-site.com',
            'origin'  => 'https://origin-sg.your-site.com',
            'regions' => ['SG', 'MY', 'TH', 'VN', 'ID', 'PH'],
            'weight'  => 100,
            'enabled' => true,
        ],
        [
            'id'      => 'node-us',
            'name'    => '美国节点',
            'domain'  => 'https://us.cdn.your-site.com',
            'origin'  => 'https://origin-us.your-site.com',
            'regions' => ['US', 'CA', 'MX'],
            'weight'  => 100,
            'enabled' => true,
        ],
        [
            'id'      => 'node-global',
            'name'    => '全球兜底节点',
            'domain'  => 'https://global.cdn.your-site.com',
            'origin'  => 'https://origin-global.your-site.com',
            'regions' => ['*'],
            'weight'  => 10,
            'enabled' => true,
        ],
    ],

    // ==================== 缓存清理（Purge）配置 ====================

    // 是否启用远程缓存清理接口（通过 HTTP 触发，如 ?purge=1&token=xxx）
    'purge_enable'  => true,

    // 缓存清理密钥（外部调用 purge 接口时需提供此 token 进行验证）
    // 生产环境请务必修改为强随机字符串，防止未授权清理
    'purge_token'   => '',

    // IP 地区识别配置
    'region' => [
        'api_url'   => '',  // 例如：https://ipapi.co/{ip}/json/
        'api_key'   => '',
        'timeout'   => 3,
        'local_map' => [
            '1.0.0.0-1.255.255.255'     => 'CN',
            '14.0.0.0-14.255.255.255'   => 'CN',
            '58.0.0.0-58.255.255.255'   => 'CN',
            '117.0.0.0-117.255.255.255' => 'CN',
            '120.0.0.0-120.255.255.255' => 'CN',
            '220.0.0.0-220.255.255.255' => 'CN',
            '49.0.0.0-49.255.255.255'   => 'SG',
            '8.0.0.0-8.255.255.255'     => 'US',
        ],
        'region_map' => [
            'CN' => ['11' => 'BJ', '31' => 'SH', '44' => 'GD'],
        ],
    ],
];
