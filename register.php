<?php
session_start();
require_once 'member_system.php';
require_once 'includes/functions.php';

// 如果已登录，则跳转到会员中心
if (isset($_SESSION['member_id'])) {
    header('Location: member.php');
    exit;
}

// 获取邀请码（如果有）
$inviteCode = isset($_GET['invite']) ? trim($_GET['invite']) : '';

// 获取邀请人信息
$inviterInfo = null;
if (!empty($inviteCode)) {
    $memberSystem = new MemberSystem();
    $inviterInfo = $memberSystem->getInviterInfo($inviteCode);
    
    // 处理邀请链接访问奖励积分
    if ($inviterInfo['success']) {
        // 检查是否已经记录过此邀请链接访问
        $visitCookieName = 'invite_visit_' . md5($inviteCode);
        if (!isset($_COOKIE[$visitCookieName])) {
            // 获取访客IP
            $visitorIP = getRealIpAddr();
            
            // 记录访问并给邀请人增加积分
            $visitResult = $memberSystem->recordInviteLinkVisit($inviteCode, $visitorIP);
            
            // 如果成功记录访问，添加提示消息
            if ($visitResult['success']) {
                $inviteVisitMessage = "感谢您使用邀请链接访问，邀请人已获得10积分奖励！";
            }
            
            // 设置cookie防止重复记录（有效期7天）
            setcookie($visitCookieName, '1', time() + 7 * 24 * 3600, '/');
        }
    }
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
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $captcha = isset($_POST['captcha']) ? trim($_POST['captcha']) : '';
        // 优先使用表单提交的邀请码，如果没有则使用URL中的邀请码
        $postInviteCode = isset($_POST['invite_code']) ? trim($_POST['invite_code']) : '';
        $inviteCode = !empty($postInviteCode) ? $postInviteCode : $inviteCode;
        
        // 验证码验证
        if (empty($captcha) || !isset($_SESSION['captcha']) || strtoupper($captcha) !== $_SESSION['captcha']) {
            $message = '验证码错误，请重新输入';
            $messageType = 'error';
        }
        // 简单验证
        else if (empty($username) || empty($email) || empty($password)) {
            $message = '请填写所有必填字段';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '请输入有效的电子邮件地址';
            $messageType = 'error';
        } elseif (strlen($password) < 6) {
            $message = '密码必须至少包含6个字符';
            $messageType = 'error';
        } elseif ($password !== $confirmPassword) {
            $message = '两次输入的密码不一致';
            $messageType = 'error';
        } else {
            // 注册会员
            $memberSystem = new MemberSystem();
            $result = $memberSystem->register($username, $password, $email, $inviteCode);
            
            if ($result['success']) {
                $_SESSION['registration_success'] = true;
                $_SESSION['registered_username'] = $username;
                
                // 清除验证码
                unset($_SESSION['captcha']);
                
                // 自动登录
                $loginResult = $memberSystem->login($username, $password);
                if ($loginResult['success']) {
                    // 显式设置会话验证状态为true，解决重定向循环问题
                    $_SESSION['verified'] = true;
                    $_SESSION['verified_time'] = time();
                    $_SESSION['last_activity'] = time();
                    
                    // 添加延迟和记录
                    usleep(500000); // 等待0.5秒确保会话已保存
                    error_log("用户注册成功: {$username}, 会话ID: ".session_id().", 验证状态: ".($_SESSION['verified'] ? 'true' : 'false'));
                    
                    header('Location: member.php');
                    exit;
                } else {
                    // 登录失败，重定向到登录页面
                    $_SESSION['login_error'] = $loginResult['message'];
                    header('Location: login.php');
                    exit;
                }
            } else {
                $message = $result['message'];
                $messageType = 'error';
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
    <title>会员注册 - 无极导航</title>
    <meta name="description" content="注册无极导航会员，享受更多专属服务和积分奖励。">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .security-note {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 15px;
            background-color: var(--warning-bg, rgba(255, 193, 7, 0.1));
            border-left: 3px solid var(--warning-color, #ffc107);
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--text-color);
        }
        
        .security-note svg {
            color: var(--warning-color, #ffc107);
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>会员注册</h1>
        
        <div class="register-container">
            <?php if (!empty($message)): ?>
                <div class="message show <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($inviteVisitMessage)): ?>
                <div class="message show success">
                    <?php echo $inviteVisitMessage; ?>
                </div>
            <?php endif; ?>
            
            <div class="security-note">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                <span>为防止滥用，同一IP地址只允许注册一个账号</span>
            </div>
            
            <form method="post" action="register.php" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="username">用户名 <span style="color: var(--accent-color);">*</span></label>
                    <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" placeholder="请设置您的用户名">
                </div>
                
                <div class="form-group">
                    <label for="email">电子邮箱 <span style="color: var(--accent-color);">*</span></label>
                    <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="请输入您的电子邮箱">
                </div>
                
                <div class="form-group">
                    <label for="password">密码 <span style="color: var(--accent-color);">*</span></label>
                    <input type="password" id="password" name="password" required minlength="6" placeholder="请设置密码，不少于6个字符">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">确认密码 <span style="color: var(--accent-color);">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="请再次输入密码">
                </div>
                
                <div class="form-group">
                    <label for="captcha">验证码 <span style="color: var(--accent-color);">*</span></label>
                    <div class="captcha-container">
                        <img src="captcha.php" alt="验证码" id="captchaImage" class="captcha">
                        <button type="button" id="refreshCaptcha" class="btn-icon" title="刷新验证码">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                        </button>
                    </div>
                    <input type="text" id="captcha" name="captcha" placeholder="请输入验证码" required>
                </div>
                
                <div class="form-group">
                    <label for="invite_code">邀请码 (可选)</label>
                    <input type="text" id="invite_code" name="invite_code" value="<?php echo htmlspecialchars($inviteCode); ?>" <?php if (!empty($inviteCode)) echo 'readonly'; ?> placeholder="如有邀请码请在此输入">
                    <?php if ($inviterInfo && $inviterInfo['success']): ?>
                    <div class="inviter-info">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        您正在使用 <strong><?php echo htmlspecialchars($inviterInfo['inviter']['username']); ?></strong> 的邀请码注册
                        <?php if ($inviterInfo['inviter']['vip_level'] > 0): ?>
                        <span class="inviter-vip">VIP</span>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="invite-code-info">如果您有邀请码，请在此输入。使用邀请码注册可获得额外积分奖励。</div>
                    <?php endif; ?>
                </div>
                
                <div class="terms-checkbox">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">我已阅读并同意<a href="#" target="_blank">服务条款</a>和<a href="#" target="_blank">隐私政策</a></label>
                </div>
                
                <div class="form-footer">
                    <a href="login.php" class="login-link">已有账号？立即登录</a>
                    <button type="submit" class="btn">注册</button>
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
            const form = document.getElementById('registerForm');
            
            // 自动聚焦用户名输入框
            document.getElementById('username').focus();
            
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'message show error';
                    messageDiv.textContent = '两次输入的密码不一致';
                    
                    const existingMessage = document.querySelector('.message');
                    if (existingMessage) {
                        existingMessage.replaceWith(messageDiv);
                    } else {
                        form.insertBefore(messageDiv, form.firstChild);
                    }
                }
            });
            
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