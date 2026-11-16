<?php
/**
 * Simple CDN 后台 - 仪表盘
 * 由 Cdn::handleAdmin() 鉴权后 require，无需重复鉴权。
 */
$stats = \SimpleCdn\Cdn::getStats();
$plugins = \SimpleCdn\Cdn::getPlugins();
$purgeToday = \SimpleCdn\Cdn::getPurgeLogToday();
$healthStatuses = \SimpleCdn\Cdn::getHealthStatus();

$onlineCount = 0;
$offlineCount = 0;
foreach ($healthStatuses as $node) {
    if ($node['status'] === 'online') $onlineCount++;
    elseif ($node['status'] === 'offline') $offlineCount++;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simple CDN 后台 - 仪表盘</title>
<style>
:root {
    --bg: #f1f5f9;
    --card: rgba(255, 255, 255, 0.65);
    --card-border: rgba(255, 255, 255, 0.9);
    --text: #0f172a;
    --text-muted: #64748b;
    --primary: #2563eb;
    --primary-hover: #1d4ed8;
    --success: #059669;
    --warn: #d97706;
    --danger: #dc2626;
    --radius: 14px;
    --shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@media (prefers-color-scheme: dark) {
    :root {
        --bg: #0f172a;
        --card: rgba(30, 41, 59, 0.65);
        --card-border: rgba(255, 255, 255, 0.08);
        --text: #f8fafc;
        --text-muted: #94a3b8;
        --primary: #3b82f6;
        --primary-hover: #60a5fa;
        --success: #10b981;
        --warn: #f59e0b;
        --danger: #ef4444;
        --shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
    }
}

* { box-sizing: border-box; }
html {
    scroll-behavior: smooth;
    -webkit-tap-highlight-color: transparent;
}
body {
    font-family: system-ui, -apple-system, Segoe UI, Microsoft YaHei, "PingFang SC", sans-serif;
    background: var(--bg);
    color: var(--text);
    margin: 0;
    line-height: 1.6;
    min-height: 100vh;
}

/* 背景装饰 */
body::before {
    content: "";
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background:
        radial-gradient(circle at 20% 30%, rgba(37, 99, 235, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(5, 150, 105, 0.06) 0%, transparent 50%);
    z-index: -1;
    pointer-events: none;
}

.topbar {
    background: var(--card);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--card-border);
    padding: 14px 28px;
    display: flex;
    align-items: center;
    gap: 16px;
    position: sticky;
    top: 0;
    z-index: 100;
}
.topbar h1 {
    font-size: 17px;
    margin: 0;
    color: var(--text);
    font-weight: 600;
    letter-spacing: -0.02em;
}
.topbar .spacer { flex: 1; }
.topbar a {
    color: var(--primary);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
}
.topbar a:hover { color: var(--primary-hover); text-decoration: underline; }

.container {
    max-width: 1200px;
    margin: 28px auto;
    padding: 0 24px;
}

/* 统计卡片网格 */
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}
.stat-card {
    background: var(--card);
    border: 1px solid var(--card-border);
    border-radius: var(--radius);
    padding: 20px;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}
.stat-card::before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--success));
    opacity: 0;
    transition: var(--transition);
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow);
    border-color: rgba(37, 99, 235, 0.3);
}
.stat-card:hover::before { opacity: 1; }
.stat-card h2 {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    margin: 0 0 10px;
    font-weight: 600;
}
.stat-card .value {
    font-size: 34px;
    font-weight: 700;
    color: var(--text);
    margin: 0 0 4px;
    line-height: 1.2;
}
.stat-card .sub {
    font-size: 13px;
    color: var(--text-muted);
    margin: 0;
}

/* 节点状态标签 */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}
.badge-online {
    background: rgba(5, 150, 105, 0.1);
    color: var(--success);
    border: 1px solid rgba(5, 150, 105, 0.2);
}
.badge-offline {
    background: rgba(220, 38, 38, 0.1);
    color: var(--danger);
    border: 1px solid rgba(220, 38, 38, 0.2);
}
.badge-unknown {
    background: rgba(148, 163, 184, 0.1);
    color: var(--text-muted);
    border: 1px solid rgba(148, 163, 184, 0.2);
}

/* 按钮 */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: var(--transition);
}
.btn:hover { transform: translateY(-1px); opacity: 0.95; }
.btn:active { transform: translateY(0); }
.btn-primary {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
}
.btn-primary:hover { background: var(--primary-hover); box-shadow: 0 6px 18px rgba(37, 99, 235, 0.45); }
.btn-secondary {
    background: rgba(148, 163, 184, 0.1);
    color: var(--text-muted);
    border: 1px solid rgba(148, 163, 184, 0.2);
}
.btn-secondary:hover {
    background: rgba(148, 163, 184, 0.2);
    color: var(--text);
}
.btn-sm { padding: 6px 12px; font-size: 12px; }

