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

// Fetch user email
try {
    $stmt = $conn->prepare("SELECT EmailId FROM tbl_members WHERE ID = :id");
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userEmail = $user['EmailId'] ?? '';
} catch (PDOException $e) {
    $message = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: " . htmlspecialchars($e->getMessage());
    $messageType = "danger";
    $userEmail = '';
}

// Fetch user orders with flower details
$orders = [];
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
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .profile-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 2rem;
        }

        .nav-tabs {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #e8e8e8;
        }

        .nav-item {
            flex: 1;
            text-align: center;
        }

        .nav-link {
            display: block;
            padding: 1rem;
            font-size: 1.6rem;
            color: var(--text-dark);
            text-decoration: none;
            transition: var(--transition);
        }

        .nav-link:hover {
            color: var(--primary-pink);
        }

        .nav-link.active {
            color: var(--primary-pink);
            border-bottom: 3px solid var(--primary-pink);
            font-weight: 500;
        }

        .status-tabs {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #e8e8e8;
            margin-top: 1rem;
        }

        .status-tab-item {
            flex: 1;
            text-align: center;
        }

        .status-tab-link {
            display: block;
            padding: 0.8rem;
            font-size: 1.4rem;
            color: var(--text-dark);
            text-decoration: none;
            transition: var(--transition);
        }

        .tab-content {
            margin-top: 2rem;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        .tab-title {
            font-size: 1.8rem;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
        }

        .order-item {
            display: flex;
            align-items: center;
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.05);
        }

        .order-image {
            flex: 0 0 100px;
            margin-right: 1.5rem;
        }

        .order-image img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }

        .order-info {
            flex: 1;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .order-header span {
            font-size: 1.4rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        .order-date {
            color: #666;
        }

        .order-details p {
            margin: 0.3rem 0;
            font-size: 1.4rem;
            color: #666;
        }

        .order-details p strong {
            color: var(--text-dark);
        }

        .status-label {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 1.2rem;
            font-weight: 500;
            text-align: center;
            margin-top: 0.5rem;
        }

        .status-awaiting {
            background-color: #95a5a6;
            color: #fff;
        }

        .status-paid {
            background-color: #2ecc71;
            color: #fff;
        }

        .status-edited {
            background-color: #e74c3c;
            color: #fff;
        }

        .status-processing {
            background-color: #f1c40f;
            color: #fff;
        }

        .status-completed {
            background-color: #7bed9f;
            color: #fff;
        }

        .message-admin {
            background-color: #f8d7da;
            color: #721c24;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            margin-top: 0.5rem;
            font-size: 1.4rem;
        }

        .btn-details {
            background: var(--primary-pink);
            color: var(--white);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 0.5rem;
        }

        .btn-details:hover {
            background: var(--dark-pink);
            transform: translateY(-1px);
        }

        .no-data-alert {
            text-align: center;
            padding: 2rem;
            background: #f8d7da;
            color: #721c24;
            border-radius: var(--border-radius);
            font-size: 1.4rem;
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
                    <a class="status-tab-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'tracking' ? 'active' : ''); ?>" href="user-order.php?tab=tracking">
                        <i class="fas fa-truck me-1"></i> ชำระเงินสำเร็จ & กำลังดำเนินการ
                    </a>
                </div>
                <div class="status-tab-item">
                    <a class="status-tab-link <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'completed' ? 'active' : ''); ?>" href="user-order.php?tab=completed">
                        <i class="fas fa-check-circle me-1"></i> คำสั่งซื้อสำเร็จ
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
                        <?php $awaitingOrders = array_filter($orders, function($order) { return $order['Status'] == 0; }); ?>
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
                        <?php $editedOrders = array_filter($orders, function($order) { return $order['Status'] == 2; }); ?>
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
                                            <p><strong>สถานะ:</strong> <span class="status-label status-edited"><i class="fas fa-edit me-1"></i>แก้ไขการชำระเงิน</span></p>
                                            <?php if (!empty($order['Message'])): ?>
                                                <p class="message-admin"><strong>ข้อความจากแอดมิน:</strong> <?php echo htmlspecialchars($order['Message']); ?></p>
                                            <?php endif; ?>
                                            <button class="btn-details" onclick="alert('ฟังก์ชันนี้อยู่ในระหว่างการพัฒนา')">ดูรายละเอียด</button>
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
                    <h4 class="tab-title">กำลังดำเนินการ</h4>
                    <?php if ($orders): ?>
                        <?php $trackingOrders = array_filter($orders, function($order) { return in_array($order['Status'], [1, 3]); }); ?>
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
                        <?php $completedOrders = array_filter($orders, function($order) { return $order['Status'] == 4; }); ?>
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
    </script>
</body>
</html>