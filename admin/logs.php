<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 检查管理员权限
if (!isAdmin()) {
    redirect('login.php');
}

// 获取操作日志
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_clause = "";
$params = [];

// 搜索条件
if (isset($_GET['storage_id']) && !empty($_GET['storage_id'])) {
    $where_clause .= " WHERE storage_id LIKE ?";
    $params[] = '%' . $_GET['storage_id'] . '%';
}

if (isset($_GET['operation_type']) && !empty($_GET['operation_type'])) {
    $where_clause .= $where_clause ? " AND" : " WHERE";
    $where_clause .= " operation_type = ?";
    $params[] = $_GET['operation_type'];
}

// 获取总数
$count_sql = "SELECT COUNT(*) as total FROM operation_logs" . $where_clause;
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total = $stmt->fetch()['total'];

// 获取日志数据 - 使用更安全的方式构建LIMIT子句
$limit_offset = " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
$sql = "SELECT * FROM operation_logs" . $where_clause . " ORDER BY created_at DESC" . $limit_offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_pages = ceil($total / $per_page);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>操作日志 - 数字存储系统</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .log-filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        .logs-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .pagination {
            text-align: center;
            margin: 20px 0;
        }
        
        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 2px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
            border: 1px solid #e2e8f0;
        }
        
        .pagination a.current {
            background: #667eea;
            color: white;
        }
        
        .pagination a:hover:not(.current) {
            background: #f7fafc;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1>操作日志</h1>
            <div class="admin-nav">
                <a href="index.php" class="btn btn-primary">返回后台</a>
                <a href="logout.php" class="btn btn-danger">退出登录</a>
            </div>
        </div>
        
        <!-- 筛选器 -->
        <div class="log-filters">
            <h3>筛选条件</h3>
            <form method="get" class="filter-form">
                <div class="form-group">
                    <label>存储柜编号:</label>
                    <input type="text" name="storage_id" value="<?php echo htmlspecialchars($_GET['storage_id'] ?? ''); ?>" placeholder="输入存储柜编号">
                </div>
                
                <div class="form-group">
                    <label>操作类型:</label>
                    <select name="operation_type">
                        <option value="">全部</option>
                        <option value="store" <?php echo ($_GET['operation_type'] ?? '') === 'store' ? 'selected' : ''; ?>>存东西</option>
                        <option value="retrieve" <?php echo ($_GET['operation_type'] ?? '') === 'retrieve' ? 'selected' : ''; ?>>取东西</option>
                        <option value="manage" <?php echo ($_GET['operation_type'] ?? '') === 'manage' ? 'selected' : ''; ?>>管理</option>
                        <option value="admin" <?php echo ($_GET['operation_type'] ?? '') === 'admin' ? 'selected' : ''; ?>>管理员</option>
                    </select>
                </div>
                
                <div></div>
                
                <div>
                    <button type="submit" class="btn btn-primary">筛选</button>
                    <a href="logs.php" class="btn">重置</a>
                </div>
            </form>
        </div>
        
        <!-- 日志表格 -->
        <div class="logs-table">
            <h3 style="padding: 20px; margin: 0; border-bottom: 1px solid #e2e8f0;">
                操作日志 (共 <?php echo $total; ?> 条记录)
            </h3>
            
            <?php if (empty($logs)): ?>
                <div class="message info" style="margin: 20px;">暂无日志记录</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>存储柜编号</th>
                            <th>操作类型</th>
                            <th>描述</th>
                            <th>IP地址</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['storage_id']); ?></td>
                                <td>
                                    <?php
                                    $operation_names = [
                                        'store' => '存东西',
                                        'retrieve' => '取东西',
                                        'manage' => '管理',
                                        'admin' => '管理员'
                                    ];
                                    echo $operation_names[$log['operation_type']] ?? $log['operation_type'];
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['description']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- 分页 -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php
                    $query_params = $_GET;
                    $query_params['page'] = $i;
                    $query_string = http_build_query($query_params);
                    ?>
                    <a href="?<?php echo $query_string; ?>" class="<?php echo $i === $page ? 'current' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
