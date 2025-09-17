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
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);

if (!$flower_id || !$quantity) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT ID, flower_name, price, stock_quantity FROM tbl_flowers WHERE ID = ?");
    $stmt->execute([$flower_id]);
    $flower = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$flower) {
        echo json_encode(['status' => 'error', 'message' => 'สินค้าไม่พบ']);
        exit();
    }

    if ($quantity > $flower['stock_quantity']) {
        echo json_encode(['status' => 'error', 'message' => 'จำนวนที่เลือกเกินกว่าสต็อกที่มีอยู่']);
        exit();
    }

    $stmt = $conn->prepare("SELECT id FROM tbl_cart WHERE user_id = ? AND flower_id = ?");
    $stmt->execute([$user_id, $flower_id]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $stmt = $conn->prepare("UPDATE tbl_cart SET quantity = ?, created_at = NOW() WHERE user_id = ? AND flower_id = ?");
        $stmt->execute([$quantity, $user_id, $flower_id]);
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM tbl_cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $cart_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        echo json_encode([
            'status' => 'success',
            'message' => 'อัปเดตจำนวนสินค้าเรียบร้อย',
            'price' => $flower['price'],
            'cartCount' => $cart_count
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'สินค้าไม่อยู่ในตะกร้า']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage())]);
}
?>