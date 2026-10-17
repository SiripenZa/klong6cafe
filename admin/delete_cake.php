<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cake.php');
    exit;
}

$cake_id = trim($_POST['cake_id'] ?? '');

if ($cake_id === '') {
    header('Location: cake.php?error=missing_id');
    exit;
}

// ลบเฉพาะ cake_id ที่ส่งมา
$stmt = $conn->prepare("DELETE FROM cake WHERE cake_id = ?");

if (!$stmt) {
    die("Prepare Error: " . $conn->error);
}

$stmt->bind_param("s", $cake_id);

if (!$stmt->execute()) {
    die("Delete Error: " . $stmt->error);
}

if ($stmt->affected_rows > 0) {
    header('Location: cake.php?deleted=1');
} else {
    header('Location: cake.php?error=not_found');
}

$stmt->close();
$conn->close();
exit;
?>