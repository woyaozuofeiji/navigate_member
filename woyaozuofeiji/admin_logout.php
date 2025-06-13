<?php
session_start();

// 清除所有管理员会话数据
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_last_activity']);

// 重定向到登录页面
header('Location: admin_login.php?logout=1');
exit;
?> 