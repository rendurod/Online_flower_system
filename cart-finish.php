<?php
session_start();
include('config/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_login'])) {
    header("Location: login.php?return_to=cart-finish.php");
    exit();
}

$user_id = $_SESSION['user_login'];

// Check for order_id
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    $_SESSION['error'] = "ไม่พบคำสั่งซื้อ";
    header("Location: user.php");
    exit();
}

// Fetch user data
$user_query = "SELECT FirstName, LastName, EmailId, ContactNo, Address FROM tbl_members WHERE ID = :id";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
$user_stmt->execute();
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    $_SESSION['error'] = "ไม่พบข้อมูลผู้ใช้";
    header("Location: login.php");
    exit();
}

// Fetch order details
$order_query = "SELECT BookingNumber, DeliveryDate, Image, SumTotal, Status, PostingDate 
                FROM tbl_orders WHERE ID = :order_id AND UserEmail = :email";
$order_stmt = $conn->prepare($order_query);
$order_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
$order_stmt->bindValue(':email', $user_data['EmailId'], PDO::PARAM_STR);
$order_stmt->execute();
$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = "ไม่พบคำสั่งซื้อ";
    header("Location: user.php");
    exit();
}

// Fetch order items and calculate total from items
$items_query = "SELECT od.FlowerId, od.Quantity, od.Price, f.flower_name, f.image 
                FROM tbl_order_details od 
                JOIN tbl_flowers f ON od.FlowerId = f.ID 
                WHERE od.OrderId = :order_id";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
$items_stmt->execute();
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total from order items to ensure no shipping cost is included
$calculated_total = 0;
foreach ($order_items as $item) {
    $calculated_total += $item['Price'] * $item['Quantity'];
}

// Compare with SumTotal from tbl_orders
$total_amount = $order['SumTotal'];
if (abs($calculated_total - $total_amount) > 0.01) {
    // If there's a discrepancy (likely due to 50 Baht shipping), use calculated total
    $total_amount = $calculated_total;
    // Optionally update tbl_orders to fix SumTotal
    try {
        $update_query = "UPDATE tbl_orders SET SumTotal = :total_amount WHERE ID = :order_id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindValue(':total_amount', $total_amount, PDO::PARAM_STR);
        $update_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
        $update_stmt->execute();
    } catch (PDOException $e) {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตยอดรวม: " . htmlspecialchars($e->getMessage());
    }
}

$order_status = $order['Status'] == 0 ? 'รอการยืนยัน' : ($order['Status'] == 1 ? 'ยืนยันแล้ว' : 'ยกเลิก');

