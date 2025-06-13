<?php
// 积分规则管理页面
require_once dirname(dirname(__DIR__)) . '/includes/db_connect.php';
require_once dirname(dirname(__DIR__)) . '/member_system.php';

// 初始化会员系统
$memberSystem = new MemberSystem();

// 处理表单提交 - 更新积分规则
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rule'])) {
    $rule_id = intval($_POST['rule_id']);
    $points = intval($_POST['points']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $result = $memberSystem->updatePointRule($rule_id, $points, $is_active);
    
    if ($result['success']) {
        $success_message = $result['message'];
    } else {
        $error_message = $result['message'];
    }
}

// 获取所有积分规则
$rules_result = $memberSystem->getPointRules();
$rules = $rules_result['success'] ? $rules_result['rules'] : [];
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">积分规则管理</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="?page=dashboard">首页</a></li>
                    <li class="breadcrumb-item active">积分规则管理</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <!-- 搜索表单 -->
        <div class="card">
            <div class="card-header">
                <h3>积分规则管理</h3>
                <div>通过设置不同规则的积分值，控制系统各奖励点的积分发放量</div>
            </div>
            <div class="card-body">
                <style>
                    .rules-table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 14px;
                        margin-top: 15px;
                    }
                    .rules-table th, .rules-table td {
                        padding: 12px;
                        text-align: left;
                        border: 1px solid #ddd;
                        vertical-align: middle;
                    }
                    .rules-table th {
                        background-color: #f5f5f5;
                        font-weight: 600;
                        position: sticky;
                        top: 0;
                        z-index: 10;
                    }
                    .rules-table tr:nth-child(even) {
                        background-color: #f9f9f9;
                    }
                    .rules-table tr:hover {
                        background-color: #f0f7ff;
                    }
                    .badge {
                        display: inline-block;
                        padding: 5px 10px;
                        font-size: 12px;
                        font-weight: 600;
                        border-radius: 4px;
                    }
                    .badge-success {
                        background-color: #28a745;
                        color: white;
                    }
                    .badge-danger {
                        background-color: #dc3545;
                        color: white;
                    }
                    .edit-btn {
                        padding: 6px 12px;
                        background-color: #3498db;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        transition: background-color 0.3s;
                        font-size: 13px;
                    }
                    .edit-btn:hover {
                        background-color: #2980b9;
                    }
                    .edit-btn i {
                        margin-right: 5px;
                    }
                    .alert {
                        padding: 12px 20px;
                        margin-bottom: 20px;
                        border-radius: 4px;
                    }
                    .alert-success {
                        background-color: #d4edda;
                        color: #155724;
                        border: 1px solid #c3e6cb;
                    }
                    .alert-danger {
                        background-color: #f8d7da;
                        color: #721c24;
                        border: 1px solid #f5c6cb;
                    }
                    .alert i {
                        margin-right: 8px;
                    }
                    .modal-content {
                        border-radius: 8px;
                    }
                    .modal-header {
                        background-color: #f8f9fa;
                        border-bottom: 1px solid #e9ecef;
                    }
                    .modal-footer {
                        background-color: #f8f9fa;
                        border-top: 1px solid #e9ecef;
                    }
                    .form-group {
                        margin-bottom: 15px;
                    }
                    .form-control {
                        display: block;
                        width: 100%;
                        padding: 8px 12px;
                        font-size: 14px;
                        line-height: 1.5;
                        color: #495057;
                        background-color: #fff;
                        border: 1px solid #ced4da;
                        border-radius: 4px;
                        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
                    }
                    .form-control:focus {
                        border-color: #80bdff;
                        outline: 0;
                        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
                    }
                    .custom-control {
                        position: relative;
                        display: block;
                        min-height: 1.5rem;
                        padding-left: 1.5rem;
                    }
                </style>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="rules-table">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="15%">规则名称</th>
                                <th width="15%">规则标识</th>
                                <th width="10%">积分值</th>
                                <th width="35%">描述</th>
                                <th width="10%">状态</th>
                                <th width="10%">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rules)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">暂无积分规则</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rules as $rule): ?>
                                    <tr>
                                        <td><?php echo $rule['id']; ?></td>
                                        <td><?php echo htmlspecialchars($rule['rule_name']); ?></td>
                                        <td><?php echo htmlspecialchars($rule['rule_key']); ?></td>
                                        <td><strong><?php echo $rule['points']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($rule['description']); ?></td>
                                        <td>
                                            <?php if ($rule['is_active']): ?>
                                                <span class="badge badge-success">启用</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">禁用</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="edit-btn" 
                                                    onclick="editRule(<?php echo $rule['id']; ?>, 
                                                                   '<?php echo htmlspecialchars($rule['rule_name'], ENT_QUOTES); ?>', 
                                                                   '<?php echo htmlspecialchars($rule['rule_key'], ENT_QUOTES); ?>', 
                                                                   <?php echo $rule['points']; ?>, 
                                                                   '<?php echo htmlspecialchars($rule['description'], ENT_QUOTES); ?>', 
                                                                   <?php echo $rule['is_active']; ?>)">
                                                <i class="fas fa-edit"></i> 编辑
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 编辑规则模态窗口 -->
<div id="editRuleModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5);">
    <div style="background-color: white; margin: 10% auto; padding: 20px; border-radius: 8px; width: 500px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); position: relative;">
        <h3 style="margin-top: 0; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 10px;">编辑积分规则</h3>
        <span onclick="closeEditModal()" style="position: absolute; right: 20px; top: 15px; cursor: pointer; font-size: 20px;">&times;</span>
        
        <form method="post" action="?page=point_rules">
            <input type="hidden" name="rule_id" id="edit_rule_id">
            
            <div class="form-group">
                <label for="edit_rule_name">规则名称</label>
                <input type="text" class="form-control" id="edit_rule_name" disabled>
            </div>
            
            <div class="form-group">
                <label for="edit_rule_key">规则标识</label>
                <input type="text" class="form-control" id="edit_rule_key" disabled>
            </div>
            
            <div class="form-group">
                <label for="edit_points">积分值</label>
                <input type="number" class="form-control" id="edit_points" name="points" required min="0">
                <small id="pointsHelp" class="form-text text-muted" style="margin-top: 5px;">设置此规则触发时奖励的积分数量</small>
            </div>
            
            <div class="form-group">
                <label for="edit_description">描述</label>
                <textarea class="form-control" id="edit_description" disabled rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>规则状态</label>
                <div style="margin-top: 5px;">
                    <label style="display: inline-flex; align-items: center; margin-right: 15px;">
                        <input type="checkbox" id="edit_is_active" name="is_active" style="margin-right: 5px;">
                        启用规则
                    </label>
                    <small class="form-text text-muted" style="margin-top: 5px;">禁用规则后，该规则不会触发积分奖励</small>
                </div>
            </div>
            
            <div style="margin-top: 20px; text-align: right;">
                <button type="button" onclick="closeEditModal()" style="background-color: #f5f5f5; border: 1px solid #ddd; padding: 8px 15px; border-radius: 4px; cursor: pointer; margin-right: 10px;">取消</button>
                <button type="submit" name="update_rule" style="background-color: #3498db; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">保存更改</button>
            </div>
        </form>
    </div>
</div>

<script>
    function editRule(id, name, key, points, description, active) {
        document.getElementById('edit_rule_id').value = id;
        document.getElementById('edit_rule_name').value = name;
        document.getElementById('edit_rule_key').value = key;
        document.getElementById('edit_points').value = points;
        document.getElementById('edit_description').value = description;
        document.getElementById('edit_is_active').checked = active == 1;
        
        document.getElementById('editRuleModal').style.display = 'block';
    }
    
    function closeEditModal() {
        document.getElementById('editRuleModal').style.display = 'none';
    }
    
    // 点击模态框外部关闭
    window.onclick = function(event) {
        var modal = document.getElementById('editRuleModal');
        if (event.target == modal) {
            closeEditModal();
        }
    }
</script> 