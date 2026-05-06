<?php
require_once 'config.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    echo "<div class='alert alert-danger'>الموظف غير موجود</div>";
    exit();
}
?>

<form action="update_employee.php" method="POST">
    <input type="hidden" name="id" value="<?= $emp['id'] ?>">
    
    <div class="mb-3">
        <label class="form-label">الرقم الوظيفي</label>
        <input type="text" name="employee_number" class="form-control" value="<?= htmlspecialchars($emp['employee_number']) ?>" required>
    </div>
    
    <div class="mb-3">
        <label class="form-label">الاسم الكامل</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($emp['name']) ?>" required>
    </div>
    
    <div class="mb-3">
        <label class="form-label">الصفة</label>
        <select name="rank" class="form-control" required>
            <option value="ضابط" <?= $emp['rank'] == 'ضابط' ? 'selected' : '' ?>>ضابط</option>
            <option value="صف ضابط" <?= $emp['rank'] == 'صف ضابط' ? 'selected' : '' ?>>صف ضابط</option>
            <option value="موظف" <?= $emp['rank'] == 'موظف' ? 'selected' : '' ?>>موظف</option>
            <option value="متدرب" <?= $emp['rank'] == 'متدرب' ? 'selected' : '' ?>>متدرب</option>
        </select>
    </div>
    
    <div class="mb-3">
        <label class="form-label">تاريخ التعيين</label>
        <input type="date" name="hire_date" class="form-control" value="<?= $emp['hire_date'] ?>" required>
    </div>
    
    <div class="mb-3">
        <label class="form-label">الإدارة</label>
        <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($emp['department']) ?>" required>
    </div>
    
    <button type="submit" class="btn btn-primary w-100">
        <i class="fas fa-save"></i> تحديث البيانات
    </button>
</form>