<?php
session_start();

// 记录当前会话状态（用于调试）
$sessionDebug = [
    'has_member_id' => isset($_SESSION['member_id']),
    'has_verified' => isset($_SESSION['verified']) && $_SESSION['verified'] === true,
    'session_id' => session_id()
];
error_log('INDEX.PHP 会话状态: ' . json_encode($sessionDebug));

require_once 'member_system.php';
require_once 'includes/functions.php';

// 如果已登录且已验证，则跳转到导航页
if (isset($_SESSION['member_id']) && isset($_SESSION['verified']) && $_SESSION['verified'] === true) {
    error_log('INDEX.PHP: 用户已登录且已验证，重定向到 navigation.php');
    header('Location: navigation.php');
    exit;
}

// 处理会员登录
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // 检查CSRF令牌
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '安全验证失败，请重试';
        $messageType = 'error';
    } else {
        // 获取提交的数据
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $captcha = isset($_POST['captcha']) ? trim($_POST['captcha']) : '';
        $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === 'on';
        
        // 验证码验证
        if (empty($captcha) || !isset($_SESSION['captcha'])) {
            $message = '验证码错误或已过期，请重新输入';
            $messageType = 'error';
            error_log("验证码验证失败: 验证码为空或会话中不存在验证码。用户输入=[" . (empty($captcha) ? "空" : $captcha) . "], 会话中验证码存在=" . (isset($_SESSION['captcha']) ? "是" : "否"));
        }
        else {
            // 将用户输入的验证码转换为大写再比较
            $userCaptcha = strtoupper(trim($captcha));
            $sessionCaptcha = $_SESSION['captcha']; // 获取会话中的验证码
            
            if ($userCaptcha !== $sessionCaptcha) {
                $message = '验证码错误，请重新输入';
                $messageType = 'error';
                
                // 记录错误信息，帮助调试
                error_log("验证码不匹配: 用户输入=[$userCaptcha], 会话中=[$sessionCaptcha], 会话ID=" . session_id());
            }
            // 简单验证
            else if (empty($username) || empty($password)) {
                $message = '请填写用户名和密码';
                $messageType = 'error';
            } else {
                // 登录会员
                $memberSystem = new MemberSystem();
                $result = $memberSystem->login($username, $password);
                
                if ($result['success']) {
                    // 设置记住我的Cookie
                    if ($rememberMe) {
                        $token = bin2hex(random_bytes(32));
                        setcookie('remember_token', $token, time() + 30 * 24 * 60 * 60, '/', '', true, true);
                    }
                    
                    // 设置验证状态
                    $_SESSION['verified'] = true;
                    $_SESSION['verified_time'] = time();
                    
                    // 清除验证码
                    unset($_SESSION['captcha']);
                    
                    header('Location: navigation.php');
                    exit;
                } else {
                    $message = $result['message'];
                    $messageType = 'error';
                }
            }
        }
    }
}

// 生成新的CSRF令牌
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <?php include 'analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>福利导航 | 成人导航</title>
    <link rel="stylesheet" href="css/style.css">
    <meta name="theme-color" content="#5371ff">
    <meta name="keywords" content="福利导航,成人导航,色情网站,成人福利导航网站">
    <meta name="description" content="成人福利导航是一个专注成人福利网站的色情导航网站,同时欢迎站长朋友们加入我们导航网站">
    <style>
        .debug-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>欢迎访问导航中心</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message show <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="login-container">
            <form method="post" action="index.php" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="username">用户名或邮箱</label>
                    <input type="text" id="username" name="username" placeholder="请输入用户名或邮箱" required>
                </div>
                
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" placeholder="请输入密码" required>
                    <a href="#" class="forgot-password">忘记密码？</a>
                </div>
                
                <div class="form-group">
                    <label for="captcha">验证码</label>
                    <div class="captcha-container">
                        <img src="captcha.php?t=<?php echo time(); ?>" alt="验证码" id="captchaImage" class="captcha">
                        <button type="button" id="refreshCaptcha" class="btn-icon" title="刷新验证码">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                        </button>
                    </div>
                    <input type="text" id="captcha" name="captcha" placeholder="请输入验证码" required>
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <label for="remember_me">记住我</label>
                </div>
                
                <div class="form-footer">
                    <a href="register.php" class="register-link">没有账号？立即注册</a>
                    <button type="submit" class="btn">登录</button>
                </div>
                
                <div class="login-footer">
                    <div class="footer-copyright">
                        <p>© <?php echo date('Y'); ?> 成人导航 - 您的专属上网入口</p>
                        <p><a href="privacy.php">隐私政策</a> · <a href="terms.php">服务条款</a></p>
                    </div>
                </div>
            </form>
        </div>
        
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 刷新验证码
            const refreshCaptchaButton = document.getElementById('refreshCaptcha');
            const captchaImage = document.getElementById('captchaImage');
            
            refreshCaptchaButton.addEventListener('click', function(e) {
                e.preventDefault(); // 阻止默认行为
                captchaImage.src = 'captcha.php?refresh=' + new Date().getTime();
            });
            
            // 自动聚焦到用户名输入框
            document.getElementById('username').focus();
            
            // 每30秒自动刷新验证码，确保不会过期
            setInterval(function() {
                captchaImage.src = 'captcha.php?refresh=' + new Date().getTime();
            }, 30000);
        });
    </script>
</body>
</html>