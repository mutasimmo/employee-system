<?php
require_once 'config.php';

// جلب جميع الموظفين
$sql = "SELECT * FROM employees ORDER BY hire_date DESC";
$stmt = $pdo->query($sql);
$employees = $stmt->fetchAll();

// إنشاء HTML للتقرير
$html = '
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير الموظفين</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; margin: 20px; }
        h1 { text-align: center; color: #1e3c72; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background: #1e3c72; color: white; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <h1>📋 تقرير الموظفين</h1>
    <p>تاريخ التقرير: ' . date('d/m/Y h:i A') . '</p>
    
    <table>
        <thead>
            <tr>
                <th>الرقم</th>
                <th>الاسم</th>
                <th>الصفة</th>
                <th>تاريخ التعيين</th>
                <th>سنوات الخدمة</th>
                <th>تاريخ انتهاء العقد</th>
                <th>نوع الخدمة</th>
                <th>حالة العقد</th>
                <th>الإدارة</th>
            </tr>
        </thead>
        <tbody>';

foreach ($employees as $emp) {
    $duration = calculateServiceDuration($emp['hire_date']);
    $contract = calculateContractDetails($emp['hire_date'], $emp['rank']);
    
    $html .= '
            <tr>
                <td>' . htmlspecialchars($emp['employee_number']) . '</td>
                <td>' . htmlspecialchars($emp['name']) . '</td>
                <td>' . htmlspecialchars($emp['rank']) . '</td>
                <td>' . date('d/m/Y', strtotime($emp['hire_date'])) . '</td>
                <td>' . $duration['formatted'] . '</td>
                <td>' . $contract['end_date_formatted'] . '</td>
                <td>' . $contract['service_type'] . '</td>
                <td>' . $contract['status'] . '</td>
                <td>' . htmlspecialchars($emp['department']) . '</td>
            </tr>';
}

$html .= '
        </tbody>
     </table>
    <div class="footer">تم إنشاء هذا التقرير بواسطة نظام إدارة الموظفين</div>
</body>
</html>';

// استخدام مكتبة Dompdf (تحتاج إلى تثبيتها)
// للاستخدام السريع، سنستخدم طريقة بديلة: طباعة HTML مباشرة مع أمر الطباعة
// أو يمكنك تثبيت Dompdf عبر composer

// الطريقة الأسهل: فتح نافذة الطباعة مباشرة
echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>تقرير الموظفين</title>
    <style>
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 20px; }
        }
        body { font-family: Arial, sans-serif; }
        .no-print { text-align: center; margin-bottom: 20px; }
        button { padding: 10px 20px; margin: 0 10px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background: #1e3c72; color: white; }
    </style>
</head>
<body>
    <div class="no-print">
        <h2>📄 معاينة التقرير</h2>
        <button onclick="window.print()">🖨️ طباعة / حفظ PDF</button>
        <button onclick="window.close()">✖️ إغلاق</button>
        <hr>
    </div>
    ' . $html . '
</body>
</html>';
?>