<?php
session_start();
include('config/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_login'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_login'];
$message = '';
$messageType = '';

// Fetch user email
try {
    $stmt = $conn->prepare("SELECT EmailId FROM tbl_members WHERE ID = :id");
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userEmail = $user['EmailId'] ?? '';
} catch (PDOException $e) {
    $message = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: " . htmlspecialchars($e->getMessage());
    $messageType = "danger";
    $userEmail = '';
}

// Get order_id from query string
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    $_SESSION['error'] = "ไม่พบคำสั่งซื้อที่ระบุ";
    header("Location: user-order.php?tab=edited");
    exit();
}

// Fetch order details
$order = [];
try {
    $stmt = $conn->prepare("
        SELECT o.*, f.flower_name, f.price, f.image
        FROM tbl_orders o 
        LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID 
        WHERE o.ID = :id AND o.UserEmail = :email AND o.Status = 2
    ");
    $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
    $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['error'] = "ไม่พบคำสั่งซื้อที่ระบุหรือคำสั่งซื้อไม่อยู่ในสถานะแก้ไขการชำระเงิน";
        header("Location: user-order.php?tab=edited");
        exit();
    }
} catch (PDOException $e) {
    $message = "เกิดข้อผิดพลาดในการดึงข้อมูลคำสั่งซื้อ: " . htmlspecialchars($e->getMessage());
    $messageType = "danger";
}

// Handle slip upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $target_dir = "uploads/slips/";
    $max_file_size = 5 * 1024 * 1024; // 5MB
    $new_image = $order['Image'];

    if (isset($_FILES['slip_image']) && $_FILES['slip_image']['error'] == 0) {
        if ($_FILES['slip_image']['size'] > $max_file_size) {
            $message = 'ขนาดไฟล์รูปภาพใหญ่เกินไป (สูงสุด 5MB)';
            $messageType = 'danger';
        } else {
            $file_extension = strtolower(pathinfo($_FILES['slip_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = uniqid() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;

                if (move_uploaded_file($_FILES['slip_image']['tmp_name'], $target_file)) {
                    $new_image = $new_filename;

                    // Delete old slip if exists
                    if (!empty($order['Image']) && file_exists($target_dir . $order['Image'])) {
                        unlink($target_dir . $order['Image']);
                    }
                } else {
                    $message = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ';
                    $messageType = 'danger';
                }
            } else {
                $message = 'รองรับเฉพาะไฟล์รูปภาพ (JPG, JPEG, PNG, GIF)';
                $messageType = 'danger';
            }
        }
    } else {
        $message = 'กรุณาเลือกไฟล์สลิปการชำระเงิน';
        $messageType = 'danger';
    }

    if (empty($message)) {
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("
                UPDATE tbl_orders 
                SET Image = :image, Status = 5, LastupdateDate = CURRENT_TIMESTAMP 
                WHERE ID = :id AND UserEmail = :email
            ");
            $stmt->bindValue(':image', $new_image, PDO::PARAM_STR);
            $stmt->bindValue(':id', $order_id, PDO::PARAM_INT);
            $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
            $stmt->execute();
            $conn->commit();

            $_SESSION['success'] = 'อัปโหลดสลิปการชำระเงินใหม่เรียบร้อยแล้ว';
            header("Location: user-order.php?tab=tracking");
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . htmlspecialchars($e->getMessage());
            $messageType = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัปโหลดสลิปการชำระเงิน - Flower Shop</title>
    <!-- LOGO -->
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .order-details p {
            margin: 0.5rem 0;
            font-size: 1.4rem;
        }

        .order-details p strong {
            color: var(--text-dark);
        }

        .slip-image {
            max-width: 300px;
            max-height: 300px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin: 1rem 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-size: 1.4rem;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-control {
            font-size: 1.4rem;
        }

        .btn-submit {
            background: var(--primary-pink);
            color: var(--white);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-size: 1.4rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-submit:hover {
            background: var(--dark-pink);
        }

        .btn-back {
            background: #6c757d;
            color: var(--white);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-size: 1.4rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        .message-admin {
            background-color: #f8d7da;
            color: #721c24;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            margin-top: 0.5rem;
            font-size: 1.4rem;
        }
    </style>
</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <div class="container">
        <h2 class="mb-4">อัปโหลดสลิปการชำระเงินใหม่</h2>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="order-details">
            <p><strong>หมายเลขคำสั่งซื้อ:</strong> <?php echo htmlspecialchars($order['BookingNumber']); ?></p>
            <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></p>
            <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</p>
            <p><strong>ราคารวม:</strong> <?php echo number_format($order['Quantity'] * ($order['price'] ?? 0), 2); ?> บาท</p>
            <p><strong>วันที่สั่งซื้อ:</strong> <?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></p>
            <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
            <?php if (!empty($order['Message'])): ?>
                <p class="message-admin"><strong>ข้อความจากแอดมิน:</strong> <?php echo htmlspecialchars($order['Message']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label">สลิปการชำระเงินเดิม:</label>
            <?php if (!empty($order['Image']) && file_exists("uploads/slips/" . $order['Image'])): ?>
                <img src="uploads/slips/<?php echo htmlspecialchars($order['Image']); ?>" alt="Payment Slip" class="slip-image">
            <?php else: ?>
                <p>ไม่มีสลิปการชำระเงิน</p>
            <?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="slip_image" class="form-label">อัปโหลดสลิปใหม่:</label>
                <input type="file" class="form-control" id="slip_image" name="slip_image" accept="image/*" required>
                <small class="form-text text-muted">รองรับไฟล์: JPG, JPEG, PNG, GIF (สูงสุด 5MB)</small>
            </div>

            <div class="form-group d-flex justify-content-between">
                <button type="submit" name="submit" class="btn-submit">
                    <i class="fas fa-save me-2"></i>บันทึก
                </button>
                <a href="user-order.php?tab=edited" class="btn-back">
                    <i class="fas fa-arrow-left me-2"></i>ย้อนกลับ
                </a>
            </div>
        </form>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
                timer: 3000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'user-order.php?tab=tracking';
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