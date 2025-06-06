<?php
session_start();
include('config/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_login'])) {
    header("Location: login.php");
    exit();
}

// Get user ID
$user_id = $_SESSION['user_login'];

// Fetch user data from tbl_members (as recipient)
$user_query = "SELECT FirstName, LastName, EmailId, ContactNo, Address FROM tbl_members WHERE ID = :id";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
$user_stmt->execute();
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    $_SESSION['error'] = "ไม่พบข้อมูลผู้ใช้";
    header("Location: login.php");
    exit();
}

// Fetch the latest order for the user
$order_query = "SELECT o.BookingNumber, o.Quantity, o.DeliveryDate, o.Image, o.PostingDate, f.flower_name, f.price 
                FROM tbl_orders o 
                JOIN tbl_flowers f ON o.FlowerId = f.ID 
                WHERE o.UserEmail = :email 
                ORDER BY o.PostingDate DESC LIMIT 1";
$order_stmt = $conn->prepare($order_query);
$order_stmt->bindValue(':email', $user_data['EmailId'], PDO::PARAM_STR);
$order_stmt->execute();
$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = "ไม่พบข้อมูลคำสั่งซื้อ";
    header("Location: products.php");
    exit();
}

// Fetch sender data from tbl_contact (latest record)
$sender_query = "SELECT nameteam, address, tel FROM tbl_contact ORDER BY creationDate DESC LIMIT 1";
$sender_stmt = $conn->prepare($sender_query);
$sender_stmt->execute();
$sender_data = $sender_stmt->fetch(PDO::FETCH_ASSOC);

if (!$sender_data) {
    $sender_data = [
        'nameteam' => 'FlowerShop Team (ไม่พบข้อมูล)',
        'address' => 'ที่อยู่ร้านค้า (ไม่พบข้อมูล)',
        'tel' => '0-000-000-0000 (ไม่พบข้อมูล)'
    ];
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คำสั่งซื้อสำเร็จ - FlowerShop</title>
    <!-- LOGO -->
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/productFinish.css">
</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- Order Finish Section -->
    <section class="order-finish-section">
        <div class="step-container">
            <div class="step">
                <div class="circle">1</div>
                <div class="label">เลือกสินค้า</div>
            </div>
            <div class="step">
                <div class="line"></div>
                <div class="circle">2</div>
                <div class="label">กรอกข้อมูล</div>
            </div>
            <div class="step active">
                <div class="line"></div>
                <div class="circle">3</div>
                <div class="label active-label">คำสั่งซื้อสำเร็จ</div>
            </div>
        </div>
        <div class="order-finish-container">
            <!-- Success Icon -->
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <!-- Success Title -->
            <h1 class="success-title">คำสั่งซื้อสำเร็จ!</h1>
            <!-- Success Message -->
            <p class="success-message">ขอบคุณที่สั่งซื้อกับเรา คำสั่งซื้อของคุณได้รับการบันทึกเรียบร้อยแล้ว กรุณารอการยืนยันจากแอดมิน</p>

            <!-- Order Summary -->
            <div class="order-summary">
                <h4>สรุปคำสั่งซื้อ</h4>
                <div class="order-summary-item">
                    <span>หมายเลขคำสั่งซื้อ</span>
                    <span><?php echo htmlspecialchars($order['BookingNumber']); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>ชื่อสินค้า</span>
                    <span><?php echo htmlspecialchars($order['flower_name']); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>ราคาต่อหน่วย</span>
                    <span>฿<?php echo number_format($order['price'], 2); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>จำนวน</span>
                    <span><?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</span>
                </div>
                <div class="order-summary-item">
                    <span>ราคารวม</span>
                    <span>฿<?php echo number_format($order['price'] * $order['Quantity'], 2); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>วันที่สั่งซื้อ</span>
                    <span><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>วันที่จัดส่ง</span>
                    <span><?php echo date('d/m/Y', strtotime($order['DeliveryDate'])); ?></span>
                </div>
            </div>

            <!-- Shipping Details -->
            <div class="shipping-details">
                <h5>ข้อมูลผู้รับ</h5>
                <div class="order-summary-item">
                    <span>ชื่อ</span>
                    <span><?php echo htmlspecialchars($user_data['FirstName'] . ' ' . $user_data['LastName']); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>ที่อยู่</span>
                    <span><?php echo htmlspecialchars($user_data['Address']); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>โทรศัพท์</span>
                    <span><?php echo htmlspecialchars($user_data['ContactNo']); ?></span>
                </div>

                <h5>ข้อมูลผู้ส่ง</h5>
                <div class="order-summary-item">
                    <span>ชื่อ</span>
                    <span><?php echo htmlspecialchars($sender_data['nameteam']); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>ที่อยู่</span>
                    <span><?php echo htmlspecialchars($sender_data['address']); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>โทรศัพท์</span>
                    <span><?php echo htmlspecialchars($sender_data['tel']); ?></span>
                </div>
            </div>

            <!-- Payment Details -->
            <div class="payment-details">
                <h4>รายละเอียดการชำระเงิน</h4>
                <?php if (!empty($order['Image'])): ?>
                    <div class="payment-slip">
                        <span>สลิปการชำระเงิน</span>
                        <img src="uploads/slips/<?php echo htmlspecialchars($order['Image']); ?>" alt="Payment Slip">
                    </div>
                <?php else: ?>
                    <p>ยังไม่มีสลิปการชำระเงิน</p>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="products.php" class="btn-continue">
                    <i class="fas fa-shopping-bag"></i> ซื้อสินค้าต่อ
                </a>
                <a href="user-order.php" class="btn-view-orders">
                    <i class="fas fa-list"></i> ดูคำสั่งซื้อ
                </a>
            </div>
        </div>
    </section>

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends -->

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>