<?php
require_once 'config.php';

// تحديد ترتيب العرض
$order_by = $_GET['order_by'] ?? 'employee_number_asc';

switch ($order_by) {
    case 'employee_number_asc':
        $order_sql = "ORDER BY CAST(employee_number AS UNSIGNED) ASC";
        break;
    case 'employee_number_desc':
        $order_sql = "ORDER BY CAST(employee_number AS UNSIGNED) DESC";
        break;
    case 'name_asc':
        $order_sql = "ORDER BY name ASC";
        break;
    case 'hire_date_desc':
        $order_sql = "ORDER BY hire_date DESC";
        break;
    case 'hire_date_asc':
        $order_sql = "ORDER BY hire_date ASC";
        break;
    default:
        $order_sql = "ORDER BY CAST(employee_number AS UNSIGNED) ASC";
}

$sql = "SELECT * FROM employees $order_sql";
$stmt = $pdo->query($sql);
$employees = $stmt->fetchAll();

$search = $_GET['search'] ?? '';
$filter_rank = $_GET['filter_rank'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

// ============================================
// إحصائيات متقدمة
// ============================================

$stats_ranks = ['ضابط' => 0, 'صف ضابط' => 0, 'موظف' => 0, 'متدرب' => 0];
$stats_contracts = ['خدمة مستديمة' => 0, 'عقد سنوي' => 0, 'تدريب' => 0];
$status_stats = ['ساري' => 0, 'منتهي' => 0, 'تنبيه' => 0, 'مستديم' => 0];
$active_trainees = 0;
$expired_annual = 0;

$all_employees = $pdo->query("SELECT * FROM employees")->fetchAll();

foreach ($all_employees as $emp) {
    $contract = calculateContractDetails($emp['hire_date'], $emp['rank']);
    
    $stats_ranks[$emp['rank']]++;
    
    if ($contract['service_type'] == 'خدمة مستديمة') $stats_contracts['خدمة مستديمة']++;
    elseif ($contract['service_type'] == 'عقد سنوي') $stats_contracts['عقد سنوي']++;
    elseif ($contract['service_type'] == 'تدريب') $stats_contracts['تدريب']++;
    
    if ($emp['rank'] == 'متدرب' && $contract['status'] == 'ساري') $active_trainees++;
    
    if ($contract['service_type'] == 'عقد سنوي' && $contract['status'] == 'انتهى') {
        $expired_annual++;
    }
    
    if ($contract['status'] == 'ساري') $status_stats['ساري']++;
    elseif ($contract['status'] == 'منتهي') $status_stats['منتهي']++;
    elseif ($contract['status'] == 'تنبيه') $status_stats['تنبيه']++;
    elseif ($contract['status'] == 'مستديم') $status_stats['مستديم']++;
}

$total = array_sum($stats_ranks);
$success_msg = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الموظفين</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        /* Dark Mode */
        body.dark {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #eee;
        }
        
        body.dark .card,
        body.dark .table-container,
        body.dark .search-card,
        body.dark .add-card {
            background: #1e1e2e;
            color: #eee;
            border-color: #333;
        }
        
        body.dark .table {
            color: #eee;
        }
        
        body.dark .table tbody tr {
            border-color: #333;
        }
        
        body.dark .table-hover tbody tr:hover {
            background: #2d2d44;
        }
        
        body.dark .form-control,
        body.dark .form-select {
            background: #2d2d44;
            border-color: #444;
            color: #eee;
        }
        
        body.dark .form-control:focus,
        body.dark .form-select:focus {
            background: #3d3d5c;
            color: #eee;
        }
        
        /* Layout */
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: fixed;
            right: 0;
            top: 0;
            height: 100%;
            z-index: 1000;
            box-shadow: -5px 0 20px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        body.dark .sidebar {
            background: linear-gradient(180deg, #0f0f1a 0%, #1a1a2e 100%);
        }
        
        .sidebar.collapsed {
            right: -280px;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h3 {
            font-size: 1.2rem;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .sidebar-menu {
            padding: 15px 0;
        }
        
        .sidebar-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border-right: 3px solid transparent;
        }
        
        .sidebar-item:hover {
            background: rgba(255,255,255,0.1);
            border-right-color: #ffd700;
        }
        
        .sidebar-item i {
            width: 24px;
            font-size: 1.1rem;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-right: 280px;
            padding: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .main-content.expanded {
            margin-right: 0;
        }
        
        /* Toggle Button */
        .toggle-btn {
            position: fixed;
            right: 290px;
            top: 15px;
            background: white;
            border: none;
            border-radius: 50%;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1001;
            transition: all 0.3s;
        }
        
        body.dark .toggle-btn {
            background: #2d2d44;
            color: white;
        }
        
        .toggle-btn.collapsed {
            right: 15px;
        }
        
        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #666;
        }
        
        body.dark .stat-label {
            color: #aaa;
        }
        
        /* Card Colors */
        .card-blue { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .card-pink { background: linear-gradient(135deg, #f093fb, #f5576c); color: white; }
        .card-cyan { background: linear-gradient(135deg, #4facfe, #00f2fe); color: white; }
        .card-green { background: linear-gradient(135deg, #43e97b, #38f9d7); color: white; }
        .card-orange { background: linear-gradient(135deg, #fa709a, #fee140); color: white; }
        .card-purple { background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: white; }
        .card-red { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        .card-teal { background: linear-gradient(135deg, #14b8a6, #0d9488); color: white; }
        
        /* Cards */
        .add-card, .search-card {
            background: white;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .card-header-custom {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Table */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        
        .table {
            margin-bottom: 0;
            text-align: center;
            font-size: 0.85rem;
        }
        
        .table thead th {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border: none;
            padding: 10px 8px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .table tbody td {
            padding: 10px 8px;
            vertical-align: middle;
            white-space: nowrap;
        }
        
        /* Table Row Colors */
        .table-danger {
            background-color: #ffebee !important;
        }
        .table-warning {
            background-color: #fff3e0 !important;
        }
        .table-info {
            background-color: #e3f2fd !important;
        }
        .table-success {
            background-color: #e8f5e9 !important;
        }
        
        body.dark .table-danger { background-color: #4a1a1a !important; }
        body.dark .table-warning { background-color: #4a3a1a !important; }
        body.dark .table-info { background-color: #1a3a4a !important; }
        body.dark .table-success { background-color: #1a4a1a !important; }
        
        /* Badges */
        .badge-rank {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-officer { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .badge-nco { background: linear-gradient(135deg, #f093fb, #f5576c); color: white; }
        .badge-employee { background: linear-gradient(135deg, #4facfe, #00f2fe); color: white; }
        .badge-trainee { background: linear-gradient(135deg, #43e97b, #38f9d7); color: white; }
        
        .status-active { color: #10b981; font-weight: 600; }
        .status-expired { color: #ef4444; font-weight: 600; }
        .status-permanent { color: #8b5cf6; font-weight: 600; }
        .status-warning { color: #f59e0b; font-weight: 600; }
        
        /* Buttons */
        .btn-edit, .btn-delete {
            border: none;
            padding: 5px 12px;
            margin: 0 2px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-edit {
            background: #f59e0b;
            color: white;
        }
        
        .btn-edit:hover {
            background: #d97706;
            transform: scale(1.05);
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        
        .btn-delete:hover {
            background: #dc2626;
            transform: scale(1.05);
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            color: white;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        
        /* Alert Message */
        .alert-message {
            font-size: 0.7rem;
            margin-top: 5px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                right: -280px;
            }
            .sidebar.show {
                right: 0;
            }
            .main-content {
                margin-right: 0;
            }
            .toggle-btn {
                right: 15px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            .stat-number {
                font-size: 1.3rem;
            }
            .table {
                font-size: 0.7rem;
            }
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate {
            animation: fadeInUp 0.4s ease forwards;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-users fa-2x"></i>
            <h3>نظام الموظفين</h3>
            <small>إدارة متكاملة</small>
        </div>
        <div class="sidebar-menu">
            <a href="index.php" class="sidebar-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>الرئيسية</span>
            </a>
            <div class="sidebar-item" onclick="showAddForm()">
                <i class="fas fa-user-plus"></i>
                <span>إضافة موظف</span>
            </div>
            <a href="import_excel.php" class="sidebar-item">
                <i class="fas fa-file-import"></i>
                <span>استيراد Excel</span>
            </a>
            <a href="export_excel.php" class="sidebar-item">
                <i class="fas fa-file-export"></i>
                <span>تصدير Excel</span>
            </a>
            <a href="export_pdf.php" target="_blank" class="sidebar-item">
                <i class="fas fa-file-pdf"></i>
                <span>تصدير PDF</span>
            </a>
            <div class="sidebar-item" onclick="window.print()">
                <i class="fas fa-print"></i>
                <span>طباعة</span>
            </div>
            <div class="sidebar-item" onclick="toggleDarkMode()">
                <i class="fas fa-moon"></i>
                <span>الوضع الليلي</span>
            </div>
        </div>
    </div>
    
    <!-- Toggle Button -->
    <button class="toggle-btn" id="toggleBtn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        
        <!-- Toast Notifications -->
        <div class="toast-notification" style="position: fixed; bottom: 20px; left: 20px; z-index: 1100;">
            <?php if ($success_msg == 'added'): ?>
            <div class="alert alert-success alert-dismissible fade show">✅ تم إضافة الموظف بنجاح<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if ($success_msg == 'updated'): ?>
            <div class="alert alert-info alert-dismissible fade show">✏️ تم تحديث البيانات بنجاح<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if ($success_msg == 'deleted'): ?>
            <div class="alert alert-danger alert-dismissible fade show">🗑️ تم حذف الموظف بنجاح<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
        </div>
        
        <!-- Title -->
        <div class="text-center mb-3 animate">
            <h1 style="color: #1e3c72; font-size: 1.8rem;">نظام إدارة الموظفين</h1>
            <p class="text-muted">ضباط | صف ضابط | موظفين | متدربين</p>
        </div>
        
        <!-- Statistics Grid -->
        <div class="stats-grid animate">
            <div class="stat-card card-blue"><div class="stat-number"><?= $stats_ranks['ضابط'] ?></div><div class="stat-label">الضباط</div></div>
            <div class="stat-card card-pink"><div class="stat-number"><?= $stats_ranks['صف ضابط'] ?></div><div class="stat-label">صف ضابط</div></div>
            <div class="stat-card card-cyan"><div class="stat-number"><?= $stats_ranks['موظف'] ?></div><div class="stat-label">الموظفين</div></div>
            <div class="stat-card card-green"><div class="stat-number"><?= $stats_ranks['متدرب'] ?></div><div class="stat-label">المتدربين</div></div>
            <div class="stat-card card-orange"><div class="stat-number"><?= $total ?></div><div class="stat-label">المجموع الكلي</div></div>
            <div class="stat-card card-purple"><div class="stat-number"><?= $stats_contracts['خدمة مستديمة'] ?></div><div class="stat-label">خدمة مستديمة</div></div>
            <div class="stat-card card-red"><div class="stat-number"><?= $stats_contracts['عقد سنوي'] ?></div><div class="stat-label">عقود سنوية</div></div>
            <div class="stat-card card-teal"><div class="stat-number"><?= $status_stats['تنبيه'] ?></div><div class="stat-label">تنبيهات</div></div>
        </div>
        
        <!-- Add Form -->
        <div class="add-card" id="addForm" style="display: none;">
            <div class="card-header-custom">
                <i class="fas fa-user-plus text-primary"></i>
                <span>إضافة موظف جديد</span>
                <button class="btn btn-sm btn-outline-danger ms-auto" onclick="hideAddForm()"><i class="fas fa-times"></i></button>
            </div>
            <form action="save_employee.php" method="POST">
                <div class="row g-2">
                    <div class="col-md-2 col-sm-6"><input type="text" name="employee_number" class="form-control" placeholder="الرقم" required></div>
                    <div class="col-md-3 col-sm-6"><input type="text" name="name" class="form-control" placeholder="الاسم" required></div>
                    <div class="col-md-2 col-sm-6">
                        <select name="rank" class="form-select" required>
                            <option value="ضابط">ضابط</option>
                            <option value="صف ضابط">صف ضابط</option>
                            <option value="موظف">موظف</option>
                            <option value="متدرب">متدرب</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6"><input type="date" name="hire_date" class="form-control" required></div>
                    <div class="col-md-2 col-sm-6"><input type="text" name="department" class="form-control" placeholder="الإدارة" required></div>
                    <div class="col-md-1 col-sm-6"><button type="submit" class="btn btn-primary-custom w-100"><i class="fas fa-save"></i></button></div>
                </div>
            </form>
        </div>
        
        <!-- Search -->
        <div class="search-card">
            <div class="card-header-custom">
                <i class="fas fa-search text-secondary"></i>
                <span>بحث وتصفية وترتيب</span>
            </div>
            <form method="GET">
                <div class="row g-2">
                    <div class="col-md-3 col-sm-6"><input type="text" name="search" class="form-control" placeholder="بحث بالاسم أو الرقم" value="<?= htmlspecialchars($search) ?>"></div>
                    <div class="col-md-2 col-sm-6">
                        <select name="filter_rank" class="form-select">
                            <option value="">جميع الصفات</option>
                            <option value="ضابط" <?= $filter_rank == 'ضابط' ? 'selected' : '' ?>>ضابط</option>
                            <option value="صف ضابط" <?= $filter_rank == 'صف ضابط' ? 'selected' : '' ?>>صف ضابط</option>
                            <option value="موظف" <?= $filter_rank == 'موظف' ? 'selected' : '' ?>>موظف</option>
                            <option value="متدرب" <?= $filter_rank == 'متدرب' ? 'selected' : '' ?>>متدرب</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <select name="filter_status" class="form-select">
                            <option value="">جميع الحالات</option>
                            <option value="ساري" <?= $filter_status == 'ساري' ? 'selected' : '' ?>>ساري</option>
                            <option value="منتهي" <?= $filter_status == 'منتهي' ? 'selected' : '' ?>>منتهي</option>
                            <option value="تنبيه" <?= $filter_status == 'تنبيه' ? 'selected' : '' ?>>تنبيه</option>
                            <option value="مستديم" <?= $filter_status == 'مستديم' ? 'selected' : '' ?>>مستديم</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <select name="order_by" class="form-select">
                            <option value="employee_number_asc" <?= $order_by == 'employee_number_asc' ? 'selected' : '' ?>>🔢 الرقم (تصاعدي)</option>
                            <option value="employee_number_desc" <?= $order_by == 'employee_number_desc' ? 'selected' : '' ?>>🔢 الرقم (تنازلي)</option>
                            <option value="name_asc" <?= $order_by == 'name_asc' ? 'selected' : '' ?>>🔤 الاسم (أ-ي)</option>
                            <option value="hire_date_desc" <?= $order_by == 'hire_date_desc' ? 'selected' : '' ?>>📅 الأحدث أولاً</option>
                            <option value="hire_date_asc" <?= $order_by == 'hire_date_asc' ? 'selected' : '' ?>>📅 الأقدم أولاً</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-12"><button type="submit" class="btn btn-secondary w-100"><i class="fas fa-filter"></i> تطبيق</button></div>
                </div>
            </form>
        </div>
        
        <!-- Table -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0"><i class="fas fa-list"></i> قائمة الموظفين</h5>
                <div>
                    <a href="renumber.php" class="btn btn-sm btn-warning" onclick="return confirm('⚠️ هل أنت متأكد؟ سيتم إعادة ترقيم جميع الموظفين')"><i class="fas fa-sort-numeric-down-alt"></i> إعادة ترقيم</a>
                    <button onclick="deleteAllEmployees()" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i> حذف الكل</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>#</th><th>الرقم</th><th>الاسم</th><th>الصفة</th><th>تاريخ التعيين</th><th>سنوات الخدمة</th><th>تاريخ انتهاء العقد</th><th>نوع الخدمة</th><th>الحالة</th><th>الإدارة</th><th>الإجراءات</th></tr>
                    </thead>
                    <tbody>
                        <?php 
                        $display_count = 0;
                        $counter = 1;
                        foreach ($employees as $emp):
                            $duration = calculateServiceDuration($emp['hire_date']);
                            $contract = calculateContractDetails($emp['hire_date'], $emp['rank']);
                            
                            if ($search && stripos($emp['name'], $search) === false && stripos($emp['employee_number'], $search) === false) continue;
                            if ($filter_rank && $emp['rank'] != $filter_rank) continue;
                            if ($filter_status && $contract['status'] != $filter_status) continue;
                            
                            $display_count++;
                            
                            $badgeClass = '';
                            if ($emp['rank'] == 'ضابط') $badgeClass = 'badge-officer';
                            elseif ($emp['rank'] == 'صف ضابط') $badgeClass = 'badge-nco';
                            elseif ($emp['rank'] == 'موظف') $badgeClass = 'badge-employee';
                            else $badgeClass = 'badge-trainee';
                            
                            // تحديد لون الصف حسب الحالة
                            $rowClass = '';
                            if ($contract['status'] == 'انتهى') $rowClass = 'table-danger';
                            elseif ($contract['status'] == 'تنبيه') {
                                if ($emp['rank'] == 'متدرب') $rowClass = 'table-info';
                                else $rowClass = 'table-warning';
                            } elseif ($contract['status'] == 'مستديم') $rowClass = 'table-success';
                            
                            // تحديد لون النص حسب الحالة
                            $statusClass = '';
                            if ($contract['status'] == 'انتهى') $statusClass = 'status-expired';
                            elseif ($contract['status'] == 'تنبيه') $statusClass = 'status-warning';
                            elseif ($contract['status'] == 'مستديم') $statusClass = 'status-permanent';
                            else $statusClass = 'status-active';
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td><?= $counter++ ?></td>
                            <td><strong><?= htmlspecialchars($emp['employee_number']) ?></strong></td>
                            <td><?= htmlspecialchars($emp['name']) ?></td>
                            <td><span class="badge-rank <?= $badgeClass ?>"><?= htmlspecialchars($emp['rank']) ?></span></td>
                            <td><?= date('d/m/Y', strtotime($emp['hire_date'])) ?></td>
                            <td><?= $duration['formatted'] ?></td>
                            <td><?= $contract['end_date_formatted'] ?></td>
                            <td><?= $contract['service_type'] ?></td>
                            <td class="<?= $statusClass ?>">
                                <?= $contract['status'] ?>
                                <?php if ($contract['alert_message']): ?>
                                    <div class="alert-message"><small><?= $contract['alert_message'] ?></small></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($emp['department']) ?></td>
                            <td>
                                <button class="btn-edit" onclick="editEmployee(<?= $emp['id'] ?>)"><i class="fas fa-edit"></i></button>
                                <button class="btn-delete" onclick="deleteEmployee(<?= $emp['id'] ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($display_count == 0): ?>
                        <tr><td colspan="11" class="text-center py-4"><i class="fas fa-inbox fa-2x text-muted mb-2"></i><p class="text-muted">لا توجد بيانات</p></td><tr>?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> تعديل بيانات الموظف</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="editModalBody"><div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">جاري التحميل...</p></div></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleBtn = document.getElementById('toggleBtn');
        if (window.innerWidth > 992) {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            toggleBtn.classList.toggle('collapsed');
        } else {
            sidebar.classList.toggle('show');
        }
    }
    
    function showAddForm() {
        document.getElementById('addForm').style.display = 'block';
        document.getElementById('addForm').scrollIntoView({ behavior: 'smooth' });
        if (window.innerWidth <= 992) document.getElementById('sidebar').classList.remove('show');
    }
    
    function hideAddForm() { document.getElementById('addForm').style.display = 'none'; }
    
    function toggleDarkMode() {
        document.body.classList.toggle('dark');
        localStorage.setItem('darkMode', document.body.classList.contains('dark'));
    }
    
    if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark');
    
    function editEmployee(id) {
        $.get('get_employee.php', { id: id }, function(data) {
            $('#editModalBody').html(data);
            new bootstrap.Modal(document.getElementById('editModal')).show();
        });
    }
    
    function deleteEmployee(id) {
        if (confirm('⚠️ هل أنت متأكد من حذف هذا الموظف؟')) window.location.href = 'delete_employee.php?id=' + id;
    }
    
    function deleteAllEmployees() {
        if (confirm('⚠️ تحذير: هل أنت متأكد من حذف جميع الموظفين؟')) {
            if (confirm('✅ تأكيد نهائي: اكتب "حذف الكل" للموافقة')) {
                let confirmation = prompt('أكتب "حذف الكل" لتأكيد حذف جميع الموظفين:');
                if (confirmation === 'حذف الكل') window.location.href = 'delete_all.php';
                else alert('❌ تم إلغاء عملية الحذف');
            }
        }
    }
    
    setTimeout(() => { $('.alert').fadeOut(500); }, 3000);
</script>
</body>
</html>