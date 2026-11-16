<?php
/**
 * Simple CDN 后台 - 多节点管理
 * 管理 CDN 节点列表、地区映射、启用/禁用状态
 * 数据保存在 nodes.json，无需数据库
 */

require_once __DIR__ . '/../cdn.php';

// 确保已初始化配置
\SimpleCdn\Cdn::enable([]);

// 获取节点健康状态
$healthStatuses = \SimpleCdn\Cdn::getHealthStatus();

$nodesFile = __DIR__ . '/../nodes.json';
$nodes = [];

// 加载现有节点
if (is_file($nodesFile)) {
    $content = @file_get_contents($nodesFile);
    if ($content !== false) {
        $decoded = @json_decode($content, true);
        if (is_array($decoded)) {
            $nodes = $decoded;
        }
    }
}

// 处理 POST 操作
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_node') {
        $newNode = [
            'id'      => trim($_POST['node_id'] ?? ''),
            'name'    => trim($_POST['node_name'] ?? ''),
            'domain'  => trim($_POST['node_domain'] ?? ''),
            'origin'  => trim($_POST['node_origin'] ?? ''),
            'regions' => $_POST['node_regions'] ?? [],
            'weight'  => (int) ($_POST['node_weight'] ?? 100),
            'enabled' => isset($_POST['node_enabled']) && $_POST['node_enabled'] === '1',
        ];

        // 验证
        if ($newNode['id'] === '' || $newNode['domain'] === '' || $newNode['origin'] === '') {
            $message = '节点ID、域名、回源站点均为必填项';
            $messageType = 'error';
        } elseif (isset($_POST['edit_index']) && is_numeric($_POST['edit_index'])) {
            // 编辑现有节点
            $index = (int) $_POST['edit_index'];
            if (isset($nodes[$index])) {
                $nodes[$index] = $newNode;
                $message = '节点已更新';
            }
        } else {
            // 检查 ID 是否重复
            $dup = false;
            foreach ($nodes as $n) {
                if ($n['id'] === $newNode['id']) {
                    $dup = true;
                    break;
                }
            }
            if ($dup) {
                $message = '节点ID已存在';
                $messageType = 'error';
            } else {
                $nodes[] = $newNode;
                $message = '节点已添加';
            }
        }

        if ($messageType !== 'error') {
            // 保存
            $json = json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if (@file_put_contents($nodesFile, $json) !== false) {
                if ($message === '') {
                    $message = '操作成功';
                }
            } else {
                $message = '保存失败，请检查 nodes.json 目录写权限';
                $messageType = 'error';
            }
        }

        // 重新加载
        if (is_file($nodesFile)) {
            $content = @file_get_contents($nodesFile);
            if ($content !== false) {
                $decoded = @json_decode($content, true);
                if (is_array($decoded)) {
                    $nodes = $decoded;
                }
            }
        }
    }

    if ($action === 'delete_node') {
        $index = (int) ($_POST['node_index'] ?? -1);
        if (isset($nodes[$index])) {
            array_splice($nodes, $index, 1);
            $json = json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if (@file_put_contents($nodesFile, $json) !== false) {
                $message = '节点已删除';
                // 重新加载
                if (is_file($nodesFile)) {
                    $content = @file_get_contents($nodesFile);
                    if ($content !== false) {
                        $decoded = @json_decode($content, true);
                        if (is_array($decoded)) {
                            $nodes = $decoded;
                        }
                    }
                }
            } else {
                $message = '删除失败，请检查写权限';
                $messageType = 'error';
            }
        } else {
            $message = '节点不存在';
            $messageType = 'error';
        }
    }

    if ($action === 'toggle_node') {
        $index = (int) ($_POST['node_index'] ?? -1);
        $enable = $_POST['enable'] === '1';
        if (isset($nodes[$index])) {
            $nodes[$index]['enabled'] = $enable;
            $json = json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if (@file_put_contents($nodesFile, $json) !== false) {
                $message = $enable ? '节点已启用' : '节点已禁用';
                // 重新加载
                if (is_file($nodesFile)) {
                    $content = @file_get_contents($nodesFile);
                    if ($content !== false) {
                        $decoded = @json_decode($content, true);
                        if (is_array($decoded)) {
                            $nodes = $decoded;
                        }
                    }
                }
            } else {
                $message = '操作失败，请检查写权限';
                $messageType = 'error';
            }
        } else {
            $message = '节点不存在';
            $messageType = 'error';
        }
    }
}

