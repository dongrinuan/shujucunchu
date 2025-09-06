<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// 检查管理员权限
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => '权限不足']);
    exit;
}

if (!isset($_POST['sub_storage_id']) || !isset($_POST['volume_limit'])) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$sub_storage_id = $_POST['sub_storage_id'];
$volume_limit_mb = $_POST['volume_limit'];

try {
    // 验证输入
    if (!is_numeric($volume_limit_mb) || $volume_limit_mb <= 0) {
        echo json_encode(['success' => false, 'message' => '请输入有效的体积限制值']);
        exit;
    }
    
    $volume_limit_bytes = $volume_limit_mb * 1024 * 1024; // 转换为字节
    $max_volume_limit = 2048 * 1024 * 1024; // 最大2GB
    
    if ($volume_limit_bytes > $max_volume_limit) {
        echo json_encode(['success' => false, 'message' => '体积限制不能超过2GB']);
        exit;
    }
    
    // 验证子存储柜是否存在
    $stmt = $pdo->prepare("
        SELECT s.id, s.current_volume, m.storage_id, m.storage_name
        FROM sub_storages s 
        JOIN main_storages m ON s.main_storage_id = m.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$sub_storage_id]);
    $sub_storage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sub_storage) {
        echo json_encode(['success' => false, 'message' => '子存储柜不存在']);
        exit;
    }
    
    // 检查当前使用量是否超过新限制
    if ($sub_storage['current_volume'] > $volume_limit_bytes) {
        $current_mb = round($sub_storage['current_volume'] / 1024 / 1024, 2);
        echo json_encode([
            'success' => false, 
            'message' => "无法设置此限制！当前已使用 {$current_mb}MB，请先清理文件或设置更大的限制"
        ]);
        exit;
    }
    
    // 更新体积限制
    $stmt = $pdo->prepare("UPDATE sub_storages SET volume_limit = ? WHERE id = ?");
    $result = $stmt->execute([$volume_limit_bytes, $sub_storage_id]);
    
    if ($result) {
        // 记录操作日志
        logOperation($pdo, $sub_storage['storage_id'], 'admin', 
            "管理员更新子存储柜体积限制为 {$volume_limit_mb}MB", 
            $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        
        echo json_encode([
            'success' => true, 
            'message' => '体积限制更新成功',
            'new_limit' => $volume_limit_bytes,
            'new_limit_mb' => $volume_limit_mb
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '更新失败']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '系统错误: ' . $e->getMessage()]);
}
?>
