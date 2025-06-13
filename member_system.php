<?php
// 引入数据库连接
require_once __DIR__ . '/includes/db_connect.php';
// 引入函数库
require_once __DIR__ . '/includes/functions.php';

class MemberSystem {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDbConnection();
    }
    
    /**
     * 生成唯一邀请码
     */
    private function generateInviteCode() {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        $unique = false;
        
        while (!$unique) {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }
            
            // 检查邀请码是否已存在
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM members WHERE invite_code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetchColumn() == 0) {
                $unique = true;
            }
        }
        
        return $code;
    }
    
    /**
     * 检查IP是否已经被用来注册过账号
     */
    public function checkIpRegistered($ip) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM members WHERE register_ip = ?");
            $stmt->execute([$ip]);
            $count = $stmt->fetchColumn();
            
            return ['success' => true, 'registered' => ($count > 0), 'count' => $count];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => '检查IP失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 注册新会员
     */
    public function register($username, $password, $email, $inviteCode = null) {
        try {
            // 检查用户名是否已存在
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM members WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => '用户名已存在'];
            }
            
            // 检查邮箱是否已存在
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM members WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => '邮箱已被注册'];
            }
            
            // 获取注册IP地址
            $registerIp = getRealIpAddr();
            
            // 检查IP是否已被用于注册
            $ipCheck = $this->checkIpRegistered($registerIp);
            if ($ipCheck['success'] && $ipCheck['registered']) {
                return ['success' => false, 'message' => '该IP地址已被用于注册，不允许重复注册'];
            }
            
            // 获取注册奖励积分
            $stmt = $this->pdo->prepare("
                SELECT points FROM point_rules 
                WHERE rule_key = 'register_bonus' AND is_active = 1
            ");
            $stmt->execute();
            $registerPoints = $stmt->fetchColumn() ?: 20; // 默认20积分
            
            // 获取邀请奖励积分
            $invitePoints = 0;
            if ($inviteCode) {
                $stmt = $this->pdo->prepare("
                    SELECT points FROM point_rules 
                    WHERE rule_key = 'invite_bonus' AND is_active = 1
                ");
                $stmt->execute();
                $invitePoints = $stmt->fetchColumn() ?: 50; // 默认50积分
            }
            
            // 验证邀请码并获取推荐人ID
            $referrerId = null;
            if ($inviteCode) {
                $stmt = $this->pdo->prepare("SELECT id FROM members WHERE invite_code = ?");
                $stmt->execute([$inviteCode]);
                $referrer = $stmt->fetch();
                if ($referrer) {
                    $referrerId = $referrer['id'];
                } else {
                    return ['success' => false, 'message' => '无效的邀请码'];
                }
            }
            
            // 加密密码
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // 生成邀请码
            $newInviteCode = $this->generateInviteCode();

            // 获取注册IP地址
            $registerIp = getRealIpAddr();
            
            // 添加新会员
            $stmt = $this->pdo->prepare("
                INSERT INTO members (username, password, email, invite_code, referrer_id, register_ip, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$username, $hashedPassword, $email, $newInviteCode, $referrerId, $registerIp]);
            $newMemberId = $this->pdo->lastInsertId();
            
            // 如果是通过邀请注册的，给推荐人增加积分
            if ($referrerId) {
                $this->addPoints($referrerId, $invitePoints, '邀请新会员奖励');
            }
            
            // 给新会员添加初始积分
            $this->addPoints($newMemberId, $registerPoints, '新会员注册奖励');
            
            // 记录注册信息到日志
            error_log("新会员注册: ID={$newMemberId}, 用户名={$username}, IP={$registerIp}");
            
            return [
                'success' => true, 
                'message' => '注册成功',
                'member' => [
                    'id' => $newMemberId,
                    'username' => $username,
                    'email' => $email,
                    'invite_code' => $newInviteCode
                ]
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => '注册失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 会员登录
     */
    public function login($username, $password) {
        try {
            // 查询用户信息
            $stmt = $this->pdo->prepare("
                SELECT id, username, password, email, points, invite_code
                FROM members
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $member = $stmt->fetch();
            
            // 获取登录IP和用户代理
            $ipAddress = getRealIpAddr();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $currentTime = date('Y-m-d H:i:s');
            
            if (!$member) {
                // 记录失败的登录尝试
                $this->recordLoginAttempt(null, $username, $ipAddress, $userAgent, 'failed');
                return ['success' => false, 'message' => '用户名或密码错误'];
            }
            
            // 验证密码
            if (!password_verify($password, $member['password'])) {
                // 记录失败的登录尝试
                $this->recordLoginAttempt($member['id'], $member['username'], $ipAddress, $userAgent, 'failed');
                return ['success' => false, 'message' => '用户名或密码错误'];
            }
            
            // 记录成功的登录
            $this->recordLoginAttempt($member['id'], $member['username'], $ipAddress, $userAgent, 'success');
            
            // 检查members表是否存在last_login列，如果不存在则添加
            try {
                // 先检查列是否存在
                $columnExistsQuery = "SHOW COLUMNS FROM members LIKE 'last_login'";
                $columnExists = $this->pdo->query($columnExistsQuery)->rowCount() > 0;
                
                if (!$columnExists) {
                    // 添加last_login列
                    $this->pdo->exec("ALTER TABLE members ADD COLUMN last_login datetime DEFAULT NULL");
                    error_log("已添加last_login列到members表");
                }
                
                // 更新上次登录时间
                $stmt = $this->pdo->prepare("UPDATE members SET last_login = ? WHERE id = ?");
                $stmt->execute([$currentTime, $member['id']]);
            } catch (PDOException $e) {
                // 捕获列操作的错误，但不中断登录流程
                error_log("更新last_login时出错: " . $e->getMessage());
            }
            
            // 移除密码信息
            unset($member['password']);
            
            // 记录登录信息到会话
            $_SESSION['member_id'] = $member['id'];
            $_SESSION['member_username'] = $member['username'];
            $_SESSION['member_email'] = $member['email'];
            $_SESSION['member_points'] = $member['points'];
            $_SESSION['member_invite_code'] = $member['invite_code'];
            $_SESSION['member_login_time'] = time();
            
            // 设置验证状态
            $_SESSION['verified'] = true;
            $_SESSION['verified_time'] = time();
            $_SESSION['last_activity'] = time();
            
            // 记录登录信息
            error_log("用户登录成功: {$username}, IP={$ipAddress}, 会话ID: ".session_id());
            
            return [
                'success' => true,
                'message' => '登录成功',
                'member' => $member
            ];
            
        } catch (PDOException $e) {
            error_log("登录失败: " . $e->getMessage());
            return ['success' => false, 'message' => '登录失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 记录登录尝试
     */
    private function recordLoginAttempt($memberId, $username, $ipAddress, $userAgent, $status) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO login_logs (member_id, username, ip_address, user_agent, status, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$memberId, $username, $ipAddress, $userAgent, $status]);
            return true;
        } catch (PDOException $e) {
            error_log("记录登录尝试失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 为会员添加积分并记录积分交易
     * 
     * @param int $memberId 会员ID
     * @param int $points 积分数量（可以是负数，表示扣除积分）
     * @param string $description 交易描述
     * @param bool $useTransaction 是否使用事务，如果在外部已有事务则应设为false
     * @return array 操作结果
     */
    public function addPoints($memberId, $points, $description = '', $useTransaction = true) {
        try {
            error_log("addPoints - 开始添加积分: 会员ID=$memberId, 积分=$points, 描述=$description, 使用事务=$useTransaction");
            
            // 验证积分不能为0
            if ($points == 0) {
                error_log("addPoints - 积分为0，不执行任何操作");
                return [
                    'success' => false,
                    'message' => '积分不能为0'
                ];
            }
            
            // 如果是减少积分，先检查会员是否有足够的积分
            if ($points < 0) {
                $stmt = $this->pdo->prepare("SELECT points FROM members WHERE id = ?");
                $stmt->execute([$memberId]);
                $currentPoints = $stmt->fetchColumn();
                
                error_log("addPoints - 当前会员积分: $currentPoints, 尝试扣除: " . abs($points));
                
                if ($currentPoints + $points < 0) {
                    error_log("addPoints - 积分不足，无法扣除");
                    return [
                        'success' => false,
                        'message' => '积分不足'
                    ];
                }
            }
            
            // 开始事务（如果需要）
            $transactionStarted = false;
            if ($useTransaction) {
                try {
                    $this->pdo->beginTransaction();
                    $transactionStarted = true;
                    error_log("addPoints - 开始数据库事务");
                } catch (PDOException $e) {
                    // 如果已经有事务在运行，捕获异常但继续执行
                    error_log("addPoints - 无法开启新事务，可能已存在事务: " . $e->getMessage());
                }
            }
            
            try {
                // 更新会员积分
                $stmt = $this->pdo->prepare("
                    UPDATE members SET 
                    points = points + ?, 
                    updated_at = NOW() 
                    WHERE id = ?
                ");
                $result = $stmt->execute([$points, $memberId]);
                
                if (!$result || $stmt->rowCount() == 0) {
                    throw new Exception("更新会员积分失败，可能会员不存在");
                }
                
                error_log("addPoints - 会员积分已更新, 受影响行数: " . $stmt->rowCount());
                
                // 记录积分交易
                $stmt = $this->pdo->prepare("
                    INSERT INTO point_transactions 
                    (member_id, points, description, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $result = $stmt->execute([$memberId, $points, $description]);
                
                if (!$result) {
                    throw new Exception("记录积分交易失败");
                }
                
                error_log("addPoints - 积分交易记录已添加, ID: " . $this->pdo->lastInsertId());
                
                // 提交事务（如果由我们开启）
                if ($transactionStarted) {
                    $this->pdo->commit();
                    error_log("addPoints - 事务已提交，积分添加成功");
                }
                
                return [
                    'success' => true,
                    'message' => '积分' . ($points > 0 ? '增加' : '减少') . '成功',
                    'points' => $points
                ];
                
            } catch (Exception $e) {
                // 回滚事务（如果由我们开启）
                if ($transactionStarted) {
                    $this->pdo->rollBack();
                    error_log("addPoints - 事务已回滚，原因: " . $e->getMessage());
                }
                throw $e; // 重新抛出异常以便上层捕获
            }
            
        } catch (PDOException $e) {
            error_log("addPoints - PDO异常: " . $e->getMessage());
            error_log("addPoints - SQL状态码: " . $e->getCode());
            
            return [
                'success' => false,
                'message' => '操作积分失败: 数据库错误',
                'error' => $e->getMessage()
            ];
        } catch (Exception $e) {
            error_log("addPoints - 一般异常: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => '操作积分失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取会员积分历史
     */
    public function getPointsHistory($memberId, $limit = 10, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, points, description, created_at
                FROM point_transactions
                WHERE member_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$memberId, $limit, $offset]);
            
            return [
                'success' => true,
                'history' => $stmt->fetchAll()
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => '获取积分历史失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 获取会员推荐列表
     */
    public function getReferrals($memberId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, created_at 
                FROM members 
                WHERE referrer_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$memberId]);
            $referrals = $stmt->fetchAll();
            
            return [
                'success' => true,
                'referrals' => $referrals
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '获取推荐列表失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取邀请统计数据
     */
    public function getInviteStats($memberId) {
        try {
            // 获取邀请总人数
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total_count
                FROM members 
                WHERE referrer_id = ?
            ");
            $stmt->execute([$memberId]);
            $totalInvites = $stmt->fetchColumn();
            
            // 获取本月邀请人数
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as month_count
                FROM members 
                WHERE referrer_id = ? 
                AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ");
            $stmt->execute([$memberId]);
            $monthInvites = $stmt->fetchColumn();
            
            // 获取获得的邀请积分总数
            $stmt = $this->pdo->prepare("
                SELECT SUM(points) as total_points
                FROM point_transactions
                WHERE member_id = ? AND description LIKE '%邀请%'
            ");
            $stmt->execute([$memberId]);
            $invitePoints = $stmt->fetchColumn() ?: 0;
            
            // 获取在邀请链中的层级（几级推荐人）
            $level = 0;
            $currentId = $memberId;
            while ($currentId) {
                $stmt = $this->pdo->prepare("SELECT referrer_id FROM members WHERE id = ?");
                $stmt->execute([$currentId]);
                $currentId = $stmt->fetchColumn();
                if ($currentId) {
                    $level++;
                }
            }
            
            return [
                'success' => true,
                'stats' => [
                    'total_invites' => $totalInvites,
                    'month_invites' => $monthInvites,
                    'invite_points' => $invitePoints,
                    'invite_level' => $level
                ]
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '获取邀请统计失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 退出登录
     */
    public function logout() {
        // 清除会员相关的会话数据
        if (isset($_SESSION['member_id'])) {
            unset($_SESSION['member_id']);
            unset($_SESSION['member_username']);
            unset($_SESSION['member_email']);
            unset($_SESSION['member_points']);
            unset($_SESSION['member_invite_code']);
            unset($_SESSION['member_login_time']);
            unset($_SESSION['verified']);
            unset($_SESSION['verified_time']);
            unset($_SESSION['last_activity']);
        }
        
        // 完全清除会话
        $_SESSION = array();
        
        return ['success' => true, 'message' => '已成功退出登录'];
    }
    
    /**
     * 获取会员信息，包括积分、VIP等级等
     */
    public function getMemberInfo($memberId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM members WHERE id = ?");
            $stmt->execute([$memberId]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$member) {
                return ['success' => false, 'message' => '会员不存在'];
            }
            
            return ['success' => true, 'member' => $member];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => '获取会员信息失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 获取邀请人信息
     */
    public function getInviterInfo($inviteCode) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, vip_level
                FROM members
                WHERE invite_code = ?
            ");
            $stmt->execute([$inviteCode]);
            $inviter = $stmt->fetch();
            
            if ($inviter) {
                return [
                    'success' => true,
                    'inviter' => $inviter
                ];
            } else {
                return [
                    'success' => false,
                    'message' => '无效的邀请码'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '获取邀请人信息失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 记录访客通过邀请链接访问并给邀请人增加积分
     */
    public function recordInviteLinkVisit($inviteCode, $visitorIP) {
        try {
            // 先检查邀请码是否有效
            $inviterInfo = $this->getInviterInfo($inviteCode);
            if (!$inviterInfo['success']) {
                return $inviterInfo;
            }
            
            $inviterId = $inviterInfo['inviter']['id'];
            
            // 检查是否已经记录过这个IP的访问（防止重复加分）
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM access_logs
                WHERE inviter_id = ? AND visitor_ip = ? AND visit_date = CURDATE()
            ");
            $stmt->execute([$inviterId, $visitorIP]);
            
            if ($stmt->fetchColumn() > 0) {
                return [
                    'success' => false,
                    'message' => '今日已记录过此访问'
                ];
            }
            
            // 记录访问日志
            $stmt = $this->pdo->prepare("
                INSERT INTO access_logs (inviter_id, visitor_ip, visit_date)
                VALUES (?, ?, CURDATE())
            ");
            $stmt->execute([$inviterId, $visitorIP]);
            
            // 给邀请人添加积分
            $this->addPoints($inviterId, 10, '邀请链接访问奖励');
            
            return [
                'success' => true,
                'message' => '成功记录访问并奖励积分给邀请人'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '记录邀请链接访问失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取积分奖励商品列表
     * 
     * @param string $category 商品类别（可选）
     * @return array 奖励商品列表和操作状态
     */
    public function getRewards($category = '') {
        try {
            $sql = "SELECT * FROM rewards WHERE is_active = 1";
            $params = [];
            
            // 如果指定了类别，添加到查询条件
            if (!empty($category)) {
                $sql .= " AND category = ?";
                $params[] = $category;
            }
            
            $sql .= " ORDER BY points_cost ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'rewards' => $rewards];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => '获取奖励列表失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 获取积分规则
     */
    public function getPointRules() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM point_rules 
                ORDER BY id ASC
            ");
            $stmt->execute();
            return [
                'success' => true,
                'rules' => $stmt->fetchAll()
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '获取积分规则失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 更新积分规则
     */
    public function updatePointRule($ruleId, $points, $isActive = null) {
        try {
            $sql = "UPDATE point_rules SET points = ?";
            $params = [$points];
            
            if ($isActive !== null) {
                $sql .= ", is_active = ?";
                $params[] = $isActive;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $ruleId;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return [
                'success' => true,
                'message' => '积分规则更新成功'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '更新积分规则失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 检查今日是否已签到
     */
    public function checkTodaySignIn($memberId) {
        try {
            $today = date('Y-m-d');
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM daily_sign_ins 
                WHERE member_id = ? AND sign_date = ?
            ");
            $stmt->execute([$memberId, $today]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("检查签到状态失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 执行每日签到
     */
    public function doDailySignIn($memberId) {
        try {
            error_log("doDailySignIn - 开始签到流程，会员ID: " . $memberId);
            
            // 检查是否已签到
            if ($this->checkTodaySignIn($memberId)) {
                error_log("doDailySignIn - 会员ID $memberId 今日已签到");
                return [
                    'success' => false,
                    'message' => '今日已签到'
                ];
            }
            
            // 获取签到奖励积分
            $stmt = $this->pdo->prepare("
                SELECT points FROM point_rules 
                WHERE rule_key = 'daily_signin' AND is_active = 1
            ");
            $stmt->execute();
            $points = $stmt->fetchColumn();
            
            error_log("doDailySignIn - 签到奖励积分: " . ($points ?: '未配置'));
            
            if (!$points) {
                error_log("doDailySignIn - 签到奖励未配置");
                return [
                    'success' => false,
                    'message' => '签到奖励未配置'
                ];
            }
            
            // 开始事务
            error_log("doDailySignIn - 开始数据库事务");
            $this->pdo->beginTransaction();
            
            try {
                // 记录签到
                $stmt = $this->pdo->prepare("
                    INSERT INTO daily_sign_ins (member_id, sign_date, points_rewarded, created_at)
                    VALUES (?, CURDATE(), ?, NOW())
                ");
                $stmt->execute([$memberId, $points]);
                error_log("doDailySignIn - 签到记录已插入 daily_sign_ins 表");
                
                // 添加积分 - 传递false表示不使用独立事务，因为我们已经开启了事务
                $addPointsResult = $this->addPoints($memberId, $points, '每日签到奖励', false);
                error_log("doDailySignIn - 添加积分结果: " . print_r($addPointsResult, true));
                
                if (!$addPointsResult['success']) {
                    throw new Exception("添加积分失败: " . $addPointsResult['message']);
                }
                
                // 提交事务
                $this->pdo->commit();
                error_log("doDailySignIn - 事务已提交");
                
                error_log("doDailySignIn - 签到成功，获得 $points 积分");
                return [
                    'success' => true,
                    'message' => '签到成功，获得' . $points . '积分',
                    'points' => $points
                ];
            } catch (Exception $e) {
                // 回滚事务
                $this->pdo->rollBack();
                error_log("doDailySignIn - 事务已回滚，原因: " . $e->getMessage());
                throw $e; // 重新抛出异常以便上层捕获
            }
            
        } catch (PDOException $e) {
            error_log("doDailySignIn - PDO异常: " . $e->getMessage());
            error_log("doDailySignIn - SQL状态码: " . $e->getCode());
            return [
                'success' => false,
                'message' => '签到失败: 数据库错误',
                'error' => $e->getMessage()
            ];
        } catch (Exception $e) {
            error_log("doDailySignIn - 一般异常: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '签到失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取会员签到记录
     */
    public function getSignInHistory($memberId, $limit = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT sign_date, points_rewarded, created_at
                FROM daily_sign_ins
                WHERE member_id = ?
                ORDER BY sign_date DESC
                LIMIT ?
            ");
            $stmt->execute([$memberId, $limit]);
            
            return [
                'success' => true,
                'history' => $stmt->fetchAll()
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '获取签到记录失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取会员连续签到天数
     */
    public function getConsecutiveSignInDays($memberId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as days
                FROM (
                    SELECT sign_date
                    FROM daily_sign_ins
                    WHERE member_id = ?
                    ORDER BY sign_date DESC
                ) as dates
                WHERE sign_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $stmt->execute([$memberId]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("获取连续签到天数失败: " . $e->getMessage());
            return 0;
        }
    }
}
?> 