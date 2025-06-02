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
        } elseif ($orderId && $reason) {
            try {
                $stmt = $conn->prepare("
                    UPDATE tbl_orders 
                    SET Status = 6, Message = :reason 
                    WHERE ID = :order_id AND UserEmail = :email AND Status IN (0, 1, 2, 3, 5)
                ");
                $stmt->bindValue(':reason', $reason, PDO::PARAM_STR);
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
            $message = "กรุณาระบุเหตุผลในการยกเลิก";
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
    <style>
        .status-cancelled {
            background-color: #dc3545;
        }

        .btn-cancel {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            margin-top: 0.5rem;
            border-radius: 50px;
            /* เพิ่มความโค้งมน */
            transition: all 0.3s ease;
            font-size: 0.9rem;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }

        .btn-cancel:hover {
            background-color: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(220, 53, 69, 0.3);
        }

        .btn-cancel:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }
    </style>
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
                    <a class="status-tab-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'awaiting' ? 'active' : ''); ?>" href="user-order.php?tab=awaiting">
                        <i class="fas fa-clock me-1"></i> รอแจ้งชำระเงิน
                    </a>
                </div>
                <div class="status-tab-item">
                    <a class="status-tab-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'edited' ? 'active' : ''); ?>" href="user-order.php?tab=edited">
                        <i class="fas fa-edit me-1"></i> แก้ไขการชำระเงิน
                    </a>
                </div>
                <div class="status-tab-item">
                    <a class="status-tab-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'tracking' ? 'active' : ''); ?>"
                        href="user-order.php?tab=tracking"
                        style="font-size: 1.25rem; white-space: nowrap;">
                        <i class="fas fa-truck me-1"></i> ชำระเงินสำเร็จ & กำลังดำเนินการ
                    </a>
                </div>
                <div class="status-tab-item">
                    <a class="status-tab-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'completed' ? 'active' : ''); ?>" href="user-order.php?tab=completed">
                        <i class="fas fa-check-circle me-1"></i> คำสั่งซื้อสำเร็จ
                    </a>
                </div>
                <div class="status-tab-item">
                    <a class="status-tab-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'cancelled' ? 'active' : ''); ?>" href="user-order.php?tab=cancelled">
                        <i class="fas fa-times-circle me-1"></i> ยกเลิกคำสั่งซื้อ
                    </a>
                </div>
            </div>

            <!-- Order Content -->
            <div class="tab-content">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                        <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- Awaiting Payment Tab -->
                <div class="tab-pane <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'awaiting' ? 'active' : ''); ?>" id="awaiting">
                    <h4 class="tab-title">รอแจ้งชำระเงิน</h4>
                    <?php if ($orders): ?>
                        <?php $awaitingOrders = array_filter($orders, function ($order) {
                            return $order['Status'] == 0;
                        }); ?>
                        <?php if (!empty($awaitingOrders)): ?>
                            <?php foreach ($awaitingOrders as $order): ?>
                                <div class="order-item">
                                    <div class="order-image">
                                        <img src="<?php echo !empty($order['image']) && file_exists("admin/uploads/flowers/" . $order['image']) ? "admin/uploads/flowers/" . htmlspecialchars($order['image']) : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($order['flower_name']); ?>">
                                    </div>
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span>Order #<?php echo htmlspecialchars($order['BookingNumber']); ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                            <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</p>
                                            <p><strong>ราคารวม:</strong> <?php echo number_format($order['Quantity'] * ($order['price'] ?? 0), 2); ?> บาท</p>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong> <span class="status-label status-awaiting"><i class="fas fa-clock me-1"></i>รอแจ้งชำระเงิน</span></p>
                                            <button class="btn-details" onclick="alert('ฟังก์ชันนี้อยู่ในระหว่างการพัฒนา')">ดูรายละเอียด</button>
                                            <button class="btn-cancel" onclick="cancelOrder(<?php echo htmlspecialchars($order['ID']); ?>, '<?php echo htmlspecialchars($order['BookingNumber']); ?>')">ยกเลิกคำสั่งซื้อ</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-alert">ไม่มีคำสั่งซื้อที่รอแจ้งชำระเงิน</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data-alert">ไม่มีคำสั่งซื้อที่รอแจ้งชำระเงิน</div>
                    <?php endif; ?>
                </div>

                <!-- Edited Payment Tab -->
                <div class="tab-pane <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'edited' ? 'active' : ''); ?>" id="edited">
                    <h4 class="tab-title">แก้ไขการชำระเงิน</h4>
                    <?php if ($orders): ?>
                        <?php $editedOrders = array_filter($orders, function ($order) {
                            return in_array($order['Status'], [2, 5]);
                        }); ?>
                        <?php if (!empty($editedOrders)): ?>
                            <?php foreach ($editedOrders as $order): ?>
                                <div class="order-item">
                                    <div class="order-image">
                                        <img src="<?php echo !empty($order['image']) && file_exists("admin/uploads/flowers/" . $order['image']) ? "admin/uploads/flowers/" . htmlspecialchars($order['image']) : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($order['flower_name']); ?>">
                                    </div>
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span>Order #<?php echo htmlspecialchars($order['BookingNumber']); ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                            <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</p>
                                            <p><strong>ราคารวม:</strong> <?php echo number_format($order['Quantity'] * ($order['price'] ?? 0), 2); ?> บาท</p>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong>
                                                <span class="status-label <?php echo $order['Status'] == 2 ? 'status-edited' : 'status-new-slip'; ?>">
                                                    <i class="fas <?php echo $order['Status'] == 2 ? 'fa-edit' : 'fa-upload'; ?> me-1"></i>
                                                    <?php echo $order['Status'] == 2 ? 'แก้ไขการชำระเงิน' : 'แนบสลิปใหม่'; ?>
                                                </span>
                                            </p>
                                            <?php if (!empty($order['Message'])): ?>
                                                <p class="message-admin"><strong>ข้อความจากแอดมิน:</strong> <?php echo htmlspecialchars($order['Message']); ?></p>
                                            <?php endif; ?>
                                            <a href="user-slip.php?order_id=<?php echo htmlspecialchars($order['ID']); ?>" class="btn-details">
                                                <i class="fas fa-upload me-1"></i>อัปโหลดสลิปใหม่
                                            </a>
                                            <button class="btn-cancel" onclick="cancelOrder(<?php echo htmlspecialchars($order['ID']); ?>, '<?php echo htmlspecialchars($order['BookingNumber']); ?>')">ยกเลิกคำสั่งซื้อ</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-alert">ไม่มีคำสั่งซื้อที่ต้องแก้ไขการชำระเงิน</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data-alert">ไม่มีคำสั่งซื้อที่ต้องแก้ไขการชำระเงิน</div>
                    <?php endif; ?>
                </div>

                <!-- Processing Tab -->
                <div class="tab-pane <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'tracking' ? 'active' : ''); ?>" id="tracking">
                    <h4 class="tab-title">ชำระเงินสำเร็จ & กำลังดำเนินการ</h4>
                    <?php if ($orders): ?>
                        <?php $trackingOrders = array_filter($orders, function ($order) {
                            return in_array($order['Status'], [1, 3]);
                        }); ?>
                        <?php if (!empty($trackingOrders)): ?>
                            <?php foreach ($trackingOrders as $order): ?>
                                <div class="order-item">
                                    <div class="order-image">
                                        <img src="<?php echo !empty($order['image']) && file_exists("admin/uploads/flowers/" . $order['image']) ? "admin/uploads/flowers/" . htmlspecialchars($order['image']) : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($order['flower_name']); ?>">
                                    </div>
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span>Order #<?php echo htmlspecialchars($order['BookingNumber']); ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                            <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</p>
                                            <p><strong>ราคารวม:</strong> <?php echo number_format($order['Quantity'] * ($order['price'] ?? 0), 2); ?> บาท</p>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong>
                                                <span class="status-label <?php echo $order['Status'] == 1 ? 'status-paid' : 'status-processing'; ?>">
                                                    <i class="fas <?php echo $order['Status'] == 1 ? 'fa-check' : 'fa-truck'; ?> me-1"></i>
                                                    <?php echo $order['Status'] == 1 ? 'การชำระเงินสำเร็จ' : 'กำลังดำเนินการ'; ?>
                                                </span>
                                            </p>
                                            <button class="btn-details" onclick="alert('ฟังก์ชันนี้อยู่ในระหว่างการพัฒนา')">ดูรายละเอียด</button>
                                            <button class="btn-cancel" onclick="cancelOrder(<?php echo htmlspecialchars($order['ID']); ?>, '<?php echo htmlspecialchars($order['BookingNumber']); ?>')">ยกเลิกคำสั่งซื้อ</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-alert">ไม่มีคำสั่งซื้อที่กำลังดำเนินการ</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data-alert">ไม่มีคำสั่งซื้อที่กำลังดำเนินการ</div>
                    <?php endif; ?>
                </div>

                <!-- Completed Tab -->
                <div class="tab-pane <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'completed' ? 'active' : ''); ?>" id="completed">
                    <h4 class="tab-title">คำสั่งซื้อสำเร็จ</h4>
                    <?php if ($orders): ?>
                        <?php $completedOrders = array_filter($orders, function ($order) {
                            return $order['Status'] == 4;
                        }); ?>
                        <?php if (!empty($completedOrders)): ?>
                            <?php foreach ($completedOrders as $order): ?>
                                <div class="order-item">
                                    <div class="order-image">
                                        <img src="<?php echo !empty($order['image']) && file_exists("admin/uploads/flowers/" . $order['image']) ? "admin/uploads/flowers/" . htmlspecialchars($order['image']) : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($order['flower_name']); ?>">
                                    </div>
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span>Order #<?php echo htmlspecialchars($order['BookingNumber']); ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                            <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</p>
                                            <p><strong>ราคารวม:</strong> <?php echo number_format($order['Quantity'] * ($order['price'] ?? 0), 2); ?> บาท</p>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong> <span class="status-label status-completed"><i class="fas fa-check-circle me-1"></i>คำสั่งซื้อสำเร็จ</span></p>
                                            <button class="btn-details" onclick="alert('ฟังก์ชันนี้อยู่ในระหว่างการพัฒนา')">ดูรายละเอียด</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data-alert">ไม่มีคำสั่งซื้อสำเร็จ</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data-alert">ไม่มีคำสั่งซื้อสำเร็จ</div>
                    <?php endif; ?>
                </div>

                <!-- Cancelled Tab -->
                <div class="tab-pane <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'cancelled' ? 'active' : ''); ?>" id="cancelled">
                    <h4 class="tab-title">ยกเลิกคำสั่งซื้อ</h4>
                    <?php if ($orders): ?>
                        <?php $cancelledOrders = array_filter($orders, function ($order) {
                            return $order['Status'] == 6;
                        }); ?>
                        <?php if (!empty($cancelledOrders)): ?>
                            <?php foreach ($cancelledOrders as $order): ?>
                                <div class="order-item">
                                    <div class="order-image">
                                        <img src="<?php echo !empty($order['image']) && file_exists("admin/uploads/flowers/" . $order['image']) ? "admin/uploads/flowers/" . htmlspecialchars($order['image']) : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($order['flower_name']); ?>">
                                    </div>
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span>Order #<?php echo htmlspecialchars($order['BookingNumber']); ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                            <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</p>
                                            <p><strong>ราคารวม:</strong> <?php echo number_format($order['Quantity'] * ($order['price'] ?? 0), 2); ?> บาท</p>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong> <span class="status-label status-cancelled"><i class="fas fa-times-circle me-1"></i>ยกเลิกคำสั่งซื้อ</span></p>
                                            <?php if (!empty($order['Message'])): ?>
                                                <p class="message-admin"><strong>เหตุผลการยกเลิก:</strong> <?php echo htmlspecialchars($order['Message']); ?></p>
                                            <?php endif; ?>
                                            <button class="btn-details" onclick="alert('ฟังก์ชันนี้อยู่ในระหว่างการพัฒนา')">ดูรายละเอียด</button>
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

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

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
            const tab = '<?php echo isset($_GET['tab']) ? $_GET['tab'] : 'completed'; ?>';
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            document.getElementById(tab).classList.add('active');
        });

        // Function to handle order cancellation
        function cancelOrder(orderId, bookingNumber) {
            Swal.fire({
                title: 'ยกเลิกคำสั่งซื้อ #' + bookingNumber,
                input: 'textarea',
                inputLabel: 'กรุณาระบุเหตุผลในการยกเลิก',
                inputPlaceholder: 'พิมพ์เหตุผลที่นี่...',
                showCancelButton: true,
                confirmButtonText: 'ยืนยันการยกเลิก',
                cancelButtonText: 'ปิด',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                inputValidator: (value) => {
                    if (!value) {
                        return 'กรุณาระบุเหตุผลในการยกเลิก!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    form.innerHTML = `
                        <input type="hidden" name="cancel_order" value="1">
                        <input type="hidden" name="order_id" value="${orderId}">
                        <input type="hidden" name="reason" value="${result.value}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>

</html>