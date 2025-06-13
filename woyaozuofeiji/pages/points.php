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
    
    // 构建WHERE子句
    $where_clause = "";
    $params = [];
    
    if (!empty($search)) {
        $where_clause .= " AND (m.username LIKE ? OR m.email LIKE ? OR pt.description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // 获取总记录数
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM point_transactions pt
        JOIN members m ON pt.member_id = m.id
        WHERE 1=1 $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_records = $row['total'];
    
    // 计算总页数
    $total_pages = ceil($total_records / $records_per_page);
    
    // 查询积分记录
    $sql = "
        SELECT pt.*, m.username, m.email
        FROM point_transactions pt
        JOIN members m ON pt.member_id = m.id
        WHERE 1=1 $where_clause
        ORDER BY pt.created_at DESC
        LIMIT $offset, $records_per_page
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = '数据库错误: ' . $e->getMessage();
    $transactions = [];
    $total_pages = 0;
}
?>

<!-- 页面标题 -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <h3>积分记录管理</h3>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger" style="margin: 20px; padding: 10px; border-radius: 4px; background-color: #f8d7da; color: #721c24;">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- 搜索工具栏 -->
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center;">
        <form method="GET" action="" class="search-form" style="width: 100%;">
            <div style="display: flex; gap: 10px;">
                <input type="text" name="search" placeholder="搜索用户名、邮箱或说明..." value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="flex: 1;">
                <button type="submit" class="btn btn-primary">搜索</button>
                <?php if (!empty($search)): ?>
                    <a href="?page=points" class="btn btn-secondary">清除</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- 积分记录列表 -->
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>用户信息</th>
                <th>积分变动</th>
                <th>说明</th>
                <th>时间</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($transactions)): ?>
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo $transaction['id']; ?></td>
                        <td>
                            <div><strong><?php echo htmlspecialchars($transaction['username']); ?></strong></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($transaction['email']); ?></div>
                        </td>
                        <td>
                            <span style="color: <?php echo $transaction['points'] >= 0 ? '#28a745' : '#dc3545'; ?>; font-weight: bold;">
                                <?php echo $transaction['points'] > 0 ? '+' : ''; ?><?php echo number_format($transaction['points']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">没有找到积分记录</td>
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