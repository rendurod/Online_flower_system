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

// Fetch orders with status 1 or 2
$orders = [];
try {
    $stmt = $conn->prepare("
        SELECT o.ID, o.BookingNumber, o.Quantity, o.DeliveryDate, o.Status, o.PostingDate,
               CONCAT(m.FirstName, ' ', m.LastName) AS CustomerName,
               f.flower_name
        FROM tbl_orders o
        LEFT JOIN tbl_members m ON o.UserEmail = m.EmailId
        LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID
        WHERE o.Status IN (1, 2, 5)
        ORDER BY o.PostingDate DESC
    ");
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
    <title>ยืนยันการชำระเงิน - FlowerShop</title>
    <!-- LOGO -->
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Custom fonts -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <!-- Custom styles -->
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

        .table th,
        .table td {
            vertical-align: middle;
            font-size: 1rem;
        }

        .filter-container {
            margin-bottom: 1.5rem;
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
                        <h1 class="h3 mb-0 text-gray-800">ยืนยันการชำระเงิน</h1>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ตารางข้อมูลคำสั่งซื้อ</h6>
                        </div>
                        <div class="card-body">
                            <!-- Status Filter -->
                            <div class="filter-container">
                                <label for="statusFilter" class="me-2">กรองตามสถานะ:</label>
                                <select id="statusFilter" class="form-control" style="width: auto; display: inline-block;">
                                    <option value="">ทั้งหมด</option>
                                    <option value="1" <?php echo isset($_GET['status']) && $_GET['status'] == '1' ? 'selected' : ''; ?>>การชำระเงินสำเร็จ</option>
                                    <option value="2" <?php echo isset($_GET['status']) && $_GET['status'] == '2' ? 'selected' : ''; ?>>แก้ไขการชำระเงิน</option>
                                    <option value="5" <?php echo isset($_GET['status']) && $_GET['status'] == '5' ? 'selected' : ''; ?>>แนปสลิปใหม่</option>
                                </select>
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
                                            <th class="no-sort text-center">จัดการ</th>
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
                                                            1 => ['text' => 'การชำระเงินสำเร็จ', 'class' => 'status-paid', 'icon' => 'fa-check'],
                                                            2 => ['text' => 'แก้ไขการชำระเงิน', 'class' => 'status-edited', 'icon' => 'fa-edit'],
                                                            5 => ['text' => 'แนบสลิปใหม่', 'icon' => 'fa-upload', 'class' => 'status-new-slip', 'option_class' => '']

                                                        ];
                                                        $status = isset($statusOptions[$order['Status']]) ? $order['Status'] : 1;
                                                        ?>
                                                        <span class="status-label <?php echo $statusOptions[$status]['class']; ?>">
                                                            <i class="fas <?php echo $statusOptions[$status]['icon']; ?> me-1"></i>
                                                            <?php echo $statusOptions[$status]['text']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="order-payment-confirm.php?order_id=<?php echo htmlspecialchars($order['ID']); ?>" class="btn btn-pink">
                                                            <i class="fas fa-eye me-1"></i> ดูรายละเอียด
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center">ไม่มีคำสั่งซื้อที่รอการยืนยัน</td>
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
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#dataTable').DataTable({
                "columnDefs": [{
                    "orderable": false,
                    "targets": "no-sort"
                }],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Thai.json"
                }
            });

            // Custom filtering function
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                var selectedStatus = $('#statusFilter').val();
                var statusCell = $(table.cell(dataIndex, 6).node()).text().trim();

                // ถ้าไม่ได้เลือกสถานะ (ทั้งหมด)
                if (!selectedStatus) {
                    return true;
                }

                // ตรวจสอบสถานะ
                if (selectedStatus === '1' && statusCell.includes('การชำระเงินสำเร็จ')) {
                    return true;
                }
                if (selectedStatus === '2' && statusCell.includes('แก้ไขการชำระเงิน')) {
                    return true;
                }
                if (selectedStatus === '5' && statusCell.includes('แนบสลิปใหม่')) {
                    return true;
                }

                return false;
            });

            // Event listener for status filter
            $('#statusFilter').on('change', function() {
                table.draw(); // Redraw the table with the filter
            });

            // Set initial filter if needed
            var urlParams = new URLSearchParams(window.location.search);
            var initialStatus = urlParams.get('status');
            if (initialStatus) {
                $('#statusFilter').val(initialStatus);
                table.draw();
            }
        });
    </script>
</body>

</html>