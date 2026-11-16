<?php
return [
    // 回源站点地址（独立模式必填）
    'origin'         => 'https://your-origin.com',

    // 缓存目录，必须对 Web 运行用户可写
    'cache_dir'      => __DIR__ . '/runtime-cache',

    // 统计数据目录（无数据库，按日期分片 JSON 存储）
    'stats_dir'      => __DIR__ . '/stats',

    // 是否启用命中率统计
    'stats_enable'   => true,

    // 缓存有效期（秒）
    'ttl'            => 3600,

    // 缓存键盐值：同一台主机部署多个站点时改成不同值，避免缓存串站
    'cache_key_salt' => 'simple-cdn',

    // 是否按 移动端 / PC 端 分别缓存
    'separate_ua'    => true,

    // CDN 站点自身的路径前缀，例如部署在 /cdn/ 下则填 '/cdn'，回源时自动剥离
    'path_prefix'    => '',

    // 屏蔽 Deprecated 警告，防止其被输出到响应体污染缓存内容
    'exclude_deprecated' => true,

    // 回源代理，默认空 = 直连源站，不继承服务器的 http_proxy
    'origin_proxy'  => '',

    // 后台登录密码（必填，否则后台无法访问）
    'admin_password' => '',

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
    // 启用后，访问主域名会根据访问者地区自动 302 重定向到最近的 CDN 节点
    'multi_node_enable' => false,

    // 主站域名（用于生成重定向链接，如 https://cdn.your-site.com）
    'main_domain' => '',

    // CDN 节点列表：每个节点可独立作为一个 CDN 站点使用
    //   id          节点唯一标识
    //   name        节点显示名称
    //   domain      节点域名（访问者会被 302 重定向到这里）
    //   origin      该节点的回源站点（未命中缓存时回源抓取）
    //   regions     覆盖地区代码列表，如 ['CN-BJ', 'CN'] 或 ['*'] 表示全地区
    //   weight      权重（数字越大优先级越高，用于同地区多节点时排序）
    //   enabled     是否启用该节点
    'cdn_nodes' => [
        // 示例：北京节点，覆盖中国北京及全国兜底
        [
            'id'      => 'node-bj',
            'name'    => '北京节点',
            'domain'  => 'https://bj.cdn.your-site.com',
            'origin'  => 'https://origin-bj.your-site.com',
            'regions' => ['CN-BJ', 'CN'],
            'weight'  => 100,
            'enabled' => true,
        ],
        // 示例：新加坡节点，覆盖东南亚
        [
            'id'      => 'node-sg',
            'name'    => '新加坡节点',
            'domain'  => 'https://sg.cdn.your-site.com',
            'origin'  => 'https://origin-sg.your-site.com',
            'regions' => ['SG', 'MY', 'TH', 'VN', 'ID', 'PH'],
            'weight'  => 100,
            'enabled' => true,
        ],
        // 示例：美国节点，覆盖北美
        [
            'id'      => 'node-us',
            'name'    => '美国节点',
            'domain'  => 'https://us.cdn.your-site.com',
            'origin'  => 'https://origin-us.your-site.com',
            'regions' => ['US', 'CA', 'MX'],
            'weight'  => 100,
            'enabled' => true,
        ],
        // 示例：全球兜底节点（* 匹配所有地区）
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
        // 外部 IP 查询 API 模板，{ip} 会被替换为访问者 IP
        // 留空表示仅使用本地映射（离线环境）
        'api_url'   => '',  // 例如：https://ipapi.co/{ip}/json/
        'api_key'   => '',
        'timeout'   => 3,

        // 本地 IP 段映射（离线兜底），格式：'起始IP-结束IP' => '地区代码'
        // 支持单 IP：'1.2.3.4' => 'CN'
        'local_map' => [
            // 中国常见 IP 段（示例，实际部署时建议补充完整）
            '1.0.0.0-1.255.255.255'     => 'CN',
            '14.0.0.0-14.255.255.255'   => 'CN',
            '58.0.0.0-58.255.255.255'   => 'CN',
            '117.0.0.0-117.255.255.255' => 'CN',
            '120.0.0.0-120.255.255.255' => 'CN',
            '220.0.0.0-220.255.255.255' => 'CN',
            // 新加坡
            '49.0.0.0-49.255.255.255'   => 'SG',
            // 美国
            '8.0.0.0-8.255.255.255'     => 'US',
        ],

        // 国家代码 -> 省/州代码映射（用于更精细的地区划分，可选）
        'region_map' => [
            'CN' => ['11' => 'BJ', '31' => 'SH', '44' => 'GD'],
        ],
    ],

    // ==================== 清理日志审计配置 ====================

    // 是否启用清理日志审计
    'purge_log_enable'  => true,

    // 清理日志目录（按日期分片 JSON 存储）
    'purge_log_dir'     => __DIR__ . '/purge_logs',

    // ==================== 节点健康检查配置 ====================

    // 是否启用节点健康检查
    'health_check_enable' => true,

    // 健康检查数据目录
    'health_check_dir'    => __DIR__ . '/health_checks',

    // ==================== 缓存预热同步配置 ====================

    // 是否启用缓存预热（清理后自动预热热门URL到各节点）
    'cache_warm_enable'  => true,

    // 并发预热数（同时预热的节点数，1-20）
    'cache_warm_concurrency' => 5,

    // ==================== 负载均衡调度配置 ====================

    // 负载均衡策略：
    //   round_robin   = 轮询（同权重节点轮流）
    //   least_rt      = 最小响应时间优先（响应快的节点优先）
    //   weighted      = 加权随机（按权重随机选择）
    'lb_strategy' => 'least_rt',

    // 健康检查阈值：连续失败超过此次数的节点，暂时从调度中剔除
    'lb_health_threshold' => 3,
];
