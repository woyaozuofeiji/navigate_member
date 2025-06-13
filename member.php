<?php
// 引入验证检查
require_once __DIR__ . '/includes/auth_check.php';

require_once 'member_system.php';

// 获取会员信息
$memberSystem = new MemberSystem();

// 获取最新的会员信息（包括积分）
$memberInfo = $memberSystem->getMemberInfo($_SESSION['member_id']);
if ($memberInfo['success']) {
    // 更新会话中的会员信息
    $_SESSION['member_points'] = $memberInfo['member']['points'];
    // 如果有其他需要更新的信息，也可以在这里更新
}

// 获取积分历史
$pointsHistory = $memberSystem->getPointsHistory($_SESSION['member_id'], 5);

// 获取推荐列表
$referrals = $memberSystem->getReferrals($_SESSION['member_id']);

// 获取邀请统计
$inviteStats = $memberSystem->getInviteStats($_SESSION['member_id']);

// 获取积分奖励商品
$cryptoRewards = $memberSystem->getRewards('crypto');
$physicalRewards = $memberSystem->getRewards('physical');

// 生成邀请链接
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$scriptDir = $scriptDir !== '/' ? $scriptDir : '';
$inviteLink = $baseUrl . $scriptDir . '/register.php?invite=' . $_SESSION['member_invite_code'];

