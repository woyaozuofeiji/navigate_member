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
        $where_clause .= " AND (inviter.username LIKE ? OR inviter.email LIKE ? OR invited.username LIKE ? OR invited.email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // 获取总记录数
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM members invited
        JOIN members inviter ON invited.referrer_id = inviter.id
        WHERE invited.referrer_id IS NOT NULL $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_records = $row['total'];
    
    // 计算总页数
    $total_pages = ceil($total_records / $records_per_page);
    
    // 查询邀请记录
    $sql = "
        SELECT 
            invited.id as invited_id,
            invited.username as invited_username,
            invited.email as invited_email,
            invited.created_at as invited_date,
            inviter.id as inviter_id,
            inviter.username as inviter_username,
            inviter.email as inviter_email
        FROM members invited
        JOIN members inviter ON invited.referrer_id = inviter.id
        WHERE invited.referrer_id IS NOT NULL $where_clause
        ORDER BY invited.created_at DESC
        LIMIT $offset, $records_per_page
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取邀请统计
    $stats_sql = "
        SELECT 
            inviter.id,
            inviter.username,
            COUNT(invited.id) as invite_count
        FROM members inviter
        LEFT JOIN members invited ON inviter.id = invited.referrer_id
        WHERE invited.referrer_id IS NOT NULL
        GROUP BY inviter.id
        ORDER BY invite_count DESC
        LIMIT 5
    ";
    $stmt = $pdo->query($stats_sql);
    $invite_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = '数据库错误: ' . $e->getMessage();
    $invites = [];
    $invite_stats = [];
    $total_pages = 0;
}
?>

<!-- 页面标题 -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <h3>邀请记录管理</h3>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger" style="margin: 20px; padding: 10px; border-radius: 4px; background-color: #f8d7da; color: #721c24;">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- 邀请统计 -->
    <div class="card-body">
        <h4>邀请排行榜</h4>
        <div class="row" style="display: flex; flex-wrap: wrap; margin: 0 -10px;">
            <?php if (!empty($invite_stats)): ?>
                <?php foreach ($invite_stats as $stat): ?>
                    <div class="col" style="flex: 1; min-width: 200px; padding: 10px;">
                        <div style="background-color: #f8f9fa; border-radius: 5px; padding: 15px; text-align: center;">
                            <h5><?php echo htmlspecialchars($stat['username']); ?></h5>
                            <p style="font-size: 24px; font-weight: bold; margin: 10px 0; color: #4361ee;"><?php echo $stat['invite_count']; ?></p>
                            <p style="margin: 0; color: #6c757d;">邀请人数</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col" style="flex: 1; min-width: 200px; padding: 10px;">
                    <div style="background-color: #f8f9fa; border-radius: 5px; padding: 15px; text-align: center;">
                        <p>暂无邀请数据</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 搜索工具栏 -->
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #dee2e6;">
        <form method="GET" action="" class="search-form" style="width: 100%;">
            <div style="display: flex; gap: 10px;">
                <input type="text" name="search" placeholder="搜索邀请人或被邀请人..." value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="flex: 1;">
                <button type="submit" class="btn btn-primary">搜索</button>
                <?php if (!empty($search)): ?>
                    <a href="?page=invites" class="btn btn-secondary">清除</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- 邀请记录列表 -->
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>邀请人</th>
                <th>被邀请人</th>
                <th>注册时间</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($invites)): ?>
                <?php foreach ($invites as $invite): ?>
                    <tr>
                        <td><?php echo $invite['invited_id']; ?></td>
                        <td>
                            <div><strong><?php echo htmlspecialchars($invite['inviter_username']); ?></strong></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($invite['inviter_email']); ?></div>
                            <div class="text-muted small">ID: <?php echo $invite['inviter_id']; ?></div>
                        </td>
                        <td>
                            <div><strong><?php echo htmlspecialchars($invite['invited_username']); ?></strong></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($invite['invited_email']); ?></div>
                        </td>
                        <td><?php echo date('Y-m-d H:i', strtotime($invite['invited_date'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center;">没有找到邀请记录</td>
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