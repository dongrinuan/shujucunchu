<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['storage_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

if (!isset($_GET['sub_storage_id'])) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$sub_storage_id = $_GET['sub_storage_id'];

try {
    // 验证子存储柜是否属于当前登录的主存储柜
    $stmt = $pdo->prepare("
        SELECT s.*, m.storage_id 
        FROM sub_storages s 
        JOIN main_storages m ON s.main_storage_id = m.id 
        WHERE s.id = ? AND m.storage_id = ?
    ");
    $stmt->execute([$sub_storage_id, $_SESSION['storage_id']]);
    $sub_storage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sub_storage) {
        echo json_encode(['success' => false, 'message' => '子存储柜不存在或无权限']);
        exit;
    }
    
    // 获取体积信息
    $volume_info = getSubStorageVolumeInfo($pdo, $sub_storage_id);
    
    if (!$volume_info) {
        echo json_encode(['success' => false, 'message' => '获取体积信息失败']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'volume_limit' => $volume_info['volume_limit'],
        'current_volume' => $volume_info['current_volume'],
        'usage_percentage' => round(($volume_info['current_volume'] / $volume_info['volume_limit']) * 100, 2),
        'available_space' => $volume_info['volume_limit'] - $volume_info['current_volume']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '系统错误']);
}
?>
