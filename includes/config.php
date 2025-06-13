<?php
/**
 * 系统配置文件
 * 包含数据库配置和其他系统设置
 */

// 开发环境设置
$environment = 'production'; // 可选: 'development', 'production'

// 数据库配置
$config = [];

// 开发环境配置
$config['development'] = [
    'db' => [
        'host' => '107.173.167.195',
        'port' => '3306',
        'name' => 'daohang',
        'user' => 'daohang',
        'pass' => 'RRtpX2zRXC6dax7M',
        'charset' => 'utf8mb4',
    ],
    'debug' => true,
];

// 生产环境配置
$config['production'] = [
    'db' => [
        'host' => '127.0.0.1', // 生产服务器IP
        'port' => '3306',
        'name' => 'sql_new_lurhqnfu',
        'user' => 'sql_new_lurhqnfu',
        'pass' => 'b201752e6b0f', // 生产环境中应更改为强密码
        'charset' => 'utf8mb4',
    ],
    'debug' => false,
];

// 获取当前环境配置
$currentConfig = $config[$environment];

// 错误显示设置
if ($currentConfig['debug']) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

// 网站URL配置
$baseUrl = "http://localhost";
if (isset($_SERVER['HTTP_HOST'])) {
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
}

$scriptDir = "";
if (isset($_SERVER['SCRIPT_NAME'])) {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $scriptDir = $scriptDir !== '/' ? $scriptDir : '';
}
$siteUrl = $baseUrl . $scriptDir;

// 定义一些常量
define('SITE_URL', $siteUrl);
define('DB_PREFIX', 'nav_'); // 表前缀，如需使用

// 如果这个文件被直接包含（而不是通过require_once），返回当前配置
return $currentConfig; 