<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

$message = '';
$message_type = '';
$import_summary = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['xlsx', 'xls', 'csv'];
    
    if (!in_array($file_ext, $allowed_ext)) {
        $message = 'صيغة الملف غير مدعومة. استخدم xlsx, xls, csv';
        $message_type = 'danger';
    } else {
        $new_filename = 'import_' . date('Ymd_His') . '.' . $file_ext;
        $file_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $result = smartImport($file_path, $file_ext);
            $message = $result['message'];
            $message_type = $result['type'];
            $import_summary = $result['summary'] ?? [];
        } else {
            $message = 'حدث خطأ أثناء رفع الملف';
            $message_type = 'danger';
        }
    }
}

function smartImport($file_path, $file_ext) {
    global $pdo;
    
    $stats = [
        'total_rows' => 0,
        'inserted' => 0,
        'skipped' => 0,
        'duplicate' => 0,
        'invalid_rank' => 0,
        'invalid_date' => 0,
        'empty_data' => 0,
        'ref_errors' => 0,
        'errors_list' => []
    ];
    
    $default_department = 'غير محدد';
    
    try {
        $rows = [];
        
        if ($file_ext == 'csv') {
            // قراءة CSV
            $content = file_get_contents($file_path);
            // إزالة BOM إذا وجد
            if (substr($content, 0, 3) == "\xEF\xBB\xBF") {
                $content = substr($content, 3);
            }
            $content = mb_convert_encoding($content, 'UTF-8', 'auto');
            $lines = explode("\n", $content);
            
            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    $rows[] = str_getcsv($line);
                }
            }
        } else {
            // قراءة Excel
            try {
                $spreadsheet = IOFactory::load($file_path);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
            } catch (Exception $e) {
                return ['message' => 'فشل قراءة الملف: ' . $e->getMessage(), 'type' => 'danger'];
            }
        }
        
        if (empty($rows)) {
            return ['message' => 'الملف فارغ', 'type' => 'danger'];
        }
        
        // ============================================================
        // اكتشاف الترويسة والبيانات تلقائياً
        // ============================================================
        $start_row = 0;
        $headers = [];
        
        // محاولة اكتشاف الترويسة في الصف الأول
        $firstRow = $rows[0];
        $isHeader = false;
        
        // الكلمات المفتاحية التي تشير إلى أن هذا صف ترويسة
        $headerKeywords = ['employee_number', 'الرقم', 'id', 'name', 'الاسم', 'rank', 'الصفة', 'hire_date', 'التاريخ', 'department', 'الإدارة'];
        
        foreach ($firstRow as $cell) {
            $cellStr = strtolower(trim((string)$cell));
            foreach ($headerKeywords as $keyword) {
                if (strpos($cellStr, $keyword) !== false) {
                    $isHeader = true;
                    break 2;
                }
            }
        }
        
        if ($isHeader) {
            $start_row = 1;
            $headers = $firstRow;
            $stats['total_rows'] = count($rows) - 1;
        } else {
            $stats['total_rows'] = count($rows);
        }
        
        // ============================================================
        // معالجة كل صف
        // ============================================================
        for ($i = $start_row; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            // تخطي الصفوف الفارغة تماماً
            if (empty(array_filter($row))) continue;
            
            // استخراج البيانات - نأخذ أول 5 أعمدة بغض النظر عن الترويسة
            $employee_number = trim((string)($row[0] ?? ''));
            $name = trim((string)($row[1] ?? ''));
            $rank = trim((string)($row[2] ?? ''));
            $hire_date_raw = trim((string)($row[3] ?? ''));
            $department = trim((string)($row[4] ?? ''));
            
            // ============================================================
            // معالجة الأخطاء الشائعة
            // ============================================================
            
            // معالجة #REF! والأخطاء
            if (strpos($employee_number, '#REF!') !== false || strpos($employee_number, '#N/A') !== false || strpos($employee_number, '#VALUE!') !== false) {
                $employee_number = '';
            }
            if (strpos($name, '#REF!') !== false || strpos($name, '#N/A') !== false) {
                $name = '';
            }
            if (strpos($rank, '#REF!') !== false || strpos($rank, '#N/A') !== false) {
                $rank = '';
            }
            if (strpos($department, '#REF!') !== false || strpos($department, '#N/A') !== false || empty($department)) {
                $department = $default_department;
                $stats['ref_errors']++;
            }
            
            // التحقق من البيانات الأساسية
            if (empty($employee_number) || empty($name)) {
                $stats['skipped']++;
                $stats['empty_data']++;
                if (count($stats['errors_list']) < 20) {
                    $stats['errors_list'][] = "سطر " . ($i + 1) . ": بيانات ناقصة (الرقم أو الاسم فارغ)";
                }
                continue;
            }
            
            // ============================================================
            // التحقق من الصفة وتصحيحها
            // ============================================================
            $allowed_ranks = ['ضابط', 'صف ضابط', 'موظف', 'متدرب'];
            $rank_original = $rank;
            
            if (!in_array($rank, $allowed_ranks)) {
                // محاولة تصحيح الصفة
                if (strpos($rank, 'ضابط') !== false && $rank != 'صف ضابط') {
                    $rank = 'ضابط';
                } elseif (strpos($rank, 'صف') !== false || strpos($rank, 'ضابط صف') !== false) {
                    $rank = 'صف ضابط';
                } elseif (strpos($rank, 'موظف') !== false) {
                    $rank = 'موظف';
                } elseif (strpos($rank, 'متدرب') !== false || strpos($rank, 'متدر') !== false) {
                    $rank = 'متدرب';
                } else {
                    $stats['skipped']++;
                    $stats['invalid_rank']++;
                    if (count($stats['errors_list']) < 20) {
                        $stats['errors_list'][] = "سطر " . ($i + 1) . ": صفة غير صالحة ('$rank_original') - استخدم: ضابط، صف ضابط، موظف، متدرب";
                    }
                    continue;
                }
            }
            
            // ============================================================
            // معالجة التاريخ
            // ============================================================
            $hire_date = null;
            
            // إذا كان التاريخ رقم (صيغة Excel)
            if (is_numeric($hire_date_raw)) {
                try {
                    $hire_date = Date::excelToDateTimeObject($hire_date_raw)->format('Y-m-d');
                } catch (Exception $e) {}
            }
            
            // محاولة صيغ مختلفة
            if (!$hire_date) {
                $formats = [
                    'Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y', 'Y.m.d'
                ];
                foreach ($formats as $format) {
                    $dateObj = DateTime::createFromFormat($format, $hire_date_raw);
                    if ($dateObj) {
                        $hire_date = $dateObj->format('Y-m-d');
                        break;
                    }
                }
            }
            
            // محاولة عامة
            if (!$hire_date) {
                $dateObj = date_create($hire_date_raw);
                if ($dateObj) {
                    $hire_date = $dateObj->format('Y-m-d');
                }
            }
            
            if (!$hire_date) {
                $stats['skipped']++;
                $stats['invalid_date']++;
                if (count($stats['errors_list']) < 20) {
                    $stats['errors_list'][] = "سطر " . ($i + 1) . ": تاريخ غير صالح ('$hire_date_raw') - استخدم YYYY-MM-DD";
                }
                continue;
            }
            
            // ============================================================
            // التحقق من عدم تكرار الرقم
            // ============================================================
            $check = $pdo->prepare("SELECT id FROM employees WHERE employee_number = ?");
            $check->execute([$employee_number]);
            if ($check->fetch()) {
                $stats['skipped']++;
                $stats['duplicate']++;
                if (count($stats['errors_list']) < 20) {
                    $stats['errors_list'][] = "سطر " . ($i + 1) . ": الرقم '$employee_number' مكرر";
                }
                continue;
            }
            
            // ============================================================
            // إضافة الموظف
            // ============================================================
            try {
                $stmt = $pdo->prepare("INSERT INTO employees (employee_number, name, rank, hire_date, department) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$employee_number, $name, $rank, $hire_date, $department]);
                $stats['inserted']++;
            } catch (Exception $e) {
                $stats['skipped']++;
                if (count($stats['errors_list']) < 20) {
                    $stats['errors_list'][] = "سطر " . ($i + 1) . ": " . $e->getMessage();
                }
            }
        }
        
        // ============================================================
        // بناء رسالة النتيجة
        // ============================================================
        $msg = "<strong>📊 نتيجة الاستيراد:</strong><br>";
        $msg .= "✅ تم استيراد: <strong>{$stats['inserted']}</strong> موظف<br>";
        $msg .= "📈 إجمالي الصفوف المعالجة: <strong>{$stats['total_rows']}</strong><br>";
        
        if ($stats['skipped'] > 0) {
            $msg .= "⚠️ تم تخطي: <strong>{$stats['skipped']}</strong> سجل:<br>";
            if ($stats['duplicate'] > 0) $msg .= "   • أرقام مكررة: {$stats['duplicate']}<br>";
            if ($stats['invalid_rank'] > 0) $msg .= "   • صفات غير صالحة: {$stats['invalid_rank']}<br>";
            if ($stats['invalid_date'] > 0) $msg .= "   • تواريخ غير صالحة: {$stats['invalid_date']}<br>";
            if ($stats['empty_data'] > 0) $msg .= "   • بيانات ناقصة: {$stats['empty_data']}<br>";
            if ($stats['ref_errors'] > 0) $msg .= "   • تم تصحيح أخطاء #REF! في الإدارة<br>";
        }
        
        if (!empty($stats['errors_list'])) {
            $msg .= "<hr><small class='text-danger'>📝 الأخطاء التفصيلية:<br>" . implode('<br>', $stats['errors_list']) . "</small>";
            if (count($stats['errors_list']) >= 20) {
                $msg .= "<br>... و" . ($stats['skipped'] - 20) . " أخطاء أخرى";
            }
        }
        
        $type = ($stats['inserted'] > 0) ? 'success' : 'warning';
        return ['message' => $msg, 'type' => $type, 'summary' => $stats];
        
    } catch (Exception $e) {
        return ['message' => 'خطأ عام: ' . $e->getMessage(), 'type' => 'danger'];
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد البيانات - النظام المتكامل</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border-radius: 20px;
            border: none;
            overflow: hidden;
        }
        .drop-zone {
            border: 2px dashed #ccc;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #667eea;
            background: #f0f0ff;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .step-number {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            direction: ltr;
            text-align: left;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card shadow-lg">
        <div class="card-header bg-primary text-white py-3">
            <h2 class="mb-0"><i class="fas fa-file-import me-2"></i> استيراد بيانات الموظفين</h2>
            <p class="mb-0 mt-1 small">نظام ذكي يتعامل مع جميع التنسيقات والأخطاء</p>
        </div>
        <div class="card-body p-4">
            
            <!-- رسائل النتيجة -->
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show mb-4">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- نموذج الرفع -->
            <form action="" method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-cloud-upload-alt fa-4x text-muted mb-3"></i>
                    <h5>اسحب وأفلت ملف Excel أو CSV هنا</h5>
                    <p class="text-muted">أو انقر للاختيار من الجهاز</p>
                    <input type="file" name="excel_file" id="fileInput" class="d-none" accept=".xlsx,.xls,.csv">
                    <button type="button" class="btn btn-outline-primary mt-2" onclick="event.stopPropagation(); document.getElementById('fileInput').click()">
                        <i class="fas fa-folder-open"></i> اختيار ملف
                    </button>
                </div>
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-success btn-lg px-5" id="submitBtn" disabled>
                        <i class="fas fa-database me-2"></i> بدء الاستيراد
                    </button>
                    <a href="index.php" class="btn btn-secondary btn-lg px-4 ms-2">
                        <i class="fas fa-arrow-left me-2"></i> العودة
                    </a>
                </div>
            </form>
            
            <hr class="my-4">
            
            <!-- تعليمات -->
            <h5><i class="fas fa-info-circle text-info"></i> تعليمات مهمة</h5>
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="step">
                        <div class="step-number">1</div>
                        <div>الملف يجب أن يحتوي على <strong>5 أعمدة</strong> بالترتيب:</div>
                    </div>
                    <pre>
العمود A: الرقم الوظيفي
العمود B: الاسم الكامل  
العمود C: الصفة
العمود D: تاريخ التعيين
العمود E: الإدارة</pre>
                </div>
                <div class="col-md-6">
                    <div class="step">
                        <div class="step-number">2</div>
                        <div>القيم المقبولة في عمود <strong>الصفة</strong>:</div>
                    </div>
                    <pre>ضابط
صف ضابط
موظف
متدرب</pre>
                </div>
            </div>
            
            <div class="alert alert-warning mt-3">
                <i class="fas fa-shield-alt me-2"></i>
                <strong>نظام ذكي:</strong> يتعامل مع الأخطاء الشائعة مثل <code>#REF!</code>، <code>#N/A</code>، التواريخ بصيغ مختلفة، ويصحح الصفات تلقائياً.
            </div>
            
            <div class="text-center mt-3">
                <button class="btn btn-sm btn-outline-success" onclick="downloadTemplate()">
                    <i class="fas fa-download me-1"></i> تحميل قالب CSV جاهز
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const submitBtn = document.getElementById('submitBtn');
    
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            checkFile(files[0]);
        }
    });
    
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            checkFile(e.target.files[0]);
        }
    });
    
    function checkFile(file) {
        const validExts = ['xlsx', 'xls', 'csv'];
        const ext = file.name.split('.').pop().toLowerCase();
        if (validExts.includes(ext)) {
            submitBtn.disabled = false;
            dropZone.style.borderColor = '#28a745';
            dropZone.style.background = '#e8f5e9';
        } else {
            submitBtn.disabled = true;
            dropZone.style.borderColor = '#dc3545';
            dropZone.style.background = '#ffebee';
            alert('صيغة الملف غير مدعومة');
        }
    }
    
    function downloadTemplate() {
        const data = [
            'employee_number,name,rank,hire_date,department',
            '201,محمد أحمد,ضابط,2025-01-15,الإدارة العامة',
            '202,خالد سعيد,صف ضابط,2025-02-20,الموارد البشرية',
            '203,نورة علي,موظف,2025-03-10,المالية',
            '204,عمر إبراهيم,متدرب,2026-01-01,التدريب'
        ];
        const csv = data.join('\n');
        const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'template_employees.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    }
</script>
</body>
</html>