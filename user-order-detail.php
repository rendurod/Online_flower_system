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
    $user_query = "SELECT FirstName, LastName, EmailId, ContactNo, Address, Validate FROM tbl_members WHERE ID = :id";
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
    $order_query = "SELECT BookingNumber, Quantity, DeliveryDate, Image, PostingDate, LastupdateDate, Status, Message, 
                    AccountName, AccountNumber, SumTotal, FlowerId
                    FROM tbl_orders 
                    WHERE ID = :order_id AND UserEmail = :email";
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

// Fetch order items (single-item or multi-item)
$items = [];
$calculated_total = 0;
$is_multi_item = false;

try {
    // Check for multi-item order in tbl_order_details
    $items_query = "SELECT od.FlowerId, od.Quantity, od.Price, f.flower_name, f.image 
                    FROM tbl_order_details od 
                    JOIN tbl_flowers f ON od.FlowerId = f.ID 
                    WHERE od.OrderId = :order_id";
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
    $items_stmt->execute();
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($items)) {
        // Multi-item order
        $is_multi_item = true;
        foreach ($items as $item) {
            $calculated_total += $item['Price'] * $item['Quantity'];
        }
    } else {
        // Single-item order
        if ($order['FlowerId']) {
            $flower_query = "SELECT flower_name, price, image FROM tbl_flowers WHERE ID = :flower_id";
            $flower_stmt = $conn->prepare($flower_query);
            $flower_stmt->bindValue(':flower_id', $order['FlowerId'], PDO::PARAM_INT);
            $flower_stmt->execute();
            $flower = $flower_stmt->fetch(PDO::FETCH_ASSOC);
            if ($flower) {
                $items[] = [
                    'FlowerId' => $order['FlowerId'],
                    'Quantity' => $order['Quantity'],
                    'Price' => $flower['price'],
                    'flower_name' => $flower['flower_name'],
                    'image' => $flower['image']
                ];
                $calculated_total = $flower['price'] * $order['Quantity'];
            }
        }
    }

    // Verify and update SumTotal if necessary
    if (abs($calculated_total - $order['SumTotal']) > 0.01) {
        try {
            $update_query = "UPDATE tbl_orders SET SumTotal = :total_amount WHERE ID = :order_id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindValue(':total_amount', $calculated_total, PDO::PARAM_STR);
            $update_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
            $update_stmt->execute();
            $order['SumTotal'] = $calculated_total;
        } catch (PDOException $e) {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตยอดรวม: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการดึงข้อมูลรายการสินค้า: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    header("Location: user-order.php");
    exit();
}

// Fetch sender data from tbl_contact (latest record)
try {
    $sender_query = "SELECT nameteam, address, tel FROM tbl_contact ORDER BY creationDate DESC LIMIT 1";
    $sender_stmt = $conn->prepare($sender_query);
    $sender_stmt->execute();
    $sender_data = $sender_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sender_data) {
        $sender_data = [
            'nameteam' => 'FlowerShop Team',
            'address' => 'ยังไม่ได้ระบุที่อยู่',
            'tel' => 'ไม่ระบุ'
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
    0 => ['text' => 'รอแจ้งชำระเงิน', 'class' => 'status-awaiting', 'icon' => 'fa-clock', 'icon_color' => 'text-secondary'],
    1 => ['text' => 'การชำระเงินสำเร็จ', 'class' => 'status-paid', 'icon' => 'fa-check', 'icon_color' => 'text-success'],
    2 => ['text' => 'แก้ไขการชำระเงิน', 'class' => 'status-edited', 'icon' => 'fa-edit', 'icon_color' => 'text-warning'],
    3 => ['text' => 'กำลังจัดส่งสินค้า', 'class' => 'status-processing', 'icon' => 'fa-truck', 'icon_color' => 'text-warning'],
    4 => ['text' => 'คำสั่งซื้อสำเร็จ', 'class' => 'status-completed', 'icon' => 'fa-check-circle', 'icon_color' => 'text-success'],
    5 => ['text' => 'แนบสลิปใหม่', 'class' => 'status-new-slip', 'icon' => 'fa-upload', 'icon_color' => 'text-info'],
    6 => ['text' => 'ยกเลิกคำสั่งซื้อ', 'class' => 'status-cancel', 'icon' => 'fa-times-circle', 'icon_color' => 'text-danger']
];
$status = isset($statusOptions[$order['Status']]) ? $order['Status'] : 0;

// Prepare refund data
$refundStatus = 'รอการโอนเงินคืน';
$refundClass = 'status-not-refunded';
$cancelReason = '';
$refundMessage = '';
if (is_string($order['Message']) && !empty($order['Message'])) {
    $refundStatus = strpos($order['Message'], '//RefundedByAdmin') !== false ? 'โอนเงินคืนแล้ว' : 'รอการโอนเงินคืน';
    $refundClass = strpos($order['Message'], '//RefundedByAdmin') !== false ? 'status-refunded' : 'status-not-refunded';
    $messageParts = explode('//RefundedByAdmin', $order['Message']);
    $cancelReason = trim($messageParts[0]);
    $refundMessage = isset($messageParts[1]) ? trim($messageParts[1]) : '';
}

// Check if slip image file exists
$imagePath = !empty($order['Image']) ? 'uploads/slips/' . htmlspecialchars($order['Image'], ENT_QUOTES, 'UTF-8') : '';
$slipImage = $imagePath && file_exists($imagePath) ? $imagePath : 'assets/img/Image-not-found.png';
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
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
        .refund-details,
        .order-summary {
            border-left: 4px solid #4e73df;
            padding-left: 10px;
            margin-bottom: 1.5rem;
        }

        .cancel-details h4,
        .payment-details h4,
        .refund-details h4,
        .order-summary h4 {
            color: #4e73df;
        }

        .order-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        .order-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 1rem;
            border: 1px solid rgba(232, 67, 147, 0.2);
        }

        .order-item div {
            flex: 1;
        }

        .order-item h5 {
            font-size: 1.2rem;
            margin: 0;
            color: #333;
        }

        .order-item p {
            margin: 0.3rem 0;
            color: #666;
        }

        .address-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-verified {
            background-color: #28a745;
            color: #fff;
        }

        .status-not-verified {
            background-color: #dc3545;
            color: #fff;
        }

        .status-incorrect {
            background-color: #ffc107;
            color: #000;
        }

        .status-incorrect-text {
            color: #dc3545;
            font-size: 0.9rem;
            margin-top: 0.5rem;
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
                <i class="fas <?php echo htmlspecialchars($statusOptions[$status]['icon'], ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($statusOptions[$status]['icon_color'], ENT_QUOTES, 'UTF-8'); ?>"></i>
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
                    <span>วันที่สั่งซื้อ</span>
                    <span><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                </div>
                <div class="order-summary-item">
                    <span>วันที่จัดส่ง</span>
                    <span><?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></span>
                </div>
                <?php if ($order['Status'] == 6): ?>
                    <div class="order-summary-item">
                        <span>วันที่ยกเลิก</span>
                        <span><?php echo $order['LastupdateDate'] ? date('d/m/Y H:i', strtotime($order['LastupdateDate'])) : 'ไม่ระบุ'; ?></span>
                    </div>
                <?php endif; ?>
                <h5>รายการสินค้า</h5>
                <?php foreach ($items as $item): ?>
                    <div class="order-item">
                        <img src="<?php echo !empty($item['image']) && file_exists("admin/uploads/flowers/" . $item['image'])
                                        ? "admin/uploads/flowers/" . htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8')
                                        : "assets/img/default-flower.jpg"; ?>" 
                             alt="<?php echo htmlspecialchars($item['flower_name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div>
                            <h5><?php echo htmlspecialchars($item['flower_name'] ?? 'ไม่ระบุ', ENT_QUOTES, 'UTF-8'); ?></h5>
                            <p>จำนวน: <?php echo htmlspecialchars($item['Quantity'], ENT_QUOTES, 'UTF-8'); ?> ชิ้น</p>
                            <p>ราคาต่อหน่วย: ฿<?php echo number_format($item['Price'], 2); ?></p>
                            <p>ราคารวม: ฿<?php echo number_format($item['Price'] * $item['Quantity'], 2); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="order-summary-item">
                    <span><strong>ยอดรวมทั้งหมด</strong></span>
                    <span><strong>฿<?php echo number_format($order['SumTotal'], 2); ?></strong></span>
                </div>
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
                    <span><?php echo htmlspecialchars($user_data['Address'] ?: 'ยังไม่ได้ระบุที่อยู่', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="order-details-item">
                    <span>โทรศัพท์</span>
                    <span><?php echo htmlspecialchars($user_data['ContactNo'] ?: 'ไม่ระบุ', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php
                $addressStatus = '';
                $statusClass = '';
                $reasonText = '';
                $iconClass = '';
                if (empty($user_data['Address'])) {
                    $addressStatus = 'ยังไม่มีข้อมูล';
                    $statusClass = 'status-not-verified';
                    $iconClass = 'fa-clock';
                } elseif ($user_data['Validate'] === 'ที่อยู่ถูกต้อง') {
                    $addressStatus = 'ที่อยู่ได้รับการยืนยัน';
                    $statusClass = 'status-verified';
                    $iconClass = 'fa-check-circle';
                } elseif (!empty($user_data['Validate']) && $user_data['Validate'] !== 'ยังไม่ยืนยัน') {
                    $addressStatus = 'ที่อยู่ไม่ผ่านการตรวจสอบ';
                    $statusClass = 'status-incorrect';
                    $reasonText = "เหตุผล: " . htmlspecialchars($user_data['Validate'], ENT_QUOTES, 'UTF-8');
                    $iconClass = 'fa-times-circle';
                } else {
                    $addressStatus = 'รอการตรวจสอบ';
                    $statusClass = 'status-not-verified';
                    $iconClass = 'fa-clock';
                }
                ?>
                <div class="address-status <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas <?php echo htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($addressStatus, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php if ($statusClass === 'status-incorrect'): ?>
                    <div class="status-incorrect-text"><?php echo $reasonText; ?></div>
                <?php endif; ?>

                <h4>ข้อมูลผู้ส่ง</h4>
                <div class="order-details-item">
                    <span>ชื่อ</span>
                    <span><?php echo htmlspecialchars($sender_data['nameteam'] ?: 'FlowerShop Team', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="order-details-item">
                    <span>ที่อยู่</span>
                    <span><?php echo htmlspecialchars($sender_data['address'] ?: 'ยังไม่ได้ระบุที่อยู่', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="order-details-item">
                    <span>โทรศัพท์</span>
                    <span><?php echo htmlspecialchars($sender_data['tel'] ?: 'ไม่ระบุ', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>

            <!-- Payment Details -->
            <!-- <div class="payment-details">
                <h4>รายการชำระเงิน</h4>
                <div class="payment-slip">
                    <span>สลิปการชำระเงิน</span>
                    <img src="<?php echo $slipImage; ?>" alt="Payment Slip">
                </div>
            </div> -->

            <!-- Refund and Cancel Details -->
            <?php if ($order['Status'] == 6): ?>
                <div class="cancel-details">
                    <h4>ข้อมูลการยกเลิกและการคืนเงิน</h4>
                    <div class="order-details-item">
                        <span>สถานะการคืนเงิน</span>
                        <span class="refund-text <?php echo htmlspecialchars($refundClass, ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fas fa-<?php echo $refundStatus === 'โอนเงินคืนแล้ว' ? 'check-circle' : 'times-circle'; ?> me-1"></i>
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

                <?php if ($refundStatus === 'โอนเงินคืนแล้ว'): ?>
                    <div class="refund-details">
                        <h4>รายละเอียดการคืนเงิน</h4>
                        <div class="payment-slip">
                            <span>สลิปการคืนเงิน</span>
                            <img src="<?php echo $slipImage; ?>" alt="Refund Slip">
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
    <?php include("includes/footer.php"); ?>
    <!-- footer ends -->

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Sweetalert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
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