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

// Get order ID from URL
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    $_SESSION['error'] = "ไม่พบคำสั่งซื้อ";
    header("Location: user-order.php");
    exit();
}

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

// Fetch order details
$order_query = "SELECT o.BookingNumber, o.Quantity, o.DeliveryDate, o.Image, o.PostingDate, o.Status, o.Message, o.AccountName, o.AccountNumber, f.flower_name, f.price 
                FROM tbl_orders o 
                JOIN tbl_flowers f ON o.FlowerId = f.ID 
                WHERE o.ID = :order_id AND o.UserEmail = :email";
$order_stmt = $conn->prepare($order_query);
$order_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
$order_stmt->bindValue(':email', $user_data['EmailId'], PDO::PARAM_STR);
$order_stmt->execute();
$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = "ไม่พบข้อมูลคำสั่งซื้อ";
    header("Location: user-order.php");
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

// Status options for icon and text
$statusOptions = [
    0 => ['text' => 'รอแจ้งชำระเงิน', 'class' => 'status-awaiting', 'icon' => 'fa-clock'],
    1 => ['text' => 'การชำระเงินสำเร็จ', 'class' => 'status-paid', 'icon' => 'fa-check'],
    2 => ['text' => 'แก้ไขการชำระเงิน', 'class' => 'status-edited', 'icon' => 'fa-edit'],
    3 => ['text' => 'กำลังจัดส่งสินค้า', 'class' => 'status-processing', 'icon' => 'fa-truck'],
    4 => ['text' => 'คำสั่งซื้อสำเร็จ', 'class' => 'status-completed', 'icon' => 'fa-check-circle'],
    5 => ['text' => 'แนบสลิปใหม่', 'class' => 'status-new-slip', 'icon' => 'fa-upload'],
    6 => ['text' => 'ยกเลิกคำสั่งซื้อ', 'class' => 'status-cancel', 'icon' => 'fa-times-circle']
];
$status = isset($statusOptions[$order['Status']]) ? $order['Status'] : 1; // Default to 1 if invalid
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดคำสั่งซื้อ - FlowerShop</title>
    <!-- LOGO -->
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/productFinish.css">
    <style>
        .status-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .status-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #333;
        }
        .back-button {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background-color: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }
        .back-button:hover {
            background-color: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(220, 53, 69, 0.3);
        }
        .back-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }
        .order-finish-container {
            position: relative;
            padding-top: 3rem; /* Space for back button */
        }
    </style>
</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- Order Detail Section -->
    <section class="order-finish-section">
        <div class="order-finish-container">
            <!-- Back Button -->
            <a href="user-order.php" class="back-button">
                <i class="fas fa-arrow-left me-1"></i> ย้อนกลับ
            </a>

            <!-- Status Icon -->
            <div class="success-icon status-icon">
                <i class="fas <?php echo htmlspecialchars($statusOptions[$status]['icon']); ?>"></i>
            </div>
            <!-- Status Title -->
            <h1 class="success-title status-title"><?php echo htmlspecialchars($statusOptions[$status]['text']); ?></h1>
            <!-- Status Message -->
            <p class="success-message">ขอบคุณที่สั่งซื้อกับเรา คำสั่งซื้อของคุณอยู่ในสถานะ: <?php echo htmlspecialchars($statusOptions[$status]['text']); ?></p>

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
                    <span><?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></span>
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
                        <img src="Uploads/slips/<?php echo htmlspecialchars($order['Image']); ?>" alt="Payment Slip">
                    </div>
                <?php else: ?>
                    <p>ยังไม่มีสลิปการชำระเงิน</p>
                <?php endif; ?>
            </div>

            <!-- Additional Info for Cancelled Orders -->
            <?php if ($order['Status'] == 6 && (!empty($order['Message']) || !empty($order['AccountName']) || !empty($order['AccountNumber']))): ?>
                <div class="cancel-details">
                    <h4>ข้อมูลการยกเลิก</h4>
                    <?php if (!empty($order['Message'])): ?>
                        <div class="order-summary-item">
                            <span>เหตุผลการยกเลิก</span>
                            <span><?php echo htmlspecialchars($order['Message']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($order['AccountName'])): ?>
                        <div class="order-summary-item">
                            <span>ชื่อบัญชี</span>
                            <span><?php echo htmlspecialchars($order['AccountName']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($order['AccountNumber'])): ?>
                        <div class="order-summary-item">
                            <span>เลขที่บัญชี</span>
                            <span><?php echo htmlspecialchars($order['AccountNumber']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends -->

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>