$slip_image = !empty($order['Image']) && file_exists("Uploads/slips/" . $order['Image'])
    ? "Uploads/slips/" . htmlspecialchars($order['Image'])
    : "assets/img/default-slip.jpg";
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คำสั่งซื้อสำเร็จ - FlowerShop</title>
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/productFinish.css">
    <style>
        .order-success-section {
            padding: 4rem 0;
            background-color: #fcf5f7;
        }

        .order-success-container {
            background: #fff;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            max-width: 800px;
            margin: 0 auto;
        }

        .order-success-container h2 {
            font-size: 2.4rem;
            color: var(--text-dark);
            text-align: center;
            margin-bottom: 2rem;
        }

        .order-details,
        .user-details,
        .order-items {
            margin-bottom: 2rem;
        }

        .order-details h3,
        .user-details h3,
        .order-items h3 {
            font-size: 1.8rem;
            color: var(--text-dark);
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .order-details p,
        .user-details p {
            font-size: 1.6rem;
            color: var(--text-light);
            margin: 0.5rem 0;
        }

        .order-item {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .order-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            margin-right: 1.5rem;
        }

        .order-item div {
            flex: 1;
        }

        .order-item h4 {
            font-size: 1.7rem;
            color: var(--text-dark);
            margin: 0;
        }

        .order-item p {
            font-size: 1.5rem;
            color: var(--text-light);
            margin: 0.5rem 0 0;
        }

        .slip-image {
            max-width: 200px;
            border-radius: 10px;
            margin-top: 1rem;
        }

        .back-btn {
            display: inline-block;
            margin-top: 2rem;
            padding: 1rem 3rem;
            font-size: 1.6rem;
            color: #fff;
            background: var(--primary-pink);
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
        }

        .back-btn:hover {
            background: var(--dark-pink);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn-continue,
        .btn-view-orders {
            padding: 1rem 3rem;
            font-size: 1.6rem;
            color: #fff;
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-continue {
            background: var(--primary-pink);
        }

        .btn-continue:hover {
            background: var(--dark-pink);
        }

        .btn-view-orders {
            background: #6c757d; /* Bootstrap secondary color */
        }

        .btn-view-orders:hover {
            background: #5a6268;
        }
    </style>
</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- Order Success Section -->
    <section class="order-success-section">
        <div class="container">
            <div class="step-container">
                <div class="step">
                    <div class="circle">1</div>
                    <div class="label">สินค้าในตะกร้า</div>
                </div>
                <div class="step">
                    <div class="line"></div>
                    <div class="circle">2</div>
                    <div class="label">ตรวจสอบการซื้อ</div>
                </div>
                <div class="step active">
                    <div class="line"></div>
                    <div class="circle">3</div>
                    <div class="label active-label">คำสั่งซื้อสำเร็จ</div>
                </div>
            </div>
            <div class="order-success-container">
                <!-- Success Icon -->
                <div class="text-center">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <!-- Success Title -->
                    <h1 class="success-title">คำสั่งซื้อสำเร็จ!</h1>
                    <!-- Success Message -->
                    <p class="success-message">ขอบคุณที่สั่งซื้อกับเรา คำสั่งซื้อของคุณได้รับการบันทึกเรียบร้อยแล้ว กรุณารอการยืนยันจากแอดมิน</p>
                </div>

                <!-- Order Details -->
                <div class="order-details mt-10">
                    <h3>รายละเอียดคำสั่งซื้อ</h3>
                    <p><strong>หมายเลขคำสั่งซื้อ:</strong> <?php echo htmlspecialchars($order['BookingNumber']); ?></p>
                    <p><strong>วันที่สั่งซื้อ:</strong> <?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></p>
                    <p><strong>วันที่จัดส่ง:</strong> <?php echo date('d/m/Y', strtotime($order['DeliveryDate'])); ?></p>
                    <p><strong>สถานะ:</strong> <?php echo htmlspecialchars($order_status); ?></p>
                </div>

                <!-- Order Items -->
                <div class="order-items">
                    <h3>รายการสินค้า</h3>
                    <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
                            <img src="<?php echo !empty($item['image']) && file_exists("admin/uploads/flowers/" . $item['image'])
                                            ? "admin/uploads/flowers/" . htmlspecialchars($item['image'])
                                            : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($item['flower_name']); ?>">
                            <div>
                                <h4><?php echo htmlspecialchars($item['flower_name']); ?></h4>
                                <p>จำนวน: <?php echo $item['Quantity']; ?> ชิ้น</p>
                                <p>ราคา: ฿<?php echo number_format($item['Price'] * $item['Quantity'], 2); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <p><strong>ยอดรวมทั้งหมด:</strong> ฿<?php echo number_format($total_amount, 2); ?></p>
                </div>

                <!-- User Details -->
                <div class="user-details">
                    <h3>ข้อมูลผู้สั่งซื้อ</h3>
                    <p><strong>ชื่อ:</strong> <?php echo htmlspecialchars($user_data['FirstName'] . ' ' . $user_data['LastName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user_data['EmailId']); ?></p>
                    <p><strong>โทร:</strong> <?php echo htmlspecialchars($user_data['ContactNo']); ?></p>
                    <p><strong>ที่อยู่จัดส่ง:</strong> <?php echo htmlspecialchars($user_data['Address']); ?></p>
                </div>

                <!-- Payment Slip -->
                <div class="order-details">
                    <h3>สลิปโอนเงิน</h3>
                    <img src="<?php echo $slip_image; ?>" alt="Payment Slip" class="slip-image">
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="products.php" class="btn-continue">
                        <i class="fas fa-shopping-bag"></i> ซื้อสินค้าต่อ
                    </a>
                    <a href="user-order.php" class="btn-view-orders">
                        <i class="fas fa-list"></i> ดูคำสั่งซื้อ
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends -->

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['success'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'สั่งซื้อสำเร็จ',
                    text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
                    showConfirmButton: false,
                    timer: 2000
                });
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
                    confirmButtonText: 'ตกลง'
                });
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        });
    </script>
</body>

</html>