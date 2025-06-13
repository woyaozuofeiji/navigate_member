<?php
// 启用输出缓冲，防止"headers already sent"错误
ob_start();

// 检查会话状态，避免重复启动
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 检查管理员是否已登录，如果未登录则重定向到登录页面
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// 检查会话超时
if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > 1800)) {
    // 清除会话并重定向到登录页面
    session_unset();
    session_destroy();
    header("Location: admin_login.php?timeout=1");
    exit;
}
$_SESSION['admin_last_activity'] = time(); // 更新最后活动时间

// 获取当前显示的页面
$valid_pages = [
    'dashboard', 
    'members', 
    'rewards',
    'redemptions',
    'points',
    'invites',
    'logins'
];

$current_page = isset($_GET['page']) && in_array($_GET['page'], $valid_pages)
    ? $_GET['page']
    : 'dashboard';

// 获取页面标题
$page_titles = [
    'dashboard' => '控制面板',
    'members' => '会员管理',
    'rewards' => '奖品管理',
    'redemptions' => '兑换管理',
    'points' => '积分规则',
    'invites' => '邀请统计',
    'logins' => '登录日志'
];

$page_title = isset($page_titles[$current_page]) ? $page_titles[$current_page] : '管理后台';

// 导航菜单项
$menu_items = [
    [
        'id' => 'dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'text' => '控制面板',
        'url' => '?page=dashboard'
    ],
    [
        'id' => 'members',
        'icon' => 'fas fa-users',
        'text' => '会员管理',
        'url' => '?page=members'
    ],
    [
        'id' => 'point_logs',
        'icon' => 'fas fa-coins',
        'text' => '积分日志',
        'url' => '?page=point_logs'
    ],
    [
        'id' => 'invite_logs',
        'icon' => 'fas fa-user-plus',
        'text' => '邀请记录',
        'url' => '?page=invite_logs'
    ],
    [
        'id' => 'login_logs',
        'icon' => 'fas fa-sign-in-alt',
        'text' => '登录日志',
        'url' => '?page=login_logs'
    ],
    [
        'id' => 'rewards',
        'icon' => 'fas fa-gift',
        'text' => '积分商品管理',
        'url' => '?page=rewards'
    ],
    [
        'id' => 'point_rules',
        'icon' => 'fas fa-cog',
        'text' => '积分规则管理',
        'url' => '?page=point_rules'
    ]
];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - 管理后台</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --secondary-color: #f39c12;
            --text-color: #333;
            --text-light: #666;
            --light-gray: #f5f5f5;
            --border-color: #ddd;
            --dark-bg: #343a40;
            --dark-light: #495057;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* 侧边栏样式 */
        .sidebar {
            width: 250px;
            background-color: var(--dark-bg);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.1);
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h3 {
            color: white;
            margin: 0;
            font-size: 1.5rem;
        }
        
        .sidebar-menu {
            padding: 10px 0;
        }
        
        /* 新的侧边栏导航样式 */
        .sidebar-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-nav li {
            margin: 2px 0;
        }
        
        .sidebar-nav li a {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.2s;
            border-left: 4px solid transparent;
        }
        
        .sidebar-nav li a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar-nav li.active a {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--primary-color);
        }
        
        .sidebar-nav li a svg {
            margin-right: 10px;
            min-width: 18px;
        }
        
        /* 旧的菜单项样式，保留以维持兼容性 */
        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.2s;
            border-left: 4px solid transparent;
        }
        
        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .menu-item.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--primary-color);
        }
        
        .menu-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* 内容区域样式 */
        .content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            overflow-x: hidden;
        }
        
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .topbar-title h1 {
            font-size: 1.5rem;
            color: var(--text-color);
            margin: 0;
        }
        
        .topbar-actions {
            display: flex;
            align-items: center;
        }
        
        .user-info {
            margin-right: 15px;
            font-size: 14px;
            color: var(--text-light);
        }
        
        .logout-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .logout-btn:hover {
            background-color: var(--primary-dark);
        }
        
        /* 卡片样式 */
        .card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            background-color: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 18px;
            color: var(--text-color);
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* 表单样式 */
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        /* 按钮样式 */
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
            display: inline-block;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background-color: #138496;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }
        
        /* 表格样式 */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        /* 分页样式 */
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 20px 0 0;
            justify-content: center;
        }
        
        .pagination li {
            margin: 0 2px;
        }
        
        .pagination li a {
            display: block;
            padding: 8px 12px;
            text-decoration: none;
            color: var(--text-color);
            background-color: white;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .pagination li a:hover {
            background-color: #f5f5f5;
        }
        
        .pagination li a.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        /* 响应式样式 */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
                overflow: visible;
            }
            
            .sidebar-header h3 {
                display: none;
            }
            
            .menu-item span, .sidebar-nav li a span {
                display: none;
            }
            
            .menu-item i, .sidebar-nav li a svg {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .content {
                margin-left: 60px;
            }
        }
        
        @media (max-width: 576px) {
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .topbar-actions {
                margin-top: 10px;
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 侧边栏 -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>管理控制台</h2>
                <span class="admin-name"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            </div>
            <div class="sidebar-menu">
                <a href="index.php" class="menu-item <?php echo empty($_GET['page']) ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    <span>控制面板</span>
                </a>
                <a href="index.php?page=members" class="menu-item <?php echo isset($_GET['page']) && $_GET['page'] === 'members' ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span>会员管理</span>
                </a>
                <a href="index.php?page=rewards" class="menu-item <?php echo isset($_GET['page']) && $_GET['page'] === 'rewards' ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                    <span>奖品管理</span>
                </a>
                <a href="index.php?page=redemptions" class="menu-item <?php echo isset($_GET['page']) && $_GET['page'] === 'redemptions' ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                    <span>兑换管理</span>
                </a>
                <a href="index.php?page=points" class="menu-item <?php echo isset($_GET['page']) && $_GET['page'] === 'points' ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span>积分规则</span>
                </a>
                <a href="index.php?page=invites" class="menu-item <?php echo isset($_GET['page']) && $_GET['page'] === 'invites' ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    <span>邀请统计</span>
                </a>
                <a href="index.php?page=logins" class="menu-item <?php echo isset($_GET['page']) && $_GET['page'] === 'logins' ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    <span>登录日志</span>
                </a>
                <a href="admin_logout.php" class="menu-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span>退出登录</span>
                </a>
            </div>
        </div>
        
        <!-- 内容区域 -->
        <div class="content">
            <!-- 顶部栏 -->
            <div class="topbar">
                <div class="topbar-title">
                    <h1><?php echo $page_title; ?></h1>
                </div>
                <div class="topbar-actions">
                    <div class="user-info">
                        欢迎, <?php echo isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'Admin'; ?>
                    </div>
                    <a href="../admin_logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> 退出登录
                    </a>
                </div>
            </div>
            
            <!-- 页面内容 -->
            <div class="page-content">
                <?php
                // 根据当前页面引入对应的内容
                switch ($current_page) {
                    case 'dashboard':
                        include 'pages/dashboard.php';
                        break;
                    case 'members':
                        include 'pages/members.php';
                        break;
                    case 'rewards':
                        include 'pages/rewards.php';
                        break;
                    case 'redemptions':
                        include 'pages/redemptions.php';
                        break;
                    case 'points':
                        include 'pages/points.php';
                        break;
                    case 'invites':
                        include 'pages/invites.php';
                        break;
                    case 'logins':
                        include 'pages/logins.php';
                        break;
                    default:
                        include 'pages/dashboard.php';
                        break;
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>

<?php
// 输出所有缓冲的内容并关闭缓冲
ob_end_flush();
?> 