<?php
/**
 * Simple CDN 后台 - 清理日志审计
 * 查看历史缓存清理操作记录
 */

require_once __DIR__ . '/../cdn.php';
\SimpleCdn\Cdn::enable([]);

// 读取清理日志
$purgeLogs = \SimpleCdn\Cdn::getPurgeLogRecent(100);
$purgeToday = \SimpleCdn\Cdn::getPurgeLogToday();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simple CDN 后台 - 清理日志审计</title>
<link rel="stylesheet" href="assets/admin.css?v=1.0.0">
</head>
<body>
<div class="topbar">
<h1>清理日志审计</h1>
<div class="spacer"></div>
<div class="btn-group" style="display:flex;gap:8px;">
<a href="action.php?do=purge_log_export&format=csv" class="btn btn-sm btn-secondary">导出CSV</a>
<a href="action.php?do=purge_log_export&format=json" class="btn btn-sm btn-secondary">导出JSON</a>
</div>
<a href="index.php" class="btn btn-sm btn-secondary">← 返回仪表盘</a>
</div>

<div class="container">
<div class="grid">
<div class="stat-card">
<div class="value"><?php echo number_format($purgeToday['total']); ?></div>
<div class="label">今日清理次数</div>
</div>
<div class="stat-card">
<div class="value"><?php echo number_format($purgeToday['deleted']); ?></div>
<div class="label">今日删除文件数</div>
</div>
<div class="stat-card">
<div class="value" style="color:var(--success);"><?php echo number_format($purgeToday['success']); ?></div>
<div class="label">节点清理成功</div>
</div>
<div class="stat-card">
<div class="value" style="color:var(--danger);"><?php echo number_format($purgeToday['failed']); ?></div>
<div class="label">节点清理失败</div>
</div>
</div>

<div class="card">
<h2>清理历史记录（最近 100 条）</h2>
<?php if (empty($purgeLogs)): ?>
<p class="empty">暂无清理日志记录。</p>
<?php else: ?>
<div class="table-wrap">
<table class="table">
<thead>
<tr>
<th style="width:16%;">时间</th>
<th style="width:12%;">操作者</th>
<th style="width:14%;">范围</th>
<th style="width:10%;">主站删除</th>
<th style="width:14%;">令牌</th>
<th style="width:auto;">节点详情</th>
</tr>
</thead>
<tbody>
<?php foreach ($purgeLogs as $log): ?>
<tr>
<td><?php echo htmlspecialchars($log['time'] ?? ''); ?></td>
<td>
<span class="badge <?php echo $log['operator'] === 'admin' ? 'badge-success' : 'badge-info'; ?>">
<?php echo $log['operator'] === 'admin' ? '后台' : '远程API'; ?>
</span>
</td>
<td>
<span class="badge <?php echo $log['scope'] === 'all_nodes' ? 'badge-success' : 'badge-info'; ?>">
<?php
$scopeMap = ['local' => '仅主站', 'all_nodes' => '全部节点'];
echo $scopeMap[$log['scope'] ?? ''] ?? $log['scope'] ?? '';
?>
</span>
</td>
<td><?php echo number_format($log['deleted'] ?? 0); ?></td>
<td><span class="token"><?php echo htmlspecialchars($log['token_used'] ?? ''); ?></span></td>
<td>
<?php
$nodes = $log['nodes'] ?? [];
if (empty($nodes)) {
    echo '<span class="muted">无节点数据</span>';
} else {
    foreach ($nodes as $n) {
        $statusClass = $n['status'] === 'success' ? 'badge-success' : ($n['status'] === 'failed' ? 'badge-fail' : 'badge-skip');
        echo '<span class="badge ' . $statusClass . '">' . htmlspecialchars($n['status'] ?? '') . '</span>';
        echo '<span class="token" style="margin-left:6px;">' . htmlspecialchars($n['id'] ?? '') . '</span>';
        if (!empty($n['message'])) {
            echo '<br><span class="muted" style="font-size:11px;">' . htmlspecialchars($n['message']) . '</span>';
        }
    }
}
?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>

<div class="card">
<h2>审计说明</h2>
<div class="muted">
<ul style="margin:0; padding-left:20px;">
<li><strong>操作者</strong>：后台（admin）或远程 API（remote_api）触发的清理操作。</li>
<li><strong>范围</strong>：仅主站（local）或全部节点（all_nodes）。</li>
<li><strong>令牌</strong>：远程 API 调用时的 purge_token 已脱敏，仅显示后4位。</li>
<li><strong>节点状态</strong>：success=清理成功，failed=清理失败，skipped=跳过（离线或当前节点）。</li>
<li>日志按日期保存在 <code>purge_logs/</code> 目录，格式为 JSON，最多保留最近 500 条，无需数据库。</li>
</ul>
</div>
</div>

</div>
</body>
</html>
