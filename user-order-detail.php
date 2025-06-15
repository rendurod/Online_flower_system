<?php
session_start();
include('config/db.php');

// Check database connection
if (!$conn) {
    $_SESSION['error'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
    header("Location: login.php");
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_login'])) {
    $_SESSION['error'] = "กรุณาเข้าสู่ระบบก่อน";
    header("Location: login.php");
    exit();
}

// Get user ID
$user_id = filter_var($_SESSION['user_login'], FILTER_VALIDATE_INT);
if ($user_id === false) {
    $_SESSION['error'] = "ข้อมูลผู้ใช้ไม่ถูกต้อง";
    header("Location: login.php");
    exit();
}

// Get order ID from URL
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    $_SESSION['error'] = "ไม่พบคำสั่งซื้อ";
    header("Location: user-order.php");
    exit();
}

// Fetch user data from tbl_members (as recipient)
try {
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
} catch (PDOException $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    header("Location: login.php");
    exit();
}

// Fetch order details
try {
    $order_query = "SELECT o.BookingNumber, o.Quantity, o.DeliveryDate, o.Image, o.PostingDate, o.LastupdateDate, o.Status, o.Message, o.AccountName, o.AccountNumber, f.flower_name, f.price, f.image as flower_image
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
} catch (PDOException $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    header("Location: user-order.php");
    exit();
}

