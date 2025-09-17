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

    if (!$userEmail) {
        $message = "ไม่พบข้อมูลผู้ใช้";
        $messageType = "danger";
    }
} catch (PDOException $e) {
    $message = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: " . htmlspecialchars($e->getMessage());
    $messageType = "danger";
}

// Fetch user orders with items from tbl_order_details or tbl_orders
$orders = [];
try {
    if ($userEmail) {
        // Fetch orders and check if they have multiple items in tbl_order_details
        $stmt = $conn->prepare("
            SELECT o.ID, o.BookingNumber, o.UserEmail, o.DeliveryDate, o.Image AS SlipImage, o.PostingDate, o.Status, o.TotalAmount,
                   f.ID AS FlowerId, f.flower_name, f.price, o.Quantity
            FROM tbl_orders o
            LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID
            WHERE o.UserEmail = :email
        ");
        $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
        $stmt->execute();
        $raw_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group orders and fetch items from tbl_order_details
        foreach ($raw_orders as $order) {
            $order_id = $order['ID'];
            $items = [];

            // Check for items in tbl_order_details
            $items_stmt = $conn->prepare("
                SELECT od.Quantity, od.Price, f.flower_name
                FROM tbl_order_details od
                JOIN tbl_flowers f ON od.FlowerId = f.ID
                WHERE od.OrderId = :order_id
            ");
            $items_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
            $items_stmt->execute();
            $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate total from items for verification
            $calculated_total = 0;
            if (!empty($order_items)) {
                // Multi-item order
                $items = $order_items;
                foreach ($items as $item) {
                    $calculated_total += $item['Price'] * $item['Quantity'];
                }
            } else {
                // Single-item order from tbl_orders
                if ($order['FlowerId']) {
                    $items[] = [
                        'flower_name' => $order['flower_name'] ?? 'ไม่ระบุ',
                        'Quantity' => $order['Quantity'],
                        'Price' => $order['price'] ?? 0
                    ];
                    $calculated_total = $order['price'] * $order['Quantity'];
                }
            }

            // Compare and update TotalAmount if necessary
            if (abs($calculated_total - $order['TotalAmount']) > 0.01) {
                try {
                    $update_query = "UPDATE tbl_orders SET TotalAmount = :total_amount WHERE ID = :order_id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bindValue(':total_amount', $calculated_total, PDO::PARAM_STR);
                    $update_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
                    $update_stmt->execute();
                } catch (PDOException $e) {
                    $message = "เกิดข้อผิดพลาดในการอัปเดตยอดรวม: " . htmlspecialchars($e->getMessage());
                    $messageType = "danger";
                }
                $order['TotalAmount'] = $calculated_total;
            }

            $orders[$order_id] = [
                'BookingNumber' => $order['BookingNumber'],
                'PostingDate' => $order['PostingDate'],
                'DeliveryDate' => $order['DeliveryDate'],
                'Status' => $order['Status'],
                'TotalAmount' => $order['TotalAmount'],
                'SlipImage' => $order['SlipImage'],
                'Items' => $items
            ];
        }
    }
} catch (PDOException $e) {
    $message = "เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: " . htmlspecialchars($e->getMessage());
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
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการสั่งซื้อ - Flower Shop</title>
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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

                <!-- Processing Tab (Status 0, 2, 5) -->
                <div class="tab-pane <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'processing' ? 'active' : (!isset($_GET['tab']) ? 'active' : '')); ?>" id="processing">
                    <h4 class="tab-title">ดำเนินการ</h4>
                    <?php if ($orders): ?>
                        <?php $processingOrders = array_filter($orders, function ($order) {
                            return in_array($order['Status'], [0, 2, 5]);
                        }); ?>
                        <?php if (!empty($processingOrders)): ?>
                            <?php foreach ($processingOrders as $order_id => $order): ?>
                                <div class="order-item <?php echo in_array($order['Status'], [2, 5]) ? 'urgent' : ''; ?>">
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span class="order-number">Order #<?php echo htmlspecialchars($order['BookingNumber']); ?><?php if (count($order['Items']) > 1): ?> <span class="badge bg-primary ms-2">คำสั่งซื้อหลายรายการ</span><?php endif; ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <?php if (count($order['Items']) > 1): ?>
                                                <h5>รายการสินค้า:</h5>
                                                <ul class="item-list">
                                                    <?php foreach ($order['Items'] as $item): ?>
                                                        <li>
                                                            <strong><?php echo htmlspecialchars($item['flower_name'] ?? 'ไม่ระบุ'); ?></strong>
                                                            <span>จำนวน: <?php echo htmlspecialchars($item['Quantity']); ?> ชิ้น</span>
                                                            <span>ราคา: <?php echo number_format($item['Quantity'] * ($item['Price'] ?? 0), 2); ?> บาท</span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <p><strong>ยอดรวมทั้งหมด:</strong> <?php echo number_format($order['TotalAmount'], 2); ?> บาท</p>
                                            <?php else: ?>
                                                <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['Items'][0]['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                                <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Items'][0]['Quantity']); ?> ชิ้น</p>
                                                <p><strong>ยอดรวมทั้งหมด:</strong> <?php echo number_format($order['TotalAmount'], 2); ?> บาท</p>
                                            <?php endif; ?>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong>
                                                <span class="status-label <?php echo $order['Status'] == 0 ? 'status-awaiting' : ($order['Status'] == 2 ? 'status-edited' : 'status-new-slip'); ?>">
                                                    <i class="fas <?php echo $order['Status'] == 0 ? 'fa-clock' : ($order['Status'] == 2 ? 'fa-edit' : 'fa-upload'); ?> me-1"></i>
                                                    <?php
                                                    $statusText = [
                                                        0 => 'รอแจ้งชำระเงิน',
                                                        2 => 'แก้ไขการชำระเงิน',
                                                        5 => 'แนบสลิปใหม่'
                                                    ];
                                                    echo $statusText[$order['Status']] ?? 'ไม่ระบุ';
                                                    ?>
                                                </span>
                                            </p>
                                            <?php if ($order['Status'] == 2): ?>
                                                <a href="user-slip.php?order_id=<?php echo htmlspecialchars($order_id); ?>" class="btn-details">
                                                    <i class="fas fa-upload me-1"></i>อัปโหลดสลิปใหม่
                                                </a>
                                            <?php endif; ?>
                                            <div class="order-details-footer">
                                                <a href="user-order-detail.php?order_id=<?php echo htmlspecialchars($order_id); ?>" class="btn-details"><i class="fas fa-info-circle me-1"></i>ดูรายละเอียด</a>
                                            </div>
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
                            <?php foreach ($paidOrders as $order_id => $order): ?>
                                <div class="order-item">
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span class="order-number">Order #<?php echo htmlspecialchars($order['BookingNumber']); ?><?php if (count($order['Items']) > 1): ?> <span class="badge bg-primary ms-2">คำสั่งซื้อหลายรายการ</span><?php endif; ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <?php if (count($order['Items']) > 1): ?>
                                                <h5>รายการสินค้า:</h5>
                                                <ul class="item-list">
                                                    <?php foreach ($order['Items'] as $item): ?>
                                                        <li>
                                                            <strong><?php echo htmlspecialchars($item['flower_name'] ?? 'ไม่ระบุ'); ?></strong>
                                                            <span>จำนวน: <?php echo htmlspecialchars($item['Quantity']); ?> ชิ้น</span>
                                                            <span>ราคา: <?php echo number_format($item['Quantity'] * ($item['Price'] ?? 0), 2); ?> บาท</span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <p><strong>ยอดรวมทั้งหมด:</strong> <?php echo number_format($order['TotalAmount'], 2); ?> บาท</p>
                                            <?php else: ?>
                                                <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['Items'][0]['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                                <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Items'][0]['Quantity']); ?> ชิ้น</p>
                                                <p><strong>ยอดรวมทั้งหมด:</strong> <?php echo number_format($order['TotalAmount'], 2); ?> บาท</p>
                                            <?php endif; ?>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong> <span class="status-label status-paid"><i class="fas fa-check me-1"></i>ชำระเงินสำเร็จ</span></p>
                                            <div class="order-details-footer">
                                                <a href="user-order-detail.php?order_id=<?php echo htmlspecialchars($order_id); ?>" class="btn-details"><i class="fas fa-info-circle me-1"></i>ดูรายละเอียด</a>
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
                            <?php foreach ($shippingOrders as $order_id => $order): ?>
                                <div class="order-item">
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span class="order-number">Order #<?php echo htmlspecialchars($order['BookingNumber']); ?><?php if (count($order['Items']) > 1): ?> <span class="badge bg-primary ms-2">คำสั่งซื้อหลายรายการ</span><?php endif; ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <?php if (count($order['Items']) > 1): ?>
                                                <h5>รายการสินค้า:</h5>
                                                <ul class="item-list">
                                                    <?php foreach ($order['Items'] as $item): ?>
                                                        <li>
                                                            <strong><?php echo htmlspecialchars($item['flower_name'] ?? 'ไม่ระบุ'); ?></strong>
                                                            <span>จำนวน: <?php echo htmlspecialchars($item['Quantity']); ?> ชิ้น</span>
                                                            <span>ราคา: <?php echo number_format($item['Quantity'] * ($item['Price'] ?? 0), 2); ?> บาท</span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <p><strong>ยอดรวมทั้งหมด:</strong> <?php echo number_format($order['TotalAmount'], 2); ?> บาท</p>
                                            <?php else: ?>
                                                <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['Items'][0]['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                                <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Items'][0]['Quantity']); ?> ชิ้น</p>
                                                <p><strong>ยอดรวมทั้งหมด:</strong> <?php echo number_format($order['TotalAmount'], 2); ?> บาท</p>
                                            <?php endif; ?>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong> <span class="status-label status-processing"><i class="fas fa-truck me-1"></i>กำลังจัดส่งสินค้า</span></p>
                                            <div class="order-details-footer">
                                                <a href="user-order-detail.php?order_id=<?php echo htmlspecialchars($order_id); ?>" class="btn-details"><i class="fas fa-info-circle me-1"></i>ดูรายละเอียด</a>
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
                            <?php foreach ($completedOrders as $order_id => $order): ?>
                                <div class="order-item">
                                    <div class="order-info">
                                        <div class="order-header">
                                            <span class="order-number">Order #<?php echo htmlspecialchars($order['BookingNumber']); ?><?php if (count($order['Items']) > 1): ?> <span class="badge bg-primary ms-2">คำสั่งซื้อหลายรายการ</span><?php endif; ?></span>
                                            <span class="order-date"><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></span>
                                        </div>
                                        <div class="order-details">
                                            <?php if (count($order['Items']) > 1): ?>
                                                <h5>รายการสินค้า:</h5>
                                                <ul class="item-list">
                                                    <?php foreach ($order['Items'] as $item): ?>
                                                        <li>
                                                            <strong><?php echo htmlspecialchars($item['flower_name'] ?? 'ไม่ระบุ'); ?></strong>
                                                            <span>จำนวน: <?php echo htmlspecialchars($item['Quantity']); ?> ชิ้น</span>
                                                            <span>ราคา: <?php echo number_format($item['Quantity'] * ($item['Price'] ?? 0), 2); ?> บาท</span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <p><strong>ยอดรวมทั้งหมด:</strong> <?php echo number_format($order['TotalAmount'], 2); ?> บาท</p>
                                            <?php else: ?>
                                                <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['Items'][0]['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                                                <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Items'][0]['Quantity']); ?> ชิ้น</p>
                                                <p><strong>ยอดรวมทั้งหมด:</strong> <?php echo number_format($order['TotalAmount'], 2); ?> บาท</p>
                                            <?php endif; ?>
                                            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                                            <p><strong>สถานะ:</strong> <span class="status-label status-completed"><i class="fas fa-check-circle me-1"></i>จัดส่งสำเร็จ</span></p>
                                            <div class="order-details-footer">
                                                <a href="user-order-detail.php?order_id=<?php echo htmlspecialchars($order_id); ?>" class="btn-details"><i class="fas fa-info-circle me-1"></i>ดูรายละเอียด</a>
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
    </script>
</body>

</html>