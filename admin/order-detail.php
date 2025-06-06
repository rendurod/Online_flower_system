<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

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
    $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ดูแลระบบ: " . htmlspecialchars($e->getMessage());
    header("Location: login.php");
    exit();
}

// ดึง order_id จาก query string
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if ($order_id <= 0) {
    error_log("Invalid or missing order_id");
    $_SESSION['error_message'] = "ไม่พบคำสั่งซื้อที่ระบุ";
    header("Location: orders.php");
    exit();
}

// Fetch order details
$order = [];
try {
    $stmt = $conn->prepare("
        SELECT o.*, 
               CONCAT(m.FirstName, ' ', m.LastName) AS CustomerName,
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
        header("Location: orders.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching order: " . $e->getMessage());
    $_SESSION['error_message'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: ' . htmlspecialchars($e->getMessage());
    header("Location: orders.php");
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    // Debug ข้อมูลที่รับมา
    $new_status = isset($_POST['status']) ? intval($_POST['status']) : 0;
    $message = isset($_POST['message']) ? trim(htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8')) : null;
    error_log("Received POST data: status=$new_status, message=" . ($message ?? 'NULL') . ", order_id=$order_id");

    // ตรวจสอบสถานะใหม่
    $valid_statuses = [1, 2];
    if (!in_array($new_status, $valid_statuses)) {
        error_log("Invalid status: $new_status");
        $_SESSION['error_message'] = 'สถานะที่เลือกไม่ถูกต้อง';
        header("Location: order-detail.php?order_id=$order_id");
        exit();
    }

    // ตรวจสอบข้อความสำหรับสถานะ 2
    if ($new_status == 2 && empty($message)) {
        error_log("Missing message for status 2");
        $_SESSION['error_message'] = 'กรุณาระบุข้อความสำหรับการแก้ไขการชำระเงิน';
        header("Location: order-detail.php?order_id=$order_id");
        exit();
    }

    try {
        // อัปเดตสถานะและข้อความ
        $sql = "UPDATE tbl_orders SET Status = :status, Message = :message WHERE ID = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':status', $new_status, PDO::PARAM_INT);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
        $stmt->execute();

        // ตรวจสอบจำนวนแถวที่อัปเดต
        $row_count = $stmt->rowCount();
        error_log("Update query executed for order_id: $order_id, rows affected: $row_count");
        if ($row_count > 0) {
            error_log("Status updated successfully for order_id: $order_id, new_status: $new_status");
            $_SESSION['success_message'] = 'อัปเดตสถานะเรียบร้อยแล้ว';
            header("Location: order-confirm.php");
            exit();
        } else {
            error_log("No rows updated for order_id: $order_id");
            $_SESSION['error_message'] = 'ไม่มีการเปลี่ยนแปลงสถานะ';
            header("Location: order-detail.php?order_id=$order_id");
            exit();
        }
    } catch (PDOException $e) {
        $error_info = $stmt->errorInfo();
        error_log("Error updating status: " . $e->getMessage() . " | SQLSTATE: " . $error_info[0] . " | Driver Error: " . $error_info[1] . " | Message: " . $error_info[2]);
        $_SESSION['error_message'] = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . htmlspecialchars($e->getMessage());
        header("Location: order-detail.php?order_id=$order_id");
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
    <!-- LOGO -->
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Custom fonts for this template -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom styles for this template -->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
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

        .status-edited {
            background-color: #e74c3c;
            color: #fff;
        }

        .status-option-0 {
            background-color: #95a5a6;
            color: #fff;
        }

        .status-option-1 {
            background-color: #2ecc71;
            color: #fff;
        }

        .status-option-2 {
            background-color: #e74c3c;
            color: #fff;
        }

        #message-input {
            display: none;
            width: 100%;
            padding: 0.5rem;
            font-size: 1.2rem;
            border: 2px solid rgba(232, 67, 147, 0.2);
            border-radius: inherit;
            margin-top: 0.5rem;
        }

        #message-input:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(232, 147, 147, 0.1);
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
                        <h1 class="h3 mb-0 text-gray-800">รายละเอียดคำสั่งซื้อ: #<?php echo htmlspecialchars($order['BookingNumber']); ?></h1>
                        <a href="orders.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white"></i> กลับไปยังรายการคำสั่งซื้อ
                        </a>
                    </div>

                    <div class="card shadow mb-4 order-detail-container">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ข้อมูลคำสั่งซื้อ</h6>
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
                                        <th>ชื่อสินค้า</th>
                                        <td><?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>จำนวน</th>
                                        <td><?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</td>
                                    </tr>
                                    <tr>
                                        <th>ราคารวม</th>
                                        <td>฿<?php echo number_format($order['Quantity'] * ($order['price'] ?? 0), 2); ?></td>
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
                                            <img src="<?php echo !empty($order['image']) && file_exists("uploads/flowers/" . $order['image']) ? "uploads/flowers/" . htmlspecialchars($order['image']) : "img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($order['flower_name']); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>สลิปการชำระเงิน</th>
                                        <td>
                                            <?php if (!empty($order['Image'])): ?>
                                                <img src="../Uploads/slips/<?php echo htmlspecialchars($order['Image']); ?>" alt="Payment Slip" style="max-width: 200px; border-radius: inherit; border: 2px solid rgba(232, 67, 147, 0.2);">
                                            <?php else: ?>
                                                ไม่มีสลิป
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (!empty($order['Message'])): ?>
                                        <tr>
                                            <th>ข้อความ</th>
                                            <td><?php echo htmlspecialchars($order['Message']); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <form method="POST" id="update-status-form" action="order-detail.php?order_id=<?php echo $order_id; ?>">
                                <div class="form-group">
                                    <label for="status" class="font-weight-bold text-gray-800">
                                        <i class="fas fa-info-circle text-pink mr-2"></i>สถานะคำสั่งซื้อ
                                    </label>
                                    <?php
                                    $status_options = [
                                        0 => ['text' => 'รอดำเนินการ', 'icon' => 'fa-clock', 'class' => 'status-awaiting', 'option_class' => 'status-option-0'],
                                        1 => ['text' => 'การชำระเงินสำเร็จ', 'icon' => 'fa-check', 'class' => 'status-paid', 'option_class' => 'status-option-1'],
                                        2 => ['text' => 'แก้ไขการชำระเงิน', 'icon' => 'fa-edit', 'class' => 'status-edited', 'option_class' => 'status-option-2']
                                    ];
                                    $current_status = isset($status_options[$order['Status']]) ? $order['Status'] : 0;
                                    ?>
                                    <p class="status-label <?php echo $status_options[$current_status]['class']; ?>">
                                        <i class="fas <?php echo $status_options[$current_status]['icon']; ?> me-1"></i>
                                        <?php echo $status_options[$current_status]['text']; ?>
                                    </p>
                                    <select name="status" id="status" class="status-select" required>
                                        <?php foreach ($status_options as $status => $details): ?>
                                            <option value="<?php echo $status; ?>" class="<?php echo $details['option_class']; ?>" <?php echo $status == $current_status ? 'selected' : ''; ?> <?php echo $status == 0 ? 'disabled' : ''; ?>>
                                                <?php echo htmlspecialchars($details['text']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="message" id="message-input" placeholder="กรุณาระบุเหตุผลสำหรับการแก้ไขการชำระเงิน" value="<?php echo htmlspecialchars($order['Message'] ?? ''); ?>">
                                </div>
                                <div class="form-group d-flex justify-content-start align-items-center">
                                    <button type="submit" name="update_status" class="btn btn-primary mr-2">
                                        <i class="fas fa-save mr-2"></i>บันทึก
                                    </button>
                                    <a href="orders.php" class="btn btn-secondary">
                                        <i class="fas fa-times mr-2"></i>ยกเลิก
                                    </a>
                                </div>
                            </form>
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

    <!-- Scripts -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Show/hide message input based on status
        const statusSelect = document.getElementById('status');
        const messageInput = document.getElementById('message-input');
        if (statusSelect && messageInput) {
            statusSelect.addEventListener('change', function() {
                if (this.value === '2') {
                    messageInput.style.display = 'block';
                    messageInput.required = true;
                } else {
                    messageInput.style.display = 'none';
                    messageInput.required = false;
                    messageInput.value = '';
                }
            });

            // Initial check
            if (statusSelect.value === '2') {
                messageInput.style.display = 'block';
                messageInput.required = true;
            } else {
                messageInput.style.display = 'none';
                messageInput.required = false;
            }
        }
    </script>
</body>

</html>