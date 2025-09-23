<?php
session_start();
require_once 'config/db.php';

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

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filter_query = '';
$filter_params = [];

// Build query based on filter
if ($filter === 'admin') {
    $filter_query = " AND o.Message LIKE '%//จากFlowerTeam'";
} elseif ($filter === 'user') {
    $filter_query = " AND (o.AccountName IS NOT NULL AND o.AccountNumber IS NOT NULL AND o.Message NOT LIKE '%//จากFlowerTeam')";
}

// Fetch cancelled orders
$orders = [];
try {
    $sql = "
        SELECT o.*, 
               m.ID as MemberID,
               CONCAT(m.FirstName, ' ', m.LastName) AS CustomerName,
               f.flower_name, f.price
        FROM tbl_orders o
        LEFT JOIN tbl_members m ON o.UserEmail = m.EmailId
        LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID
        WHERE o.Status = 4 $filter_query
        ORDER BY o.LastupdateDate DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($filter_params);
    $raw_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process orders and fetch items from tbl_order_details
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
                $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตยอดรวม: " . htmlspecialchars($e->getMessage());
            }
            $order['SumTotal'] = $calculated_total;
        }

        $orders[$order_id] = [
            'ID' => $order['ID'],
            'BookingNumber' => $order['BookingNumber'],
            'CustomerName' => $order['CustomerName'],
            'MemberID' => $order['MemberID'],
            'flower_name' => $order['flower_name'],
            'LastupdateDate' => $order['LastupdateDate'],
            'Message' => $order['Message'],
            'SumTotal' => $order['SumTotal'],
            'Items' => $items
        ];
    }
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
    <title>คำสั่งซื้อที่ยกเลิก - FlowerShop</title>
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .status-label {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
        }

        .status-cancelled {
            background-color: #dc3545;
            color: #fff;
        }

        .status-refunded {
            background-color: #28a745;
            color: #fff;
        }

        .status-not-refunded {
            background-color: #dc3545;
            color: #fff;
        }

        .btn-view-details {
            background-color: #4e73df;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .btn-view-details:hover {
            background-color: #3b5cb3;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(78, 115, 223, 0.3);
        }

        .btn-view-details:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(78, 115, 223, 0.2);
        }

        .filter-select {
            width: 200px;
            padding: 0.5rem;
            font-size: 1rem;
            border: 2px solid rgba(232, 67, 147, 0.2);
            border-radius: 4px;
        }

        .filter-select:focus {
            outline: none;
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .table th,
        .table td {
            vertical-align: middle;
            font-size: 1rem;
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
                        <h1 class="h3 mb-0 text-gray-800">คำสั่งซื้อที่ยกเลิก</h1>
                        <select class="filter-select" onchange="window.location.href='order-cancel.php?filter=' + this.value">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                            <option value="admin" <?php echo $filter === 'admin' ? 'selected' : ''; ?>>ยกเลิกโดยแอดมิน</option>
                            <option value="user" <?php echo $filter === 'user' ? 'selected' : ''; ?>>ยกเลิกโดยลูกค้า</option>
                        </select>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">รายการคำสั่งซื้อที่ยกเลิก</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ลำดับ</th>
                                            <th>เลขคำสั่งซื้อ</th>
                                            <th>ชื่อลูกค้า</th>
                                            <th>สินค้าที่เลือก</th>
                                            <th>จำนวนรวม</th>
                                            <th>ยอดรวม</th>
                                            <th>วันที่ยกเลิก</th>
                                            <th class="col col-2">เหตุผล</th>
                                            <th>ผู้ยกเลิก</th>
                                            <th class="text-center">คืนเงิน</th>
                                            <th class="no-sort text-center">เพิ่มเติม</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($orders)): ?>
                                            <?php $index = 1; ?>
                                            <?php foreach ($orders as $order_id => $order): ?>
                                                <tr>
                                                    <td><?php echo $index++; ?></td>
                                                    <td class="col col-1">
                                                        <a href="order-cancel-detail.php?order_id=<?php echo htmlspecialchars($order_id); ?>" class="text-primary">
                                                            <?php echo htmlspecialchars($order['BookingNumber']); ?><?php if (count($order['Items']) > 1): ?> <span class="badge badge-multi ms-2">หลายรายการ</span><?php endif; ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <?php if ($order['MemberID']): ?>
                                                            <a href="edit-member.php?id=<?php echo htmlspecialchars($order['MemberID']); ?>"
                                                                class="text-primary"
                                                                title="แก้ไขข้อมูลสมาชิก">
                                                                <?php echo htmlspecialchars($order['CustomerName']); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <?php echo htmlspecialchars($order['CustomerName']); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (count($order['Items']) > 1): ?>
                                                            <ul class="item-list">
                                                                <?php foreach ($order['Items'] as $item): ?>
                                                                    <li><?php echo htmlspecialchars($item['flower_name'] ?? 'ไม่ระบุ'); ?> (<?php echo htmlspecialchars($item['Quantity']); ?> ชิ้น)</li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php else: ?>
                                                            <?php echo htmlspecialchars($order['Items'][0]['flower_name'] ?? 'ไม่ระบุ'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo array_sum(array_column($order['Items'], 'Quantity')); ?> ชิ้น</td>
                                                    <td class="text-danger">฿<?php echo number_format($order['SumTotal'], 2); ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($order['LastupdateDate'])); ?></td>
                                                    <td class="col col-1"><?php echo htmlspecialchars($order['Message'] ?? 'ไม่ระบุ'); ?></td>
                                                    <td class="text-center">
                                                        <?php if (strpos($order['Message'], '//จากFlowerTeam') !== false): ?>
                                                            <span class="badge bg-danger text-white">
                                                                <i class="fas fa-user-shield me-1"></i>แอดมิน
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success text-white">
                                                                <i class="fas fa-user me-1"></i>ลูกค้า
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if (strpos($order['Message'], '//RefundedByAdmin') !== false): ?>
                                                            <span class="status-label status-refunded">
                                                                <i class="fas fa-check-circle me-1"></i>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="status-label status-not-refunded">
                                                                <i class="fas fa-times-circle me-1"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="order-cancel-detail.php?order_id=<?php echo htmlspecialchars($order_id); ?>" class="btn btn-pink btn-sm">
                                                            <i class="fas fa-eye mr-2"></i>ดู
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="11" class="text-center">ไม่มีคำสั่งซื้อที่ถูกยกเลิก</td>
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

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <script src="vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#dataTable').DataTable({
                "columnDefs": [{
                    "orderable": false,
                    "targets": "no-sort"
                }],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Thai.json"
                }
            });

            // Handle error messages
            <?php if (isset($_SESSION['error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#dc3545'
                });
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        });
    </script>
</body>

</html>