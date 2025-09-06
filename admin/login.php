<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        redirect('index.php');
    } else {
        $error = '用户名或密码错误！';
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - 数字存储系统</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container">
        <h1>数字存储系统</h1>
        
        <div class="login-card">
            <div class="login-header">
                <h2>管理员登录</h2>
                <p>请输入管理员账号和密码</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="post" class="login-form">
                <div class="form-group">
                    <label>用户名:</label>
                    <input type="text" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label>密码:</label>
                    <input type="password" name="password" required>
                </div>
                
                <button type="submit" class="login-btn">登录</button>
            </form>
            
            <div class="back-to-home">
                <a href="../index.php">← 返回主页</a>
            </div>
        </div>
    </div>
</body>
</html>
