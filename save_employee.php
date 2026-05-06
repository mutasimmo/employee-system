<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $employee_number = $_POST['employee_number'];
    $name = $_POST['name'];
    $rank = $_POST['rank'];
    $hire_date = $_POST['hire_date'];
    $department = $_POST['department'];
    
    try {
        // التحقق من عدم وجود رقم مكرر
        $check = $pdo->prepare("SELECT id FROM employees WHERE employee_number = ?");
        $check->execute([$employee_number]);
        
        if ($check->fetch()) {
            header('Location: index.php?error=duplicate');
            exit();
        }
        
        $stmt = $pdo->prepare("INSERT INTO employees (employee_number, name, rank, hire_date, department) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$employee_number, $name, $rank, $hire_date, $department]);
        
        header('Location: index.php?success=added');
    } catch (Exception $e) {
        header('Location: index.php?error=save_failed');
    }
    exit();
}
?>