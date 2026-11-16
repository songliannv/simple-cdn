<?php
/**
 * Simple CDN 后台 - 命中率统计
 * 按路径分片查看详细的命中/回源/绕过数据
 */

require_once __DIR__ . '/../cdn.php';
\SimpleCdn\Cdn::enable([]);

$stats = \SimpleCdn\Cdn::getStats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simple CDN 后台 - 命中率统计</title>
<link rel="stylesheet" href="assets/admin.css?v=1.0.0">
</head>
<body>
<div class="topbar">
<h1>命中率统计</h1>
<div class="spacer"></div>
<a href="index.php" class="btn btn-sm btn-secondary">← 返回仪表盘</a>
</div>

<div class="container">
<div class="grid">
<div class="stat-card">
<h2>总请求数</h2>
<div class="value"><?php echo number_format($stats['total']); ?></div>
<div class="sub">今日累计请求</div>
</div>
<div class="stat-card">
<h2>命中数</h2>
<div class="value"><?php echo number_format($stats['hit']); ?></div>
<div class="sub">缓存命中</div>
</div>
<div class="stat-card">
<h2>回源数</h2>
<div class="value"><?php echo number_format($stats['origin']); ?></div>
<div class="sub">未命中回源</div>
</div>
<div class="stat-card">
<h2>绕过数</h2>
<div class="value"><?php echo number_format($stats['bypass']); ?></div>
<div class="sub">插件/规则绕过</div>
</div>
<div class="stat-card">
<h2>命中率</h2>
<div class="value" style="font-size:24px;"><?php echo $stats['rate']; ?>%</div>
<div class="sub"><?php echo $stats['rate'] >= 80 ? '健康' : '需优化'; ?></div>
</div>
</div>

<div class="section">
<h3>按路径分片统计</h3>
<?php if (empty($stats['paths'])): ?>
<p class="empty">暂无路径统计数据。</p>
<?php else: ?>
<table class="table">
<thead>
<tr>
<th style="width:25%;">路径</th>
<th style="width:15%;">总请求</th>
<th style="width:15%;">命中</th>
<th style="width:15%;">未命中</th>
<th style="width:15%;">回源</th>
<th style="width:15%;">命中率</th>
</tr>
</thead>
<tbody>
<?php foreach ($stats['paths'] as $path => $data): ?>
<tr>
<td><?php echo htmlspecialchars($path); ?></td>
<td><?php echo number_format($data['total'] ?? 0); ?></td>
<td><?php echo number_format($data['hit'] ?? 0); ?></td>
<td><?php echo number_format($data['miss'] ?? 0); ?></td>
<td><?php echo number_format($data['origin'] ?? 0); ?></td>
<td>
    <span class="badge <?php echo (($data['hit'] ?? 0) / max(($data['total'] ?? 1), 1) * 100) >= 80 ? 'badge-success' : 'badge-warn'; ?>">
        <?php echo number_format((($data['hit'] ?? 0) / max(($data['total'] ?? 1), 1)) * 100, 2); ?>%
    </span>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

</div>
</body>
</html>
