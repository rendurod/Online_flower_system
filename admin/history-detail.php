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
    writeLog("Invalid order_id: $order_id. Redirecting to history.php");
    $_SESSION['error'] = "ไม่พบคำสั่งซื้อที่ระบุ";
    header("Location: history.php");
    exit();
}

// Fetch order details
$order = [];
try {
    $stmt = $conn->prepare("
        SELECT o.*, 
               CONCAT(m.FirstName, ' ', m.LastName) AS CustomerName,
               m.Address AS CustomerAddress,
               m.ContactNo AS CustomerContact,
               f.flower_name, f.price, f.image, f.stock_quantity
        FROM tbl_orders o
        LEFT JOIN tbl_members m ON o.UserEmail = m.EmailId
        LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID
        WHERE o.ID = :id
    ");
    $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        writeLog("Order not found for ID: $order_id. Redirecting to history.php");
        $_SESSION['error'] = "ไม่พบคำสั่งซื้อที่ระบุ";
        header("Location: history.php");
        exit();
    }
    writeLog("Fetched order details: " . json_encode($order));
} catch (PDOException $e) {
    writeLog("Error fetching order details: " . $e->getMessage());
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: ' . htmlspecialchars($e->getMessage());
    header("Location: history.php");
    exit();
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

        .order-image img {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: var(--border-radius);
            border: 2px solid rgba(232, 67, 147, 0.2);
        }

        .status-label {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .status-awaiting { background-color: #95a5a6; color: #fff; }
        .status-paid { background-color: #2ecc71; color: #fff; }
        .status-edited { background-color: #e74c3c; color: #fff; }
        .status-processing { background-color: #f1c40f; color: #fff; }
        .status-completed { background-color: #7bed9f; color: #fff; }

        .stock-highlight {
            color: #e74c3c;
            font-weight: bold;
            font-size: 1.6rem;
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
                        <a href="history.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white"></i> กลับไปยังประวัติคำสั่งซื้อ
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
                                        <td class="stock-highlight"><?php echo htmlspecialchars($order['stock_quantity'] ?? 'ไม่ระบุ'); ?> ชิ้น</td>
                                    </tr>
                                    <tr>
                                        <th>ที่อยู่</th>
                                        <td><?php echo htmlspecialchars($order['CustomerAddress'] ?? 'ไม่ระบุ'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>เบอร์ติดต่อ</th>
                                        <td><?php echo htmlspecialchars($order['CustomerContact'] ?? 'ไม่ระบุ'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>สถานะคำสั่งซื้อ</th>
                                        <td>
                                            <?php
                                            $statusOptions = [
                                                0 => ['text' => 'รอแจ้งชำระเงิน', 'icon' => 'fa-clock', 'class' => 'status-awaiting'],
                                                1 => ['text' => 'การชำระเงินสำเร็จ', 'icon' => 'fa-check', 'class' => 'status-paid'],
                                                2 => ['text' => 'แก้ไขการชำระเงิน', 'icon' => 'fa-edit', 'class' => 'status-edited'],
                                                3 => ['text' => 'กำลังดำเนินการ', 'icon' => 'fa-cog', 'class' => 'status-processing'],
                                                4 => ['text' => 'จัดส่งสำเร็จ', 'icon' => 'fa-check-circle', 'class' => 'status-completed']
                                            ];

                                            $currentStatus = isset($statusOptions[$order['Status']]) ? $order['Status'] : 0;
                                            ?>
                                            <p class="status-label <?php echo $statusOptions[$currentStatus]['class']; ?>">
                                                <i class="fas <?php echo $statusOptions[$currentStatus]['icon']; ?> me-1"></i>
                                                <?php echo $statusOptions[$currentStatus]['text']; ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>รูปภาพสินค้า</th>
                                        <td class="order-image">
                                            <img src="<?php echo !empty($order['image']) && file_exists("uploads/flowers/" . $order['image']) ? "uploads/flowers/" . htmlspecialchars($order['image']) : "img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>สลิปการชำระเงิน</th>
                                        <td>
                                            <?php if (!empty($order['Image'])): ?>
                                                <img src="../Uploads/slips/<?php echo htmlspecialchars($order['Image']); ?>" alt="Payment Slip" style="max-width: 200px; border-radius: var(--border-radius); border: 2px solid rgba(232, 67, 147, 0.2);">
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
        // console.log('jQuery loaded:', typeof jQuery !== 'undefined' ? 'Yes' : 'No');
        // console.log('SweetAlert2 loaded:', typeof Swal !== 'undefined' ? 'Yes' : 'No');

        <?php if (isset($_SESSION['success'])): ?>
            // console.log('Showing success SweetAlert with message: <?php echo htmlspecialchars($_SESSION['success']); ?>');
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
                timer: 3000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'history.php';
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            // console.log('Showing error SweetAlert with message: <?php echo htmlspecialchars($_SESSION['error']); ?>');
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