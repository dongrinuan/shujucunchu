<?php
session_start();
require_once 'config/database.php';

// 检查用户权限
if (!isset($_SESSION['storage_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '无权限']);
    exit;
}

if (!isset($_GET['text_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '缺少参数']);
    exit;
}

$text_id = $_GET['text_id'];

try {
    // 验证用户是否有权限访问这个文本
    $stmt = $pdo->prepare("
        SELECT st.*, ss.main_storage_id 
        FROM stored_texts st 
        JOIN sub_storages ss ON st.sub_storage_id = ss.id 
        JOIN main_storages ms ON ss.main_storage_id = ms.id 
        WHERE st.id = ? AND ms.storage_id = ?
    ");
    $stmt->execute([$text_id, $_SESSION['storage_id']]);
    $text = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$text) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '文本不存在或无权限']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'content' => $text['content'],
        'title' => $text['title'],
        'content_type' => $text['content_type']
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '数据库错误']);
}
?>
