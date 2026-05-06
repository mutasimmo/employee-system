-- نظام إدارة الموظفين - قاعدة البيانات
-- متدرب: 3 أشهر / باقي الصفات: سنة ثم خدمة مستديمة بعد سنتين

CREATE DATABASE IF NOT EXISTS employee_system;
USE employee_system;

CREATE TABLE IF NOT EXISTS employees (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_number VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    rank ENUM('ضابط', 'صف ضابط', 'موظف', 'متدرب') NOT NULL,
    hire_date DATE NOT NULL,
    department VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- بيانات تجريبية
INSERT INTO employees (employee_number, name, rank, hire_date, department) VALUES
('101', 'أحمد محمد علي', 'ضابط', '2020-01-15', 'العمليات'),
('102', 'خالد عبدالله', 'صف ضابط', '2023-06-20', 'الإمداد'),
('103', 'نورة سعيد', 'موظف', '2025-01-10', 'الشؤون الإدارية'),
('104', 'عمر إبراهيم', 'متدرب', '2026-02-01', 'التدريب'),
('105', 'سارة أحمد', 'متدرب', '2026-01-01', 'التدريب');