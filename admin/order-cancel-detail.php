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
    error_log("Invalid or missing order_id");
    $_SESSION['error'] = "ไม่พบคำสั่งซื้อที่ระบุ";
    header("Location: order-cancel.php");
    exit();
}

// Fetch order details
$order = [];
try {
    $stmt = $conn->prepare("
        SELECT o.*, 
               CONCAT(m.FirstName, ' ', m.LastName) AS CustomerName,
               m.ContactNo, m.Address,
               f.flower_name, f.price, f.image, f.flower_category, f.flower_description
        FROM tbl_orders o
        LEFT JOIN tbl_members m ON o.UserEmail = m.EmailId
        LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID
        WHERE o.ID = :id AND o.Status = 6
    ");
    $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log("Order not found or not cancelled for id: $order_id");
        $_SESSION['error'] = "ไม่พบคำสั่งซื้อที่ถูกยกเลิก";
        header("Location: order-cancel.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching order: " . $e->getMessage());
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: ' . htmlspecialchars($e->getMessage());
    header("Location: order-cancel.php");
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
    <title>รายละเอียดคำสั่งซื้อที่ยกเลิก - FlowerShop</title>
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .status-label {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .status-cancelled {
            background-color: #dc3545;
            color: #fff;
        }

        /* สไตล์สำหรับตารางข้อมูลบัญชี */
        .account-info-card {
            border-left: 4px solid #4e73df;
            margin-bottom: 2rem;
        }

        .account-info-card .card-header {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 1.4rem;
            color: #4e73df;
        }

        .account-info-table th {
            width: 40%;
            background-color: rgba(78, 115, 223, 0.1);
        }

        /* สไตล์สำหรับตารางราคา */
        .price-summary-card {
            border-left: 4px solid #1cc88a;
            margin-bottom: 2rem;
        }

        .price-summary-card .card-header {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 1.4rem;
            color: #1cc88a;
        }

        .price-summary-table th {
            width: 40%;
            background-color: rgba(28, 200, 138, 0.1);
        }

        .total-price {
            font-size: 1.6rem;
            font-weight: bold;
            color: #dc3545;
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
                        <h1 class="h3 mb-0 text-gray-800">รายละเอียดคำสั่งซื้อที่ยกเลิก: #<?php echo htmlspecialchars($order['BookingNumber']); ?></h1>
                        <a href="order-cancel.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white"></i> กลับไปยังรายการคำสั่งซื้อที่ยกเลิก
                        </a>
                    </div>

                    <!-- ตารางข้อมูลบัญชี -->
                    <div class="card shadow mb-4 account-info-card">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold">ข้อมูลบัญชีสำหรับการคืนเงิน</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered account-info-table">
                                <tbody>
                                    <tr>
                                        <th>ชื่อบัญชี</th>
                                        <td><?php echo htmlspecialchars($order['AccountName'] ?? 'ไม่ระบุ'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>เลขที่บัญชี</th>
                                        <td><?php echo htmlspecialchars($order['AccountNumber'] ?? 'ไม่ระบุ'); ?></td>
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
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ตารางสรุปราคา -->
                    <div class="card shadow mb-4 price-summary-card">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold">สรุปราคา</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered price-summary-table">
                                <tbody>
                                    <tr>
                                        <th>ชื่อสินค้า</th>
                                        <td><?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>จำนวน</th>
                                        <td><?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</td>
                                    </tr>
                                    <tr>
                                        <th>ราคาต่อชิ้น</th>
                                        <td>฿<?php echo number_format($order['price'] ?? 0, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th>ราคารวม</th>
                                        <td class="total-price">฿<?php echo number_format($order['Quantity'] * ($order['price'] ?? 0), 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ตารางข้อมูลทั่วไป -->
                    <div class="card shadow mb-4 order-detail-container">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ข้อมูลคำสั่งซื้อที่ยกเลิก</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th>ชื่อลูกค้า</th>
                                        <td><?php echo htmlspecialchars($order['CustomerName'] ?? 'ไม่ระบุ'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>อีเมล</th>
                                        <td><?php echo htmlspecialchars($order['UserEmail'] ?? 'ไม่ระบุ'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>เบอร์โทรศัพท์</th>
                                        <td><?php echo htmlspecialchars($order['ContactNo'] ?? 'ไม่ระบุ'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>วันที่สั่งซื้อ</th>
                                        <td><?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>วันที่ยกเลิก</th>
                                        <td><?php echo date('d/m/Y H:i', strtotime($order['LastupdateDate'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>หมวดหมู่สินค้า</th>
                                        <td><?php echo htmlspecialchars($order['flower_category'] ?? 'ไม่ระบุ'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>วันที่จัดส่ง</th>
                                        <td><?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>เหตุผลการยกเลิก</th>
                                        <td><?php echo htmlspecialchars($order['Message'] ?? 'ไม่ระบุ'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>ผู้ยกเลิก</th>
                                        <td><?php echo strpos($order['Message'], '//จากFlowerTeam') !== false ? 'แอดมิน' : 'ลูกค้า'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>สถานะ</th>
                                        <td>
                                            <span class="status-label status-cancelled">
                                                <i class="fas fa-times-circle me-1"></i>ยกเลิกคำสั่งซื้อ
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>รูปภาพสินค้า</th>
                                        <td class="order-image">
                                            <img src="<?php echo !empty($order['image']) && file_exists("uploads/flowers/" . $order['image']) ? "uploads/flowers/" . htmlspecialchars($order['image']) : "img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($order['flower_name']); ?>">
                                        </td>
                                    </tr>
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

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>

    <script>
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