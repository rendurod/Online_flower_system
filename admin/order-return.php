<?php
session_start();
require_once 'config/db.php';

// ตรวจสอบว่ามี session adminid หรือไม่
if (!isset($_SESSION['adminid'])) {
    error_log("No admin session found");
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
        error_log("Admin not found for id: $admin_id");
        $_SESSION['error'] = "ไม่พบข้อมูลผู้ดูแลระบบ";
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching admin: " . $e->getMessage());
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
               f.flower_name, f.price, f.image
        FROM tbl_orders o
        LEFT JOIN tbl_members m ON o.UserEmail = m.EmailId
        LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID
        WHERE o.ID = :id AND o.Status = 6 AND o.Message NOT LIKE '%//RefundedByAdmin'
    ");
    $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log("Order not found, not cancelled, or already refunded for id: $order_id");
        $_SESSION['error'] = "ไม่พบคำสั่งซื้อที่ถูกยกเลิกหรือโอนเงินคืนแล้ว";
        header("Location: order-cancel.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching order: " . $e->getMessage());
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: ' . htmlspecialchars($e->getMessage());
    header("Location: order-cancel.php");
    exit();
}

// Handle refund update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_refund'])) {
    $message = trim(htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8'));

    if (empty($message)) {
        $_SESSION['error'] = 'กรุณาระบุข้อความการคืนเงิน';
        header("Location: order-return.php?order_id=$order_id");
        exit();
    }

    // Append refund marker
    $message .= ' //RefundedByAdmin';

    // Handle file upload
    $image = null;
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../Uploads/slips/";
        $imageFileType = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $unique_name = uniqid('slip_', true) . '.' . $imageFileType;
        $target_file = $target_dir . $unique_name;

        if (!in_array($imageFileType, $allowed_types)) {
            $_SESSION['error'] = 'ไฟล์รูปภาพต้องเป็น JPG, JPEG, PNG หรือ GIF เท่านั้น';
            header("Location: order-return.php?order_id=$order_id");
            exit();
        }

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image = $unique_name;
        } else {
            $_SESSION['error'] = 'เกิดข้อผิดพลาดในการอัปโหลดสลิปการคืนเงิน';
            header("Location: order-return.php?order_id=$order_id");
            exit();
        }
    }

    try {
        $sql = "UPDATE tbl_orders SET Message = :message";
        if ($image) {
            $sql .= ", Image = :image";
        }
        $sql .= " WHERE ID = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        if ($image) {
            $stmt->bindValue(':image', $image, PDO::PARAM_STR);
        }
        $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = 'อัปเดตข้อมูลการคืนเงินสำเร็จ';
            header("Location: order-cancel-detail.php?order_id=$order_id");
            exit();
        } else {
            $_SESSION['error'] = 'ไม่มีการเปลี่ยนแปลงข้อมูล';
            header("Location: order-return.php?order_id=$order_id");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error updating refund: " . $e->getMessage());
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . htmlspecialchars($e->getMessage());
        header("Location: order-return.php?order_id=$order_id");
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
    <title>อัปเดตการคืนเงิน - FlowerShop</title>
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

        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .btn-save {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            background-color: #218838;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(40, 167, 69, 0.3);
        }

        .btn-save:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
        }

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
                        <h1 class="h3 mb-0 text-gray-800">อัปเดตการคืนเงิน: #<?php echo htmlspecialchars($order['BookingNumber']); ?></h1>
                        <a href="order-cancel-detail.php?order_id=<?php echo htmlspecialchars($order['ID']); ?>" class="btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white"></i> กลับไปยังรายละเอียดคำสั่งซื้อ
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

                    <!-- ฟอร์มอัปเดตการคืนเงิน -->
                    <div class="card shadow mb-4 order-detail-container">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">อัปเดตข้อมูลการคืนเงิน</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="update_refund" value="1">
                                <div class="mb-3">
                                    <label for="message" class="form-label">ข้อความการคืนเงิน <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="4" placeholder="กรุณาระบุข้อความการคืนเงิน" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="image" class="form-label">สลิปการคืนเงิน</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    <small class="form-text text-muted">ไฟล์ที่รองรับ: JPG, JPEG, PNG, GIF</small>
                                </div>
                                <div class="d-flex justify-content-start">
                                    <button type="submit" class="btn btn-save mr-2">
                                        <i class="fas fa-save mr-2"></i>บันทึก
                                    </button>
                                    <a href="order-cancel-detail.php?order_id=<?php echo htmlspecialchars($order['ID']); ?>" class="btn btn-secondary">
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
    </script>
</body>

</html>