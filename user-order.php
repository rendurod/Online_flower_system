<?php
session_start();
include('config/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_login'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_login'];
$message = '';
$messageType = '';

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $reason = trim(htmlspecialchars($_POST['reason'] ?? '', ENT_QUOTES, 'UTF-8'));
    $accountName = trim(htmlspecialchars($_POST['account_name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $accountNumber = trim(htmlspecialchars($_POST['account_number'] ?? '', ENT_QUOTES, 'UTF-8'));

    // If "อื่น ๆ" is selected, use the custom bank name
    if (isset($_POST['bank_select']) && $_POST['bank_select'] === 'อื่น ๆ') {
        $accountName = trim(htmlspecialchars($_POST['custom_bank'] ?? '', ENT_QUOTES, 'UTF-8'));
    }

    // Fetch user email
    try {
        $stmt = $conn->prepare("SELECT EmailId FROM tbl_members WHERE ID = :id");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $userEmail = $user['EmailId'] ?? '';

        if (!$userEmail) {
            $message = "ไม่พบข้อมูลผู้ใช้";
            $messageType = "danger";
        } elseif ($orderId && $reason && $accountName && $accountNumber) {
            try {
                $stmt = $conn->prepare("
                    UPDATE tbl_orders 
                    SET Status = 6, Message = :reason, AccountName = :account_name, AccountNumber = :account_number 
                    WHERE ID = :order_id AND UserEmail = :email AND Status IN (0, 1, 2, 3, 5)
                ");
                $stmt->bindValue(':reason', $reason, PDO::PARAM_STR);
                $stmt->bindValue(':account_name', $accountName, PDO::PARAM_STR);
                $stmt->bindValue(':account_number', $accountNumber, PDO::PARAM_STR);
                $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $message = "ยกเลิกคำสั่งซื้อสำเร็จ";
                    $messageType = "success";
                } else {
                    $message = "ไม่สามารถยกเลิกคำสั่งซื้อได้ อาจเนื่องจากสถานะไม่ถูกต้องหรือคำสั่งซื้อไม่พบ";
                    $messageType = "danger";
                }
            } catch (PDOException $e) {
                $message = "เกิดข้อผิดพลาดในการยกเลิกคำสั่งซื้อ: " . htmlspecialchars($e->getMessage());
                $messageType = "danger";
            }
        } else {
            $message = "กรุณาระบุเหตุผลในการยกเลิก, ชื่อบัญชี และเลขที่บัญชี";
            $messageType = "danger";
        }
    } catch (PDOException $e) {
        $message = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: " . htmlspecialchars($e->getMessage());
        $messageType = "danger";
    }
}

// Fetch user orders with flower details
$orders = [];
try {
    $stmt = $conn->prepare("SELECT EmailId FROM tbl_members WHERE ID = :id");
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userEmail = $user['EmailId'] ?? '';

    if ($userEmail) {
        try {
            $stmt = $conn->prepare("
                SELECT o.*, f.flower_name, f.price, f.image
                FROM tbl_orders o 
                LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID 
                WHERE o.UserEmail = :email 
                ORDER BY o.PostingDate DESC
            ");
            $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $message = "เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: " . htmlspecialchars($e->getMessage());
            $messageType = "danger";
        }
    } else {
        $message = "ไม่พบข้อมูลผู้ใช้";
        $messageType = "danger";
    }
} catch (PDOException $e) {
    $message = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: " . htmlspecialchars($e->getMessage());
    $messageType = "danger";
}

// Calculate order counts for each tab
$processingCount = count(array_filter($orders, function ($order) {
    return in_array($order['Status'], [0, 2, 5]);
}));
$paidCount = count(array_filter($orders, function ($order) {
    return $order['Status'] == 1;
}));
$shippingCount = count(array_filter($orders, function ($order) {
    return $order['Status'] == 3;
}));
$completedCount = count(array_filter($orders, function ($order) {
    return $order['Status'] == 4;
}));
$cancelledCount = count(array_filter($orders, function ($order) {
    return $order['Status'] == 6;
}));
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการสั่งซื้อ - Flower Shop</title>
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
    <link rel="stylesheet" href="assets/css/user-profile.css">
    <link rel="stylesheet" href="assets/css/userOrder.css">
</head>

<body class="profile">
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <div class="profile-container">
        <div class="profile-card">
            <!-- Navigation Tabs (Profile and Orders) -->
            <div class="nav-tabs">
                <div class="nav-item">
                    <a class="nav-link" href="user-profile.php">โปรไฟล์ส่วนตัว</a>
                </div>
                <div class="nav-item">
                    <a class="nav-link active" href="user-order.php">ประวัติการสั่งซื้อ</a>
                </div>
            </div>

            <!-- Order Status Tabs -->
            <div class="status-tabs">
                <div class="status-tab-item">
                    <a class="status-tab-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'processing' ? 'active' : ''); ?>" href="user-order.php?tab=processing">
                        <i class="fas fa-clock me-1"></i> ดำเนินการ
                        <?php if ($processingCount > 0): ?>
                            <span class="badge-count"><?php echo $processingCount; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="status-tab-item">
                    <a class="status-tab-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'paid' ? 'active' : ''); ?>" href="user-order.php?tab=paid">
                        <i class="fas fa-check me-1"></i> ชำระเงินสำเร็จ
                        <?php if ($paidCount > 0): ?>
                            <span class="badge-count"><?php echo $paidCount; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="status-tab-item">
                    <a class="status-tab-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'shipping' ? 'active' : ''); ?>" href="user-order.php?tab=shipping">
                        <i class="fas fa-truck me-1"></i> กำลังจัดส่งสินค้า
                        <?php if ($shippingCount > 0): ?>
                            <span class="badge-count"><?php echo $shippingCount; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="status-tab-item">
                    <a class="status-tab-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'completed' ? 'active' : ''); ?>" href="user-order.php?tab=completed">
                        <i class="fas fa-check-circle me-1"></i> จัดส่งสำเร็จ
                        <?php if ($completedCount > 0): ?>
                            <span class="badge-count"><?php echo $completedCount; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="status-tab-item">
                    <a class="status-tab-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'cancelled' ? 'active' : ''); ?>" href="user-order.php?tab=cancelled">
                        <i class="fas fa-times-circle the-1"></i> ยกเลิกคำสั่งซื้อ
                        <?php if ($cancelledCount > 0): ?>
                            <span class="badge-count"><?php echo $cancelledCount; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <!-- Loader -->
            <div class="loader" id="loader" style="display: none;">
                <i class="fas fa-spinner fa-spin"></i> กำลังโหลด...
            </div>

            <!-- Order Content -->
            <div class="tab-content">
                <!-- Toast Notification -->
                <?php if (!empty($message)): ?>
                    <div class="toast-container position-fixed top-0 end-0 p-3">
                        <div id="messageToast" class="toast align-items-center text-white bg-<?php echo $messageType; ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                                    <?php echo htmlspecialchars($message); ?>
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Processing Tab (Status 0, 1, 2, 5) -->
                <div class="tab-pane <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'processing' ? 'active' : (!isset($_GET['tab']) ? 'active' : '')); ?>" id="processing">
                    <h4 class="tab-title">ดำเนินการ</h4>
                    <?php if ($orders): ?>
                        <?php $processingOrders = array_filter($orders, function ($order) {
                            return in_array($order['Status'], [0, 2, 5]);
                        }); ?>
                        <?php if (!empty($processingOrders)): ?>
                            <?php foreach ($processingOrders as $order): ?>
                                <div class="order-item <?php echo in_array($order['Status'], [2, 5]) ? 'urgent' : ''; ?>">
                                    <div class="order-image">
                                        <img src="<?php echo !empty($order['image']) && is_file("admin/uploads/flowers/" . $order['image']) ? "admin/uploads/flowers/" . htmlspecialchars($order['image']) : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($order['flower_name']); ?>">
                                    </div>
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span class="order-number">Order #<?php echo htmlspecialchars($order['BookingNumber']); ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                            <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</p>
                                            <p><strong>ราคารวม:</strong> <?php echo number_format($order['Quantity'] * ($order['price'] ?? 0), 2); ?> บาท</p>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong>
                                                <span class="status-label <?php echo $order['Status'] == 0 ? 'status-awaiting' : ($order['Status'] == 1 ? 'status-paid' : ($order['Status'] == 2 ? 'status-edited' : 'status-new-slip')); ?>">
                                                    <i class="fas <?php echo $order['Status'] == 0 ? 'fa-clock' : ($order['Status'] == 1 ? 'fa-check' : ($order['Status'] == 2 ? 'fa-edit' : 'fa-upload')); ?> me-1"></i>
                                                    <?php
                                                    $statusText = [
                                                        0 => 'รอแจ้งชำระเงิน',
                                                        1 => 'ชำระเงินสำเร็จ',
                                                        2 => 'แก้ไขการชำระเงิน',
                                                        5 => 'แนบสลิปใหม่'
                                                    ];
                                                    echo $statusText[$order['Status']] ?? 'ไม่ระบุ';
                                                    ?>
                                                </span>
                                            </p>
                                            <?php if (!empty($order['Message']) && in_array($order['Status'], [2, 5])): ?>
                                                <p class="message-admin"><strong>ข้อความจากแอดมิน:</strong> <?php echo htmlspecialchars($order['Message']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($order['Status'] == 2): ?>
                                                <a href="user-slip.php?order_id=<?php echo htmlspecialchars($order['ID']); ?>" class="btn-details">
                                                    <i class="fas fa-upload me-1"></i>อัปโหลดสลิปใหม่
                                                </a>
                                            <?php endif; ?>
                                            <button class="btn-cancel" data-bs-toggle="modal" data-bs-target="#cancelModal" data-order-id="<?php echo htmlspecialchars($order['ID']); ?>" data-booking-number="<?php echo htmlspecialchars($order['BookingNumber']); ?>">ยกเลิกคำสั่งซื้อ</button>
                                            
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-alert">ไม่มีคำสั่งซื้อที่อยู่ในขั้นตอนดำเนินการ</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data-alert">ไม่มีคำสั่งซื้อที่อยู่ในขั้นตอนดำเนินการ</div>
                    <?php endif; ?>
                </div>

                <!-- Paid Tab (Status 1) -->
                <div class="tab-pane <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'paid' ? 'active' : ''); ?>" id="paid">
                    <h4 class="tab-title">ชำระเงินสำเร็จ</h4>
                    <?php if ($orders): ?>
                        <?php $paidOrders = array_filter($orders, function ($order) {
                            return $order['Status'] == 1;
                        }); ?>
                        <?php if (!empty($paidOrders)): ?>
                            <?php foreach ($paidOrders as $order): ?>
                                <div class="order-item">
                                    <div class="order-image">
                                        <img src="<?php echo !empty($order['image']) && is_file("admin/uploads/flowers/" . $order['image']) ? "admin/uploads/flowers/" . htmlspecialchars($order['image']) : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($order['flower_name']); ?>">
                                    </div>
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span class="order-number">Order #<?php echo htmlspecialchars($order['BookingNumber']); ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                            <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</p>
                                            <p><strong>ราคารวม:</strong> <?php echo number_format($order['Quantity'] * ($order['price'] ?? 0), 2); ?> บาท</p>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong> <span class="status-label status-paid"><i class="fas fa-check me-1"></i>ชำระเงินสำเร็จ</span></p>
                                            <div class="order-details-footer">
                                                <a href="user-order-detail.php?order_id=<?php echo htmlspecialchars($order['ID']); ?>" class="btn-details"><i class="fas fa-info-circle me-1"></i>ดูรายละเอียด</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-alert">ไม่มีคำสั่งซื้อที่ชำระเงินสำเร็จ</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data-alert">ไม่มีคำสั่งซื้อที่ชำระเงินสำเร็จ</div>
                    <?php endif; ?>
                </div>

                <!-- Shipping Tab (Status 3) -->
                <div class="tab-pane <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'shipping' ? 'active' : ''); ?>" id="shipping">
                    <h4 class="tab-title">กำลังจัดส่งสินค้า</h4>
                    <?php if ($orders): ?>
                        <?php $shippingOrders = array_filter($orders, function ($order) {
                            return $order['Status'] == 3;
                        }); ?>
                        <?php if (!empty($shippingOrders)): ?>
                            <?php foreach ($shippingOrders as $order): ?>
                                <div class="order-item">
                                    <div class="order-image">
                                        <img src="<?php echo !empty($order['image']) && is_file("admin/uploads/flowers/" . $order['image']) ? "admin/uploads/flowers/" . htmlspecialchars($order['image']) : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($order['flower_name']); ?>">
                                    </div>
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span class="order-number">Order #<?php echo htmlspecialchars($order['BookingNumber']); ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                            <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</p>
                                            <p><strong>ราคารวม:</strong> <?php echo number_format($order['Quantity'] * ($order['price'] ?? 0), 2); ?> บาท</p>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong> <span class="status-label status-processing"><i class="fas fa-truck me-1"></i>กำลังจัดส่งสินค้า</span></p>
                                            <div class="order-details-footer">
                                                <a href="user-order-detail.php?order_id=<?php echo htmlspecialchars($order['ID']); ?>" class="btn-details"><i class="fas fa-info-circle me-1"></i>ดูรายละเอียด</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-alert">ไม่มีคำสั่งซื้อที่กำลังจัดส่ง</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data-alert">ไม่มีคำสั่งซื้อที่กำลังจัดส่ง</div>
                    <?php endif; ?>
                </div>

                <!-- Completed Tab (Status 4) -->
                <div class="tab-pane <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'completed' ? 'active' : ''); ?>" id="completed">
                    <h4 class="tab-title">จัดส่งสำเร็จ</h4>
                    <?php if ($orders): ?>
                        <?php $completedOrders = array_filter($orders, function ($order) {
                            return $order['Status'] == 4;
                        }); ?>
                        <?php if (!empty($completedOrders)): ?>
                            <?php foreach ($completedOrders as $order): ?>
                                <div class="order-item">
                                    <div class="order-image">
                                        <img src="<?php echo !empty($order['image']) && is_file("admin/uploads/flowers/" . $order['image']) ? "admin/uploads/flowers/" . htmlspecialchars($order['image']) : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($order['flower_name']); ?>">
                                    </div>
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span class="order-number">Order #<?php echo htmlspecialchars($order['BookingNumber']); ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                            <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</p>
                                            <p><strong>ราคารวม:</strong> <?php echo number_format($order['Quantity'] * ($order['price'] ?? 0), 2); ?> บาท</p>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong> <span class="status-label status-completed"><i class="fas fa-check-circle me-1"></i>จัดส่งสำเร็จ</span></p>
                                            <div class="order-details-footer">
                                                <a href="user-order-detail.php?order_id=<?php echo htmlspecialchars($order['ID']); ?>" class="btn-details"><i class="fas fa-info-circle me-1"></i>ดูรายละเอียด</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-alert">ไม่มีคำสั่งซื้อที่จัดส่งสำเร็จ</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data-alert">ไม่มีคำสั่งซื้อที่จัดส่งสำเร็จ</div>
                    <?php endif; ?>
                </div>

                <!-- Cancelled Tab (Status 6) -->
                <div class="tab-pane <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'cancelled' ? 'active' : ''); ?>" id="cancelled">
                    <h4 class="tab-title">รายการยกเลิกสำเร็จ</h4>
                    <?php if ($orders): ?>
                        <?php $cancelledOrders = array_filter($orders, function ($order) {
                            return $order['Status'] == 6;
                        }); ?>
                        <?php if (!empty($cancelledOrders)): ?>
                            <?php foreach ($cancelledOrders as $order): ?>
                                <div class="order-item">
                                    <div class="order-image">
                                        <img src="<?php echo !empty($order['image']) && is_file("admin/uploads/flowers/" . $order['image']) ? "admin/uploads/flowers/" . htmlspecialchars($order['image']) : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($order['flower_name']); ?>">
                                    </div>
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span class="order-number">Order #<?php echo htmlspecialchars($order['BookingNumber']); ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                            <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</p>
                                            <p><strong>ราคารวม:</strong> <?php echo number_format($order['Quantity'] * ($order['price'] ?? 0), 2); ?> บาท</p>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong> <span class="status-label status-cancelled"><i class="fas fa-times-circle me-1"></i>ยกเลิกคำสั่งซื้อ</span></p>
                                            <?php if (!empty($order['Message'])): ?>
                                                <?php
                                                $messageParts = explode('//RefundedByAdmin', $order['Message']);
                                                $cancelReason = trim($messageParts[0]);
                                                $refundMessage = isset($messageParts[1]) ? trim($messageParts[1]) : '';
                                                ?>
                                                <p class="message-admin"><strong>เหตุผลการยกเลิก:</strong> <?php echo htmlspecialchars($cancelReason ?: 'ไม่ระบุ'); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($order['AccountName']) && !empty($order['AccountNumber'])): ?>
                                                <p><strong>ชื่อบัญชี:</strong> <?php echo htmlspecialchars($order['AccountName']); ?></p>
                                                <p><strong>เลขที่บัญชี:</strong> <?php echo htmlspecialchars($order['AccountNumber']); ?></p>
                                            <?php endif; ?>
                                            <p><strong>สถานะการคืนเงิน:</strong>
                                                <span class="refund-status <?php echo strpos($order['Message'], '//RefundedByAdmin') !== false ? 'status-refunded' : 'status-not-refunded'; ?>">
                                                    <i class="fas fa-<?php echo strpos($order['Message'], '//RefundedByAdmin') !== false ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                                    <?php echo strpos($order['Message'], '//RefundedByAdmin') !== false ? 'โอนเงินคืนแล้ว' : 'รอการโอนเงินคืน'; ?>
                                                </span>
                                            </p>
                                            <div class="order-details-footer">
                                                <a href="user-order-detail.php?order_id=<?php echo htmlspecialchars($order['ID']); ?>" class="btn-details"><i class="fas fa-info-circle me-1"></i>ดูรายละเอียด</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-alert">ไม่มีคำสั่งซื้อที่ถูกยกเลิก</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data-alert">ไม่มีคำสั่งซื้อที่ถูกยกเลิก</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Order Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelModalLabel">ยกเลิกคำสั่งซื้อ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="cancelForm">
                    <div class="modal-body">
                        <input type="hidden" name="cancel_order" value="1">
                        <input type="hidden" name="order_id" id="cancelOrderId">
                        <div class="mb-3">
                            <label for="reason" class="form-label">เหตุผลในการยกเลิก <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="4" placeholder="กรุณาระบุเหตุผลในการยกเลิก" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="bank_select" class="form-label">ชื่อธนาคาร <span class="text-danger">*</span></label>
                            <select class="form-control" id="bank_select" name="account_name" required>
                                <option value="" disabled selected>เลือกธนาคาร</option>
                                <option value="ธนาคารกรุงเทพ (BBL)">ธนาคารกรุงเทพ (BBL)</option>
                                <option value="ธนาคารกสิกรไทย (KBank)">ธนาคารกสิกรไทย (KBank)</option>
                                <option value="ธนาคารกรุงไทย (KTB)">ธนาคารกรุงไทย (KTB)</option>
                                <option value="ธนาคารไทยพานิชย์ (SCB)">ธนาคารไทยพาณิชย์ (SCB)</option>
                                <option value="ธนาคารกรุงศรีอยุธยา (Krungsri / BAY)">ธนาคารกรุงศรีอยุธยา (Krungsri / BAY)</option>
                                <option value="ธนาคารทหารไทยธนชาต (TTB)">ธนาคารทหารไทยธนชาต (TTB)</option>
                                <option value="ธนาคารออมสิน (GSB)">ธนาคารออมสิน (GSB)</option>
                                <option value="ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร (BAAC)">ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร (BAAC)</option>
                                <option value="อื่น ๆ">อื่น ๆ</option>
                            </select>
                        </div>
                        <div class="mb-3" id="custom_bank_container">
                            <label for="custom_bank" class="form-label">ชื่อธนาคารอื่น ๆ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="custom_bank" name="custom_bank" placeholder="กรุณาระบุชื่อธนาคาร">
                        </div>
                        <div class="mb-3">
                            <label for="account_number" class="form-label">เลขที่บัญชี <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="account_number" name="account_number" placeholder="กรุณาระบุเลขที่บัญชี" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-danger">ยืนยันการยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script>
        // Smooth tab transition for order status tabs
        document.querySelectorAll('.status-tabs .status-tab-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.status-tabs .status-tab-link').forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
                document.getElementById(this.getAttribute('href').split('?tab=')[1]).classList.add('active');
            });
        });

        // Initialize active tab based on URL parameter
        document.addEventListener('DOMContentLoaded', function() {
            const loader = document.getElementById('loader');
            loader.style.display = 'block';
            setTimeout(() => {
                loader.style.display = 'none';
                const tab = '<?php echo isset($_GET['tab']) ? htmlspecialchars($_GET['tab']) : 'processing'; ?>';
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
                document.getElementById(tab).classList.add('active');
                document.querySelectorAll('.status-tab-link').forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href').includes(tab)) {
                        link.classList.add('active');
                    }
                });
                // Show toast if there is a message
                const toastEl = document.getElementById('messageToast');
                if (toastEl) {
                    const toast = new bootstrap.Toast(toastEl);
                    toast.show();
                }
            }, 500); // Simulate loading for 0.5 seconds
        });

        // Function to set order ID in modal
        document.querySelectorAll('.btn-cancel').forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                const bookingNumber = this.getAttribute('data-booking-number');
                document.getElementById('cancelOrderId').value = orderId;
                document.getElementById('cancelModalLabel').textContent = 'ยกเลิกคำสั่งซื้อ #' + bookingNumber;
                document.getElementById('bank_select').value = '';
                document.getElementById('custom_bank_container').style.display = 'none';
                document.getElementById('custom_bank').value = '';
            });
        });

        // Show/hide custom bank input based on dropdown selection
        document.getElementById('bank_select').addEventListener('change', function() {
            const customBankContainer = document.getElementById('custom_bank_container');
            const customBankInput = document.getElementById('custom_bank');
            if (this.value === 'อื่น ๆ') {
                customBankContainer.style.display = 'block';
                customBankInput.setAttribute('required', 'required');
            } else {
                customBankContainer.style.display = 'none';
                customBankInput.removeAttribute('required');
            }
        });
    </script>
</body>

</html>