// 处理表单提交
$message = '';
$messageType = '';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <?php include 'analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会员中心 - 无极导航</title>
    <meta name="description" content="无极导航会员中心，管理您的账号、积分和VIP权益。">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/navigation.css">
    <style>
        .member-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .member-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5371ff, #ff6b8b);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            margin-right: 20px;
        }
        
        .user-details h2 {
            margin: 0 0 5px 0;
            font-size: 20px;
        }
        
        .user-status {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .points-badge {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .member-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .member-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .member-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
        }
        
        .member-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-icon {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #5371ff, #ff6b8b);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-icon svg {
            width: 14px;
            height: 14px;
            stroke: white;
        }
        
        /* 积分历史样式 */
        .points-history {
            list-style-type: none;
            padding: 0;
            margin: 0;
            max-height: 250px;
            overflow-y: auto;
        }
        
        .points-history li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .points-history li:last-child {
            border-bottom: none;
        }
        
        .points-history li:nth-child(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .history-details {
            flex: 1;
            padding-right: 10px;
        }
        
        .history-title {
            font-weight: 500;
            margin-bottom: 4px;
            word-break: break-word;
        }
        
        .history-date {
            font-size: 12px;
            color: var(--text-light);
        }
        
        .points-badge {
            font-weight: 600;
            min-width: 70px;
            text-align: right;
        }
        
        .points-badge.positive {
            color: #28a745;
        }
        
        .points-badge.negative {
            color: #dc3545;
        }
        
        /* 邀请链接样式 */
        .invite-link-container {
            display: flex;
            align-items: center;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .invite-link {
            flex: 1;
            padding: 10px 15px;
            font-size: 14px;
            color: var(--text-color);
            word-break: break-all;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .copy-button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .copy-button:hover {
            background-color: #4261e4;
        }
        
        .invite-link:hover {
            background: var(--hover-color);
        }
        
        /* 修复移动端顶部导航 */
        .app-header {
            position: relative;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px var(--shadow-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            z-index: 10;
            border-radius: var(--border-radius-lg);
        }
        
        /* 退出登录按钮样式 */
        .logout-btn {
            background-color: var(--error-color);
            color: white;
            border: none;
            font-size: 14px;
            margin-left: 10px;
        }
        
        .logout-btn:hover {
            background-color: #c0392b;
        }
        
        /* 用户操作按钮样式 */
        .user-actions {
            display: flex;
            align-items: center;
        }
        
        /* 积分历史按钮 */
        .view-all-btn {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            text-decoration: none;
        }
        
        .view-all-btn:hover {
            background-color: rgba(83, 113, 255, 0.1);
        }
        
        .view-all-btn svg {
            width: 16px;
            height: 16px;
        }
        
        /* 签到按钮样式 */
        .sign-in-btn {
            background: linear-gradient(135deg, #5371ff, #38b2ac);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 6px rgba(83, 113, 255, 0.3);
        }
        
        .sign-in-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(83, 113, 255, 0.4);
        }
        
        .member-badge.signed {
            background: #e6f7ff;
            color: #0070f3;
            border-color: #b3e0ff;
        }
        
        /* 签到成功提示 */
        .sign-in-success {
            position: fixed;
            top: 70px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 200, 83, 0.9);
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .sign-in-success.show {
            opacity: 1;
        }
        
        .sign-in-success svg {
            width: 20px;
            height: 20px;
        }
        
        /* 签到记录样式 */
        .signin-history {
            list-style-type: none;
            padding: 0;
            margin: 0;
            max-height: 250px;
            overflow-y: auto;
        }
        
        .signin-history li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .signin-history li:last-child {
            border-bottom: none;
        }
        
        .signin-history li:nth-child(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .signin-info {
            display: flex;
            flex-direction: column;
        }
        
        .signin-date {
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .signin-time {
            font-size: 12px;
            color: var(--text-light);
        }
        
        .signin-points {
            font-weight: 600;
            color: #28a745;
            min-width: 60px;
            text-align: right;
        }
        
        .empty-message {
            text-align: center;
            padding: 20px 0;
            color: var(--text-light);
            font-style: italic;
        }
        
        .consecutive-days {
            background: linear-gradient(135deg, #5371ff, #38b2ac);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(83, 113, 255, 0.3);
        }
        
        .days-count {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .days-text {
            font-size: 13px;
            opacity: 0.9;
        }
        
        /* 响应式样式 */
        @media (max-width: 1024px) {
            .member-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .app-header {
                padding: 10px 15px;
                border-radius: 8px;
                margin-bottom: 15px;
            }
            
            .header-actions {
                display: flex;
                gap: 10px;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .app-logo {
                display: flex;
                align-items: center;
            }
            
            .logo-text {
                font-size: 16px;
            }
            
            .member-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
                padding-bottom: 15px;
            }
            
            .member-grid {
                grid-template-columns: 1fr;
            }
            
            .invite-actions {
                flex-direction: column;
            }
            
            .qrcode-container {
                margin-right: 0;
                margin-bottom: 20px;
            }
            
            .share-buttons {
                flex-direction: row;
                flex-wrap: wrap;
            }
            
            .share-btn {
                flex: 1;
                min-width: 80px;
                margin-right: 8px;
            }
            
            .member-container {
                margin-top: 15px;
                padding: 0 15px;
            }
            
            .user-avatar {
                width: 50px;
                height: 50px;
                font-size: 20px;
                margin-right: 15px;
            }
            
            .user-details h2 {
                font-size: 18px;
            }
        }
        
        @media (max-width: 480px) {
            .app-header {
                padding: 8px 12px;
                border-radius: 6px;
            }
            
            .btn-secondary {
                font-size: 12px;
                padding: 6px 10px;
            }
            
            .app-logo svg {
                width: 20px;
                height: 20px;
            }
            
            .logo-text {
                font-size: 14px;
            }
            
            .user-details h2 {
                font-size: 16px;
            }
            
            .member-card {
                padding: 15px;
            }
            
            .member-card h3 {
                font-size: 16px;
            }
            
            .rewards-section {
                padding: 15px;
                margin-left: 15px;
                margin-right: 15px;
                width: calc(100% - 30px);
            }
            
            .rewards-container {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .reward-image {
                height: 120px;
            }
        }
        
        /* 邀请统计样式 */
        .invite-stats {
            display: flex;
            margin-bottom: 20px;
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .stat-item {
            flex: 1;
            text-align: center;
            padding: 15px 5px;
            border-right: 1px solid var(--border-color);
        }
        
        .stat-item:last-child {
            border-right: none;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-light);
        }
        
        /* 邀请分享样式 */
        .invite-actions {
            display: flex;
            margin-bottom: 25px;
            justify-content: space-between;
        }
        
        .qrcode-container {
            text-align: center;
            margin-right: 20px;
        }
        
        #qrcode {
            width: 120px;
            height: 120px;
            margin: 0 auto;
            background: white;
            padding: 5px;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .qrcode-tip {
            font-size: 12px;
            margin-top: 8px;
            color: var(--text-light);
        }
        
        .share-buttons {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex: 1;
        }
        
        .share-btn {
            display: flex;
            align-items: center;
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            margin-bottom: 8px;
            transition: opacity 0.3s;
        }
        
        .share-btn:hover {
            opacity: 0.9;
        }
        
        .share-btn svg {
            margin-right: 8px;
        }
        
        .wechat {
            background-color: #07C160;
        }
        
        .weibo {
            background-color: #E6162D;
        }
        
        .qq {
            background-color: #12B7F5;
        }
        
        .referral-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        
        .referral-list li {
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .referral-list li:last-child {
            border-bottom: none;
        }
        
        .referral-user {
            display: flex;
            align-items: center;
            margin-bottom: 3px;
        }
        
        .referral-date {
            font-size: 12px;
            color: var(--text-light);
        }
        
        /* 弹出消息样式 */
        .message-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            max-width: 400px;
            display: none;
        }
        
        .message-popup.success {
            background-color: #e5ffe5;
            color: #009900;
            border-left: 4px solid #009900;
        }
        
        .message-popup.error {
            background-color: #ffe5e5;
            color: #ff3333;
            border-left: 4px solid #ff3333;
        }
        
        .message-popup.show {
            display: block;
            animation: slide-in 0.5s forwards, fade-out 0.5s forwards 3s;
        }
        
        @keyframes slide-in {
            0% { transform: translateX(100%); }
            100% { transform: translateX(0); }
        }
        
        @keyframes fade-out {
            0% { opacity: 1; }
            100% { opacity: 0; }
        }
        
        /* 积分兑换模块样式 */
        .rewards-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 40px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--text-color);
            gap: 10px;
        }
        
        .section-title svg {
            width: 24px;
            height: 24px;
            stroke: white;
            background: linear-gradient(135deg, #5371ff, #ff6b8b);
            border-radius: 6px;
            padding: 5px;
        }
        
        .rewards-intro {
            color: var(--text-light);
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .rewards-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 25px;
        }
        
        .reward-tab {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-color);
            position: relative;
            transition: color 0.3s;
        }
        
        .reward-tab.active {
            color: var(--primary-color);
        }
        
        .reward-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary-color);
        }
        
        .rewards-content {
            display: none;
        }
        
        .rewards-content.active {
            display: block;
        }
        
        .rewards-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .reward-card {
            background: var(--input-bg);
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            height: 100%;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
        }
        
        .reward-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color);
        }
        
        .reward-image {
            width: 100%;
            position: relative;
            overflow: hidden;
            height: 140px;
            background-color: #f5f5f5;
        }
        
        .reward-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .reward-details {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .reward-details h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: var(--text-color);
            height: 42px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .reward-details p {
            font-size: 13px;
            color: var(--text-light);
            margin-bottom: 15px;
            flex: 1;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            min-height: 60px;
        }
        
        .reward-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            border-top: 1px solid var(--border-color);
            padding-top: 12px;
        }
        
        .reward-icon {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            background: rgba(83, 113, 255, 0.1);
            border-radius: 12px;
        }
        
        .reward-title {
            margin: 0 0 8px 0;
            font-size: 18px;
            color: var(--text-color);
        }
        
        .reward-description {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 15px;
        }
        
        .reward-points {
            font-size: 15px;
            margin-bottom: 20px;
            color: var(--text-color);
        }
        
        .reward-points span {
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .reward-tag {
            position: absolute;
            top: -10px;
            right: 10px;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
            font-size: 12px;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 12px;
        }
        
        .exchange-btn {
            padding: 10px;
            background: var(--primary-color);
            color: white;
            text-align: center;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            transition: background-color 0.3s;
            margin-top: auto;
        }
        
        .exchange-btn:hover {
            background-color: #4261e4;
        }
        
        @media (max-width: 1024px) {
            .rewards-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .member-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .rewards-container {
                grid-template-columns: 1fr;
            }
            
            .member-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .member-grid {
                grid-template-columns: 1fr;
            }
            
            .invite-actions {
                flex-direction: column;
            }
            
            .qrcode-container {
                margin-right: 0;
                margin-bottom: 20px;
            }
            
            .share-buttons {
                flex-direction: row;
                flex-wrap: wrap;
            }
            
            .share-btn {
                flex: 1;
                min-width: 80px;
                margin-right: 8px;
            }
            
            .rewards-section {
                padding: 20px 15px;
                margin-left: 20px;
                margin-right: 20px;
                width: calc(100% - 40px);
            }
        }
        
        @media (max-width: 480px) {
            .app-header {
                padding: 8px 12px;
                border-radius: 6px;
            }
            
            .btn-secondary {
                font-size: 12px;
                padding: 6px 10px;
            }
            
            .app-logo svg {
                width: 20px;
                height: 20px;
            }
            
            .logo-text {
                font-size: 14px;
            }
            
            .user-details h2 {
                font-size: 16px;
            }
            
            .member-card {
                padding: 15px;
            }
            
            .member-card h3 {
                font-size: 16px;
            }
            
            .rewards-section {
                padding: 15px;
                margin-left: 15px;
                margin-right: 15px;
                width: calc(100% - 30px);
            }
            
            .rewards-container {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .reward-image {
                height: 120px;
            }
        }
        
        /* 电脑端样式优化 */
        @media (min-width: 1025px) {
            .member-container {
                max-width: 1200px;
                margin: 30px auto;
                padding: 0 30px;
            }
            
            .member-grid {
                grid-template-columns: 1fr 1fr;
                grid-gap: 30px;
            }
            
            .member-card {
                height: 100%;
                min-height: 350px;
                display: flex;
                flex-direction: column;
                padding: 25px;
            }
            
            .member-card > h3 {
                margin-bottom: 20px;
            }
            
            .signin-history, .points-history {
                flex: 1;
                overflow-y: auto;
                max-height: 250px;
            }
            
            .rewards-section {
                max-width: 1200px;
                margin: 0 auto 40px auto;
                padding: 30px;
            }
            
            .rewards-container {
                grid-template-columns: repeat(3, 1fr);
                grid-gap: 25px;
            }
            
            .reward-card {
                height: 100%;
                min-height: 320px;
                transition: all 0.3s ease;
            }
            
            .reward-image {
                height: 160px;
                position: relative;
                overflow: hidden;
            }
            
            .reward-image img {
                transition: transform 0.5s ease;
            }
            
            .reward-card:hover .reward-image img {
                transform: scale(1.05);
            }
            
            .reward-details {
                display: flex;
                flex-direction: column;
                flex: 1;
            }
            
            .reward-details p {
                flex: 1;
                margin-bottom: 15px;
            }
            
            .user-info {
                padding: 25px;
                border-radius: 12px;
                margin-bottom: 25px;
            }
            
            .user-avatar {
                width: 70px;
                height: 70px;
                margin-right: 20px;
            }
            
            .invite-link-container {
                margin: 20px 0;
            }
        }
        
        /* 修正邀请好友卡片高度 */
        @media (min-width: 1025px) {
            .invite-stats {
                margin: 20px 0;
            }
            
            .qrcode-container {
                margin-right: 25px;
            }
            
            #qrcode {
                width: 140px;
                height: 140px;
            }
            
            .share-buttons {
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                height: 140px;
            }
            
            .share-btn {
                padding: 10px 15px;
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
                    <line x1="12" y1="8" x2="12" y2="16"></line>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
                <span class="logo-text">导航中心</span>
            </div>
            
            <div class="header-actions">
                <a href="navigation.php" class="btn btn-secondary">返回导航</a>
                <button id="themeToggle" class="btn btn-icon btn-secondary" title="切换主题">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                </button>
            </div>
        </header>
        
        <!-- 主内容区域 -->
        <main class="content-wrapper">
            <div class="member-container">
                <!-- 会员信息头部 -->
                <div class="member-header">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['member_username'], 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <h2><?php echo htmlspecialchars($_SESSION['member_username']); ?></h2>
                            <div style="display: flex; gap: 10px; align-items: center; margin-top: 5px;">
                                <div class="member-badge">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span><?php echo number_format($_SESSION['member_points']); ?> 积分</span>
                                </div>
                                <div id="signInStatus">
                                    <?php
                                    // 检查今日是否已签到
                                    $hasSignedIn = $memberSystem->checkTodaySignIn($_SESSION['member_id']);
                                    if ($hasSignedIn): 
                                    ?>
                                    <div class="member-badge signed">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span>今日已签到</span>
                                    </div>
                                    <?php else: ?>
                                    <button id="signInButton" class="sign-in-btn">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M8 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M16 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M3 8H21" stroke="currentColor" stroke-width="2"/>
                                            <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                            <circle cx="12" cy="14" r="2" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        <span>每日签到</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-actions">
                        <a href="edit_profile.php" class="btn btn-secondary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 14.66V20C20 20.5304 19.7893 21.0391 19.4142 21.4142C19.0391 21.7893 18.5304 22 18 22H4C3.46957 22 2.96086 21.7893 2.58579 21.4142C2.21071 21.0391 2 20.5304 2 20V6C2 5.46957 2.21071 4.96086 2.58579 4.58579C2.96086 4.21071 3.46957 4 4 4H9.34" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M18 2L22 6L12 16H8V12L18 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            编辑资料
                        </a>
                        <a href="logout.php" class="btn logout-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M16 17L21 12L16 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            退出登录
                        </a>
                    </div>
                </div>
                
                <!-- 会员卡片网格 -->
                <div class="member-grid">
                    <!-- 积分历史卡片 -->
                    <div class="member-card">
                        <h3>
                            <div class="card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                </svg>
                            </div>
                            积分历史
                        </h3>
                        
                        <?php if ($pointsHistory['success'] && !empty($pointsHistory['history'])): ?>
                            <ul class="points-history">
                                <?php foreach ($pointsHistory['history'] as $record): ?>
                                    <li>
                                        <div class="points-info">
                                            <div class="points-desc"><?php echo htmlspecialchars($record['description']); ?></div>
                                            <div class="points-date"><?php echo date('Y-m-d H:i', strtotime($record['created_at'])); ?></div>
                                        </div>
                                        <div class="points-value <?php echo $record['points'] >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo $record['points'] >= 0 ? '+' : ''; ?><?php echo $record['points']; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div style="margin-top: 15px; text-align: center;">
                                <a href="points_history.php" class="view-all-btn">
                                    查看全部
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 18l6-6-6-6"></path>
                                    </svg>
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="empty-state">暂无积分记录</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 签到记录 -->
                    <div class="member-card">
                        <h3>
                            <div class="card-icon">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M19 4H5C3.89543 4 3 4.89543 3 6V20C3 21.1046 3.89543 22 5 22H19C20.1046 22 21 21.1046 21 20V6C21 4.89543 20.1046 4 19 4Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M16 2V6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M8 2V6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M3 10H21" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            签到记录
                        </h3>
                        <?php
                        // 获取签到历史
                        $signin_result = $memberSystem->getSignInHistory($_SESSION['member_id'], 5);
                        $signin_history = $signin_result['success'] ? $signin_result['history'] : [];
                        
                        // 获取连续签到天数
                        $consecutive_days = $memberSystem->getConsecutiveSignInDays($_SESSION['member_id']);
                        ?>
                        <div class="card-body">
                            <?php if ($consecutive_days > 0): ?>
                                <div class="consecutive-days">
                                    <div class="days-count"><?php echo $consecutive_days; ?></div>
                                    <div class="days-text">连续签到天数</div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (empty($signin_history)): ?>
                                <p class="empty-message">您还没有签到记录</p>
                            <?php else: ?>
                                <ul class="signin-history">
                                    <?php foreach ($signin_history as $record): ?>
                                        <li>
                                            <div class="signin-info">
                                                <div class="signin-date"><?php echo date('Y-m-d', strtotime($record['sign_date'])); ?></div>
                                                <div class="signin-time"><?php echo date('H:i', strtotime($record['created_at'])); ?></div>
                                            </div>
                                            <div class="signin-points">+<?php echo $record['points_rewarded']; ?></div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 邀请好友卡片 -->
                <div class="member-card">
                    <h3>
                        <div class="card-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <line x1="20" y1="8" x2="20" y2="14"></line>
                                <line x1="23" y1="11" x2="17" y2="11"></line>
                            </svg>
                        </div>
                        邀请好友
                    </h3>
                    
                    <p>邀请好友注册，双方均可获得 <strong>50</strong> 积分奖励</p>
                    
                    <?php if ($inviteStats['success']): ?>
                    <div class="invite-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $inviteStats['stats']['total_invites']; ?></div>
                            <div class="stat-label">总邀请人数</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $inviteStats['stats']['month_invites']; ?></div>
                            <div class="stat-label">本月邀请</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $inviteStats['stats']['invite_points']; ?></div>
                            <div class="stat-label">邀请积分</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="invite-link-container">
                        <div class="invite-link" id="inviteLink"><?php echo $inviteLink; ?></div>
                        <button class="copy-button" id="copyButton">复制</button>
                    </div>

                    <div class="invite-actions">
                        <div class="qrcode-container" id="qrcodeContainer">
                            <div id="qrcode"></div>
                            <div class="qrcode-tip">扫码分享</div>
                        </div>
                        <div class="share-buttons">
                            <button class="share-btn wechat" id="shareWechat">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M8.687 4.201c-3.874 0-7 2.354-7 5.253 0 1.782 1.058 3.456 2.78 4.582-.093.268-.377.943-.435 1.084-.07.167-.167.428.184.233.262-.147.91-.588 1.293-.86.365.114.769.167 1.178.167.09 0 .175-.007.264-.01 3.874 0 7-2.354 7-5.253 0-2.9-3.126-5.253-7-5.253zm-2.757 2.64c.521 0 .948.427.948.948 0 .524-.427.95-.948.95-.527 0-.951-.427-.951-.95 0-.521.424-.948.95-.948zm5.444 0c.52 0 .944.427.944.948 0 .524-.424.95-.944.95-.527 0-.951-.427-.951-.95 0-.521.424-.948.95-.948z"/>
                                    <path d="M19.313 10.725c-3.139 0-5.687 1.82-5.687 4.069s2.548 4.069 5.687 4.069c.108 0 .214-.3.32-.01.368.272.837.608 1.043.726.29.154.205-.054.147-.183l-.353-.88c1.388-.88 2.228-2.25 2.228-3.722 0-2.25-2.548-4.069-5.687-4.069zm-2.112 2.048c.42 0 .763.344.763.767 0 .42-.343.764-.763.764-.424 0-.767-.343-.767-.764 0-.423.343-.767.767-.767zm4.159 0c.423 0 .77.344.77.767 0 .42-.347.764-.77.764-.42 0-.764-.343-.764-.764 0-.423.343-.767.764-.767z"/>
                                </svg>
                                微信
                            </button>
                            <button class="share-btn weibo" id="shareWeibo">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 1024 1024" fill="currentColor">
                                    <path d="M851.4 590.193c-22.196-66.233-90.385-90.422-105.912-91.863-15.523-1.442-29.593-9.94-19.295-27.505 10.302-17.566 29.304-68.684-7.248-104.681-36.564-36.14-116.512-22.462-173.094 0.866-56.434 23.327-53.39 7.055-51.65-8.925 1.89-16.848 32.355-111.02-60.791-122.395C311.395 220.86 154.85 370.754 99.572 457.15 16 587.607 29.208 675.873 29.208 675.873h0.58c10.009 121.819 190.787 218.869 412.328 218.869 190.5 0 350.961-71.853 398.402-169.478 0 0 0.143-0.433 0.575-1.156 4.938-10.506 8.71-21.168 11.035-32.254 6.668-26.205 11.755-64.215-0.728-101.66z m-436.7 251.27c-157.71 0-285.674-84.095-285.674-187.768 0-103.671 127.82-187.76 285.674-187.76 157.705 0 285.673 84.089 285.673 187.76 0 103.815-127.968 187.768-285.673 187.768z" />
                                    <path d="M803.096 425.327c2.896 1.298 5.945 1.869 8.994 1.869 8.993 0 17.7-5.328 21.323-14.112 5.95-13.964 8.993-28.793 8.993-44.205 0-62.488-51.208-113.321-114.181-113.321-15.379 0-30.32 3.022-44.396 8.926-11.755 4.896-17.263 18.432-12.335 30.24 4.933 11.662 18.572 17.134 30.465 12.238 8.419-3.46 17.268-5.33 26.41-5.33 37.431 0 67.752 30.241 67.752 67.247 0 9.068-1.735 17.857-5.369 26.202a22.832 22.832 0 0 0 12.335 30.236l0.01 0.01z" />
                                </svg>
                                微博
                            </button>
                            <button class="share-btn qq" id="shareQQ">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12.001 3.033c-4.996 0-9.052 3.16-9.052 7.061 0 1.842 1.169 3.748 3.094 5.437l-.054 5.564s1.927-1.883 3.054-2.498c.978.27 2.02.429 3.114.429 4.996 0 9.052-3.158 9.052-7.062 0-3.9-4.056-7.061-9.052-7.061zM6.089 11.811c-.574 0-1.042-.468-1.042-1.043 0-.77 0-2.086 1.042-2.086.576 0 1.042.468 1.042 1.043 0 .77-.467 2.086-1.042 2.086zm5.913 0c-.574 0-1.043-.468-1.043-1.043 0-.77.469-1.043 1.043-1.043.575 0 1.043.468 1.043 1.043 0 .77-.467 1.043-1.043 1.043z"/>
                                </svg>
                                QQ
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 积分兑换模块 - 单独一行 -->
            <div class="rewards-section">
                <h2 class="section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="7"></circle>
                        <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                    </svg>
                    积分兑换中心
                </h2>
                <p class="rewards-intro">使用您的积分兑换价值丰厚的奖励，包括USDT和精选实物礼品</p>
                
                <div class="rewards-tabs">
                    <div class="reward-tab active" data-tab="crypto">数字货币</div>
                    <div class="reward-tab" data-tab="physical">实物奖励</div>
                </div>
                
                <!-- 数字货币兑换选项 -->
                <div class="rewards-content active" id="crypto-content">
                    <div class="rewards-container">
                        <?php if ($cryptoRewards['success'] && !empty($cryptoRewards['rewards'])): ?>
                            <?php foreach($cryptoRewards['rewards'] as $reward): ?>
                                <div class="reward-card">
                                    <div class="reward-image">
                                        <img src="<?php echo htmlspecialchars($reward['image_url'] ?: 'assets/images/reward-placeholder.png'); ?>" alt="<?php echo htmlspecialchars($reward['name']); ?>">
                                        <?php if ($reward['quantity'] !== null && $reward['quantity'] <= 3 && $reward['quantity'] > 0): ?>
                                            <span class="badge badge-warning">库存紧张</span>
                                        <?php elseif ($reward['quantity'] !== null && $reward['quantity'] <= 0): ?>
                                            <span class="badge badge-danger">已售罄</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="reward-details">
                                        <h3><?php echo htmlspecialchars($reward['name']); ?></h3>
                                        <p><?php echo htmlspecialchars($reward['description']); ?></p>
                                        <div class="reward-footer">
                                            <span class="reward-points"><?php echo number_format($reward['points_cost']); ?> 积分</span>
                                            <?php if ($reward['quantity'] === null || $reward['quantity'] > 0): ?>
                                                <a href="redeem.php?id=<?php echo $reward['id']; ?>" class="btn btn-sm btn-primary">立即兑换</a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-disabled" disabled>已售罄</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="grid-column: 1 / -1; text-align: center; padding: 30px;">
                                <p>暂无可兑换的数字货币奖励</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 实物奖励兑换选项 -->
                <div class="rewards-content" id="physical-content">
                    <div class="rewards-container">
                        <?php if ($physicalRewards['success'] && !empty($physicalRewards['rewards'])): ?>
                            <?php foreach($physicalRewards['rewards'] as $reward): ?>
                                <div class="reward-card">
                                    <div class="reward-image">
                                        <img src="<?php echo htmlspecialchars($reward['image_url'] ?: 'assets/images/reward-placeholder.png'); ?>" alt="<?php echo htmlspecialchars($reward['name']); ?>">
                                        <?php if ($reward['quantity'] !== null && $reward['quantity'] <= 3 && $reward['quantity'] > 0): ?>
                                            <span class="badge badge-warning">库存紧张</span>
                                        <?php elseif ($reward['quantity'] !== null && $reward['quantity'] <= 0): ?>
                                            <span class="badge badge-danger">已售罄</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="reward-details">
                                        <h3><?php echo htmlspecialchars($reward['name']); ?></h3>
                                        <p><?php echo htmlspecialchars($reward['description']); ?></p>
                                        <div class="reward-footer">
                                            <span class="reward-points"><?php echo number_format($reward['points_cost']); ?> 积分</span>
                                            <?php if ($reward['quantity'] === null || $reward['quantity'] > 0): ?>
                                                <a href="redeem.php?id=<?php echo $reward['id']; ?>" class="btn btn-sm btn-primary">立即兑换</a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-disabled" disabled>已售罄</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="grid-column: 1 / -1; text-align: center; padding: 30px;">
                                <p>暂无可兑换的实物奖励</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
        
        <!-- 页脚 -->
        <footer class="app-footer">
            <div class="footer-text">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>安全通道已建立 · 数据已加密</span>
            </div>
            <div class="footer-copyright">
                <p>© 2023-2025 无极导航 - 精选优质网络资源的专业导航平台</p>
            </div>
        </footer>
    </div>
    
    <!-- 消息提示 -->
    <?php if (!empty($message)): ?>
        <div class="message-popup <?php echo $messageType; ?> show">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <!-- 主题切换和复制邀请链接的脚本 -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 主题切换
            const themeToggle = document.getElementById('themeToggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    document.body.classList.toggle('dark-theme');
                    localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light');
                });
                
                // 应用保存的主题
                if (localStorage.getItem('theme') === 'dark') {
                    document.body.classList.add('dark-theme');
                }
            }
            
            // 复制邀请链接
            const copyButton = document.getElementById('copyButton');
            const inviteLink = document.getElementById('inviteLink');
            
            if (copyButton && inviteLink) {
                copyButton.addEventListener('click', function() {
                    var inviteLink = document.getElementById('inviteLink').innerText;
                    navigator.clipboard.writeText(inviteLink).then(function() {
                        // 复制成功处理
                        var copyBtn = document.getElementById('copyButton');
                        var originalText = copyBtn.innerText;
                        copyBtn.innerText = '已复制';
                        setTimeout(function() {
                            copyBtn.innerText = originalText;
                        }, 2000);
                    });
                });
            }
            
            // 签到功能处理
            if (document.getElementById('signInButton')) {
                document.getElementById('signInButton').addEventListener('click', function() {
                    // 禁用按钮，防止重复点击
                    this.disabled = true;
                    this.innerHTML = '<span>处理中...</span>';
                    
                    // 发送签到请求
                    fetch('sign_in.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin' // 确保发送cookies
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('网络响应异常，状态码: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('签到响应数据:', data); // 调试输出
                        
                        if (data.success) {
                            // 显示签到成功提示
                            showSignInSuccess(data.points);
                            
                            // 更新签到状态显示
                            document.getElementById('signInStatus').innerHTML = `
                                <div class="member-badge signed">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span>今日已签到</span>
                                </div>
                            `;
                            
                            // 更新积分显示 - 修复选择器，确保更新正确的积分显示元素
                            var pointsBadges = document.querySelectorAll('.member-badge:not(.signed) span');
                            var pointsBadge = pointsBadges[0]; // 获取第一个元素
                            
                            if (pointsBadge) {
                                try {
                                    var currentPointsText = pointsBadge.innerText;
                                    console.log('当前积分文本:', currentPointsText);
                                    
                                    var currentPoints = parseInt(currentPointsText.replace(/,/g, '').replace(/\s+积分$/, ''));
                                    console.log('解析后的当前积分:', currentPoints);
                                    
                                    if (isNaN(currentPoints)) {
                                        throw new Error('无法解析当前积分');
                                    }
                                    
                                    var newPoints = currentPoints + data.points;
                                    console.log('新积分值:', newPoints);
                                    
                                    pointsBadge.innerText = newPoints.toLocaleString() + ' 积分';
                                } catch (err) {
                                    console.error('更新积分显示时出错:', err);
                                }
                            } else {
                                console.error('找不到积分显示元素');
                            }
                            
                        } else {
                            showErrorMessage(data.message || '签到失败，请稍后再试');
                            this.disabled = false;
                            this.innerHTML = `
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M8 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M16 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M3 8H21" stroke="currentColor" stroke-width="2"/>
                                    <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <circle cx="12" cy="14" r="2" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                <span>每日签到</span>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('签到请求出错:', error);
                        showErrorMessage('签到出错: ' + error.message);
                        this.disabled = false;
                        this.innerHTML = `
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M16 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M3 8H21" stroke="currentColor" stroke-width="2"/>
                                <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                <circle cx="12" cy="14" r="2" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <span>每日签到</span>
                        `;
                    });
                });
            }
            
            function showSignInSuccess(points) {
                // 创建成功提示元素
                var successElement = document.createElement('div');
                successElement.className = 'sign-in-success';
                successElement.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.709 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M22 4L12 14.01L9 11.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    签到成功，获得${points}积分！
                `;
                document.body.appendChild(successElement);
                
                // 显示提示
                setTimeout(() => {
                    successElement.classList.add('show');
                }, 100);
                
                // 3秒后移除提示
                setTimeout(() => {
                    successElement.classList.remove('show');
                    setTimeout(() => {
                        document.body.removeChild(successElement);
                    }, 300);
                }, 3000);
            }
            
            function showErrorMessage(message) {
                // 创建错误提示元素
                var errorElement = document.createElement('div');
                errorElement.className = 'message-popup error show';
                errorElement.textContent = message;
                document.body.appendChild(errorElement);
                
                // 3秒后移除提示
                setTimeout(() => {
                    errorElement.classList.remove('show');
                    setTimeout(() => {
                        document.body.removeChild(errorElement);
                    }, 300);
                }, 3000);
            }
            
            // 生成邀请链接二维码
            const qrcodeContainer = document.getElementById('qrcode');
            if (qrcodeContainer && inviteLink) {
                // 使用qrcode.js库生成二维码
                new QRCode(qrcodeContainer, {
                    text: inviteLink.textContent,
                    width: 120,
                    height: 120,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            }
            
            // 社交分享功能
            // 微信分享
            const shareWechat = document.getElementById('shareWechat');
            if (shareWechat) {
                shareWechat.addEventListener('click', function() {
                    // 显示弹窗提示用户扫描二维码
                    alert('请截图保存二维码，或复制链接分享给好友');
                });
            }
            
            // 微博分享
            const shareWeibo = document.getElementById('shareWeibo');
            if (shareWeibo && inviteLink) {
                shareWeibo.addEventListener('click', function() {
                    const text = '我邀请你加入无极导航，注册即可获得积分奖励！';
                    const url = encodeURIComponent(inviteLink.textContent);
                    window.open(`http://service.weibo.com/share/share.php?url=${url}&title=${encodeURIComponent(text)}`);
                });
            }
            
            // QQ分享
            const shareQQ = document.getElementById('shareQQ');
            if (shareQQ && inviteLink) {
                shareQQ.addEventListener('click', function() {
                    const text = '我邀请你加入无极导航，注册即可获得积分奖励！';
                    const url = encodeURIComponent(inviteLink.textContent);
                    window.open(`http://connect.qq.com/widget/shareqq/index.html?url=${url}&title=${encodeURIComponent(text)}`);
                });
            }
            
            // 3秒后自动隐藏消息提示
            const messagePopup = document.querySelector('.message-popup.show');
            if (messagePopup) {
                setTimeout(function() {
                    messagePopup.classList.remove('show');
                }, 3000);
            }
            
            // 积分兑换标签页切换
            const rewardTabs = document.querySelectorAll('.reward-tab');
            if (rewardTabs.length > 0) {
                rewardTabs.forEach(tab => {
                    tab.addEventListener('click', function() {
                        // 移除所有标签的active类
                        rewardTabs.forEach(t => t.classList.remove('active'));
                        
                        // 添加当前标签的active类
                        this.classList.add('active');
                        
                        // 隐藏所有内容
                        document.querySelectorAll('.rewards-content').forEach(content => {
                            content.classList.remove('active');
                        });
                        
                        // 显示当前标签对应的内容
                        const tabId = this.getAttribute('data-tab');
                        document.getElementById(tabId + '-content').classList.add('active');
                    });
                });
            }
        });
    </script>
</body>
</html> 