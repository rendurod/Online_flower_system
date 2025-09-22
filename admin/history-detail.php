<?php
session_start();
require_once 'config/db.php';

// ตรวจสอบ session adminid
if (!isset($_SESSION['adminid'])) {
    error_log("No admin session found");
    header("Location: login.php");
    exit();
}

// ดึงข้อมูล admin
$admin_id = $_SESSION['adminid'];
try {
    $stmt = $conn->prepare("SELECT UserName FROM admin WHERE id = :id");
    $stmt->bindParam(':id', $admin_id, PDO::PARAM_INT);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        error_log("Admin not found for id: $admin_id");
        $_SESSION['error_message'] = "ไม่พบข้อมูลผู้ดูแลระบบ";
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching admin: " . $e->getMessage());
    $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ดูแลระบบ: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    header("Location: login.php");
    exit();
}

// ดึง order_id จาก query string
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if ($order_id <= 0) {
    error_log("Invalid or missing order_id");
    $_SESSION['error_message'] = "ไม่พบคำสั่งซื้อที่ระบุ";
    header("Location: history.php");
    exit();
}

// Fetch order details
$order = [];
$items = [];
try {
    // Fetch base order details
    $stmt = $conn->prepare("
        SELECT o.*, 
               CONCAT(m.FirstName, ' ', m.LastName) AS CustomerName,
               m.Address AS CustomerAddress,
               m.ContactNo AS CustomerContact,
               f.flower_name, f.price, f.image
        FROM tbl_orders o
        LEFT JOIN tbl_members m ON o.UserEmail = m.EmailId
        LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID
        WHERE o.ID = :id
    ");
    $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log("Order not found for id: $order_id");
        $_SESSION['error_message'] = "ไม่พบคำสั่งซื้อที่ระบุ";
        header("Location: history.php");
        exit();
    }

    // Fetch items from tbl_order_details
    $items_stmt = $conn->prepare("
        SELECT od.Quantity, od.Price, f.flower_name, f.image
        FROM tbl_order_details od
        JOIN tbl_flowers f ON od.FlowerId = f.ID
        WHERE od.OrderId = :order_id
    ");
    $items_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
    $items_stmt->execute();
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no items in tbl_order_details, use single-item data
    if (empty($items) && $order['FlowerId']) {
        $items[] = [
            'flower_name' => $order['flower_name'] ?? 'ไม่ระบุ',
            'Quantity' => $order['Quantity'] ?? 1,
            'Price' => $order['price'] ?? 0,
            'image' => $order['image'] ?? 'default-flower.jpg'
        ];
    }

    // Calculate and verify SumTotal
    $calculated_total = 0;
    foreach ($items as $item) {
        $calculated_total += $item['Price'] * $item['Quantity'];
    }
    if (abs($calculated_total - $order['SumTotal']) > 0.01) {
        try {
            $update_stmt = $conn->prepare("UPDATE tbl_orders SET SumTotal = :total_amount WHERE ID = :order_id");
            $update_stmt->bindValue(':total_amount', $calculated_total, PDO::PARAM_STR);
            $update_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
            $update_stmt->execute();
            $order['SumTotal'] = $calculated_total;
        } catch (PDOException $e) {
            error_log("Error updating total amount: " . $e->getMessage());
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการอัปเดตยอดรวม: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching order: " . $e->getMessage());
    $_SESSION['error_message'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    header("Location: history.php");
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = isset($_POST['status']) ? intval($_POST['status']) : 0;
    error_log("Received POST data: status=$new_status, order_id=$order_id");

    $valid_statuses = [0, 1, 2, 3, 4];
    if (!in_array($new_status, $valid_statuses)) {
        error_log("Invalid status: $new_status");
        $_SESSION['error_message'] = 'สถานะที่เลือกไม่ถูกต้อง';
        header("Location: history-detail.php?order_id=$order_id");
        exit();
    }

    try {
        $sql = "UPDATE tbl_orders SET Status = :status WHERE ID = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':status', $new_status, PDO::PARAM_INT);
        $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
        $stmt->execute();

        $row_count = $stmt->rowCount();
        error_log("Update query executed for order_id: $order_id, rows affected: $row_count");
        if ($row_count > 0) {
            error_log("Status updated successfully for order_id: $order_id, new_status: $new_status");
            $_SESSION['success_message'] = 'อัปเดตสถานะเรียบร้อยแล้ว';
        } else {
            error_log("No rows updated for order_id: $order_id");
            $_SESSION['error_message'] = 'ไม่มีการเปลี่ยนแปลงสถานะ';
            header("Location: history-detail.php?order_id=$order_id");
            exit();
        }
    } catch (PDOException $e) {
        $error_info = $stmt->errorInfo();
        error_log("Error updating status: " . $e->getMessage() . " | SQLSTATE: " . $error_info[0] . " | Driver Error: " . $error_info[1] . " | Message: " . $error_info[2]);
        $_SESSION['error_message'] = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        header("Location: history-detail.php?order_id=$order_id");
        exit();
    }
}

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $reason = isset($_POST['cancel_reason']) ? trim(htmlspecialchars($_POST['cancel_reason'], ENT_QUOTES, 'UTF-8')) : '';
    if (empty($reason)) {
        error_log("Missing cancellation reason for order_id: $order_id");
        $_SESSION['error_message'] = 'กรุณาระบุเหตุผลในการยกเลิก';
        header("Location: history-detail.php?order_id=$order_id");
        exit();
    }

    try {
        // Append suffix to cancellation reason
        $reason_with_suffix = $reason . ' //จากAdmin';
        $sql = "UPDATE tbl_orders SET Status = 4, Message = :message WHERE ID = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':message', $reason_with_suffix, PDO::PARAM_STR);
        $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
        $stmt->execute();

        $row_count = $stmt->rowCount();
        error_log("Cancellation query executed for order_id: $order_id, rows affected: $row_count");
        if ($row_count > 0) {
            error_log("Order cancelled successfully for order_id: $order_id");
            $_SESSION['success_message'] = 'ยกเลิกคำสั่งซื้อเรียบร้อยแล้ว';
            header("Location: order-cancel.php");
            exit();
        } else {
            error_log("No rows updated for order_id: $order_id");
            $_SESSION['error_message'] = 'ไม่สามารถยกเลิกคำสั่งซื้อได้';
            header("Location: history-detail.php?order_id=$order_id");
            exit();
        }
    } catch (PDOException $e) {
        $error_info = $stmt->errorInfo();
        error_log("Error cancelling order: " . $e->getMessage() . " | SQLSTATE: " . $error_info[0] . " | Driver Error: " . $error_info[1] . " | Message: " . $error_info[2]);
        $_SESSION['error_message'] = 'เกิดข้อผิดพลาดในการยกเลิกคำสั่งซื้อ: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        header("Location: history-detail.php?order_id=$order_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <title>รายละเอียดคำสั่งซื้อ - FlowerShop</title>
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .order-detail-container {
            margin: 0 auto;
            padding: 2rem;
        }

        .table th,
        .table td {
            vertical-align: middle;
            font-size: 1.4rem;
        }

        .table th {
            width: 30%;
            background-color: #f8f9fa;
            font-weight: bold;
            color: #4e73df;
        }

        .order-image img {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: inherit;
            border: 2px solid rgba(232, 67, 147, 0.2);
        }

        .status-select {
            width: 100%;
            padding: 0.5rem;
            font-size: 1.2rem;
            border: 2px solid rgba(232, 67, 147, 0.2);
            border-radius: inherit;
        }

        .status-select:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(232, 67, 67, 0.1);
        }

        .status-label {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .status-awaiting {
            background-color: #95a5a6;
            color: #fff;
        }

        .status-paid {
            background-color: #2ecc71;
            color: #fff;
        }

        .status-processing {
            background-color: #f1c40f;
            color: #fff;
        }

        .status-completed {
            background-color: #6f42c1;
            color: #fff;
        }

        .status-cancelled {
            background-color: #dc3545;
            color: #fff;
        }

        .item-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .item-list li {
            margin-bottom: 5px;
        }

        .badge-multi {
            background-color: #007bff;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }

        .status-refunded {
            background-color: #28a745;
            color: #fff;
        }

        .status-not-refunded {
            background-color: #dc3545;
            color: #fff;
        }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include("includes/sidebar.php"); ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include("includes/header.php"); ?>
                <div class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">รายละเอียดคำสั่งซื้อ: #<?php echo htmlspecialchars($order['BookingNumber'] ?? ''); ?><?php if (count($items) > 1): ?> <span class="badge badge-multi ms-2">หลายรายการ</span><?php endif; ?></h1>
                        <a href="history.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white"></i> กลับไปยังประวัติคำสั่งซื้อ
                        </a>
                    </div>

                    <div class="card shadow mb-4 order-detail-container">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ข้อมูลคำสั่งซื้อ</h6>
                            <label for="status" class="font-weight-bold text-gray-800">
                                        <i class="fas fa-info-circle text-pink mr-2"></i>สถานะคำสั่งซื้อ
                                    </label>
                                    <?php
                                    $status_options = [
                                        0 => ['text' => 'รอแจ้งชำระเงิน', 'icon' => 'fa-clock', 'class' => 'status-awaiting', 'option_class' => 'status-option-0'],
                                        1 => ['text' => 'ชำระเงินสำเร็จ', 'icon' => 'fa-check', 'class' => 'status-paid', 'option_class' => 'status-option-1'],
                                        2 => ['text' => 'กำลังจัดส่งสินค้า', 'icon' => 'fa-truck', 'class' => 'status-processing', 'option_class' => 'status-option-2'],
                                        3 => ['text' => 'คำสั่งซื้อสำเร็จ', 'icon' => 'fa-check-circle', 'class' => 'status-completed', 'option_class' => 'status-option-3'],
                                        4 => ['text' => 'ยกเลิกคำสั่งซื้อ', 'icon' => 'fa-times-circle', 'class' => 'status-cancelled', 'option_class' => 'status-option-4']
                                    ];
                                    $current_status = isset($status_options[$order['Status']]) ? $order['Status'] : 0;
                                    ?>
                                    <p class="status-label <?php echo $status_options[$current_status]['class']; ?>">
                                        <i class="fas <?php echo $status_options[$current_status]['icon']; ?> me-1"></i>
                                        <?php echo $status_options[$current_status]['text']; ?>
                                    </p>
                        </div>
                        
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th>ชื่อลูกค้า</th>
                                        <td><?php echo htmlspecialchars($order['CustomerName'] ?? 'ไม่ระบุ'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>วันที่สั่งซื้อ</th>
                                        <td><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>รายการสินค้า</th>
                                        <td>
                                            <?php if (count($items) > 1): ?>
                                                <ul class="item-list">
                                                    <?php foreach ($items as $item): ?>
                                                        <li>
                                                            <?php echo htmlspecialchars($item['flower_name'] ?? 'ไม่ระบุ'); ?> 
                                                            (<?php echo htmlspecialchars($item['Quantity'] ?? 0); ?> ชิ้น, ฿<?php echo number_format($item['Price'] * $item['Quantity'], 2); ?>)
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($items[0]['flower_name'] ?? 'ไม่ระบุ'); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนรวม</th>
                                        <td><?php echo array_sum(array_column($items, 'Quantity')); ?> ชิ้น</td>
                                    </tr>
                                    <tr>
                                        <th>ราคารวม</th>
                                        <td class="text-danger">฿<?php echo number_format($order['SumTotal'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th>วันที่จัดส่ง</th>
                                        <td><?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>ชื่อบัญชี</th>
                                        <td><?php echo htmlspecialchars($order['AccountName'] ?? 'ไม่ระบุ'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>เลขที่บัญชี</th>
                                        <td><?php echo htmlspecialchars($order['AccountNumber'] ?? 'ไม่ระบุ'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>รูปภาพสินค้า</th>
                                        <td class="order-image">
                                            <?php if (count($items) > 1): ?>
                                                <div class="d-flex flex-wrap">
                                                    <?php foreach ($items as $item): ?>
                                                        <img src="<?php echo !empty($item['image']) && file_exists("uploads/flowers/" . $item['image']) ? "uploads/flowers/" . htmlspecialchars($item['image']) : "img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($item['flower_name'] ?? 'ไม่ระบุ'); ?>" class="me-2 mb-2">
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <img src="<?php echo !empty($items[0]['image']) && file_exists("uploads/flowers/" . $items[0]['image']) ? "uploads/flowers/" . htmlspecialchars($items[0]['image']) : "img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($items[0]['flower_name'] ?? 'ไม่ระบุ'); ?>">
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>สลิปการชำระเงิน</th>
                                        <td>
                                            <?php if (!empty($order['Image'])): ?>
                                                <img src="../Uploads/slips/<?php echo htmlspecialchars($order['Image'] ?? ''); ?>" alt="Payment Slip" style="max-width: 200px; border-radius: inherit; border: 2px solid rgba(232, 67, 147, 0.2);">
                                            <?php else: ?>
                                                ไม่มีสลิป
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if ($order['Status'] == 4): ?>
                                        <tr>
                                            <th>ข้อมูลการยกเลิก</th>
                                            <td>
                                                <?php
                                                $refundStatus = strpos($order['Message'], '//RefundedByAdmin') !== false ? 'โอนเงินคืนแล้ว' : 'รอการโอนเงินคืน';
                                                $refundClass = strpos($order['Message'], '//RefundedByAdmin') !== false ? 'status-refunded' : 'status-not-refunded';
                                                $messageParts = explode('//RefundedByAdmin', $order['Message']);
                                                $cancelReason = trim($messageParts[0]);
                                                $refundMessage = isset($messageParts[1]) ? trim($messageParts[1]) : '';
                                                ?>
                                                <p><strong>สถานะการคืนเงิน:</strong> <span class="status-label <?php echo $refundClass; ?>"><?php echo htmlspecialchars($refundStatus); ?></span></p>
                                                <?php if (!empty($cancelReason)): ?>
                                                    <p><strong>เหตุผลการยกเลิก:</strong> <?php echo htmlspecialchars($cancelReason); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($order['AccountName'])): ?>
                                                    <p><strong>ชื่อบัญชี:</strong> <?php echo htmlspecialchars($order['AccountName']); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($order['AccountNumber'])): ?>
                                                    <p><strong>เลขที่บัญชี:</strong> <?php echo htmlspecialchars($order['AccountNumber']); ?></p>
                                                <?php endif; ?>
                                                <?php if (strpos($order['Message'], '//จากAdmin') !== false): ?>
                                                    <p><strong>ผู้ยกเลิก:</strong> แอดมิน</p>
                                                <?php elseif (!empty($order['AccountName']) && !empty($order['AccountNumber'])): ?>
                                                    <p><strong>ผู้ยกเลิก:</strong> ลูกค้า</p>
                                                <?php endif; ?>
                                                <?php if (!empty($refundMessage)): ?>
                                                    <p><strong>ข้อความจากแอดมิน:</strong> <?php echo htmlspecialchars($refundMessage); ?></p>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            

                           
                        </div>
                    </div>
                </div>
            </div>
            <?php include("includes/footer.php"); ?>
        </div>
    </div>

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['success_message'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: '<?php echo htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8'); ?>',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#3085d6'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '<?php echo $order['Status'] == 4 ? "order-cancel.php" : "history.php"; ?>';
                    }
                });
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: '<?php echo htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8'); ?>',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#d33'
                });
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
        });
    </script>
</body>

</html>