/* 消息提示 */
.msg {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 500;
    border: 1px solid;
    background: var(--card);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
.msg.ok {
    background: rgba(5, 150, 105, 0.08);
    color: var(--success);
    border-color: rgba(5, 150, 105, 0.2);
}
.msg.error {
    background: rgba(220, 38, 38, 0.08);
    color: var(--danger);
    border-color: rgba(220, 38, 38, 0.2);
}
.msg.info {
    background: rgba(37, 99, 235, 0.08);
    color: var(--primary);
    border-color: rgba(37, 99, 235, 0.2);
}
.msg.warn {
    background: rgba(217, 119, 6, 0.08);
    color: var(--warn);
    border-color: rgba(217, 119, 6, 0.2);
}

/* 卡片容器 */
.card {
    background: var(--card);
    border: 1px solid var(--card-border);
    border-radius: var(--radius);
    padding: 24px;
    margin-bottom: 24px;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    transition: var(--transition);
}
.card:hover { border-color: rgba(255, 255, 255, 0.5); }
.card h2 {
    font-size: 17px;
    margin: 0 0 16px;
    color: var(--text);
    font-weight: 600;
    letter-spacing: -0.01em;
}

/* 表格 */
.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 10px;
    overflow: hidden;
    background: var(--card);
    border: 1px solid var(--card-border);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
.table th {
    text-align: left;
    padding: 14px 18px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    border-bottom: 1px solid var(--card-border);
    background: rgba(0, 0, 0, 0.02);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.table td {
    padding: 14px 18px;
    font-size: 14px;
    color: var(--text);
    border-bottom: 1px solid var(--card-border);
}
.table tr:last-child td { border-bottom: none; }
.table tr:hover { background: rgba(37, 99, 235, 0.03); }

/* 标签云 */
.tag-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 16px 0;
}
.tag {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    background: rgba(37, 99, 235, 0.08);
    color: var(--primary);
    border: 1px solid rgba(37, 99, 235, 0.2);
    cursor: pointer;
    transition: var(--transition);
}
.tag:hover {
    background: var(--primary);
    color: #fff;
}

/* 表单 */
.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 6px;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    background: rgba(255, 255, 255, 0.4);
    color: var(--text);
    transition: var(--transition);
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* 空状态 */
.empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
    font-size: 14px;
}

/* 响应式 */
@media (max-width: 768px) {
    .container { padding: 0 16px; margin: 20px auto; }
    .topbar { padding: 12px 20px; }
    .grid { grid-template-columns: 1fr; gap: 12px; }
    .stat-card { padding: 16px; }
    .stat-card .value { font-size: 28px; }
    .card { padding: 20px; }
    .table { font-size: 13px; }
    .table th, .table td { padding: 10px 12px; }
    .btn-group { flex-direction: column; gap: 8px; }
    .btn-group .btn { width: 100%; justify-content: center; }
}
@media (max-width: 480px) {
    .topbar h1 { font-size: 15px; }
    .stat-card h2 { font-size: 11px; }
    .stat-card .value { font-size: 24px; }
}
</style>
</head>
<body>
<div class="topbar">
<h1>Simple CDN 后台</h1>
<div class="spacer"></div>
<a href="?action=logout" class="btn btn-sm btn-secondary">退出登录</a>
</div>

<div class="container">
<?php if (!empty($message)): ?>
<div class="msg <?php echo $messageType === 'success' ? 'ok' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

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
<h2>命中率</h2>
<div class="value" style="font-size:24px;"><?php echo $stats['rate']; ?>%</div>
<div class="sub"><?php echo $stats['rate'] >= 80 ? '健康' : '需优化'; ?></div>
</div>
<?php if (!empty($healthStatuses)): ?>
<div class="stat-card">
<h2>节点在线</h2>
<div class="value" style="font-size:24px;"><?php echo $onlineCount; ?>/<?php echo count($healthStatuses); ?></div>
<div class="sub"><?php echo $offlineCount > 0 ? $offlineCount . ' 个离线' : '全部在线'; ?></div>
</div>
<?php endif; ?>
<div class="stat-card">
<h2>今日清理</h2>
<div class="value"><?php echo number_format($purgeToday['total']); ?></div>
<div class="sub"><?php echo number_format($purgeToday['deleted']); ?> 个文件</div>
</div>
</div>

<div class="card">
<h2>插件状态</h2>
<table class="table">
<thead>
<tr><th style="width:40%;">插件</th><th style="width:20%;">状态</th><th>操作</th></tr>
</thead>
<tbody>
<?php if (empty($plugins)): ?>
<tr><td colspan="3" class="empty">暂无已注册插件</td></tr>
<?php else: ?>
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
<h2>快速操作</h2>
<div class="tag-cloud" style="margin-bottom:16px;">
<a href="stats.php" class="tag">查看详细统计</a>
<a href="cache.php" class="tag">清理缓存</a>
<a href="plugins.php" class="tag">管理插件</a>
<a href="health.php" class="tag">节点健康检查</a>
<a href="cache_warm.php" class="tag">缓存预热</a>
<a href="purge_logs.php" class="tag">清理日志审计</a>
<a href="nodes.php" class="tag">多节点管理</a>
</div>
<div class="muted" style="font-size:13px;">提示：后台路径默认为 /admin/，可在 config.php 的 cdn_rules 中调整。</div>
</div>

</div>
</body>
</html>
