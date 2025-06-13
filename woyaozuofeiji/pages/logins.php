<?php
// 启用输出缓冲，防止"headers already sent"错误
ob_start();

// 检查管理员是否登录（不要重复启动会话）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../admin_login.php');
    exit;
}

// 更新最后活动时间
$_SESSION['admin_last_activity'] = time();

// 数据库连接 - 修正路径
require_once dirname(dirname(__DIR__)) . '/includes/db_connect.php';

// 每页显示记录数
$records_per_page = 15;

// 获取当前页码
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);

// 计算偏移量
$offset = ($page - 1) * $records_per_page;

// 搜索条件
$search = isset($_GET['search']) ? $_GET['search'] : '';

// 构建查询
try {
    $pdo = getDbConnection();
    
    // 创建登录日志表（如果不存在）
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `login_logs` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `member_id` int(11) DEFAULT NULL,
          `username` varchar(255) DEFAULT NULL,
          `ip_address` varchar(45) DEFAULT NULL,
          `user_agent` text DEFAULT NULL,
          `status` varchar(20) DEFAULT NULL COMMENT '登录状态，如success、failed',
          `created_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `member_id` (`member_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    
    // 构建WHERE子句
    $where_clause = "";
    $params = [];
    
    if (!empty($search)) {
        $where_clause .= " AND (ll.username LIKE ? OR ll.ip_address LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // 获取总记录数
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM login_logs ll
        WHERE 1=1 $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_records = $row['total'];
    
    // 计算总页数
    $total_pages = ceil($total_records / $records_per_page);
    
    // 查询登录记录
    $sql = "
        SELECT ll.*
        FROM login_logs ll
        WHERE 1=1 $where_clause
        ORDER BY ll.created_at DESC
        LIMIT $offset, $records_per_page
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取登录统计
    $stats_sql = "
        SELECT 
            DATE(created_at) as login_date, 
            COUNT(*) as login_count,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
        FROM login_logs
        WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY login_date DESC
    ";
    $stmt = $pdo->query($stats_sql);
    $login_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = '数据库错误: ' . $e->getMessage();
    $logins = [];
    $login_stats = [];
    $total_pages = 0;
}
?>

<!-- 页面标题 -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <h3>登录记录管理</h3>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger" style="margin: 20px; padding: 10px; border-radius: 4px; background-color: #f8d7da; color: #721c24;">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- 登录统计 -->
    <div class="card-body">
        <h4>最近7天登录统计</h4>
        <div class="row" style="display: flex; flex-wrap: wrap; margin: 0 -10px;">
            <?php if (!empty($login_stats)): ?>
                <?php foreach ($login_stats as $stat): ?>
                    <div class="col" style="flex: 1; min-width: 200px; padding: 10px;">
                        <div style="background-color: #f8f9fa; border-radius: 5px; padding: 15px; text-align: center;">
                            <h5><?php echo date('m-d', strtotime($stat['login_date'])); ?></h5>
                            <p style="font-size: 24px; font-weight: bold; margin: 10px 0; color: #4361ee;"><?php echo $stat['login_count']; ?></p>
                            <div style="display: flex; justify-content: center; gap: 10px;">
                                <span style="color: #28a745;">成功: <?php echo $stat['success_count']; ?></span>
                                <span style="color: #dc3545;">失败: <?php echo $stat['failed_count']; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col" style="flex: 1; min-width: 200px; padding: 10px;">
                    <div style="background-color: #f8f9fa; border-radius: 5px; padding: 15px; text-align: center;">
                        <p>暂无登录数据</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 搜索工具栏 -->
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #dee2e6;">
        <form method="GET" action="" class="search-form" style="width: 100%;">
            <div style="display: flex; gap: 10px;">
                <input type="text" name="search" placeholder="搜索用户名或IP地址..." value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="flex: 1;">
                <button type="submit" class="btn btn-primary">搜索</button>
                <?php if (!empty($search)): ?>
                    <a href="?page=logins" class="btn btn-secondary">清除</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- 登录记录列表 -->
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>用户名</th>
                <th>IP地址</th>
                <th>状态</th>
                <th>登录时间</th>
                <th>设备信息</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($logins)): ?>
                <?php foreach ($logins as $login): ?>
                    <tr>
                        <td><?php echo $login['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($login['username']); ?></strong>
                            <?php if ($login['member_id']): ?>
                                <div class="text-muted small">ID: <?php echo $login['member_id']; ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($login['ip_address']); ?></td>
                        <td>
                            <?php if ($login['status'] === 'success'): ?>
                                <span style="color: #28a745; font-weight: bold;">成功</span>
                            <?php else: ?>
                                <span style="color: #dc3545; font-weight: bold;">失败</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($login['created_at'])); ?></td>
                        <td>
                            <div class="text-muted small" style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars($login['user_agent']); ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center;">没有找到登录记录</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- 分页 -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination" style="padding: 20px; display: flex; justify-content: center;">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="btn btn-sm btn-secondary" style="margin-right: 5px;">&laquo; 上一页</a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) {
                echo '<a href="?page=1' . (!empty($search) ? '&search='.urlencode($search) : '') . '" class="btn btn-sm btn-outline-secondary" style="margin-right: 5px;">1</a>';
                if ($start_page > 2) {
                    echo '<span style="margin-right: 5px;">...</span>';
                }
            }
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                $active_class = ($i == $page) ? 'btn-primary' : 'btn-outline-secondary';
                echo '<a href="?page=' . $i . (!empty($search) ? '&search='.urlencode($search) : '') . '" class="btn btn-sm ' . $active_class . '" style="margin-right: 5px;">' . $i . '</a>';
            }
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span style="margin-right: 5px;">...</span>';
                }
                echo '<a href="?page=' . $total_pages . (!empty($search) ? '&search='.urlencode($search) : '') . '" class="btn btn-sm btn-outline-secondary" style="margin-right: 5px;">' . $total_pages . '</a>';
            }
            ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="btn btn-sm btn-secondary">下一页 &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// 输出所有缓冲的内容并关闭缓冲
ob_end_flush();
?> 