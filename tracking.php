<?php
session_start();
include('config/db.php');

// Check database connection
if (!$conn) {
    $_SESSION['error'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
    header("Location: login.php");
    exit();
}

// Initialize variables
$bookingNumber = '';
$order = null;
$items = [];
$calculated_total = 0;
$is_multi_item = false;
$message = '';
$messageType = '';

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingNumber = trim(filter_input(INPUT_POST, 'booking_number', FILTER_SANITIZE_SPECIAL_CHARS));
    if (empty($bookingNumber)) {
        $message = "กรุณากรอกหมายเลขคำสั่งซื้อ";
        $messageType = 'error';
    } else {
        try {
            // Fetch order details
            $order_query = "SELECT BookingNumber, Quantity, DeliveryDate, Image, PostingDate, LastupdateDate, Status, Message, 
                            AccountName, AccountNumber, TotalAmount, FlowerId, UserEmail
                            FROM tbl_orders 
                            WHERE BookingNumber = :booking_number";
            $order_stmt = $conn->prepare($order_query);
            $order_stmt->bindValue(':booking_number', $bookingNumber, PDO::PARAM_STR);
            $order_stmt->execute();
            $order = $order_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $message = "ไม่พบคำสั่งซื้อสำหรับหมายเลข: " . htmlspecialchars($bookingNumber, ENT_QUOTES, 'UTF-8');
                $messageType = 'error';
            } else {
                // Fetch order items (single-item or multi-item)
                try {
                    // Check for multi-item order in tbl_order_details
                    $items_query = "SELECT od.FlowerId, od.Quantity, od.Price, f.flower_name, f.image 
                                    FROM tbl_order_details od 
                                    JOIN tbl_flowers f ON od.FlowerId = f.ID 
                                    WHERE od.OrderId = (SELECT ID FROM tbl_orders WHERE BookingNumber = :booking_number)";
                    $items_stmt = $conn->prepare($items_query);
                    $items_stmt->bindValue(':booking_number', $bookingNumber, PDO::PARAM_STR);
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

                    // Verify and update TotalAmount if necessary
                    if (abs($calculated_total - $order['TotalAmount']) > 0.01) {
                        try {
                            $update_query = "UPDATE tbl_orders SET TotalAmount = :total_amount 
                                            WHERE BookingNumber = :booking_number";
                            $update_stmt = $conn->prepare($update_query);
                            $update_stmt->bindValue(':total_amount', $calculated_total, PDO::PARAM_STR);
                            $update_stmt->bindValue(':booking_number', $bookingNumber, PDO::PARAM_STR);
                            $update_stmt->execute();
                            $order['TotalAmount'] = $calculated_total;
                        } catch (PDOException $e) {
                            $message = "เกิดข้อผิดพลาดในการอัปเดตยอดรวม: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                            $messageType = 'error';
                        }
                    }
                } catch (PDOException $e) {
                    $message = "เกิดข้อผิดพลาดในการดึงข้อมูลรายการสินค้า: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                    $messageType = 'error';
                }

                // Fetch recipient data from tbl_members
                try {
                    $user_query = "SELECT FirstName, LastName, ContactNo, Address 
                                   FROM tbl_members 
                                   WHERE EmailId = :email";
                    $user_stmt = $conn->prepare($user_query);
                    $user_stmt->bindValue(':email', $order['UserEmail'], PDO::PARAM_STR);
                    $user_stmt->execute();
                    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$user_data) {
                        $user_data = [
                            'FirstName' => 'ไม่ระบุ',
                            'LastName' => '',
                            'ContactNo' => 'ไม่ระบุ',
                            'Address' => 'ไม่ระบุ'
                        ];
                    }
                } catch (PDOException $e) {
                    $message = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้รับ: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                    $messageType = 'error';
                    $user_data = [
                        'FirstName' => 'ไม่ระบุ',
                        'LastName' => '',
                        'ContactNo' => 'ไม่ระบุ',
                        'Address' => 'ไม่ระบุ'
                    ];
                }

                // Fetch sender data from tbl_contact (latest record)
                try {
                    $sender_query = "SELECT nameteam, address, tel 
                                     FROM tbl_contact 
                                     ORDER BY creationDate DESC LIMIT 1";
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
                    $message = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ส่ง: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                    $messageType = 'error';
                }

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
            }
        } catch (PDOException $e) {
            $message = "เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $messageType = 'error';
        }
    }
}

