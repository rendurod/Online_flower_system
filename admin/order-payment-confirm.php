<?php
session_start();
require_once 'config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

// Fetch admin username
$admin_id = $_SESSION['adminid'];
try {
    $stmt = $conn->prepare("SELECT UserName FROM admin WHERE id = :id");
    $stmt->bindParam(':id', $admin_id, PDO::PARAM_INT);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ดูแลระบบ: " . htmlspecialchars($e->getMessage());
    header("Location: login.php");
    exit();
}

// Get order_id from query string
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    $_SESSION['error'] = "ไม่พบคำสั่งซื้อที่ระบุ";
    header("Location: order-confirm.php");
    exit();
}

// Fetch order details
$order = [];
try {
    $stmt = $conn->prepare("
        SELECT o.*, 
               CONCAT(m.FirstName, ' ', m.LastName) AS CustomerName,
               f.flower_name, f.price
        FROM tbl_orders o
        LEFT JOIN tbl_members m ON o.UserEmail = m.EmailId
        LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID
        WHERE o.ID = :id
    ");
    $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['error'] = "ไม่พบคำสั่งซื้อที่ระบุ";
        header("Location: order-confirm.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: ' . htmlspecialchars($e->getMessage());
    header("Location: order-confirm.php");
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $new_status = intval($_POST['status']);
    $message = trim($_POST['message'] ?? '');

    if (!in_array($new_status, [1, 2])) {
        $_SESSION['error'] = 'สถานะที่เลือกไม่ถูกต้อง';
        header("Location: order-payment-confirm.php?order_id=" . $order_id);
        exit();
    }

    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            UPDATE tbl_orders 
            SET Status = :status, Message = :message, LastupdateDate = CURRENT_TIMESTAMP 
            WHERE ID = :id
        ");
        $stmt->bindValue(':status', $new_status, PDO::PARAM_INT);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
        $stmt->execute();
        $conn->commit();

        $_SESSION['success'] = 'อัปเดตสถานะคำสั่งซื้อเรียบร้อยแล้ว';
        header("Location: order-payment-confirm.php?order_id=" . $order_id);
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . htmlspecialchars($e->getMessage());
        header("Location: order-payment-confirm.php?order_id=" . $order_id);
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
    <title>ยืนยันการชำระเงิน - FlowerShop</title>
    <!-- LOGO -->
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Custom fonts -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <!-- Custom styles -->
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

        .slip-image {
            max-width: 300px;
            max-height: 300px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }

        .status-select {
            width: 100%;
            padding: 0.5rem;
            font-size: 1.2rem;
            border: 2px solid rgba(232, 67, 147, 0.2);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .status-select:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(232, 67, 147, 0.1);
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

        .status-new-slip {
            background-color: #3498db;
            color: #fff;
        }

        .status-option-paid {
            background-color: #2ecc71;
            color: #fff;
        }

        .status-option-edited {
            background-color: #e74c3c;
            color: #fff;
        }

        .alert-new-slip {
            background-color: #e8f4f8;
            color: #0c5460;
            font-size: 1.4rem;
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
                        <h1 class="h3 mb-0 text-gray-800">ยืนยันการชำระเงิน: #<?php echo htmlspecialchars($order['BookingNumber']); ?></h1>
                        <a href="order-confirm.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white"></i> กลับไปยังคำสั่งซื้อที่รอการยืนยัน
                        </a>
                    </div>

                    <div class="card shadow mb-4 order-detail-container">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ข้อมูลคำสั่งซื้อ</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($order['Status'] == 5): ?>
                                <div class="alert alert-new-slip" role="alert">
                                    <i class="fas fa-info-circle me-2"></i> ลูกค้าได้แก้ไขสลิปการโอนเงินใหม่แล้ว
                                </div>
                            <?php endif; ?>

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
                                        <th>สลิปการชำระเงิน</th>
                                        <td>
                                            <?php if (!empty($order['Image']) && file_exists("../uploads/slips/" . $order['Image'])): ?>
                                                <img src="../uploads/slips/<?php echo htmlspecialchars($order['Image']); ?>" alt="Payment Slip" class="slip-image">
                                            <?php else: ?>
                                                ไม่มีสลิป
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (!empty($order['Message'])): ?>
                                        <tr>
                                            <th>ข้อความจากแอดมิน</th>
                                            <td><?php echo htmlspecialchars($order['Message']); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <form method="POST" id="updateStatusForm">
                                <div class="form-group">
                                    <label for="status" class="font-weight-bold text-gray-700">
                                        <i class="fas fa-info-circle text-pink mr-2"></i>สถานะคำสั่งซื้อ
                                    </label>
                                    <?php
                                    $statusOptions = [
                                        1 => ['text' => 'การชำระเงินสำเร็จ', 'icon' => 'fa-check', 'class' => 'status-paid', 'option_class' => 'status-option-paid'],
                                        2 => ['text' => 'แก้ไขการชำระเงิน', 'icon' => 'fa-edit', 'class' => 'status-edited', 'option_class' => 'status-option-edited'],
                                        5 => ['text' => 'แนบสลิปใหม่', 'icon' => 'fa-upload', 'class' => 'status-new-slip', 'option_class' => '']
                                    ];
                                    $currentStatus = isset($statusOptions[$order['Status']]) ? $order['Status'] : 1;
                                    ?>
                                    <p class="status-label <?php echo $statusOptions[$currentStatus]['class']; ?>">
                                        <i class="fas <?php echo $statusOptions[$currentStatus]['icon']; ?> me-1"></i>
                                        <?php echo $statusOptions[$currentStatus]['text']; ?>
                                    </p>
                                    <select name="status" id="status" class="status-select" required>
                                        <option value="1" class="status-option-paid" <?php echo $currentStatus == 1 ? 'selected' : ''; ?>>การชำระเงินสำเร็จ</option>
                                        <option value="2" class="status-option-edited" <?php echo $currentStatus == 2 ? 'selected' : ''; ?>>แก้ไขการชำระเงิน</option>
                                    </select>
                                    <textarea name="message" id="messageInput"
                                        class="form-control mt-2"
                                        style="display: none;"
                                        placeholder="ระบุข้อความถึงลูกค้า (กรุณาระบุเหตุผลที่ต้องการให้แก้ไข)"><?php echo htmlspecialchars($order['Message'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-group d-flex justify-content-end">
                                    <button type="submit" class="btn btn-pink mr-2">
                                        <i class="fas fa-save mr-2"></i>บันทึก
                                    </button>
                                    <a href="order-confirm.php" class="btn btn-secondary">
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
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('status');
            const messageInput = document.getElementById('messageInput');

            // ฟังก์ชันตรวจสอบการแสดง/ซ่อน textarea
            function toggleMessageInput() {
                if (statusSelect.value === '2') {
                    messageInput.style.display = 'block';
                    messageInput.required = true;
                } else {
                    messageInput.style.display = 'none';
                    messageInput.required = false;
                    messageInput.value = '';
                }
            }

            // เรียกใช้ฟังก์ชันครั้งแรกเพื่อตั้งค่าเริ่มต้น
            toggleMessageInput();

            // เพิ่ม event listener สำหรับการเปลี่ยนแปลงค่า select
            statusSelect.addEventListener('change', toggleMessageInput);
        });
        document.getElementById('updateStatusForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const selectedStatus = document.getElementById('status').value;
            const statusText = document.getElementById('status').options[document.getElementById('status').selectedIndex].text;

            Swal.fire({
                title: 'ยืนยันการเปลี่ยนสถานะ',
                text: `คุณแน่ใจหรือไม่ที่จะเปลี่ยนสถานะเป็น "${statusText}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e84393',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        // แก้ไขส่วนแสดง success message
        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#e84393'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'order-confirm.php';
                }
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