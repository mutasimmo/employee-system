<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $employee_number = $_POST['employee_number'];
    $name = $_POST['name'];
    $rank = $_POST['rank'];
    $hire_date = $_POST['hire_date'];
    $department = $_POST['department'];
    
    try {
        // التحقق من عدم وجود رقم مكرر (باستثناء الموظف الحالي)
        $check = $pdo->prepare("SELECT id FROM employees WHERE employee_number = ? AND id != ?");
        $check->execute([$employee_number, $id]);
        
        if ($check->fetch()) {
            header('Location: index.php?error=duplicate');
            exit();
        }
        
        $stmt = $pdo->prepare("UPDATE employees SET 
            employee_number = ?, 
            name = ?, 
            rank = ?, 
            hire_date = ?, 
            department = ? 
            WHERE id = ?");
        
        $stmt->execute([$employee_number, $name, $rank, $hire_date, $department, $id]);
        
        header('Location: index.php?success=updated');
    } catch (Exception $e) {
        header('Location: index.php?error=update_failed');
    }
    exit();
}
?>