// Fetch sender data from tbl_contact (latest record)
try {
    $sender_query = "SELECT nameteam, address, phone as tel FROM tbl_contact ORDER BY creationDate DESC LIMIT 1";
    $sender_stmt = $conn->prepare($sender_query);
    $sender_stmt->execute();
    $sender_data = $sender_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sender_data) {
        $sender_data = [
            'nameteam' => 'FlowerShop Team',
            'address' => 'ที่อยู่ร้านค้า (ไม่พบข้อมูล)',
            'tel' => '0-000-000-0000'
        ];
    }
} catch (PDOException $e) {
    $sender_data = [
        'nameteam' => 'FlowerShop Team',
        'address' => 'ไม่สามารถดึงข้อมูลได้',
        'tel' => 'ไม่สามารถดึงข้อมูลได้'
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
$status = isset($statusOptions[$order['Status']]) ? $order['Status'] : 0;

// Prepare refund data
$refundStatus = strpos($order['Message'], '//RefundedByAdmin') !== false ? 'โอนเงินคืนแล้ว' : 'รอการโอนเงินคืน';
$refundClass = strpos($order['Message'], '//RefundedByAdmin') !== false ? 'status-refunded' : 'status-not-refunded';
$messageParts = explode('//RefundedByAdmin', $order['Message']);
$cancelReason = trim($messageParts[0]);
$refundMessage = isset($messageParts[1]) ? trim($messageParts[1]) : '';

// Check if image file exists
$imagePath = !empty($order['Image']) ? 'Uploads/slips/' . htmlspecialchars($order['Image'], ENT_QUOTES, 'UTF-8') : '';
$imageExists = $imagePath && file_exists($imagePath);
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
            padding-top: 3rem;
        }

        .payment-slip img,
        .refund-slip img {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid rgba(232, 67, 147, 0.2);
        }

        .status-refunded {
            background-color: #28a745;
            color: #fff;
        }

        .status-not-refunded {
            background-color: #dc3545;
            color: #fff;
        }

        .refund-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .cancel-details,
        .payment-details,
        .refund-details {
            border-left: 4px solid #4e73df;
            padding-left: 10px;
            margin-bottom: 1.5rem;
        }

        .cancel-details h4,
        .payment-details h4,
        .refund-details h4 {
            color: #4e73df;
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
                <i class="fas <?php echo htmlspecialchars($statusOptions[$status]['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
            </div>
            <!-- Status Title -->
            <h1 class="success-title status-title"><?php echo htmlspecialchars($statusOptions[$status]['text'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <!-- Status Message -->
            <p class="success-message">ขอบคุณที่สั่งซื้อกับเรา คำสั่งซื้อของคุณอยู่ในสถานะ: <?php echo htmlspecialchars($statusOptions[$status]['text'], ENT_QUOTES, 'UTF-8'); ?></p>

            <!-- Order Summary -->
            <div class="order-summary">
                <h4>สรุปคำสั่งซื้อ</h4>
                <div class="order-summary-item">
                    <span>หมายเลขคำสั่งซื้อ</span>
                    <span><?php echo htmlspecialchars($order['BookingNumber'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>ชื่อสินค้า</span>
                    <span><?php echo htmlspecialchars($order['flower_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>ราคาต่อหน่วย</span>
                    <span>฿<?php echo number_format($order['price'], 2); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>จำนวน</span>
                    <span><?php echo htmlspecialchars($order['Quantity'], ENT_QUOTES, 'UTF-8'); ?> ชิ้น</span>
                </div>
                <div class="order-summary-item">
                    <span>ราคารวม</span>
                    <span>฿<?php echo number_format($order['price'] * $order['Quantity'], 2); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>วันที่สั่งซื้อ</span>
                    <span><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                </div>
                <div class="order-item">
                    <span>วันที่จัดส่ง</span>
                    <span><?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></span>
                </div>
                <?php if ($order['Status'] == 6): ?>
                    <div class="order-summary-item">
                        <span>วันที่ยกเลิก</span>
                        <span><?php echo $order['LastupdateDate'] ? date('d/m/Y H:i', strtotime($order['LastupdateDate'])) : 'ไม่ระบุ'; ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Shipping Details -->
            <div class="shipping-details">
                <h4>ข้อมูลผู้รับ</h4>
                <div class="order-details-item">
                    <span>ชื่อ</span>
                    <span><?php echo htmlspecialchars($user_data['FirstName'] . ' ' . $user_data['LastName'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="order-details-item">
                    <span>ที่อยู่</span>
                    <span><?php echo htmlspecialchars($user_data['Address'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="order-details-item">
                    <span>โทรศัพท์</span>
                    <span><?php echo htmlspecialchars($user_data['ContactNo'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <h4>ข้อมูลผู้ส่ง</h4>
                <div class="order-details-item">
                    <span>ชื่อ</span>
                    <span><?php echo htmlspecialchars($sender_data['nameteam'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="order-details-item">
                    <span>ที่อยู่</span>
                    <span><?php echo htmlspecialchars($sender_data['address'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="order-details-item">
                    <span>โทรศัพท์</span>
                    <span><?php echo htmlspecialchars($sender_data['tel'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>

            <!-- Payment Details -->
            <div class="payment-details">
                <h4>รายการชำระเงิน</h4>
                <?php if ($imageExists && $order['Status'] != 6): ?>
                    <div class="payment-slip">
                        <span>สลิปการชำระเงิน</span>
                        <img src="<?php echo $imagePath; ?>" alt="Payment Slip">
                    </div>
                <?php else: ?>
                    <p>ไม่มีสลิปการชำระเงิน</p>
                <?php endif; ?>
            </div>

            <!-- Refund and Cancel Details -->
            <?php if ($order['Status'] == 6): ?>
                <div class="cancel-details">
                    <h4>ข้อมูลการยกเลิกและการคืนเงิน</h4>
                    <div class="order-details-item">
                        <span>สถานะการคืนเงิน</span>
                        <span class="refund-text <?php echo htmlspecialchars($refundClass, ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fas fa-<?php echo strpos($order['Message'], '//RefundedByAdmin') ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                            <?php echo htmlspecialchars($refundStatus, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                    <?php if (!empty($cancelReason)): ?>
                        <div class="order-details-item">
                            <span>เหตุผลการยกเลิก</span>
                            <span><?php echo htmlspecialchars($cancelReason, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($order['AccountName'])): ?>
                        <div class="order-details-item">
                            <span>ชื่อบัญชี</span>
                            <span><?php echo htmlspecialchars($order['AccountName'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($order['AccountNumber'])): ?>
                        <div class="order-details-item">
                            <span>เลขที่บัญชี</span>
                            <span><?php echo htmlspecialchars($order['AccountNumber'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($imageExists && strpos($order['Message'], '//RefundedByAdmin') !== false): ?>
                    <div class="refund-details">
                        <h4>รายละเอียดการคืนเงิน</h4>
                        <div class="payment-slip">
                            <span>สลิปการคืนเงิน</span>
                            <img src="<?php echo $imagePath; ?>" alt="Refund Slip">
                        </div>
                        <?php if (!empty($refundMessage)): ?>
                            <div class="order-details-item">
                                <span>ข้อความจากแอดมิน</span>
                                <span><?php echo htmlspecialchars($refundMessage, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- footer -->
    <?php include("footer.php"); ?>
    <!-- footer ends -->

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (isset($_SESSION['error'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const toastEl = document.createElement('div');
                toastEl.className = 'toast-container position-fixed top-0 end-0';
                toastEl.innerHTML = `
                    <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                `;
                document.body.appendChild(toastEl);
                const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                toast.show();
                <?php unset($_SESSION['error']); ?>
            });
        </script>
    <?php endif; ?>
</body>

</html>