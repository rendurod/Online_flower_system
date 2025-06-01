<?php
session_start();
require_once('config/db.php');

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
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . htmlspecialchars($e->getMessage());
    header("Location: login.php");
    exit();
}

// ดึงจำนวนข้อมูลจากแต่ละตาราง
try {
    // นับจำนวนหมวดหมู่
    $stmt_category = $conn->prepare("SELECT COUNT(*) as total FROM tbl_category");
    $stmt_category->execute();
    $total_categories = $stmt_category->fetch(PDO::FETCH_ASSOC)['total'];

    // นับจำนวนดอกไม้
    $stmt_flowers = $conn->prepare("SELECT COUNT(*) as total FROM tbl_flowers");
    $stmt_flowers->execute();
    $total_flowers = $stmt_flowers->fetch(PDO::FETCH_ASSOC)['total'];

    // นับจำนวนสมาชิก
    $stmt_members = $conn->prepare("SELECT COUNT(*) as total FROM tbl_members");
    $stmt_members->execute();
    $total_members = $stmt_members->fetch(PDO::FETCH_ASSOC)['total'];

    // นับจำนวนคำสั่งซื้อ
    $stmt_orders = $conn->prepare("SELECT COUNT(*) as total FROM tbl_orders");
    $stmt_orders->execute();
    $total_orders = $stmt_orders->fetch(PDO::FETCH_ASSOC)['total'];

    // คำนวณยอดรวมรายได้ (จากคำสั่งซื้อที่มีสถานะ 1, 3, 4)
    $stmt_revenue = $conn->prepare("
        SELECT SUM(o.Quantity * f.price) as total_revenue
        FROM tbl_orders o
        JOIN tbl_flowers f ON o.FlowerId = f.ID
        WHERE o.Status IN (1, 3, 4)
    ");
    $stmt_revenue->execute();
    $total_revenue = $stmt_revenue->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

    // นับจำนวนคำสั่งซื้อใหม่ (สถานะ 0: รอแจ้งชำระเงิน และ 1: การชำระเงินสำเร็จ)
    $stmt_new_orders = $conn->prepare("
        SELECT COUNT(*) as total
        FROM tbl_orders
        WHERE Status IN (0)
    ");
    $stmt_new_orders->execute();
    $new_orders_count = $stmt_new_orders->fetch(PDO::FETCH_ASSOC)['total'];

} catch (PDOException $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="แดชบอร์ดสำหรับจัดการ FlowerShop">
    <meta name="author" content="FlowerShop Team">
    <title>แดชบอร์ด - FlowerShop</title>
    <!-- LOGO -->
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Custom fonts for this template -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Custom styles for this template -->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .dashboard-card {
            transition: transform 0.3s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background: linear-gradient(135deg, #e84393, #ff6b6b);
            color: white;
        }
        .new-orders-card {
            background: linear-gradient(135deg, #ff6b6b, #e84393);
            color: white;
            border: none;
            animation: pulse 2s infinite;
            margin-bottom: 2rem;
        }
        .new-orders-card .card-body {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem;
        }
        .new-orders-card h2 {
            font-size: 3rem;
            margin: 0;
        }
        .new-orders-card p {
            font-size: 1.6rem;
            margin: 0;
        }
        .new-orders-card .btn {
            background-color: #fff;
            color: #e84393;
            font-weight: bold;
            padding: 0.75rem 1.5rem;
            transition: transform 0.2s;
        }
        .new-orders-card .btn:hover {
            transform: scale(1.05);
            background-color: #f8f9fa;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(232, 67, 147, 0.4); }
            70% { box-shadow: 0 0 0 20px rgba(232, 67, 147, 0); }
            100% { box-shadow: 0 0 0 0 rgba(232, 67, 147, 0); }
        }
    </style>
</head>

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <?php include("includes/sidebar.php"); ?>
        <!-- End of Sidebar -->
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <?php include("includes/header.php"); ?>
                <!-- End of Topbar -->
                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">แดชบอร์ด</h1>
                    </div>
                   
                    <!-- Content Row -->
                    <div class="row">
                        <!-- Total Categories Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2 dashboard-card">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-md font-weight-bold text-primary text-uppercase mb-1">
                                                ประเภทสินค้า</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_categories); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-tags fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Total Flowers Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2 dashboard-card">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-md font-weight-bold text-success text-uppercase mb-1">
                                                ดอกไม้ทั้งหมด</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_flowers); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-seedling fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Total Members Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2 dashboard-card">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-md font-weight-bold text-info text-uppercase mb-1">
                                                สมาชิกที่มีในระบบ</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_members); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                       
                        <!-- Total Revenue Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-danger shadow h-100 py-2 dashboard-card">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-md font-weight-bold text-danger text-uppercase mb-1">
                                                รายได้รวม</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">฿<?php echo number_format($total_revenue, 2); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                     <!-- New Orders Notification -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card new-orders-card shadow">
                                <div class="card-body">
                                    <div>
                                        <h2><?php echo number_format($new_orders_count); ?></h2>
                                        <p>คำสั่งซื้อใหม่รอดำเนินการ</p>
                                    </div>
                                    <a href="orders.php" class="btn"><i class="fas fa-cart-plus me-2"></i>ดูคำสั่งซื้อ</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /.container-fluid -->
            </div>
            <!-- End of Main Content -->
            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright © FlowerShop <?php echo date('Y'); ?></span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->
    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>
    <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>
</body>
</html>