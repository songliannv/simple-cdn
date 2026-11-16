<?php
/**
 * Simple CDN 后台 - 清缓存
 * 支持：清理主站缓存、清理所有节点缓存（通过 purge 接口通知）
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

// 读取 purge 结果（由 action.php 写入 session）
$purgeResult = null;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['purge_result'])) {
    $purgeResult = $_SESSION['purge_result'];
    unset($_SESSION['purge_result']);
}

$deleted = 0;
$message = '';
$messageType = 'info';

// 直接 POST 清理主站（不清理节点）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'clear_local') {
        $deleted = \SimpleCdn\Cdn::clearCache();
        $message = '已删除 ' . number_format($deleted) . ' 个主站缓存文件';
        $messageType = 'success';
    } elseif ($_POST['action'] === 'purge_all') {
        // 重定向到 action.php 处理（会设置 session 并返回）
        $token = \SimpleCdn\Cdn::getPurgeToken();
        header('Location: action.php?do=purge_all');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simple CDN 后台 - 清缓存</title>
<link rel="stylesheet" href="assets/admin.css?v=1.0.0">
</head>
<body>
<div class="topbar">
<h1>清缓存</h1>
<div class="spacer"></div>
<a href="index.php" class="btn btn-sm btn-secondary">← 返回仪表盘</a>
</div>

<div class="container">
<?php if ($message): ?>
<div class="msg <?php echo $messageType === 'success' ? 'ok' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($purgeResult): ?>
<div class="msg ok">
<strong>一键清理完成</strong><br>
主站已删除 <strong><?php echo number_format($purgeResult['deleted']); ?></strong> 个缓存文件，<br>
节点通知：<span class="badge badge-success"><?php echo number_format($purgeResult['notified']); ?></span> 个成功，
<span class="badge badge-fail"><?php echo number_format($purgeResult['failed']); ?></span> 个失败。
</div>
<?php endif; ?>

<div class="card">
<h2>1. 一键清理缓存</h2>
<div class="btn-group">
<form method="POST" action="" onsubmit="return confirm('确定要清空主站所有 CDN 缓存吗？清空后首次访问将重新回源。');" style="margin:0;">
<button type="submit" name="action" value="clear_local" class="btn btn-danger">清理主站缓存</button>
</form>
<form method="POST" action="" onsubmit="return confirm('确定要清理所有节点的缓存吗？主站和全部启用的节点都会被清理。');" style="margin:0;">
<button type="submit" name="action" value="purge_all" class="btn btn-primary">清理所有节点缓存</button>
</form>
<a href="purge_logs.php" class="btn btn-secondary">查看清理日志</a>
</div>
<div class="note">
<strong>清理主站缓存</strong>：仅删除本站 <code>runtime-cache/</code> 目录下的所有 <code>.cache</code> 文件。<br>
<strong>清理所有节点缓存</strong>：先清理主站，然后通过 <code>purge</code> 接口通知每个启用的 CDN 节点也清理本地缓存。<br>
清理后，首次访问时 CDN 将重新回源并重新缓存。
</div>
</div>

<?php if (!empty($cdnNodes)): ?>
<div class="card">
<h2>2. 节点列表（共 <?php echo count($cdnNodes); ?> 个）</h2>
<table class="table">
<thead>
<tr>
<th style="width:14%;">ID</th>
<th style="width:18%;">名称</th>
<th style="width:22%;">域名</th>
<th style="width:16%;">状态</th>
<th style="width:14%;">权重</th>
<th style="width:16%;">操作</th>
</tr>
</thead>
<tbody>
<?php foreach ($cdnNodes as $i => $node): ?>
<tr>
<td><?php echo htmlspecialchars($node['id'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($node['name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($node['domain'] ?? ''); ?></td>
<td>
<span class="badge <?php echo (isset($node['enabled']) && $node['enabled'] === true) ? 'badge-success' : 'badge-fail'; ?>">
<?php echo (isset($node['enabled']) && $node['enabled'] === true) ? '启用' : '禁用'; ?>
</span>
</td>
<td><?php echo (int) ($node['weight'] ?? 0); ?></td>
<td>
<?php if (isset($node['enabled']) && $node['enabled'] === true): ?>
<a href="action.php?do=toggle_node&index=<?php echo $i; ?>&enable=0" class="btn btn-sm btn-secondary" onclick="return confirm('确定禁用此节点吗？');">禁用</a>
<?php else: ?>
<a href="action.php?do=toggle_node&index=<?php echo $i; ?>&enable=1" class="btn btn-sm btn-secondary" onclick="return confirm('确定启用此节点吗？');">启用</a>
<?php endif; ?>
<a href="nodes.php?edit=<?php echo $i; ?>" class="btn btn-sm btn-secondary">编辑</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p class="note">只有<strong>启用</strong>的节点会在一键清理时被通知。管理节点请前往 <a href="nodes.php" class="btn btn-sm btn-secondary">多节点管理</a>。</p>
</div>
<?php endif; ?>

<div class="card">
<h2>3. HTTP Purge 接口（外部调用）</h2>
<p>可通过 HTTP 请求触发缓存清理，适合与发布系统、CMS 等集成：</p>

<div style="background: rgba(148, 163, 184, 0.08); border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 10px; padding: 16px; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 13px; margin-bottom: 16px;">
<strong>GET</strong> {主站域名}/?purge=1&token={purge_token}&all_nodes=1
</div>

<table class="table">
<thead>
<tr><th style="width:20%;">参数</th><th>说明</th></tr>
</thead>
<tbody>
<tr><td><code>purge</code></td><td>固定值 <code>1</code> 或 <code>true</code>，表示触发清理</td></tr>
<tr><td><code>token</code></td><td>清理密钥，需与配置文件中的 <code>purge_token</code> 一致</td></tr>
<tr><td><code>all_nodes</code></td><td>可选，<code>1</code> 表示清理完主站后同时通知所有节点也清理，<code>0</code> 或不传表示仅清理主站</td></tr>
</tbody>
</table>

<div class="note">
<strong>安全提示</strong>：请在配置文件中设置 <code>purge_token</code> 为强随机字符串，防止未授权调用清理接口。<br>
若 <code>purge_token</code> 为空，远程 purge 接口将被禁用，仅后台可操作。
</div>
</div>

</div>
</body>
</html>