// Check if slip image file exists
$slipImage = !empty($order['Image']) && file_exists('Uploads/slips/' . $order['Image'])
    ? 'Uploads/slips/' . htmlspecialchars($order['Image'], ENT_QUOTES, 'UTF-8')
    : 'assets/img/Image-not-found.png';
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตามสินค้า - FlowerShop</title>
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
    <style>
        :root {
            --primary-pink: #e84393;
            --dark-pink: #d63384;
            --text-dark: #333;
            --text-light: #666;
            --white: #fff;
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

        .tracking-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .tracking-form {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .tracking-form input {
            flex: 1;
            padding: 1rem;
            font-size: 1.6rem;
            border: 2px solid rgba(232, 67, 147, 0.2);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .tracking-form input:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(232, 67, 147, 0.1);
        }

        .tracking-form button {
            padding: 1rem 2rem;
            font-size: 1.6rem;
            background: var(--primary-pink);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .tracking-form button:hover {
            background: var(--dark-pink);
            transform: translateY(-1px);
        }

        .order-details {
            background: #f9f9f9;
            padding: 2rem;
            border-radius: var(--border-radius);
            border-left: 4px solid #4e73df;
        }

        .order-details h4 {
            font-size: 2rem;
            color: #4e73df;
            margin-bottom: 1.5rem;
        }

        .order-details p {
            font-size: 1.6rem;
            margin: 0.5rem 0;
            color: var(--text-light);
        }

        .order-details p strong {
            color: var(--text-dark);
        }

        .status-label {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 1.4rem;
            font-weight: 500;
            color: #fff;
            margin-top: 0.5rem;
        }

        .status-awaiting {
            background-color: #95a5a6;
        }

        .status-paid {
            background-color: #2ecc71;
        }

        .status-edited {
            background-color: #e74c3c;
        }

        .status-processing {
            background-color: #f1c40f;
        }

        .status-completed {
            background-color: #7bed9f;
        }

        .status-new-slip {
            background-color: #3498db;
        }

        .status-cancel {
            background-color: #e74c3c;
        }

        .status-refunded {
            background-color: #28a745;
        }

        .status-not-refunded {
            background-color: #dc3545;
        }

        .order-image img {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: var(--border-radius);
            border: 2px solid rgba(232, 67, 147, 0.2);
            margin-top: 1rem;
        }

        .message-admin {
            background-color: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
            font-size: 1.6rem;
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
            color: var(--text-dark);
        }

        .order-item p {
            margin: 0.3rem 0;
            color: var(--text-light);
        }

        .status-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .status-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }
    </style>
</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- Hero Section -->
    <section class="products-hero">
        <div class="container">
            <div class="text-center">
                <h1 class="heading mb-3">ติดตาม<span>สินค้า</span></h1>
                <p style="font-size: 1.8rem; color: var(--text-light); max-width: 600px; margin: 0 auto;">
                    ค้นพบความงามของดอกไม้สดใหม่ คัดสรรมาเป็นพิเศษเพื่อคุณ
                </p>
            </div>
        </div>
    </section>
    <!-- Hero Section Ends -->

    <!-- Tracking Section -->
    <section class="tracking-container">
        <h2 class="text-center mb-4" style="font-size: 2.4rem; color: var(--text-dark);">ค้นหาคำสั่งซื้อ</h2>
        <form method="POST" class="tracking-form">
            <input type="text" name="booking_number" placeholder="กรอกหมายเลขคำสั่งซื้อ" value="<?php echo htmlspecialchars($bookingNumber, ENT_QUOTES, 'UTF-8'); ?>" required>
            <button type="submit"><i class="fas fa-search me-2"></i>ค้นหา</button>
        </form>

        <?php if ($order): ?>
            <!-- Status Icon and Title -->
            <div class="text-center">
                <div class="status-icon">
                    <i class="fas <?php echo htmlspecialchars($statusOptions[$order['Status']]['icon'], ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($statusOptions[$order['Status']]['icon_color'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                </div>
                <h1 class="status-title"><?php echo htmlspecialchars($statusOptions[$order['Status']]['text'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="success-message">คำสั่งซื้อของคุณอยู่ในสถานะ: <span class="status-label <?php echo htmlspecialchars($statusOptions[$order['Status']]['class'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($statusOptions[$order['Status']]['text'], ENT_QUOTES, 'UTF-8'); ?>
                </span></p>
            </div>

            <!-- Order Details -->
            <div class="order-details">
                <h4>สรุปคำสั่งซื้อ</h4>
                <p><strong>หมายเลขคำสั่งซื้อ:</strong> <?php echo htmlspecialchars($order['BookingNumber'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>วันที่สั่งซื้อ:</strong> <?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></p>
                <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                <?php if ($order['Status'] == 6): ?>
                    <p><strong>วันที่ยกเลิก:</strong> <?php echo $order['LastupdateDate'] ? date('d/m/Y H:i', strtotime($order['LastupdateDate'])) : 'ไม่ระบุ'; ?></p>
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
                <p><strong>ยอดรวมทั้งหมด:</strong> ฿<?php echo number_format($order['TotalAmount'], 2); ?></p>
            </div>

            <!-- Shipping Details -->
            <div class="order-details">
                <h4>ข้อมูลผู้รับ</h4>
                <p><strong>ชื่อ:</strong> <?php echo htmlspecialchars($user_data['FirstName'] . ' ' . $user_data['LastName'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>ที่อยู่:</strong> <?php echo htmlspecialchars($user_data['Address'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>โทรศัพท์:</strong> <?php echo htmlspecialchars($user_data['ContactNo'], ENT_QUOTES, 'UTF-8'); ?></p>

                <h4>ข้อมูลผู้ส่ง</h4>
                <p><strong>ชื่อ:</strong> <?php echo htmlspecialchars($sender_data['nameteam'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>ที่อยู่:</strong> <?php echo htmlspecialchars($sender_data['address'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>โทรศัพท์:</strong> <?php echo htmlspecialchars($sender_data['tel'], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <!-- Payment Details -->
            <div class="order-details">
                <h4>รายการชำระเงิน</h4>
                <div class="order-image">
                    <p><strong>สลิปการชำระเงิน:</strong></p>
                    <img src="<?php echo $slipImage; ?>" alt="Payment Slip">
                </div>
            </div>

            <!-- Refund and Cancel Details -->
            <?php if ($order['Status'] == 6): ?>
                <div class="order-details">
                    <h4>ข้อมูลการยกเลิกและการคืนเงิน</h4>
                    <p>
                        <strong>สถานะการคืนเงิน:</strong> 
                        <span class="status-label <?php echo htmlspecialchars($refundClass, ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fas fa-<?php echo $refundStatus === 'โอนเงินคืนแล้ว' ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                            <?php echo htmlspecialchars($refundStatus, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </p>
                    <?php if (!empty($cancelReason)): ?>
                        <p><strong>เหตุผลการยกเลิก:</strong> <?php echo htmlspecialchars($cancelReason, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($order['AccountName'])): ?>
                        <p><strong>ชื่อบัญชี:</strong> <?php echo htmlspecialchars($order['AccountName'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($order['AccountNumber'])): ?>
                        <p><strong>เลขที่บัญชี:</strong> <?php echo htmlspecialchars($order['AccountNumber'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($refundStatus === 'โอนเงินคืนแล้ว'): ?>
                    <div class="order-details">
                        <h4>รายละเอียดการคืนเงิน</h4>
                        <div class="order-image">
                            <p><strong>สลิปการคืนเงิน:</strong></p>
                            <img src="<?php echo $slipImage; ?>" alt="Refund Slip">
                        </div>
                        <?php if (!empty($refundMessage)): ?>
                            <p><strong>ข้อความจากแอดมิน:</strong> <?php echo htmlspecialchars($refundMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <!-- Tracking Section Ends -->

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends -->

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (!empty($message)): ?>
            Swal.fire({
                icon: '<?php echo $messageType; ?>',
                title: '<?php echo $messageType == "error" ? "ข้อผิดพลาด" : "สำเร็จ"; ?>',
                text: '<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>',
                timer: 3000,
                showConfirmButton: false
            });
        <?php endif; ?>
    </script>
</body>

</html>