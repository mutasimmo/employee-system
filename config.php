<?php
// بيانات الاتصال بقاعدة البيانات PostgreSQL
$host = 'dpg-d7te62reo5us73b9tatg-a';
$port = '5432';
$dbname = 'employees_byoz';
$username = 'admin';
$password = '5MxgFkrbs2VEe4BSQTI5za1rBYhacTWx';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$username;password=$password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// دوال حساب الخدمة (ضعها هنا كاملة)
function calculateServiceDuration($hireDate) {
    $today = new DateTime();
    $hire = new DateTime($hireDate);
    $diff = $today->diff($hire);
    
    return [
        'years' => $diff->y,
        'months' => $diff->m,
        'days' => $diff->d,
        'total_years' => $diff->y + ($diff->m / 12) + ($diff->d / 365),
        'formatted' => $diff->y . " سنوات، " . $diff->m . " أشهر، " . $diff->d . " أيام"
    ];
}

function calculateContractDetails($hireDate, $rank) {
    $today = new DateTime();
    $hire = new DateTime($hireDate);
    $diff = $today->diff($hire);
    $totalYears = $diff->y + ($diff->m / 12);
    
    if ($rank == 'متدرب') {
        $endDate = clone $hire;
        $endDate->modify('+3 months');
        $serviceType = 'تدريب';
        $daysLeft = $today->diff($endDate)->days;
        
        if ($endDate < $today) {
            $status = 'انتهى';
            $alert_message = '❌ انتهت فترة التدريب';
        } elseif ($daysLeft <= 10) {
            $status = 'تنبيه';
            $alert_message = '⚠️ ينتهي التدريب خلال ' . $daysLeft . ' أيام';
        } else {
            $status = 'ساري';
            $alert_message = '';
        }
        
        return [
            'end_date_formatted' => $endDate->format('d/m/Y'),
            'service_type' => $serviceType,
            'status' => $status,
            'alert_message' => $alert_message
        ];
    }
    
    $endDate = clone $hire;
    $endDate->modify('+1 year');
    $daysLeft = $today->diff($endDate)->days;
    
    if ($totalYears >= 2) {
        return [
            'end_date_formatted' => '—',
            'service_type' => 'خدمة مستديمة',
            'status' => 'مستديم',
            'alert_message' => ''
        ];
    }
    
    $serviceType = 'عقد سنوي';
    
    if ($endDate < $today) {
        $status = 'انتهى';
        $alert_message = '❌ انتهى العقد';
    } elseif ($daysLeft <= 30) {
        $status = 'تنبيه';
        $alert_message = '⚠️ ينتهي العقد خلال ' . $daysLeft . ' يوماً';
    } else {
        $status = 'ساري';
        $alert_message = '';
    }
    
    return [
        'end_date_formatted' => $endDate->format('d/m/Y'),
        'service_type' => $serviceType,
        'status' => $status,
        'alert_message' => $alert_message
    ];
}
?>