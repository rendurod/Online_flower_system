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
    $stmt = $conn->prepare("SELECT ID, flower_name, price, stock_quantity FROM tbl_flowers WHERE ID = ? AND stock_quantity > 0");
    $stmt->execute([$flower_id]);
    $flower = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$flower) {
        echo json_encode(['status' => 'error', 'message' => 'สินค้าไม่พบหรือไม่มีในสต็อก']);
        exit();
    }

    $stmt = $conn->prepare("SELECT quantity FROM tbl_cart WHERE user_id = ? AND flower_id = ?");
    $stmt->execute([$user_id, $flower_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $new_quantity = $existing ? $existing['quantity'] + $quantity : $quantity;

    if ($new_quantity > $flower['stock_quantity']) {
        echo json_encode(['status' => 'error', 'message' => 'จำนวนรวมเกินกว่าสต็อกที่มีอยู่']);
        exit();
    }

    if ($existing) {
        $stmt = $conn->prepare("UPDATE tbl_cart SET quantity = ?, created_at = NOW() WHERE user_id = ? AND flower_id = ?");
        $stmt->execute([$new_quantity, $user_id, $flower_id]);
    } else {
        $stmt = $conn->prepare("INSERT INTO tbl_cart (user_id, flower_id, quantity, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $flower_id, $quantity]);
    }

    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM tbl_cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    echo json_encode([
        'status' => 'success',
        'message' => 'เพิ่มสินค้าลงตะกร้าเรียบร้อยแล้ว',
        'cartCount' => $cart_count
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage())]);
}
?>