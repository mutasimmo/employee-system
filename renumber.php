<?php
require_once 'config.php';

try {
    // الحصول على جميع الموظفين بالترتيب الحالي
    $stmt = $pdo->query("SELECT id FROM employees ORDER BY CAST(employee_number AS UNSIGNED) ASC");
    $employees = $stmt->fetchAll();
    
    $counter = 1;
    $updated = 0;
    
    foreach ($employees as $emp) {
        $update = $pdo->prepare("UPDATE employees SET employee_number = ? WHERE id = ?");
        $update->execute([$counter, $emp['id']]);
        $counter++;
        $updated++;
    }
    
    header('Location: index.php?success=renumbered');
} catch (Exception $e) {
    header('Location: index.php?error=' . urlencode($e->getMessage()));
}
?>