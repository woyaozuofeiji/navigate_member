<?php
session_start();
require_once 'member_system.php';
require_once 'includes/functions.php';

// 如果已登录，则跳转到会员中心
if (isset($_SESSION['member_id'])) {
    header('Location: member.php');
    exit;
}

// 处理表单提交
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $message = '验证码错误，请重新输入';
            $messageType = 'error';
        }
        else {
            // 将用户输入的验证码转换为大写再比较
            $userCaptcha = strtoupper(trim($captcha));
            
            if ($userCaptcha !== $_SESSION['captcha']) {
                $message = '验证码错误，请重新输入';
                $messageType = 'error';
                
                // 记录错误信息，帮助调试
                error_log("验证码不匹配: 用户输入=[$userCaptcha], 会话中=[{$_SESSION['captcha']}]");
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
                        
                        // 这里应该将token存储到数据库，与用户关联
                        // 出于简化考虑，这里省略了该步骤
                    }
                    
                    // 设置验证状态
                    $_SESSION['verified'] = true;
                    $_SESSION['verified_time'] = time();
                    
                    // 清除验证码
                    unset($_SESSION['captcha']);
                    
                    // 记录登录成功信息
                    error_log("登录成功: 用户={$username}, 会话ID=" . session_id());
                    
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

// 检查是否有注册成功的消息
if (isset($_SESSION['registration_success']) && $_SESSION['registration_success'] === true) {
    $message = '注册成功，请登录您的账号';
    $messageType = 'success';
    
    // 清除会话变量
    unset($_SESSION['registration_success']);
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
    <title>会员登录 - 无极导航</title>
    <meta name="description" content="登录无极导航会员账号，享受更多专属服务和内容。">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>会员登录</h1>
        
        <div class="login-container">
            <?php if (!empty($message)): ?>
                <div class="message show <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="login.php" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="username">用户名或邮箱</label>
                    <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" placeholder="请输入用户名或邮箱">
                </div>
                
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" required placeholder="请输入密码">
                    <a href="#" class="forgot-password">忘记密码？</a>
                </div>
                
                <div class="form-group">
                    <label for="captcha">验证码</label>
                    <div class="captcha-container">
                        <img src="captcha.php" alt="验证码" id="captchaImage" class="captcha">
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
                        <p>© <?php echo date('Y'); ?> 无极导航 - 您的专属上网入口</p>
                        <p><a href="privacy.php">隐私政策</a> · <a href="terms.php">服务条款</a></p>
                    </div>
                </div>
            </form>
        </div>
        
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 如果有成功消息，自动设置焦点到用户名输入框
            const successMessage = document.querySelector('.message.success');
            if (successMessage) {
                document.getElementById('username').focus();
                
                // 如果URL中有注册成功的用户名参数，自动填充
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('username')) {
                    document.getElementById('username').value = urlParams.get('username');
                    document.getElementById('password').focus();
                }
            } else {
                // 没有成功消息，也聚焦用户名输入框
                document.getElementById('username').focus();
            }
            
            // 刷新验证码
            const refreshCaptchaButton = document.getElementById('refreshCaptcha');
            const captchaImage = document.getElementById('captchaImage');
            
            if (refreshCaptchaButton && captchaImage) {
                refreshCaptchaButton.addEventListener('click', function() {
                    captchaImage.src = 'captcha.php?refresh=' + new Date().getTime();
                });
            }
        });
    </script>
</body>
</html> 