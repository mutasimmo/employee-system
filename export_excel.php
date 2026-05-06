<?php
require_once 'config.php';

// جلب جميع الموظفين مع حساباتهم
$sql = "SELECT * FROM employees ORDER BY hire_date DESC";
$stmt = $pdo->query($sql);
$employees = $stmt->fetchAll();

// تعيين headers لملف Excel
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="employees_export_' . date('Y-m-d') . '.xls"');

// بدء جدول HTML (Excel يقرأ HTML)
echo '<table border="1">';
echo '<tr style="background:#1e3c72; color:white;">';
echo '<th>الرقم</th>';
echo '<th>الاسم</th>';
echo '<th>الصفة</th>';
echo '<th>تاريخ التعيين</th>';
echo '<th>سنوات الخدمة</th>';
echo '<th>تاريخ انتهاء العقد</th>';
echo '<th>نوع الخدمة</th>';
echo '<th>حالة العقد</th>';
echo '<th>الإدارة</th>';
echo '</tr>';

foreach ($employees as $emp) {
    $duration = calculateServiceDuration($emp['hire_date']);
    $contract = calculateContractDetails($emp['hire_date'], $emp['rank']);
    
    echo '<tr>';
    echo '<td>' . htmlspecialchars($emp['employee_number']) . '</td>';
    echo '<td>' . htmlspecialchars($emp['name']) . '</td>';
    echo '<td>' . htmlspecialchars($emp['rank']) . '</td>';
    echo '<td>' . date('d/m/Y', strtotime($emp['hire_date'])) . '</td>';
    echo '<td>' . $duration['formatted'] . '</td>';
    echo '<td>' . $contract['end_date_formatted'] . '</td>';
    echo '<td>' . $contract['service_type'] . '</td>';
    echo '<td>' . $contract['status'] . '</td>';
    echo '<td>' . htmlspecialchars($emp['department']) . '</td>';
    echo '</tr>';
}

echo '</table>';
?>