<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

// ฟังก์ชันสำหรับบันทึก log
function writeLog($message) {
    $logFile = 'debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
writeLog("Checking PDO connection");
if (!$conn) {
    writeLog("Database connection failed");
    $_SESSION['error'] = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
    header("Location: login.php");
    exit();
}

// ตรวจสอบว่ามี session adminid หรือไม่
if (!isset($_SESSION['adminid'])) {
    writeLog("No admin session found. Redirecting to login.php");
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
        writeLog("Admin not found for ID: $admin_id. Redirecting to login.php");
        $_SESSION['error'] = "ไม่พบข้อมูลผู้ดูแลระบบ";
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    writeLog("Error fetching admin data: " . $e->getMessage());
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ดูแลระบบ: " . htmlspecialchars($e->getMessage());
    header("Location: login.php");
    exit();
}

// ดึง order_id จาก query string
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    writeLog("Invalid order_id: $order_id. Redirecting to order-success.php");
    $_SESSION['error'] = "ไม่พบคำสั่งซื้อที่ระบุ";
    header("Location: order-success.php");
    exit();
}

// Fetch order details with locking
$order = [];
try {
    $stmt = $conn->prepare("
        SELECT o.*, 
               CONCAT(m.FirstName, ' ', m.LastName) AS CustomerName,
               m.Address AS CustomerAddress,
               f.flower_name, f.price, f.stock_quantity
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
        writeLog("Order not found for ID: $order_id. Redirecting to order-success.php");
        $_SESSION['error'] = "ไม่พบคำสั่งซื้อที่ระบุ";
        header("Location: order-success.php");
        exit();
    }
    writeLog("Fetched order details: " . json_encode($order));
} catch (PDOException $e) {
    writeLog("Error fetching order details: " . $e->getMessage());
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: ' . htmlspecialchars($e->getMessage());
    header("Location: order-success.php");
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    writeLog("Received POST request: " . json_encode($_POST));

    $new_status = intval($_POST['status']);
    $message = isset($_POST['message']) ? trim($_POST['message']) : ($order['Message'] ?? '');
    $current_status = $order['Status'];

    // ตรวจสอบสถานะใหม่
    $valid_statuses = [1, 3]; // จำกัดเฉพาะสถานะ 1, 3
    if (!in_array($new_status, $valid_statuses)) {
        writeLog("Invalid new status: $new_status");
        $_SESSION['error'] = 'สถานะที่เลือกไม่ถูกต้อง';
        header("Location: order-success-confirm.php?order_id=" . $order_id);
        exit();
    }

    writeLog("Current status: $current_status, New status: $new_status, Message: $message");

    try {
        writeLog("Starting transaction");
        $conn->beginTransaction();

        // Update order status and message
        $sql = "UPDATE tbl_orders SET Status = :status, Message = :message, LastupdateDate = CURRENT_TIMESTAMP WHERE ID = :id";
        writeLog("Executing SQL for tbl_orders: $sql with params [status: $new_status, message: $message, id: $order_id]");
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':status', $new_status, PDO::PARAM_INT);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
        $result = $stmt->execute();
        writeLog("SQL execution result for tbl_orders: " . ($result ? 'Success' : 'Failed'));

        // Fetch current stock quantity with locking
        $sql = "SELECT stock_quantity FROM tbl_flowers WHERE ID = :flower_id FOR UPDATE";
        writeLog("Executing SQL to fetch stock: $sql with params [flower_id: {$order['FlowerId']}]");
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':flower_id', $order['FlowerId'], PDO::PARAM_INT);
        $stmt->execute();
        $current_stock = $stmt->fetchColumn();
        writeLog("Current stock quantity for Flower ID {$order['FlowerId']}: $current_stock");

        // Define stock-affecting statuses
        $stock_reducing_statuses = [1, 3]; // Paid, Processing
        $order_quantity = $order['Quantity'];
        writeLog("Order quantity: $order_quantity");

        // Handle stock adjustments
        if (!in_array($current_status, $stock_reducing_statuses) && in_array($new_status, $stock_reducing_statuses)) {
            writeLog("Moving to stock-reducing status. Checking stock...");
            if ($current_stock >= $order_quantity) {
                $new_stock = $current_stock - $order_quantity;
                $sql = "UPDATE tbl_flowers SET stock_quantity = :stock WHERE ID = :flower_id";
                writeLog("Reducing stock. Executing SQL: $sql with params [stock: $new_stock, flower_id: {$order['FlowerId']}]");
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':stock', $new_stock, PDO::PARAM_INT);
                $stmt->bindValue(':flower_id', $order['FlowerId'], PDO::PARAM_INT);
                $result = $stmt->execute();
                writeLog("SQL execution result for tbl_flowers: " . ($result ? 'Success' : 'Failed'));
            } else {
                writeLog("Insufficient stock. Current stock: $current_stock, Required: $order_quantity");
                $conn->rollBack();
                $_SESSION['error'] = 'จำนวนสต็อกไม่เพียงพอสำหรับคำสั่งซื้อนี้';
                header("Location: order-success-confirm.php?order_id=" . $order_id);
                exit();
            }
        } elseif (in_array($current_status, $stock_reducing_statuses) && !in_array($new_status, $stock_reducing_statuses)) {
            writeLog("Moving to non-stock-reducing status. Restoring stock...");
            $new_stock = $current_stock + $order_quantity;
            $sql = "UPDATE tbl_flowers SET stock_quantity = :stock WHERE ID = :flower_id";
            writeLog("Restoring stock. Executing SQL: $sql with params [stock: $new_stock, flower_id: {$order['FlowerId']}]");
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':stock', $new_stock, PDO::PARAM_INT);
            $stmt->bindValue(':flower_id', $order['FlowerId'], PDO::PARAM_INT);
            $result = $stmt->execute();
            writeLog("SQL execution result for tbl_flowers: " . ($result ? 'Success' : 'Failed'));
        } else {
            writeLog("No stock adjustment needed.");
        }

        $conn->commit();
        writeLog("Transaction committed successfully");
        $_SESSION['success'] = 'อัปเดตสถานะและสต็อกเรียบร้อยแล้ว';
        header("Location: order-success-confirm.php?order_id=" . $order_id);
        exit();
    } catch (PDOException $e) {
        writeLog("Transaction failed: " . $e->getMessage());
        $conn->rollBack();
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . htmlspecialchars($e->getMessage());
        header("Location: order-success-confirm.php?order_id=" . $order_id);
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

    <title>ยืนยันคำสั่งซื้อที่รอดำเนินการ - FlowerShop</title>

    <!-- LOGO -->
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Custom fonts for this template -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .order-detail-container {
            margin: 0 auto;
            padding: 2rem;
        }

        .table th, .table td {
            vertical-align: middle;
            font-size: 1.4rem;
        }

        .table th {
            width: 30%;
            background-color: #f8f9fa;
            font-weight: bold;
            color: #4e73df;
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

        .status-paid { background-color: #2ecc71; color: #fff; }
        .status-processing { background-color: #f1c40f; color: #fff; }

        .stock-highlight {
            color: #e74c3c;
            font-weight: bold;
            font-size: 1.6rem;
        }

        .status-option-1 { background-color: #2ecc71; color: #fff; }
        .status-option-3 { background-color: #f1c40f; color: #fff; }

        #messageInput {
            display: none;
            width: 100%;
            padding: 0.5rem;
            font-size: 1.2rem;
            border: 2px solid rgba(232, 67, 147, 0.2);
            border-radius: var(--border-radius);
            margin-top: 0.5rem;
        }

        #messageInput:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(232, 67, 147, 0.1);
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
                        <h1 class="h3 mb-0 text-gray-800">ยืนยันคำสั่งซื้อ: #<?php echo htmlspecialchars($order['BookingNumber']); ?></h1>
                        <a href="order-success.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white"></i> กลับไปยังคำสั่งซื้อที่รอดำเนินการ
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
                                        <th>ที่อยู่จัดส่ง</th>
                                        <td><?php echo htmlspecialchars($order['CustomerAddress'] ?? 'ไม่ระบุ'); ?></td>
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
                                        <td class="stock-highlight"><?php echo htmlspecialchars($order['stock_quantity'] ?? 'ไม่ระบุ'); ?> ชิ้น</td>
                                    </tr>
                                    <?php if (!empty($order['Message'])): ?>
                                        <tr>
                                            <th>ข้อความจากแอดมิน</th>
                                            <td><?php echo htmlspecialchars($order['Message']); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <form method="POST" id="updateStatusForm" action="order-success-confirm.php?order_id=<?php echo $order_id; ?>">
                                <div class="form-group">
                                    <label for="status" class="font-weight-bold text-gray-700">
                                        <i class="fas fa-info-circle text-pink mr-2"></i>สถานะคำสั่งซื้อ
                                    </label>
                                    <?php
                                    $statusOptions = [
                                        1 => ['text' => 'การชำระเงินสำเร็จ', 'icon' => 'fa-check', 'class' => 'status-paid', 'option_class' => 'status-option-1'],
                                        3 => ['text' => 'กำลังดำเนินการ', 'icon' => 'fa-truck', 'class' => 'status-processing', 'option_class' => 'status-option-3']
                                    ];
                                    $currentStatus = isset($statusOptions[$order['Status']]) ? $order['Status'] : 1;
                                    ?>
                                    <p class="status-label <?php echo $statusOptions[$currentStatus]['class']; ?>">
                                        <i class="fas <?php echo $statusOptions[$currentStatus]['icon']; ?> me-1"></i>
                                        <?php echo $statusOptions[$currentStatus]['text']; ?>
                                    </p>
                                    <select name="status" id="status" class="status-select" required>
                                        <?php foreach ($statusOptions as $status => $details): ?>
                                            <option value="<?php echo $status; ?>" class="<?php echo $details['option_class']; ?>" <?php echo $status == $currentStatus ? 'selected' : ''; ?>>
                                                <?php echo $details['text']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="message" id="messageInput" placeholder="กรุณาระบุเหตุผล" value="<?php echo htmlspecialchars($order['Message'] ?? ''); ?>">
                                </div>

                                <div class="form-group d-flex justify-content-end">
                                    <button type="submit" name="update_status" value="1" class="btn btn-pink mr-2">
                                        <i class="fas fa-save mr-2"></i>บันทึก
                                    </button>
                                    <a href="order-success.php" class="btn btn-secondary">
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>

    <script>
        console.log('jQuery loaded:', typeof jQuery !== 'undefined' ? 'Yes' : 'No');
        console.log('SweetAlert2 loaded:', typeof Swal !== 'undefined' ? 'Yes' : 'No');

        // SweetAlert2 confirmation before updating status
        document.getElementById('updateStatusForm').addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Form submit event triggered');
            const form = this;
            const selectedStatus = document.getElementById('status').value;
            const statusText = document.getElementById('status').options[document.getElementById('status').selectedIndex].text.trim();
            let confirmMessage = `คุณแน่ใจหรือไม่ที่จะเปลี่ยนสถานะเป็น "${statusText}"?`;

            if (selectedStatus === '3') {
                confirmMessage += `\nสต็อกสินค้าจะถูกลดลงตามจำนวนที่สั่งซื้อ (${<?php echo $order['Quantity']; ?>} ชิ้น)`;
            }

            console.log('Form data before submit:', new FormData(form));
            Swal.fire({
                title: 'ยืนยันการเปลี่ยนสถานะ',
                text: confirmMessage,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e84393',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Form submitted with status: ' + selectedStatus);
                    form.submit();
                } else {
                    console.log('Form submission cancelled');
                }
            });
        });

        // Show/hide message input based on status
        document.getElementById('status').addEventListener('change', function() {
            console.log('Status changed to: ' + this.value);
            const messageInput = document.getElementById('messageInput');
            messageInput.style.display = 'none';
            messageInput.required = false;
            messageInput.value = '';
        });

        // Initial check for message input visibility
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM fully loaded');
            const messageInput = document.getElementById('messageInput');
            messageInput.style.display = 'none';
            messageInput.required = false;
        });

        <?php if (isset($_SESSION['success'])): ?>
            console.log('Showing success SweetAlert with message: <?php echo htmlspecialchars($_SESSION['success']); ?>');
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
                timer: 3000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'order-success.php';
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            console.log('Showing error SweetAlert with message: <?php echo htmlspecialchars($_SESSION['error']); ?>');
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