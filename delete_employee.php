<?php
require_once 'config.php';

$id = $_GET['id'] ?? 0;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: index.php?success=deleted');
    } catch (Exception $e) {
        header('Location: index.php?error=delete_failed');
    }
} else {
    header('Location: index.php?error=invalid_id');
}
exit();
?>