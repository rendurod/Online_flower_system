<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

// Check if adminid session exists
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

// Fetch admin data
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

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = intval($_POST['new_status']);

    if (!in_array($new_status, [3, 4])) {
        $_SESSION['error'] = 'สถานะที่เลือกไม่ถูกต้อง';
        header("Location: order-finish.php");
        exit();
    }

    try {
        $conn->beginTransaction();

        // Update order status
        $sql = "UPDATE tbl_orders SET Status = :status, LastupdateDate = CURRENT_TIMESTAMP WHERE ID = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':status', $new_status, PDO::PARAM_INT);
        $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
        $result = $stmt->execute();

        $conn->commit();
        $_SESSION['success'] = 'อัปเดตสถานะคำสั่งซื้อเรียบร้อยแล้ว';
        header("Location: order-finish.php");
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการอัปเดตสถานะ: ' . htmlspecialchars($e->getMessage());
        header("Location: order-finish.php");
        exit();
    }
}

// Fetch orders with status 3 or 4
$orders = [];
try {
    $stmt = $conn->prepare("
        SELECT o.ID, o.BookingNumber, o.Quantity, o.DeliveryDate, o.Status, o.PostingDate,
               COALESCE(CONCAT(m.FirstName, ' ', m.LastName), 'ไม่ระบุชื่อ') AS CustomerName,
               COALESCE(f.flower_name, 'ไม่ระบุสินค้า') AS flower_name
        FROM tbl_orders o
        LEFT JOIN tbl_members m ON o.UserEmail = m.EmailId
        LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID
        WHERE o.Status IN (3, 4)
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
    <meta name="description" content="">
    <meta name="author" content="">

    <title>คำสั่งซื้อที่เสร็จสิ้น - FlowerShop</title>

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

        .status-processing {
            background-color: #f1c40f;
            color: #fff;
        }

        .status-completed {
            background-color: #7bed9f;
            color: #fff;
        }

        .btn-toggle-status {
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
        }

        .btn-to-completed {
            background-color: #2ecc71;
            color: #fff;
        }

        .btn-to-processing {
            background-color: #e74c3c;
            color: #fff;
        }

        .table th,
        .table td {
            vertical-align: middle;
            font-size: 1rem;
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
                        <h1 class="h3 mb-0 text-gray-800">คำสั่งซื้อที่เสร็จสิ้น</h1>
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
                                    <option value="3" <?php echo isset($_GET['status']) && $_GET['status'] === '3' ? 'selected' : ''; ?>>กำลังจัดส่ง</option>
                                    <option value="4" <?php echo isset($_GET['status']) && $_GET['status'] === '4' ? 'selected' : ''; ?>>คำสั่งซื้อสำเร็จ</option>
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
                                                    <td>
                                                        <a href="history-detail.php?order_id=<?php echo htmlspecialchars($order['ID']); ?>" class="text-primary">
                                                            <?php echo htmlspecialchars($order['BookingNumber']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($order['CustomerName']); ?></td>
                                                    <td><?php echo htmlspecialchars($order['flower_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</td>
                                                    <td>
                                                        <?php
                                                        if ($order['DeliveryDate'] && strtotime($order['DeliveryDate']) !== false) {
                                                            echo date('d/m/Y', strtotime($order['DeliveryDate']));
                                                        } else {
                                                            echo 'ไม่ระบุวันที่';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $statusOptions = [
                                                            3 => ['text' => 'กำลังจัดส่ง', 'class' => 'status-processing', 'icon' => 'fa-truck'],
                                                            4 => ['text' => 'คำสั่งซื้อสำเร็จ', 'class' => 'status-completed', 'icon' => 'fa-check-circle']
                                                        ];
                                                        $status = isset($statusOptions[$order['Status']]) ? $order['Status'] : 3;
                                                        ?>
                                                        <span class="status-label <?php echo $statusOptions[$status]['class']; ?>">
                                                            <i class="fas <?php echo $statusOptions[$status]['icon']; ?> me-1"></i>
                                                            <?php echo $statusOptions[$status]['text']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <form method="POST" class="status-form" style="display:inline;">
                                                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['ID']); ?>">
                                                            <input type="hidden" name="new_status" value="<?php echo $order['Status'] == 3 ? 4 : 3; ?>">
                                                            <button type="submit" class="btn btn-toggle-status <?php echo $order['Status'] == 3 ? 'btn-to-completed' : 'btn-to-processing'; ?>">
                                                                <i class="fas <?php echo $order['Status'] == 3 ? 'fa-check' : 'fa-undo'; ?> me-1"></i>
                                                                <?php echo $order['Status'] == 3 ? 'เปลี่ยนเป็นสำเร็จ' : 'เปลี่ยนเป็นกำลังจัดส่ง'; ?>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center">ไม่มีคำสั่งซื้อที่เสร็จสิ้น</td>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>

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
                var status = data[6]; // Status column

                if (!selectedStatus) {
                    return true; // Show all if no filter selected
                }

                if (selectedStatus === '3' && status.includes('กำลังจัดส่ง')) {
                    return true;
                }
                if (selectedStatus === '4' && status.includes('คำสั่งซื้อสำเร็จ')) {
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
            if (initialStatus && ['3', '4'].includes(initialStatus)) {
                $('#statusFilter').val(initialStatus);
                table.draw();
            }

            // SweetAlert2 confirmation for status change
            document.querySelectorAll('.status-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const form = this;
                    const newStatus = form.querySelector('input[name="new_status"]').value;
                    const statusText = newStatus == 4 ? 'คำสั่งซื้อสำเร็จ' : 'กำลังจัดส่ง';

                    Swal.fire({
                        title: 'ยืนยันการเปลี่ยนสถานะ',
                        text: `คุณแน่ใจหรือไม่ที่จะเปลี่ยนสถานะเป็น "${statusText}"?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#2ecc71',
                        cancelButtonColor: '#e74c3c',
                        confirmButtonText: 'ยืนยัน',
                        cancelButtonText: 'ยกเลิก'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });
        });

        <?php if (isset($_SESSION['success'])): ?>
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