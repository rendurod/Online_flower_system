<?php
session_start();
include('config/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_login'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$user_id = $_SESSION['user_login'];
$flower_id = filter_input(INPUT_POST, 'flower_id', FILTER_VALIDATE_INT);

if (!$flower_id) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit();
}

try {
    $stmt = $conn->prepare("DELETE FROM tbl_cart WHERE user_id = ? AND flower_id = ?");
    $stmt->execute([$user_id, $flower_id]);
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM tbl_cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $cart_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        echo json_encode([
            'status' => 'success',
            'message' => 'ลบสินค้าออกจากตะกร้าเรียบร้อย',
            'cartCount' => $cart_count
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'สินค้าไม่อยู่ในตะกร้า']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage())]);
}
?>