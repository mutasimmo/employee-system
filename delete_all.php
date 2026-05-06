<?php
require_once 'config.php';

try {
    // حذف جميع الموظفين
    $stmt = $pdo->prepare("TRUNCATE TABLE employees");
    $stmt->execute();
    
    // إعادة تعيين الأرقام التلقائية
    $stmt = $pdo->prepare("ALTER TABLE employees AUTO_INCREMENT = 1");
    $stmt->execute();
    
    header('Location: index.php?success=all_deleted');
} catch (Exception $e) {
    header('Location: index.php?error=' . urlencode($e->getMessage()));
}
?>