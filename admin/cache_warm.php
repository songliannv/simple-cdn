<?php
/**
 * Simple CDN 后台 - 缓存预热管理
 * 管理预热URL列表、手动触发预热、查看预热日志
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

// 获取预热URL配置
$warmUrls = \SimpleCdn\Cdn::getCacheWarmerUrls();

// 获取健康状态
$healthStatuses = \SimpleCdn\Cdn::getHealthStatus();

// 获取最近预热日志
$warmLogs = \SimpleCdn\Cdn::getWarmLogs(50);

// 读取预热结果（由 action.php 写入 session）
$warmResult = null;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['warm_result'])) {
    $warmResult = $_SESSION['warm_result'];
    unset($_SESSION['warm_result']);
}

// 处理操作
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_urls') {
        $newUrls = [];
        $urlList = $_POST['warm_url'] ?? [];
        $nameList = $_POST['warm_name'] ?? [];
        $enabledList = $_POST['warm_enabled'] ?? [];

        foreach ($urlList as $i => $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            $newUrls[] = [
                'url'    => $url,
                'name'   => trim($nameList[$i] ?? ''),
                'enabled' => isset($enabledList[$i]) && $enabledList[$i] === '1',
            ];
        }

        if (\SimpleCdn\Cdn::setCacheWarmerUrls($newUrls)) {
            $warmUrls = $newUrls;
            $message = '预热URL配置已保存';
            $messageType = 'success';
        } else {
            $message = '保存失败，请检查写权限';
            $messageType = 'error';
        }
    }

    if ($action === 'warm_now') {
        $warmResult = \SimpleCdn\Cdn::warmCacheAfterPurge();
        $message = '预热完成：共 ' . $warmResult['total'] . ' 个请求，成功 ' . $warmResult['success'] . ' 个，失败 ' . $warmResult['failed'] . ' 个';
        $messageType = $warmResult['failed'] > 0 ? 'warn' : 'success';
        $warmLogs = \SimpleCdn\Cdn::getWarmLogs(50);
    }

    if ($action === 'delete_url') {
        $index = (int) ($_POST['url_index'] ?? -1);
        if (isset($warmUrls[$index])) {
            array_splice($warmUrls, $index, 1);
            if (\SimpleCdn\Cdn::setCacheWarmerUrls($warmUrls)) {
                $message = '预热URL已删除';
                $messageType = 'success';
            } else {
                $message = '删除失败';
                $messageType = 'error';
            }
        } else {
            $message = 'URL不存在';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simple CDN 后台 - 缓存预热管理</title>
<link rel="stylesheet" href="assets/admin.css?v=1.0.0">
</head>
<body>
<div class="topbar">
<h1>缓存预热管理</h1>
<div class="spacer"></div>
<a href="index.php" class="btn btn-sm btn-secondary">← 返回仪表盘</a>
</div>

<div class="container">
<?php if ($message): ?>
<div class="msg <?php echo $messageType === 'success' ? 'ok' : ($messageType === 'error' ? 'error' : 'info'); ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($warmResult): ?>
<div class="msg info">
<strong>本次预热结果</strong>：共 <?php echo number_format($warmResult['total']); ?> 个请求，成功 <span class="badge badge-success"><?php echo number_format($warmResult['success']); ?></span> 个，失败 <span class="badge badge-fail"><?php echo number_format($warmResult['failed']); ?></span> 个
</div>
<?php endif; ?>

<div class="card">
<h2>1. 预热URL配置</h2>
<p class="note">配置需要预热的URL。清理缓存后，系统会自动将这些URL预热到各CDN节点，避免首次访问回源。</p>

<form method="POST" action="">
<input type="hidden" name="action" value="save_urls">

<div class="form-row">
<div class="form-group full">
<label>预热URL列表（每行一个URL，可选名称：URL|名称）</label>
<textarea name="warm_urls_text" rows="6" placeholder="https://cdn.example.com/index.php|首页
https://cdn.example.com/img/banner.jpg|横幅图片"><?php
foreach ($warmUrls as $u) {
    echo htmlspecialchars($u['url'] ?? '') . '|' . htmlspecialchars($u['name'] ?? '') . "\n";
}
?></textarea>
<div class="muted">格式：URL|名称（名称可选）。每行一个。</div>
</div>
</div>

<div class="checkbox-group">
<input type="checkbox" name="warm_enable" value="1" id="warm_enable" <?php echo !empty(self::$config['cache_warm_enable'] ?? true) ? 'checked' : ''; ?>>
<label for="warm_enable">启用自动预热（清理缓存后自动预热）</label>
</div>

<button type="submit" class="btn btn-primary">保存配置</button>
</form>
</div>

<div class="card">
<h2>2. 手动预热</h2>
<div class="btn-group">
<form method="POST" action="" style="margin:0;" onsubmit="return confirm('确定要立即对所有在线节点预热所有配置的URL吗？');">
<button type="submit" name="action" value="warm_now" class="btn btn-success">立即预热所有节点</button>
</form>
<a href="health.php" class="btn btn-secondary btn-sm">查看节点健康状态</a>
</div>

<div class="note">
<strong>预热机制</strong>：系统会向每个在线节点发送带 <code>__cdn_warm=1</code> 标记的请求，节点会回源抓取并缓存内容。<br>
离线节点会被自动跳过。预热结果会记录在预热日志中。
</div>
</div>

<?php if (!empty($warmLogs)): ?>
<div class="card">
<h2>3. 预热日志（最近 <?php echo count($warmLogs); ?> 条）</h2>
<table class="table">
<thead>
<tr>
<th style="width:16%;">时间</th>
<th style="width:12%;">总请求</th>
<th style="width:12%;">成功</th>
<th style="width:12%;">失败</th>
<th style="width:auto;">节点详情</th>
</tr>
</thead>
<tbody>
<?php foreach ($warmLogs as $log): ?>
<tr>
<td><?php echo htmlspecialchars($log['time'] ?? ''); ?></td>
<td><?php echo number_format($log['total'] ?? 0); ?></td>
<td><span class="badge badge-success"><?php echo number_format($log['success'] ?? 0); ?></span></td>
<td><span class="badge badge-fail"><?php echo number_format($log['failed'] ?? 0); ?></span></td>
<td>
<?php
$details = $log['details'] ?? [];
if (empty($details)) {
    echo '<span class="muted">无详情</span>';
} else {
    foreach ($details as $d) {
        $statusClass = $d['status'] === 'success' ? 'badge-success' : ($d['status'] === 'failed' ? 'badge-fail' : 'badge-skip');
        echo '<span class="badge ' . $statusClass . '">' . htmlspecialchars($d['status'] ?? '') . '</span>';
        echo '<span class="token" style="margin-left:6px;">' . htmlspecialchars($d['node_name'] ?? '') . '</span>';
        if (!empty($d['message'])) {
            echo '<br><span class="muted" style="font-size:11px;">' . htmlspecialchars($d['message']) . '</span>';
        }
    }
}
?>
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