// 获取要编辑的节点
$editNode = null;
$editIndex = -1;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $idx = (int) $_GET['edit'];
    if (isset($nodes[$idx])) {
        $editNode = $nodes[$idx];
        $editIndex = $idx;
    }
}

// 地区代码选项（常用）
$regionOptions = [
    'CN'   => '中国',
    'CN-BJ' => '中国-北京',
    'CN-SH' => '中国-上海',
    'CN-GD' => '中国-广东',
    'SG'   => '新加坡',
    'MY'   => '马来西亚',
    'TH'   => '泰国',
    'VN'   => '越南',
    'ID'   => '印度尼西亚',
    'PH'   => '菲律宾',
    'US'   => '美国',
    'CA'   => '加拿大',
    'MX'   => '墨西哥',
    'JP'   => '日本',
    'KR'   => '韩国',
    'HK'   => '中国香港',
    'TW'   => '中国台湾',
    '*'    => '全部地区（兜底）',
];

// 辅助函数：输出 HTML 转义后的值
function escVal($val): string {
    return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
}

// 辅助函数：检查地区是否被选中
function isRegionSelected(string $code, ?array $regions): bool {
    if ($regions === null) {
        return false;
    }
    return in_array($code, $regions, true);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simple CDN 后台 - 多节点管理</title>
<link rel="stylesheet" href="assets/admin.css?v=1.0.0">
</head>
<body>
<div class="topbar">
<h1>多节点管理</h1>
<div class="spacer"></div>
<a href="index.php" class="btn btn-sm btn-secondary">← 返回仪表盘</a>
</div>

<div class="container">
<?php if ($message): ?>
<div class="msg <?php echo $messageType === 'error' ? 'error' : 'success'; ?>"><?php echo escVal($message); ?></div>
<?php endif; ?>

<div class="card">
<h2><?php echo $editNode !== null ? '编辑节点' : '添加节点'; ?></h2>
<form method="POST" action="">
<?php if ($editNode !== null): ?>
<input type="hidden" name="action" value="add_node">
<input type="hidden" name="edit_index" value="<?php echo $editIndex; ?>">
<?php else: ?>
<input type="hidden" name="action" value="add_node">
<?php endif; ?>

<div class="form-row">
<div class="form-group">
<label>节点 ID</label>
<input type="text" name="node_id" value="<?php echo escVal($editNode !== null ? ($editNode['id'] ?? '') : ''); ?>" placeholder="如 node-bj" required>
</div>
<div class="form-group">
<label>节点名称</label>
<input type="text" name="node_name" value="<?php echo escVal($editNode !== null ? ($editNode['name'] ?? '') : ''); ?>" placeholder="如 北京节点" required>
</div>
<div class="form-group">
<label>节点域名</label>
<input type="url" name="node_domain" value="<?php echo escVal($editNode !== null ? ($editNode['domain'] ?? '') : ''); ?>" placeholder="如 https://bj.cdn.example.com" required>
<div class="muted">访问者将被 302 重定向到此域名</div>
</div>
<div class="form-group">
<label>回源站点</label>
<input type="url" name="node_origin" value="<?php echo escVal($editNode !== null ? ($editNode['origin'] ?? '') : ''); ?>" placeholder="如 https://origin.example.com" required>
<div class="muted">未命中缓存时回源抓取此站点</div>
</div>
</div>

<div class="form-group full">
<label>覆盖地区（按住 Ctrl 可多选）</label>
<select name="node_regions[]" multiple class="regions-select" <?php echo $editNode !== null ? '' : 'required'; ?>>
<?php foreach ($regionOptions as $code => $label): ?>
<?php
$selected = isRegionSelected($code, $editNode !== null ? ($editNode['regions'] ?? null) : null);
?>
<option value="<?php echo escVal($code); ?>" <?php echo $selected ? 'selected' : ''; ?>><?php echo escVal($label); ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="form-row">
<div class="form-group">
<label>权重（数字越大优先级越高）</label>
<input type="number" name="node_weight" value="<?php echo escVal($editNode !== null ? ($editNode['weight'] ?? 100) : 100); ?>" min="1" max="1000">
</div>
<div class="form-group">
<label style="margin-bottom: 4px;">&nbsp;</label>
<div class="checkbox-group">
<input type="checkbox" name="node_enabled" value="1" <?php echo (($editNode !== null ? ($editNode['enabled'] ?? true) : true) ? 'checked' : ''); ?> id="node_enabled">
<label for="node_enabled" style="margin:0;">启用该节点</label>
</div>
</div>
</div>

<div style="display:flex;gap:12px;">
<button type="submit" class="btn btn-primary"><?php echo $editNode !== null ? '更新节点' : '添加节点'; ?></button>
<?php if ($editNode !== null): ?>
<a href="nodes.php" class="btn btn-secondary">取消</a>
<?php endif; ?>
</div>
</form>
</div>

<div class="card">
<h2>节点列表（共 <?php echo count($nodes); ?> 个）</h2>
<?php if (empty($nodes)): ?>
<p class="empty">暂无节点配置，请先添加一个节点。</p>
<?php else: ?>
<div class="table-wrap">
<table class="table">
<thead>
<tr>
<th style="width:12%;">ID</th>
<th style="width:16%;">名称</th>
<th style="width:20%;">域名</th>
<th style="width:16%;">回源站点</th>
<th style="width:12%;">覆盖地区</th>
<th style="width:7%;">权重</th>
<th style="width:10%;">健康</th>
<th style="width:10%;">状态</th>
<th style="width:auto;">操作</th>
</tr>
</thead>
<tbody>
<?php foreach ($nodes as $i => $node): ?>
<tr>
<td><?php echo escVal($node['id'] ?? ''); ?></td>
<td><?php echo escVal($node['name'] ?? ''); ?></td>
<td><?php echo escVal($node['domain'] ?? ''); ?></td>
<td><?php echo escVal($node['origin'] ?? ''); ?></td>
<td>
<?php
$regions = $node['regions'] ?? [];
if (empty($regions)) {
    echo '<span class="muted">未设置</span>';
} else {
    foreach ($regions as $r) {
        $label = $regionOptions[$r] ?? $r;
        echo '<span class="badge" style="background:rgba(148,163,184,0.1);color:var(--text-muted);border-color:rgba(148,163,184,0.2);margin:1px;">' . escVal($label) . '</span>';
    }
}
?>
</td>
<td><?php echo (int) ($node['weight'] ?? 0); ?></td>
<td>
<?php
$nodeId = $node['id'] ?? '';
$health = $healthStatuses[$nodeId] ?? null;
if ($health === null) {
    echo '<span class="badge badge-unknown">未检测</span>';
} elseif ($health['status'] === 'online') {
    echo '<span class="badge badge-online">在线</span>';
    if ($health['response_time'] !== false) {
        echo '<br><span class="muted" style="font-size:11px;">' . $health['response_time'] . 'ms</span>';
    }
} else {
    echo '<span class="badge badge-offline">离线</span>';
    if (!empty($health['error'])) {
        echo '<br><span class="muted" style="font-size:11px;">' . escVal($health['error']) . '</span>';
    }
}
?>
</td>
<td>
<span class="badge <?php echo (isset($node['enabled']) && $node['enabled'] === true) ? 'badge-success' : 'badge-fail'; ?>">
<?php echo (isset($node['enabled']) && $node['enabled'] === true) ? '启用' : '禁用'; ?>
</span>
</td>
<td>
<a href="action.php?do=toggle_node&index=<?php echo $i; ?>&enable=<?php echo (isset($node['enabled']) && $node['enabled'] === true) ? '0' : '1'; ?>" class="btn btn-sm btn-secondary" onclick="return confirm('确定<?php echo (isset($node['enabled']) && $node['enabled'] === true) ? '禁用' : '启用'; ?>此节点吗？');">
<?php echo (isset($node['enabled']) && $node['enabled'] === true) ? '禁用' : '启用'; ?>
</a>
<a href="nodes.php?edit=<?php echo $i; ?>" class="btn btn-sm btn-secondary">编辑</a>
<a href="action.php?do=delete_node&index=<?php echo $i; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除此节点吗？');">删除</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

<div class="card">
<h2>配置说明</h2>
<div class="muted">
<ul style="margin:0; padding-left:20px;">
<li>节点数据保存在 <code>nodes.json</code> 文件中，请确保该目录对 Web 用户可写。</li>
<li><strong>调度逻辑</strong>：访问者访问主站时，系统会根据 IP 判断地区，302 重定向到匹配的 CDN 节点。</li>
<li><strong>地区匹配顺序</strong>：精确地区（如 CN-BJ）&gt; 国家级（如 CN）&gt; 兜底节点（如 * 或 --）。</li>
<li><strong>权重</strong>：同地区多个节点时，权重越高越优先被选中。</li>
<li>修改节点后立即生效，无需重启 Web 服务器。</li>
</ul>
</div>
</div>

</div>
</body>
</html>
