<?php
/**
 * Simple CDN 后台 - 节点健康检查
 * 探测各 CDN 节点可用性，显示在线/离线状态
 */

require_once __DIR__ . '/../cdn.php';
\SimpleCdn\Cdn::enable([]);

// 读取节点列表
$nodesFile = __DIR__ . '/../nodes.json';
$cdnNodes = [];
if (is_file($nodesFile)) {
    $content = @file_get_contents($nodesFile);
    if ($content !== false) {
        $decoded = @json_decode($content, true);
        if (is_array($decoded)) {
            $cdnNodes = $decoded;
        }
    }
}

// 执行健康检查
$healthStatuses = [];
if (!empty($cdnNodes)) {
    $healthStatuses = \SimpleCdn\Cdn::runHealthCheck($cdnNodes);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simple CDN 后台 - 节点健康检查</title>
<link rel="stylesheet" href="assets/admin.css?v=1.0.0">
</head>
<body>
<div class="topbar">
<h1>节点健康检查</h1>
<div class="spacer"></div>
<a href="index.php" class="btn btn-sm btn-secondary">← 返回仪表盘</a>
<button class="btn btn-sm btn-primary" onclick="location.reload()" title="重新检测所有节点">重新检测</button>
</div>

<div class="container">
<?php
$onlineCount = 0;
$offlineCount = 0;
$unknownCount = 0;
foreach ($healthStatuses as $node) {
    if ($node['status'] === 'online') $onlineCount++;
    elseif ($node['status'] === 'offline') $offlineCount++;
    else $unknownCount++;
}
$totalCount = count($healthStatuses);
?>

<div class="grid">
<div class="stat-card">
<div class="value"><?php echo $totalCount; ?></div>
<div class="label">节点总数</div>
</div>
<div class="stat-card">
<div class="value" style="color:var(--success);"><?php echo $onlineCount; ?></div>
<div class="label">在线节点</div>
</div>
<div class="stat-card">
<div class="value" style="color:var(--danger);"><?php echo $offlineCount; ?></div>
<div class="label">离线节点</div>
</div>
<div class="stat-card">
<div class="value" style="color:var(--text-muted);"><?php echo $unknownCount; ?></div>
<div class="label">未知状态</div>
</div>
</div>

<div class="card">
<h2>节点状态详情</h2>
<div style="display:flex;gap:12px;align-items:center;margin-bottom:16px;">
<button class="btn btn-primary" onclick="location.reload()">重新检测</button>
<a href="nodes.php" class="btn btn-secondary btn-sm">管理节点</a>
</div>
<?php if (empty($healthStatuses)): ?>
<p class="empty">暂无节点健康数据。请先在<a href="nodes.php" class="btn btn-sm btn-secondary">多节点管理</a>中添加节点，或点击“重新检测”。</p>
<?php else: ?>
<table class="table">
<thead>
<tr>
<th style="width:14%;">节点ID</th>
<th style="width:18%;">名称</th>
<th style="width:22%;">域名</th>
<th style="width:14%;">状态</th>
<th style="width:14%;">响应时间</th>
<th style="width:12%;">检查次数</th>
<th style="width:10%;">失败次数</th>
<th style="width:auto;">最后检查</th>
</tr>
</thead>
<tbody>
<?php foreach ($healthStatuses as $node): ?>
<tr>
<td><?php echo htmlspecialchars($node['id'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($node['name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($node['domain'] ?? ''); ?></td>
<td>
<span class="badge <?php echo $node['status'] === 'online' ? 'badge-online' : ($node['status'] === 'offline' ? 'badge-offline' : 'badge-unknown'); ?>">
<?php echo $node['status'] === 'online' ? '在线' : ($node['status'] === 'offline' ? '离线' : '未知'); ?>
</span>
</td>
<td><?php echo $node['response_time'] !== false ? $node['response_time'] . ' ms' : '—'; ?></td>
<td><?php echo (int) ($node['check_count'] ?? 0); ?></td>
<td><?php echo (int) ($node['fail_count'] ?? 0); ?></td>
<td><?php echo htmlspecialchars($node['last_check'] ?? ''); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

<div class="card">
<h2>说明</h2>
<div class="muted">
<ul style="margin:0; padding-left:20px;">
<li><strong>在线</strong>：节点 HTTP 探测返回 200-399 状态码，可正常提供缓存服务。</li>
<li><strong>离线</strong>：节点不可访问或返回错误状态码，清理和调度时会自动跳过。</li>
<li><strong>未知</strong>：尚未进行过健康检查，点击“重新检测”即可。</li>
<li>检测结果按日期保存在 <code>health_checks/</code> 目录，无需数据库。</li>
<li>离线节点在一键清理和多节点调度中会被自动跳过，不会影响整体操作。</li>
</ul>
</div>
</div>

</div>
</body>
</html>
