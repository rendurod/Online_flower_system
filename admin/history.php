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

// ดึงข้อมูล username จากฐานข้อมูล
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

// Get filter status
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$where_clause = '';
$params = [];

if ($filter_status !== 'all' && in_array($filter_status, ['0', '1', '2', '3', '4', '5', '6'])) {
    $where_clause = "WHERE o.Status = :status";
    $params[':status'] = intval($filter_status);
}

// Fetch all orders
$orders = [];
try {
    $sql = "
        SELECT o.ID, o.BookingNumber, o.Quantity, o.DeliveryDate, o.Status, o.PostingDate,
               CONCAT(m.FirstName, ' ', m.LastName) AS CustomerName,
               f.flower_name
        FROM tbl_orders o
        LEFT JOIN tbl_members m ON o.UserEmail = m.EmailId
        LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID
        $where_clause
        ORDER BY o.PostingDate DESC
    ";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: ' . htmlspecialchars($e->getMessage());
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

    <title>ประวัติคำสั่งซื้อ - FlowerShop</title>

    <!-- LOGO -->
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Custom fonts for this template -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .status-label {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
        }

        .status-awaiting { background-color: #95a5a6; color: #fff; }
        .status-paid { background-color: #2ecc71; color: #fff; }
        .status-edited { background-color: #e74c3c; color: #fff; }
        .status-processing { background-color: #f1c40f; color: #fff; }
        .status-completed { background-color: #7bed9f; color: #fff; }
        .status-new-slip { background-color: #3498db; color: #fff; }
        .status-cancel { background-color: #e74c3c; color: #000; }

        .table th, .table td {
            vertical-align: middle;
            font-size: 1rem;
        }

        .filter-container {
            margin-bottom: 1rem;
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
                        <h1 class="h3 mb-0 text-gray-800">ประวัติคำสั่งซื้อทั้งหมด</h1>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ตารางประวัติคำสั่งซื้อ</h6>
                        </div>
                        <div class="card-body">
                            <div class="filter-container">
                                <form method="GET" class="form-inline">
                                    <label for="status" class="mr-2">กรองตามสถานะ:</label>
                                    <select name="status" id="status" class="form-control mr-2" onchange="this.form.submit()">
                                        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                                        <option value="0" <?php echo $filter_status == '0' ? 'selected' : ''; ?>>รอแจ้งชำระเงิน</option>
                                        <option value="1" <?php echo $filter_status == '1' ? 'selected' : ''; ?>>การชำระเงินสำเร็จ</option>
                                        <option value="2" <?php echo $filter_status == '2' ? 'selected' : ''; ?>>แก้ไขการชำระเงิน</option>
                                        <option value="3" <?php echo $filter_status == '3' ? 'selected' : ''; ?>>กำลังจัดส่งสินค้า</option>
                                        <option value="4" <?php echo $filter_status == '4' ? 'selected' : ''; ?>>คำสั่งซื้อสำเร็จ</option>
                                        <option value="5" <?php echo $filter_status == '5' ? 'selected' : ''; ?>>แนบสลิปใหม่</option>
                                        <option value="6" <?php echo $filter_status == '6' ? 'selected' : ''; ?>>ยกเลิกคำสั่งซื้อ</option>
                                    </select>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ลำดับ</th>
                                            <th>หมายเลขคำสั่งซื้อ</th>
                                            <th>ชื่อลูกค้า</th>
                                            <th>สินค้าที่เลือก</th>
                                            <th>จำนวนที่สั่ง</th>
                                            <th>วันที่ต้องจัดส่ง</th>
                                            <th>สถานะ</th>
                                            <th class="no-sort text-center">ดูรายละเอียด</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($orders)): ?>
                                            <?php $index = 1; ?>
                                            <?php foreach ($orders as $order): ?>
                                                <tr>
                                                    <td><?php echo $index++; ?></td>
                                                    <td><?php echo htmlspecialchars($order['BookingNumber']); ?></td>
                                                    <td><?php echo htmlspecialchars($order['CustomerName'] ?? 'ไม่ระบุ'); ?></td>
                                                    <td><?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></td>
                                                    <td><?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</td>
                                                    <td><?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></td>
                                                    <td>
                                                        <?php
                                                        $statusOptions = [
                                                            0 => ['text' => 'รอแจ้งชำระเงิน', 'class' => 'status-awaiting', 'icon' => 'fa-clock'],
                                                            1 => ['text' => 'การชำระเงินสำเร็จ', 'class' => 'status-paid', 'icon' => 'fa-check'],
                                                            2 => ['text' => 'แก้ไขการชำระเงิน', 'class' => 'status-edited', 'icon' => 'fa-edit'],
                                                            3 => ['text' => 'กำลังจัดส่งสินค้า', 'class' => 'status-processing', 'icon' => 'fa-truck'],
                                                            4 => ['text' => 'คำสั่งซื้อสำเร็จ', 'class' => 'status-completed', 'icon' => 'fa-check-circle'],
                                                            5 => ['text' => 'แนปสลิปใหม่', 'class' => 'status-new-slip', 'icon' => 'fa-upload'],
                                                            6 => ['text' => 'ยกเลิกคำสั่งซื้อ', 'class' => 'status-cancel', 'icon' => 'fa-times-circle']
                                                        ];
                                                        $status = isset($statusOptions[$order['Status']]) ? $order['Status'] : 0;
                                                        ?>
                                                        <span class="status-label <?php echo $statusOptions[$status]['class']; ?>">
                                                            <i class="fas <?php echo $statusOptions[$status]['icon']; ?> me-1"></i>
                                                            <?php echo $statusOptions[$status]['text']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="history-detail.php?order_id=<?php echo htmlspecialchars($order['ID']); ?>" class="btn btn-pink">
                                                            <i class="fas fa-eye me-1"></i> ดูรายละเอียด
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center">ไม่มีข้อมูลคำสั่งซื้อ</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
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
    <script src="vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="js/demo/datatables-demo.js"></script>
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