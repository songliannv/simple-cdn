<?php
/**
 * Simple CDN 后台 - 插件管理
 * 可视化开关启用/禁用已注册插件
 */

require_once __DIR__ . '/../cdn.php';
\SimpleCdn\Cdn::enable([]);

$plugins = \SimpleCdn\Cdn::getPlugins();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simple CDN 后台 - 插件管理</title>
<link rel="stylesheet" href="assets/admin.css?v=1.0.0">
</head>
<body>
<div class="topbar">
<h1>插件管理</h1>
<div class="spacer"></div>
<a href="index.php" class="btn btn-sm btn-secondary">← 返回仪表盘</a>
</div>

<div class="container">
<div class="card">
<h2>已注册插件</h2>
<?php if (empty($plugins)): ?>
<p class="empty">暂无已注册插件。</p>
<?php else: ?>
<table class="table">
<thead>
<tr><th style="width:40%;">插件类</th><th style="width:20%;">状态</th><th>操作</th></tr>
</thead>
<tbody>
<?php foreach ($plugins as $class => $enabled): ?>
<tr>
<td><?php echo htmlspecialchars($class); ?></td>
<td>
<span class="badge <?php echo $enabled ? 'badge-online' : 'badge-offline'; ?>">
<?php echo $enabled ? '启用' : '禁用'; ?>
</span>
</td>
<td>
<a href="action.php?do=toggle_plugin&class=<?php echo urlencode($class); ?>&enable=<?php echo $enabled ? '0' : '1'; ?>" class="btn btn-sm btn-secondary">
<?php echo $enabled ? '禁用' : '启用'; ?>
</a>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>

<div class="card">
<h2>插件说明</h2>
<table class="table">
<thead>
<tr><th style="width:30%;">插件</th><th>作用</th></tr>
</thead>
<tbody>
<tr>
<td><strong>UAFilter</strong></td>
<td>识别爬虫/命令行工具 User-Agent，添加 <code>X-CDN-Bypass: 1</code> 标记，便于监控与分析</td>
</tr>
<tr>
<td><strong>CacheControl</strong></td>
<td>自动注入 <code>Cache-Control</code> 响应头，并在 <code>X-SimpleCDN: HIT0/1</code> 中标记本次请求是回源（0）还是命中缓存（1）</td>
</tr>
<tr>
<td><strong>Gzip</strong></td>
<td>客户端支持时对输出内容进行 gzip 压缩，减少传输流量；自动跳过已开启服务器压缩或不支持的场景</td>
</tr>
</tbody>
</table>
</div>

<div class="note">
<strong>操作说明</strong>：点击“启用/禁用”按钮可即时切换插件状态，无需重启 Web 服务器。<br>
修改后请刷新页面查看最新状态。
</div>
</div>
</body>
</html>
