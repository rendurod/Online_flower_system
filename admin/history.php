<?php
session_start();
require_once 'config/db.php';

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
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ดูแลระบบ: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    header("Location: login.php");
    exit();
}

// Get filter status
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$where_clause = '';
$params = [];

if ($filter_status !== 'all' && in_array($filter_status, ['0', '1', '2', '3', '4'])) {
    $where_clause = "WHERE o.Status = :status";
    $params[':status'] = intval($filter_status);
}

// Fetch all orders
$orders = [];
try {
    // Fetch base order details
    $stmt = $conn->prepare("
        SELECT o.ID, o.BookingNumber, o.DeliveryDate, o.Status, o.PostingDate, o.SumTotal,
               CONCAT(m.FirstName, ' ', m.LastName) AS CustomerName,
               f.flower_name, f.price
        FROM tbl_orders o
        LEFT JOIN tbl_members m ON o.UserEmail = m.EmailId
        LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID
        $where_clause
        ORDER BY o.PostingDate DESC
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $raw_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group orders and fetch items from tbl_order_details
    foreach ($raw_orders as $order) {
        $order_id = $order['ID'];
        $items = [];

        // Fetch items from tbl_order_details
        $items_stmt = $conn->prepare("
            SELECT od.Quantity, od.Price, f.flower_name
            FROM tbl_order_details od
            JOIN tbl_flowers f ON od.FlowerId = f.ID
            WHERE od.OrderId = :order_id
        ");
        $items_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
        $items_stmt->execute();
        $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total from items
        $calculated_total = 0;
        if (!empty($order_items)) {
            // Multi-item order
            $items = $order_items;
            foreach ($items as $item) {
                $calculated_total += $item['Price'] * $item['Quantity'];
            }
        } else {
            // Single-item order
            $items[] = [
                'flower_name' => $order['flower_name'] ?? 'ไม่ระบุ',
                'Quantity' => $order['Quantity'] ?? 1,
                'Price' => $order['price'] ?? 0
            ];
            $calculated_total = ($order['price'] ?? 0) * ($order['Quantity'] ?? 1);
        }

        // Update SumTotal if mismatched
        if (abs($calculated_total - $order['SumTotal']) > 0.01) {
            try {
                $update_stmt = $conn->prepare("UPDATE tbl_orders SET SumTotal = :total_amount WHERE ID = :order_id");
                $update_stmt->bindValue(':total_amount', $calculated_total, PDO::PARAM_STR);
                $update_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
                $update_stmt->execute();
            } catch (PDOException $e) {
                $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตยอดรวม: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
            $order['SumTotal'] = $calculated_total;
        }

        $orders[$order_id] = [
            'BookingNumber' => $order['BookingNumber'],
            'CustomerName' => $order['CustomerName'],
            'DeliveryDate' => $order['DeliveryDate'],
            'Status' => $order['Status'],
            'PostingDate' => $order['PostingDate'],
            'SumTotal' => $order['SumTotal'],
            'Items' => $items
        ];
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
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
        .status-processing { background-color: #f1c40f; color: #fff; }
        .status-completed { background-color: #6f42c1; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }

        .table th, .table td {
            vertical-align: middle;
            font-size: 1rem;
        }

        .filter-container {
            margin-bottom: 1rem;
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
                                        <option value="1" <?php echo $filter_status == '1' ? 'selected' : ''; ?>>ชำระเงินสำเร็จ</option>
                                        <option value="2" <?php echo $filter_status == '2' ? 'selected' : ''; ?>>กำลังจัดส่งสินค้า</option>
                                        <option value="3" <?php echo $filter_status == '3' ? 'selected' : ''; ?>>คำสั่งซื้อสำเร็จ</option>
                                        <option value="4" <?php echo $filter_status == '4' ? 'selected' : ''; ?>>ยกเลิกคำสั่งซื้อ</option>
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
                                            <th>จำนวนรวม</th>
                                            <th>ยอดรวม</th>
                                            <th>วันที่ต้องจัดส่ง</th>
                                            <th class="text-center">สถานะ</th>
                                            <th class="no-sort text-center">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($orders)): ?>
                                            <?php $index = 1; ?>
                                            <?php foreach ($orders as $order_id => $order): ?>
                                                <tr>
                                                    <td><?php echo $index++; ?></td>
                                                    <td><?php echo htmlspecialchars($order['BookingNumber'] ?? ''); ?><?php if (count($order['Items']) > 1): ?> <span class="badge badge-multi ms-2">หลายรายการ</span><?php endif; ?></td>
                                                    <td><?php echo htmlspecialchars($order['CustomerName'] ?? 'ไม่ระบุ'); ?></td>
                                                    <td>
                                                        <?php if (count($order['Items']) > 1): ?>
                                                            <ul class="item-list">
                                                                <?php foreach ($order['Items'] as $item): ?>
                                                                    <li><?php echo htmlspecialchars($item['flower_name'] ?? 'ไม่ระบุ'); ?> (<?php echo htmlspecialchars($item['Quantity'] ?? 0); ?> ชิ้น)</li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php else: ?>
                                                            <?php echo htmlspecialchars($order['Items'][0]['flower_name'] ?? 'ไม่ระบุ'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo array_sum(array_column($order['Items'], 'Quantity')); ?> ชิ้น</td>
                                                    <td class="text-danger">฿<?php echo number_format($order['SumTotal'], 2); ?></td>
                                                    <td><?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></td>
                                                    <td class="text-center">
                                                        <?php
                                                        $status_options = [
                                                            0 => ['text' => 'รอแจ้งชำระเงิน', 'class' => 'status-awaiting', 'icon' => 'fa-clock'],
                                                            1 => ['text' => 'ชำระเงินสำเร็จ', 'class' => 'status-paid', 'icon' => 'fa-check'],
                                                            2 => ['text' => 'กำลังจัดส่งสินค้า', 'class' => 'status-processing', 'icon' => 'fa-truck'],
                                                            3 => ['text' => 'คำสั่งซื้อสำเร็จ', 'class' => 'status-completed', 'icon' => 'fa-check-circle'],
                                                            4 => ['text' => 'ยกเลิกคำสั่งซื้อ', 'class' => 'status-cancelled', 'icon' => 'fa-times-circle']
                                                        ];
                                                        $status = isset($status_options[$order['Status']]) ? $order['Status'] : 0;
                                                        ?>
                                                        <span class="status-label <?php echo $status_options[$status]['class']; ?>">
                                                            <i class="fas <?php echo $status_options[$status]['icon']; ?> me-1"></i>
                                                            <?php echo $status_options[$status]['text']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="history-detail.php?order_id=<?php echo htmlspecialchars($order_id); ?>" class="btn btn-pink">
                                                            <i class="fas fa-eye me-1"></i> ดูรายละเอียด
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center">ไม่มีข้อมูลคำสั่งซื้อ</td>
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
        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: '<?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: '<?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
</body>
</html>