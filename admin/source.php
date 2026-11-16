<?php
/**
 * Simple CDN 后台 - 源码对接 / 源站
 * 配置回源站点和地区映射
 */

require_once __DIR__ . '/../cdn.php';

$defaultConfig = include __DIR__ . '/../config.default.php';
$statsDir = $defaultConfig['stats_dir'] ?? (__DIR__ . '/../stats');
\SimpleCdn\lib\Region::initStorage($statsDir);

$message = '';
$msgType = 'ok';
$clientIp = \SimpleCdn\lib\Region::getClientIp();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    switch ($do) {
        case 'save_source':
            $origin = trim($_POST['origin'] ?? '');
            $file = $statsDir . '/cdn_source.json';
            @file_put_contents($file, json_encode(['origin' => $origin, 'updated_at' => time()], JSON_UNESCAPED_UNICODE));
            $message = '源站地址已保存';
            break;
        case 'add_region':
            $region = trim($_POST['region'] ?? '');
            $origin = trim($_POST['origin'] ?? '');
            if ($region !== '' && $origin !== '') {
                \SimpleCdn\lib\Region::setRegionOrigin($region, $origin);
                $message = '地区映射已添加：' . htmlspecialchars($region);
            } else {
                $message = '地区和源站/资源均不能为空';
                $msgType = 'warn';
            }
            break;
        case 'del_region':
            $region = $_POST['region'] ?? '';
            if ($region !== '') {
                \SimpleCdn\lib\Region::removeRegionOrigin($region);
                $message = '已删除地区映射：' . htmlspecialchars($region);
            }
            break;
        case 'clear_all_cache':
            $deleted = \SimpleCdn\Cdn::clearCache();
            $message = '已清理全部站点缓存，删除了 ' . number_format($deleted) . ' 个缓存文件';
            break;
    }
    if ($message !== '') {
        $uri = $_SERVER['REQUEST_URI'] ?? 'source.php';
        $sep = (strpos($uri, '?') === false) ? '?' : '&';
        header('Location: source.php' . $sep . 'msg=' . urlencode($message) . ($msgType === 'warn' ? '&type=warn' : ''));
        exit;
    }
}

$msg = $_GET['msg'] ?? '';
$msgType = (($_GET['type'] ?? '') === 'warn') ? 'warn' : 'ok';

$customSourceFile = $statsDir . '/cdn_source.json';
$customSource = [];
if (is_file($customSourceFile)) {
    $customSource = json_decode(@file_get_contents($customSourceFile), true) ?: [];
}

$originConfigured = $defaultConfig['origin'] ?? '';
$originShow = $customSource['origin'] ?? $originConfigured;

$regionMap = \SimpleCdn\lib\Region::loadRegionMap();

$detectIp = '';
$detectRegion = '';
if (!empty($_GET['detect_ip'])) {
    $detectIp = $_GET['detect_ip'];
    $detectRegion = \SimpleCdn\lib\Region::detectRegionByIp($detectIp);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simple CDN 后台 - 源码对接 / 源站</title>
<link rel="stylesheet" href="assets/admin.css?v=1.0.0">
</head>
<body>
<div class="topbar">
<h1>源码对接 / 源站</h1>
<div class="spacer"></div>
<a href="index.php" class="btn btn-sm btn-secondary">← 返回仪表盘</a>
</div>

<div class="container">
<?php if ($message): ?>
<div class="msg <?php echo $msgType === 'warn' ? 'warn' : 'ok'; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card">
<h2>1. 回源站点配置</h2>
<form method="POST" action="">
<input type="hidden" name="do" value="save_source">
<div class="form-row">
<div class="form-group">
<label>源站域名</label>
<input type="url" name="origin" value="<?php echo htmlspecialchars($originShow); ?>" placeholder="如 https://www.your-site.com" required>
<div class="muted">未命中缓存时，CDN 将回源抓取此站点的内容</div>
</div>
</div>
<div style="display:flex;gap:12px;">
<button type="submit" class="btn btn-primary">保存源站配置</button>
</div>
</form>
</div>

<div class="card">
<h2>2. 地区映射配置</h2>
<p class="note">配置不同地区的访问者应回源到哪个站点。支持单 IP 或 IP 段映射。</p>

<form method="POST" action="" style="margin-bottom:16px;">
<input type="hidden" name="do" value="add_region">
<div class="form-row">
<div class="form-group">
<label>地区代码</label>
<input type="text" name="region" placeholder="如 CN-BJ 或 1.2.3.4" required>
</div>
<div class="form-group">
<label>源站 / 资源站点</label>
<input type="url" name="origin" placeholder="如 https://origin-bj.example.com" required>
</div>
</div>
<button type="submit" class="btn btn-primary">添加映射</button>
</form>

<?php if (!empty($regionMap)): ?>
<div class="table-wrap">
<table class="table">
<thead>
<tr><th style="width:30%;">地区代码</th><th>源站 / 资源站点</th><th style="width:auto;">操作</th></tr>
</thead>
<tbody>
<?php foreach ($regionMap as $region => $origin): ?>
<tr>
<td><?php echo htmlspecialchars($region); ?></td>
<td><?php echo htmlspecialchars($origin); ?></td>
<td>
<form method="POST" action="" style="margin:0;display:inline;">
<input type="hidden" name="do" value="del_region">
<input type="hidden" name="region" value="<?php echo htmlspecialchars($region); ?>">
<button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('确定删除此地区映射吗？');">删除</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<p class="empty">暂无地区映射配置。</p>
<?php endif; ?>
</div>

<div class="card">
<h2>3. IP 地区检测</h2>
<form method="GET" action="" style="margin-bottom:16px;">
<div class="form-row">
<div class="form-group">
<label>测试 IP 地址</label>
<input type="text" name="detect_ip" value="<?php echo htmlspecialchars($detectIp); ?>" placeholder="如 1.2.3.4">
</div>
</div>
<button type="submit" class="btn btn-primary">检测地区</button>
</form>

<?php if ($detectIp !== ''): ?>
<div class="card" style="margin-top:16px;">
<h3>检测结果</h3>
<div class="table-wrap">
<table class="table">
<tr><td>客户端 IP</td><td><?php echo htmlspecialchars($clientIp); ?></td></tr>
<tr><td>查询 IP</td><td><?php echo htmlspecialchars($detectIp); ?></td></tr>
<tr><td>识别地区</td><td><?php echo htmlspecialchars($detectRegion ?: '未识别'); ?></td></tr>
</table>
</div>
</div>
<?php endif; ?>
</div>

</div>
</body>
</html>
