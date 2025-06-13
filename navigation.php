<?php
// 确保会话已启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 如果未登录或未验证，重定向到登录页
if (!isset($_SESSION['member_id']) || !isset($_SESSION['verified']) || $_SESSION['verified'] !== true) {
    header('Location: index.php');
    exit;
}

// 更新最后活动时间
$_SESSION['last_activity'] = time();

// 加载加密类
require_once 'encryption.php';

// 定义加密密钥（建议使用环境变量存储）
$encryptionKey = getenv('ENCRYPTION_KEY') ?: 'your-secret-key-here';
$encryption = new Encryption($encryptionKey);

// 加载导航链接数据
require_once 'nav_links.php';

// 加密所有数据（包括URL）
$encryptedNavLinks = array_map(function($category) use ($encryption) {
    return [
        'name' => $encryption->encrypt($category['name']),
        'links' => array_map(function($link) use ($encryption) {
            return [
                'name' => $encryption->encrypt($link['name']),
                'url' => $encryption->encrypt($link['url'])
            ];
        }, $category['links'])
    ];
}, $navLinks);

// 将加密后的数据转换为JSON并再次加密整个数据结构
$encryptedData = $encryption->encrypt(json_encode($encryptedNavLinks));

// 检查是否是会员
$isMember = isset($_SESSION['member_id']);
$isVIP = isset($_SESSION['member_vip_level']) && $_SESSION['member_vip_level'] > 0;

// 获取用户积分信息和简化的商品兑换信息
require_once 'member_system.php';
$memberSystem = new MemberSystem();

// 获取最热门的两个奖励商品
$hotRewards = $memberSystem->getRewards();
$topRewards = [];
if ($hotRewards['success'] && !empty($hotRewards['rewards'])) {
    $topRewards = array_slice($hotRewards['rewards'], 0, 2);
}

