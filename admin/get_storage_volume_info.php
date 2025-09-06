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

if (!isset($_GET['storage_id'])) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$storage_id = $_GET['storage_id'];

try {
    // 验证存储柜是否存在
    $stmt = $pdo->prepare("SELECT id, storage_name FROM main_storages WHERE storage_id = ?");
    $stmt->execute([$storage_id]);
    $storage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$storage) {
        echo json_encode(['success' => false, 'message' => '存储柜不存在']);
        exit;
    }
    
    // 获取子存储柜列表及体积信息
    $stmt = $pdo->prepare("
        SELECT 
            id,
            sub_number,
            description,
            volume_limit,
            current_volume,
            ROUND((current_volume / volume_limit) * 100, 2) as usage_percentage
        FROM sub_storages 
        WHERE main_storage_id = ? 
        ORDER BY sub_number
    ");
    $stmt->execute([$storage['id']]);
    $sub_storages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'storage_name' => $storage['storage_name'],
        'sub_storages' => $sub_storages
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '系统错误: ' . $e->getMessage()]);
}
?>
