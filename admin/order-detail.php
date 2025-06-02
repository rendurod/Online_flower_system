<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if (!$conn) {
    $_SESSION['error'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
    header("Location: login.php");
    exit();
}

// ตรวจสอบว่ามี session adminid หรือไม่
if (!isset($_SESSION['adminid'])) {
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
        $_SESSION['error'] = "ไม่พบข้อมูลผู้ดูแลระบบ";
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ดูแลระบบ: " . htmlspecialchars($e->getMessage());
    header("Location: login.php");
    exit();
}

// ดึง order_id จาก query string
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    $_SESSION['error'] = "ไม่พบคำสั่งซื้อที่ระบุ";
    header("Location: orders.php");
    exit();
}

// Fetch order details with locking
$order = [];
try {
    $stmt = $conn->prepare("
        SELECT o.*, 
               CONCAT(m.FirstName, ' ', m.LastName) AS CustomerName,
               f.flower_name, f.price, f.image, f.stock_quantity
        FROM tbl_orders o
        LEFT JOIN tbl_members m ON o.UserEmail = m.EmailId
        LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID
        WHERE o.ID = :id
        FOR UPDATE
    ");
    $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['error'] = "ไม่พบคำสั่งซื้อที่ระบุ";
        header("Location: orders.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: ' . htmlspecialchars($e->getMessage());
    header("Location: orders.php");
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = intval($_POST['status']);
    $message = isset($_POST['message']) ? trim(htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8')) : ($order['Message'] ?? '');
    $current_status = $order['Status'];

    // ตรวจสอบสถานะใหม่
    $valid_statuses = [0, 1, 2];
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['error'] = 'สถานะที่เลือกไม่ถูกต้อง';
        header("Location: order-detail.php?order_id=" . $order_id);
        exit();
    }

    // ตรวจสอบข้อความสำหรับสถานะแก้ไขการชำระเงิน
    if ($new_status == 2 && empty($message)) {
        $_SESSION['error'] = 'กรุณาระบุข้อความสำหรับการแก้ไขการชำระเงิน';
        header("Location: order-detail.php?order_id=" . $order_id);
        exit();
    }

    try {
        $conn->beginTransaction();

        // Update order status and message
        $sql = "UPDATE tbl_orders SET Status = :status, Message = :message, LastupdateDate = CURRENT_TIMESTAMP WHERE ID = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':status', $new_status, PDO::PARAM_INT);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
        $stmt->execute();

        // Fetch current stock quantity with locking
        $sql = "SELECT stock_quantity FROM tbl_flowers WHERE ID = :flower_id FOR UPDATE";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':flower_id', $order['FlowerId'], PDO::PARAM_INT);
        $stmt->execute();
        $current_stock = $stmt->fetchColumn();

        // Define stock-affecting statuses
        $stock_reducing_statuses = [1];
        $order_quantity = $order['Quantity'];

        // Handle stock adjustments
        if (!in_array($current_status, $stock_reducing_statuses) && in_array($new_status, $stock_reducing_statuses)) {
            if ($current_stock >= $order_quantity) {
                $new_stock = $current_stock - $order_quantity;
                $sql = "UPDATE tbl_flowers SET stock_quantity = :stock WHERE ID = :flower_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':stock', $new_stock, PDO::PARAM_INT);
                $stmt->bindValue(':flower_id', $order['FlowerId'], PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $conn->rollBack();
                $_SESSION['error'] = 'จำนวนสต็อกไม่เพียงกล';
                header("Location: order-detail.php?order_id=" . $order_id);
                exit();
            }
        } elseif (in_array($current_status, $stock_reducing_statuses) && !in_array($new_status, $stock_reducing_statuses)) {
            $new_stock = $current_stock + $order_quantity;
            $sql = "UPDATE tbl_flowers SET stock_quantity = :stock WHERE ID = :flower_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':stock', $new_stock, PDO::PARAM_INT);
            $stmt->bindValue(':flower_id', $order['FlowerId'], PDO::PARAM_INT);
            $stmt->execute();
        }

        $conn->commit();
        $_SESSION['success'] = 'อัปเดตสถานะและสต็อกเรียบร้อยแล้ว';
    } catch (PDOException $e) {  
        $conn->rollBack();
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . htmlspecialchars($e->getMessage());
        header("Location: order-detail.php?order_id=" . $order_id);
        exit();
    }
}

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $reason = trim(htmlspecialchars($_POST['reason'] ?? '', ENT_QUOTES, 'UTF-8'));
    $current_status = $order['Status'];

    if (empty($reason)) {
        $_SESSION['error'] = 'กรุณาระบุเหตุผลในการยกเลิก';
        header("Location: order-detail.php?order_id=" . $order_id);
        exit();
    }

    if (!in_array($current_status, [0, 1, 2, 3, 5])) {
        $_SESSION['error'] = 'ไม่สามารถยกเลิกคำสั่งซื้อในสถานะนี้ได้';
        header("Location: order-detail.php?order_id=" . $order_id);
        exit();
    }

    // เพิ่มข้อความ "- จากแอดมิน" ต่อท้ายเหตุผล
    $reason .= " - จากแอดมิน";

    try {
        $conn->beginTransaction();

        // Update order status to 6 (cancelled) and message
        $sql = "UPDATE tbl_orders SET Status = :status, Message = :message, LastupdateDate = CURRENT_TIMESTAMP WHERE ID = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':status', 6, PDO::PARAM_INT);
        $stmt->bindValue(':message', $reason, PDO::PARAM_STR);
        $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
        $stmt->execute();

        // Restore stock if previously reduced
        if (in_array($current_status, [1])) {
            $sql = "SELECT stock_quantity FROM tbl_flowers WHERE ID = :flower_id FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':flower_id', $order['FlowerId'], PDO::PARAM_INT);
            $stmt->execute();
            $current_stock = $stmt->fetchColumn();

            $new_stock = $current_stock + $order['Quantity'];
            $sql = "UPDATE tbl_flowers SET stock_quantity = :stock WHERE ID = :flower_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':stock', $new_stock, PDO::PARAM_INT);
            $stmt->bindValue(':flower_id', $order['FlowerId'], PDO::PARAM_INT);
            $stmt->execute();
        }

        $conn->commit();
        $_SESSION['success'] = 'ยกเลิกคำสั่งซื้อเรียบร้อยแล้ว';
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการยกเลิกคำสั่งซื้อ: ' . htmlspecialchars($e->getMessage());
        header("Location: order-detail.php?order_id=" . $order_id);
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
            transition: all;
        }

        .status-select:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(232, 67, 67, 0.1);
        }

        .status-label {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 12px;
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

        .status-cancelled {
            background-color: #dc3545;
            color: #fff;
        }

        .stock-highlight {
            color: #e74c3c;
            font-weight: bold;
            font-size: 1.6rem;
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

        .btn-cancel-order {
            background-color: #dc3545;
            color: white;
            border: none;
            font-size: 1.1rem;
            padding: 0.75rem 1.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            margin-left: 1rem;
        }

        .btn-cancel-order:hover {
            background-color: #c82333;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
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
                                        <th>สต็อกคงเหลือ</th>
                                        <td class="stock-highlight"><?php echo htmlspecialchars($order['stock_quantity'] ?? '0'); ?> ชิ้น</td>
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
                                        0 => ['text' => 'รอแจ้งชำระเงิน', 'icon' => 'fa-clock', 'class' => 'status-awaiting', 'option_class' => 'status-option-0'],
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
                                            <option value="<?php echo $status; ?>" class="<?php echo $details['option_class']; ?>" <?php echo $status == $current_status ? 'selected' : ''; ?>>
                                                <?php echo $details['text']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="message" id="message-input" placeholder="กรุณาระบุเหตุผลสำหรับการแก้ไขการชำระเงิน" value="<?php echo htmlspecialchars($order['Message'] ?? ''); ?>">
                                </div>
                                <div class="form-group d-flex justify-content-between align-items-center">
                                    <div>
                                        <button type="submit" name="update_status" value="1" class="btn btn-primary mr-2">
                                            <i class="fas fa-save mr-2"></i>บันทึก
                                        </button>
                                        <a href="orders.php" class="btn btn-secondary">
                                            <i class="fas fa-times mr-2"></i>ยกเลิก
                                        </a>
                                    </div>
                                    <?php if (in_array($order['Status'], [0, 1, 2, 3, 5])): ?>
                                        <button type="button" class="btn btn-cancel-order" data-bs-toggle="modal" data-bs-target="#cancel-order-modal">
                                            <i class="fas fa-times-circle mr-2"></i>ยกเลิกคำสั่งซื้อ
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php include("includes/footer.php"); ?>
        </div>
    </div>

    <!-- Cancel Order Modal -->
    <div class="modal fade" id="cancel-order-modal" tabindex="-1" aria-labelledby="cancel-order-modal-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancel-order-modal-label">ยกเลิกคำสั่งซื้อ #<?php echo htmlspecialchars($order['BookingNumber']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="order-detail.php?order_id=<?php echo $order_id; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="cancel-reason" class="form-label">เหตุผลในการยกเลิก <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="cancel-reason" name="reason" rows="4" required placeholder="กรุณาระบุเหตุผลในการยกเลิก"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" name="cancel_order" class="btn btn-danger">ยืนยันการยกเลิก</button>
                    </div>
                </form>
            </div>
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
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>

    <script>
        // Show/hide message input based on status
        document.getElementById('status').addEventListener('change', function() {
            const messageInput = document.getElementById('message-input');
            if (this.value === '2') {
                messageInput.style.display = 'block';
                messageInput.required = true;
            } else {
                messageInput.style.display = 'none';
                messageInput.required = false;
                messageInput.value = '';
            }
        });

        // Initial check for message input visibility
        document.addEventListener('DOMContentLoaded', function() {
            const initialStatus = document.getElementById('status').value;
            const messageInput = document.getElementById('message-input');
            if (initialStatus === '2') {
                messageInput.style.display = 'block';
                messageInput.required = true;
            } else {
                messageInput.style.display = 'none';
                messageInput.required = false;
            }
        });

        // SweetAlert2 confirmation before updating status
        document.getElementById('update-status-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const selectedStatus = document.getElementById('status').value;
            const statusText = document.getElementById('status').options[document.getElementById('status').selectedIndex].text.trim();
            let confirmMessage = `คุณต้องการเปลี่ยนสถานะเป็น "${statusText}" หรือไม่`;

            if (selectedStatus === '1') {
                confirmMessage += `\nสต็อกสินค้าจะถูกลดลงตามจำนวนที่สั่งซื้อ (${<?php echo $order['Quantity'] ?? 0; ?>} ชิ้น)`;
            }

            if (selectedStatus === '2' && !document.getElementById('message-input').value.trim()) {
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: 'กรุณาระบุเหตุผลสำหรับการแก้ไขการชำระเงิน',
                    timer: 3000,
                    showConfirmButton: false
                });
                return;
            }

            Swal.fire({
                title: 'ยืนยันการเปลี่ยนสถานะ',
                text: confirmMessage,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e84393',
                cancelButtonText: 'ยกเลิก',
                confirmButtonText: 'ยืนยัน'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        // Show SweetAlert2 for success/error messages
        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
                timer: 3000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'orders.php';
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
</body>

</html>