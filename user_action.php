<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storage_id = trim($_POST['storage_id']);
    $password1 = $_POST['password1'];
    $password2 = $_POST['password2'] ?? '';
    $action = $_POST['action'];
    
    // 验证存储柜是否存在
    $storage = getMainStorage($pdo, $storage_id);
    
    if (!$storage) {
        $error = "存储柜 '$storage_id' 不存在！";
    } else {
        $debug_info .= ", 存储柜存在";
        // 验证一级密码
        if (!password_verify($password1, $storage['password1'])) {
            $error = "一级密码错误！";
        } else {
            $debug_info .= ", 一级密码正确";
            switch ($action) {
                case 'store':
                case 'manage':
                    // 存取和管理需要二级密码 (兼容旧的操作类型)
                    if (needsPassword2Setup($pdo, $storage_id)) {
                        // 用户首次设置二级密码
                        if (empty($password2)) {
                            $error = "存取和管理功能需要设置二级密码（首次使用）！";
                        } else {
                            if (setPassword2($pdo, $storage_id, $password2)) {
                                $_SESSION['storage_id'] = $storage_id;
                                $_SESSION['storage_data'] = getMainStorage($pdo, $storage_id); // 重新获取最新数据
                                $_SESSION['action'] = 'manage'; // 统一设为 manage 模式
                                header('Location: storage_interface.php');
                                exit();
                            } else {
                                $error = "二级密码设置失败！";
                            }
                        }
                    } else {
                        // 验证已有的二级密码
                        if (empty($password2)) {
                            $error = "存取和管理功能需要输入二级密码！";
                        } elseif (!password_verify($password2, $storage['password2'])) {
                            $error = "二级密码错误！";
                        } else {
                            $_SESSION['storage_id'] = $storage_id;
                            $_SESSION['storage_data'] = $storage;
                            $_SESSION['action'] = 'manage'; // 统一设为 manage 模式
                            header('Location: storage_interface.php');
                            exit();
                        }
                    }
                    break;
                    
                case 'retrieve':
                    // 取东西只需要一级密码
                    $_SESSION['storage_id'] = $storage_id;
                    $_SESSION['storage_data'] = $storage;
                    $_SESSION['action'] = 'retrieve';
                    header('Location: storage_interface.php');
                    exit();
                    
                default:
                    $error = "无效的操作类型！";
            }
        }
    }
    
    // 记录操作日志
    $stmt = $pdo->prepare("INSERT INTO operation_logs (storage_id, operation_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $storage_id,
        $action,
        isset($error) ? "失败: $error" : "成功: $debug_info",
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>操作结果 - 数字存储系统</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>操作结果</h1>
        
        <div class="main-content" style="grid-template-columns: 1fr;">
            <div class="user-section">
                <?php if (isset($error)): ?>
                    <?php echo showMessage($error, 'error'); ?>
                    <a href="index.php" class="btn btn-primary">返回主页</a>
                <?php else: ?>
                    <?php echo showMessage('正在跳转...', 'success'); ?>
                    <script>
                        setTimeout(function() {
                            window.location.href = 'storage_interface.php';
                        }, 1000);
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
