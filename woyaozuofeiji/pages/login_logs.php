<?php
require_once '../db_init.php';

// 检查管理员是否已登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// 获取数据库连接
$pdo = getDbConnection();

// 设置分页参数
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 20;
$offset = ($page - 1) * $items_per_page;

// 构建搜索条件
$search_conditions = [];
$params = [];

// 用户名搜索
if (isset($_GET['username']) && !empty($_GET['username'])) {
    $search_conditions[] = "username LIKE ?";
    $params[] = "%" . $_GET['username'] . "%";
}

// 邮箱搜索
if (isset($_GET['email']) && !empty($_GET['email'])) {
    $search_conditions[] = "email LIKE ?";
    $params[] = "%" . $_GET['email'] . "%";
}

// IP地址搜索
if (isset($_GET['ip']) && !empty($_GET['ip'])) {
    $search_conditions[] = "ip_address LIKE ?";
    $params[] = "%" . $_GET['ip'] . "%";
}

// 日期范围搜索
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $search_conditions[] = "created_at >= ?";
    $params[] = $_GET['start_date'] . " 00:00:00";
}

if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $search_conditions[] = "created_at <= ?";
    $params[] = $_GET['end_date'] . " 23:59:59";
}

// 登录状态搜索
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $search_conditions[] = "status = ?";
    $params[] = $_GET['status'];
}

// 组合搜索条件
$where_clause = "";
if (!empty($search_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $search_conditions);
}

// 获取登录日志总数
$total_query = "SELECT COUNT(*) FROM login_logs" . $where_clause;
$total_stmt = $pdo->prepare($total_query);
$total_stmt->execute($params);
$total_records = $total_stmt->fetchColumn();
$total_pages = ceil($total_records / $items_per_page);

// 获取登录日志数据
$query = "SELECT * FROM login_logs" . $where_clause . " ORDER BY created_at DESC LIMIT $items_per_page OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 统计信息
// 总登录次数
$stats_query = "SELECT 
    COUNT(*) as total_logins,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_logins,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_logins,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_logins
FROM login_logs";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// 获取最近成功登录的IP分布（前10个）
$ip_query = "SELECT ip_address, COUNT(*) as count 
             FROM login_logs 
             WHERE status = 'success' AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY ip_address 
             ORDER BY count DESC 
             LIMIT 10";
$ip_stmt = $pdo->prepare($ip_query);
$ip_stmt->execute();
$ip_distribution = $ip_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1>登录日志管理</h1>
</div>

<!-- 统计卡片 -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">总登录次数</h5>
                <h2 class="mb-0"><?php echo number_format($stats['total_logins']); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">成功登录</h5>
                <h2 class="mb-0"><?php echo number_format($stats['successful_logins']); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h5 class="card-title">失败登录</h5>
                <h2 class="mb-0"><?php echo number_format($stats['failed_logins']); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">今日登录</h5>
                <h2 class="mb-0"><?php echo number_format($stats['today_logins']); ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- IP分布统计 -->
<?php if (!empty($ip_distribution)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5>最近7天登录IP分布 (Top 10)</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($ip_distribution as $ip_data): ?>
            <div class="col-md-3 mb-2">
                <div class="d-flex justify-content-between">
                    <span><?php echo htmlspecialchars($ip_data['ip_address']); ?></span>
                    <span class="badge bg-primary"><?php echo $ip_data['count']; ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 搜索表单 -->
<div class="card mb-4">
    <div class="card-header">
        <h5>搜索登录记录</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="username" class="form-label">用户名</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_GET['username']) ? htmlspecialchars($_GET['username']) : ''; ?>">
            </div>
            <div class="col-md-3">
                <label for="email" class="form-label">邮箱</label>
                <input type="text" class="form-control" id="email" name="email" value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
            </div>
            <div class="col-md-3">
                <label for="ip" class="form-label">IP地址</label>
                <input type="text" class="form-control" id="ip" name="ip" value="<?php echo isset($_GET['ip']) ? htmlspecialchars($_GET['ip']) : ''; ?>">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">登录状态</label>
                <select class="form-select" id="status" name="status">
                    <option value="">全部</option>
                    <option value="success" <?php echo (isset($_GET['status']) && $_GET['status'] === 'success') ? 'selected' : ''; ?>>成功</option>
                    <option value="failed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'failed') ? 'selected' : ''; ?>>失败</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="start_date" class="form-label">开始日期</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">结束日期</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">搜索</button>
                <a href="?page=login_logs" class="btn btn-secondary">重置</a>
            </div>
        </form>
    </div>
</div>

<!-- 登录日志表格 -->
<div class="card">
    <div class="card-header">
        <h5>登录记录列表</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>用户ID</th>
                        <th>用户名</th>
                        <th>IP地址</th>
                        <th>浏览器信息</th>
                        <th>状态</th>
                        <th>登录时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $index => $log): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td><?php echo $log['member_id'] ? htmlspecialchars($log['member_id']) : '未知'; ?></td>
                                <td><?php echo htmlspecialchars($log['username']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                    <?php 
                                    $user_agent = htmlspecialchars($log['user_agent']);
                                    echo strlen($user_agent) > 50 ? substr($user_agent, 0, 50) . '...' : $user_agent; 
                                    ?>
                                </td>
                                <td>
                                    <?php if ($log['status'] == 'success'): ?>
                                        <span class="badge bg-success">成功</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">失败</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">没有找到登录记录</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 分页 -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=login_logs&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>&page=<?php echo ($page - 1); ?>">上一页</a>
                    </li>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=login_logs&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=login_logs&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>&page=<?php echo ($page + 1); ?>">下一页</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
    // 初始化提示框
    document.addEventListener('DOMContentLoaded', function() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script> 