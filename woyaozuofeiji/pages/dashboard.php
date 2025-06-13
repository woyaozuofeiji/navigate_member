<?php
require_once '../db_init.php';

// 获取数据库连接
$pdo = getDbConnection();

// 获取会员总数
$memberCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM members");
    $memberCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    $memberCount = 0;
}

// 获取当日新增会员数
$todayNewMembers = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM members WHERE DATE(created_at) = CURRENT_DATE()");
    $todayNewMembers = $stmt->fetchColumn();
} catch (PDOException $e) {
    $todayNewMembers = 0;
}

// 获取邀请总数
$invitesCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM members WHERE referrer_id IS NOT NULL");
    $invitesCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    $invitesCount = 0;
}

// 获取积分交易总数
$pointsTransactions = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM point_transactions");
    $pointsTransactions = $stmt->fetchColumn();
} catch (PDOException $e) {
    $pointsTransactions = 0;
}

// 获取最近注册的会员（最多5个）
$recentMembers = [];
try {
    $stmt = $pdo->query("
        SELECT id, username, email, points, invite_code, created_at 
        FROM members 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recentMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentMembers = [];
}

// 获取最近的积分交易（最多5个）
$recentPointTransactions = [];
try {
    $stmt = $pdo->query("
        SELECT pt.id, pt.member_id, m.username, pt.points, pt.description, pt.created_at
        FROM point_transactions pt
        JOIN members m ON pt.member_id = m.id
        ORDER BY pt.created_at DESC
        LIMIT 5
    ");
    $recentPointTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentPointTransactions = [];
}

// 获取邀请排行榜（最多5个）
$topInviters = [];
try {
    $stmt = $pdo->query("
        SELECT referrer_id, COUNT(*) as invite_count, m.username
        FROM members
        JOIN members m ON members.referrer_id = m.id
        WHERE referrer_id IS NOT NULL
        GROUP BY referrer_id
        ORDER BY invite_count DESC
        LIMIT 5
    ");
    $topInviters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $topInviters = [];
}
?>

<!-- 统计数据卡片 -->
<div class="stats-container">
    <div class="stat-card">
        <div class="stat-icon bg-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($memberCount); ?></div>
            <div class="stat-label">会员总数</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon bg-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <line x1="20" y1="8" x2="20" y2="14"></line>
                <line x1="23" y1="11" x2="17" y2="11"></line>
            </svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($todayNewMembers); ?></div>
            <div class="stat-label">今日新增会员</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon bg-warning">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <line x1="20" y1="8" x2="20" y2="14"></line>
                <line x1="23" y1="11" x2="17" y2="11"></line>
            </svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($invitesCount); ?></div>
            <div class="stat-label">邀请总数</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon bg-info">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
            </svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($pointsTransactions); ?></div>
            <div class="stat-label">积分交易总数</div>
        </div>
    </div>
</div>

<!-- 最近数据卡片 -->
<div class="card">
    <div class="card-header">
        <h3>最近注册会员</h3>
        <a href="?page=members" class="btn btn-sm btn-primary">查看全部</a>
    </div>
    <div class="card-body">
        <table class="data-table">
            <thead>
                <tr>
                    <th>用户名</th>
                    <th>邮箱</th>
                    <th>积分</th>
                    <th>邀请码</th>
                    <th>注册时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recentMembers)): ?>
                    <?php foreach ($recentMembers as $member): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['username']); ?></td>
                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                            <td><?php echo number_format($member['points']); ?></td>
                            <td><?php echo htmlspecialchars($member['invite_code']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($member['created_at'])); ?></td>
                            <td>
                                <a href="?page=members&action=edit&id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">编辑</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">暂无数据</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row" style="display: flex; gap: 20px;">
    <div class="card" style="flex: 1;">
        <div class="card-header">
            <h3>最近积分交易</h3>
            <a href="?page=points_log" class="btn btn-sm btn-primary">查看全部</a>
        </div>
        <div class="card-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>会员</th>
                        <th>积分变动</th>
                        <th>描述</th>
                        <th>时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentPointTransactions)): ?>
                        <?php foreach ($recentPointTransactions as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                                <td style="color: <?php echo $transaction['points'] >= 0 ? '#28a745' : '#dc3545'; ?>">
                                    <?php echo $transaction['points'] >= 0 ? '+' : ''; ?><?php echo $transaction['points']; ?>
                                </td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">暂无数据</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card" style="flex: 1;">
        <div class="card-header">
            <h3>邀请排行榜</h3>
            <a href="?page=invite_log" class="btn btn-sm btn-primary">查看全部</a>
        </div>
        <div class="card-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>排名</th>
                        <th>会员</th>
                        <th>邀请数量</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($topInviters)): ?>
                        <?php $rank = 1; ?>
                        <?php foreach ($topInviters as $inviter): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($inviter['username']); ?></td>
                                <td><?php echo number_format($inviter['invite_count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center;">暂无数据</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div> 