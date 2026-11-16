<?php
/**
 * 植入示例：将 Simple CDN 插入任意现有 PHP 源码
 * 只需在页面最前面 require 一次，之后自动缓存整页输出。
 */
require_once __DIR__ . '/../cdn.php';

\SimpleCdn\Cdn::enable([
    'cache_dir' => __DIR__ . '/../runtime-cache',
    'ttl'       => 300,
    'plugins'   => [
        'SimpleCdn\Plugins\UAFilter',
        'SimpleCdn\Plugins\CacheControl',
        'SimpleCdn\Plugins\Gzip',
    ],
]);

// --- 以下是原始源码，正常编写即可，首次访问会被缓存 ---
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>Simple CDN 植入示例</title>
</head>
<body>
<h1>你好，世界</h1>
<p>当前时间：<?php echo date('Y-m-d H:i:s'); ?></p>
<p>此页面已被 Simple CDN 缓存，后续访问将直接命中缓存，不再执行此 PHP 脚本。</p>
</body>
</html>