// 获取用户最新的积分数据
if ($isMember) {
    $memberInfo = $memberSystem->getMemberInfo($_SESSION['member_id']);
    if ($memberInfo['success']) {
        $_SESSION['member_points'] = $memberInfo['member']['points'];
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <?php include 'analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>导航中心 | 一站式资源平台</title>
    <link rel="stylesheet" href="css/navigation.css">
    <meta name="theme-color" content="#5371ff">
    <meta name="description" content="导航中心提供一站式资源平台，帮助用户快速访问常用网站。">
    <style>
        /* 会员菜单样式 */
        .member-menu {
            display: flex;
            align-items: center;
            margin-right: 20px;
        }
        
        .member-menu a {
            color: var(--text-color);
            text-decoration: none;
            margin-right: 15px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            transition: color 0.3s;
        }
        
        .member-menu a:hover {
            color: var(--primary-color);
        }
        
        .member-menu svg {
            margin-right: 6px;
        }
        
        .member-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5371ff, #ff6b8b);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .vip-tag {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 5px;
        }
        
        /* 会员信息区域响应式布局 */
        .member-info-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .member-info-card {
            flex: 1;
            min-width: 250px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .member-basic-info {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .member-points {
            display: flex;
            align-items: center;
            font-size: 16px;
            margin-bottom: 10px;
            color: var(--text-color);
        }
        
        .points-value {
            font-weight: 700;
            color: #FFA500;
            margin-left: 5px;
        }
        
        .invite-code {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 5px;
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 12px;
            color: var(--text-color);
            display: flex;
            align-items: center;
        }
        
        .card-title svg {
            margin-right: 6px;
        }
        
        .reward-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed rgba(125, 125, 125, 0.2);
        }
        
        .reward-name {
            font-size: 14px;
            color: var(--text-color);
        }
        
        .reward-points {
            font-size: 14px;
            font-weight: 500;
            color: #FFA500;
        }
        
        .view-more {
            text-align: right;
            margin-top: 10px;
        }
        
        .view-more a {
            color: var(--primary-color);
            font-size: 14px;
            text-decoration: none;
        }
        
        .invite-desc {
            font-size: 14px;
            margin-bottom: 12px;
            color: var(--text-color);
        }
        
        .invite-link-container {
            display: flex;
            gap: 10px;
        }
        
        .invite-input {
            flex: 1;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-color);
            font-size: 14px;
        }
        
        .copy-btn {
            white-space: nowrap;
            padding: 8px 15px;
            font-size: 14px;
        }
        
        /* 会员中心按钮 */
        .member-center-btn {
            display: block;
            width: 100%;
            text-align: center;
            background: linear-gradient(135deg, #5371ff, #ff6b8b);
            color: white;
            font-weight: 600;
            padding: 12px 20px;
            border-radius: 8px;
            margin-top: 15px;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(83, 113, 255, 0.2);
        }
        
        .member-center-btn:hover, 
        .member-center-btn:focus {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(83, 113, 255, 0.3);
        }
        
        .member-center-btn svg {
            margin-right: 8px;
            vertical-align: middle;
        }
        
        /* 移动端优化 */
        @media (max-width: 768px) {
            .member-info-container {
                flex-direction: column;
            }
            
            .member-info-card {
                width: 100%;
                min-width: 100%;
                margin-bottom: 15px;
            }
            
            .invite-link-container {
                flex-direction: column;
                gap: 8px;
            }
            
            .invite-input {
                width: 100%;
            }
            
            .copy-btn {
                width: 100%;
            }
            
            /* 移动端头部优化 */
            .app-header {
                padding: 10px 15px;
            }
            
            .logo-text {
                font-size: 14px;
            }
            
            .member-menu {
                margin-right: 10px;
            }
            
            .member-menu a span {
                display: none; /* 隐藏用户名，只显示头像 */
            }
            
            .member-avatar {
                margin-right: 0;
            }
            
            .btn {
                padding: 6px 12px;
                font-size: 13px;
            }
            
            /* 移动端会员信息区域优化 */
            .section.member-info {
                padding: 15px;
                margin-bottom: 20px;
            }
            
            .member-info-card {
                padding: 12px;
            }
            
            .member-basic-info {
                margin-bottom: 8px;
            }
            
            /* 移动端显示优化 */
            .mobile-hidden {
                display: none;
            }
            
            .mobile-visible {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- 顶部导航栏 -->
        <header class="app-header">
            <div class="app-logo">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#headerGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <defs>
                        <linearGradient id="headerGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#5371ff" />
                            <stop offset="100%" stop-color="#ff6b8b" />
                        </linearGradient>
                    </defs>
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 16l4-4-4-4"></path>
                    <path d="M8 12h8"></path>
                </svg>
                <span class="logo-text">导航中心-kannibi.com</span>
            </div>
            
            <div class="header-actions">
                <?php if ($isMember): ?>
                    <div class="member-menu">
                        <a href="member.php">
                            <?php if ($isVIP): ?>
                                <div class="member-avatar">
                                    <?php echo strtoupper(substr($_SESSION['member_username'], 0, 1)); ?>
                                </div>
                                <span><?php echo htmlspecialchars($_SESSION['member_username']); ?></span>
                                <span class="vip-tag">VIP</span>
                            <?php else: ?>
                                <div class="member-avatar">
                                    <?php echo strtoupper(substr($_SESSION['member_username'], 0, 1)); ?>
                                </div>
                                <span><?php echo htmlspecialchars($_SESSION['member_username']); ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <button id="themeToggle" class="btn btn-icon btn-secondary" title="切换主题">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                    </button>
                    <a href="logout.php" class="btn btn-danger">退出登录</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary">会员登录</a>
                    <a href="register.php" class="btn btn-primary">注册会员</a>
                    <button id="themeToggle" class="btn btn-icon btn-secondary" title="切换主题">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                    </button>
                <?php endif; ?>
            </div>
        </header>
        
        <!-- 主内容区域 -->
        <main class="content-wrapper">
            <!-- 背景装饰 -->
            <div class="bg-decoration">
                <svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200" fill="none" opacity="0.03" style="position: absolute; top: 5%; right: 5%; z-index: -1;">
                    <circle cx="100" cy="100" r="80" stroke="url(#blueGradient)" stroke-width="40" />
                    <defs>
                        <linearGradient id="blueGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#5371ff" />
                            <stop offset="100%" stop-color="#8c6ff0" />
                        </linearGradient>
                    </defs>
                </svg>
                <svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" viewBox="0 0 150 150" fill="none" opacity="0.03" style="position: absolute; bottom: 5%; left: 5%; z-index: -1;">
                    <rect x="25" y="25" width="100" height="100" stroke="url(#pinkGradient)" stroke-width="50" />
                    <defs>
                        <linearGradient id="pinkGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ff6b8b" />
                            <stop offset="100%" stop-color="#ffcb70" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            
            <!-- 会员信息和积分区域 -->
            <div class="section member-info" style="padding: 20px; margin-bottom: 30px; background: linear-gradient(135deg, rgba(83, 113, 255, 0.1), rgba(255, 107, 139, 0.1)); border-radius: 12px;">
                <!-- 移动端会员简明信息卡片 -->
                <div class="mobile-visible member-info-card" style="display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="member-basic-info">
                            <div class="member-avatar">
                                <?php echo strtoupper(substr($_SESSION['member_username'], 0, 1)); ?>
                            </div>
                            <h3 style="margin: 0 0 0 10px; font-size: 18px;">
                                <?php echo htmlspecialchars($_SESSION['member_username']); ?> 
                                <?php if ($isVIP): ?><span class="vip-tag">VIP</span><?php endif; ?>
                            </h3>
                        </div>
                        <div class="member-points" style="margin-bottom: 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #FFA500;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            <span class="points-value"><?php echo isset($_SESSION['member_points']) ? $_SESSION['member_points'] : 0; ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="member-info-container">
                    <!-- 会员信息和积分 -->
                    <div class="member-info-card">
                        <div class="member-basic-info">
                            <div class="member-avatar">
                                <?php echo strtoupper(substr($_SESSION['member_username'], 0, 1)); ?>
                            </div>
                            <h3 style="margin: 0 0 0 10px; font-size: 18px;">
                                <?php echo htmlspecialchars($_SESSION['member_username']); ?> 
                                <?php if ($isVIP): ?><span class="vip-tag">VIP</span><?php endif; ?>
                            </h3>
                        </div>
                        <div class="member-points">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #FFA500;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            <span>当前积分：<span class="points-value"><?php echo isset($_SESSION['member_points']) ? $_SESSION['member_points'] : 0; ?></span></span>
                        </div>
                        <div class="invite-code">邀请码：<?php echo htmlspecialchars($_SESSION['member_invite_code']); ?></div>
                    </div>
                    
                    <!-- 热门兑换商品 -->
                    <div class="member-info-card">
                        <h3 class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v6.5l3-3"/><path d="M12 2v6.5l-3-3"/><path d="M3.44 7.44 5.18 9.2l3.05-3.03"/><path d="M20.56 7.44 18.82 9.2l-3.05-3.03"/><path d="M3.27 16.27 5.3 14.3l-3.04-3.03"/><path d="M20.73 16.27 18.7 14.3l3.04-3.03"/><path d="M12 22v-6.5l3 3"/><path d="M12 22v-6.5l-3 3"/><circle cx="12" cy="12.5" r="1.5"/></svg>
                            热门兑换
                        </h3>
                        <?php if (!empty($topRewards)): ?>
                            <?php foreach ($topRewards as $reward): ?>
                            <div class="reward-item">
                                <span class="reward-name"><?php echo htmlspecialchars($reward['name']); ?></span>
                                <span class="reward-points"><?php echo $reward['points_cost']; ?> 积分</span>
                            </div>
                            <?php endforeach; ?>
                            <div class="view-more">
                                <a href="member.php">查看更多 →</a>
                            </div>
                        <?php else: ?>
                            <p style="font-size: 14px; color: var(--text-light);">暂无热门商品</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 邀请活动 -->
                    <div class="member-info-card">
                        <h3 class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            邀请送积分
                        </h3>
                        <p class="invite-desc">邀请好友注册，获得<strong style="color: #FFA500;">50积分</strong>奖励！</p>
                        <div class="invite-link-container">
                            <input type="text" readonly class="invite-input" value="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/register.php?invite=" . $_SESSION['member_invite_code']; ?>" id="inviteLink">
                            <button onclick="copyInviteLink()" class="btn btn-primary copy-btn">复制链接</button>
                        </div>
                    </div>
                </div>
                
                <!-- 会员中心按钮 -->
                <a href="member.php" class="member-center-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    进入会员中心
                </a>
            </div>
            
            <!-- 导航链接容器 - 将由JS填充 -->
            <div id="navContainer"></div>
        </main>
        
        <!-- 页脚 -->
        <footer class="app-footer">
            <div class="footer-text">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>安全通道已建立 · 数据已加密</span>
            </div>
            <div class="footer-copyright">
                <p>© 2023-2025 无极导航 - 精选优质网络资源的专业导航平台</p>
                <p><a href="#">网站地图</a> | <a href="#">关于我们</a> | 联系方式：woyaozuofeiji@gmail.com</p>
                <p>本站资源均来自互联网，如有侵权请联系删除</p>
                <p>kannibi.com 保留所有权利</p>
            </div>
        </footer>
    </div>
    
    <!-- 加载动画 -->
    <div class="loading-container" id="loadingContainer">
        <div class="loading-spinner">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>
        <div style="margin-top: 20px; font-weight: 500; color: var(--primary-color);">加载中...</div>
    </div>

    <!-- 存储加密数据 -->
    <div id="encryptedData" style="display: none;"><?php echo htmlspecialchars($encryptedData); ?></div>

    <!-- 现有脚本 -->
    <script src="js/navigation.js"></script>
    
    <!-- 邀请链接复制功能 -->
    <script>
        function copyInviteLink() {
            const inviteLinkInput = document.getElementById('inviteLink');
            inviteLinkInput.select();
            document.execCommand('copy');
            
            // 显示复制成功提示
            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = '已复制！';
            btn.style.backgroundColor = '#00c853';
            
            // 2秒后恢复按钮原样
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.backgroundColor = '';
            }, 2000);
        }
        
        // 初始化时处理移动端显示
        document.addEventListener('DOMContentLoaded', function() {
            function handleMobileDisplay() {
                const isMobile = window.innerWidth <= 768;
                const mobileCard = document.querySelector('.mobile-visible.member-info-card');
                const desktopCards = document.querySelectorAll('.member-info-container .member-info-card');
                
                if (mobileCard) {
                    mobileCard.style.display = isMobile ? 'block' : 'none';
                }
                
                // 在移动端只显示热门兑换和邀请送积分卡片，隐藏会员信息卡片
                if (isMobile && desktopCards.length > 0) {
                    desktopCards[0].classList.add('mobile-hidden');
                } else if (desktopCards.length > 0) {
                    desktopCards[0].classList.remove('mobile-hidden');
                }
            }
            
            // 初始调用
            handleMobileDisplay();
            
            // 监听窗口大小变化
            window.addEventListener('resize', handleMobileDisplay);
        });
    </script>
</body>
</html>
        