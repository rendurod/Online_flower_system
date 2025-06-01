<?php
session_start();
include('config/db.php');

$order = null;
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_number'])) {
    $bookingNumber = trim($_POST['booking_number']);

    if (!empty($bookingNumber)) {
        try {
            $stmt = $conn->prepare("
                SELECT o.*, f.flower_name, f.image AS flower_image
                FROM tbl_orders o
                LEFT JOIN tbl_flowers f ON o.FlowerId = f.ID
                WHERE o.BookingNumber = :booking_number
            ");
            $stmt->bindValue(':booking_number', $bookingNumber, PDO::PARAM_INT);
            $stmt->execute();
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $message = "ไม่พบคำสั่งซื้อที่มีหมายเลข $bookingNumber";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            $message = "เกิดข้อผิดพลาดในการค้นหา: " . htmlspecialchars($e->getMessage());
            $messageType = "error";
        }
    } else {
        $message = "กรุณากรอกหมายเลขคำสั่งซื้อ";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตามสินค้า - FlowerShop</title>
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
        .tracking-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .tracking-form {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .tracking-form input {
            flex: 1;
            padding: 1rem;
            font-size: 1.6rem;
            border: 2px solid rgba(232, 67, 147, 0.2);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .tracking-form input:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(232, 67, 147, 0.1);
        }

        .tracking-form button {
            padding: 1rem 2rem;
            font-size: 1.6rem;
            background: var(--primary-pink);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .tracking-form button:hover {
            background: var(--dark-pink);
            transform: translateY(-1px);
        }

        .order-details {
            background: #f9f9f9;
            padding: 2rem;
            border-radius: var(--border-radius);
        }

        .order-details h4 {
            font-size: 2rem;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
        }

        .order-details p {
            font-size: 1.6rem;
            margin: 0.5rem 0;
            color: #666;
        }

        .order-details p strong {
            color: var(--text-dark);
        }

        .status-label {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 1.4rem;
            font-weight: 500;
            color: #fff;
            margin-top: 0.5rem;
        }

        .status-awaiting {
            background-color: #95a5a6;
        }

        .status-paid {
            background-color: #2ecc71;
        }

        .status-processing {
            background-color: #f1c40f;
        }

        .status-completed {
            background-color: #7bed9f;
        }

        .status-new-slip {
            background-color: #3498db;
        }

        .status-edited {
            background-color: #e74c3c;
        }

        .order-image img {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: var(--border-radius);
            border: 2px solid rgba(232, 67, 147, 0.2);
            margin-top: 1rem;
        }

        .message-admin {
            background-color: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
            font-size: 1.6rem;
        }
    </style>
</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- Hero Section -->
    <section class="products-hero">
        <div class="container">
            <div class="text-center">
                <h1 class="heading mb-3">ติดตาม<span>สินค้า</span></h1>
                <p style="font-size: 1.8rem; color: var(--text-light); max-width: 600px; margin: 0 auto;">
                    ค้นพบความงามของดอกไม้สดใหม่ คัดสรรมาเป็นพิเศษเพื่อคุณ
                </p>
            </div>
        </div>
    </section>
    <!-- Hero Section Ends -->

    <!-- Tracking Section -->
    <section class="tracking-container">
        <h2 class="text-center mb-4" style="font-size: 2.4rem; color: var(--text-dark);">ค้นหาคำสั่งซื้อ</h2>
        <form method="POST" class="tracking-form">
            <input type="text" name="booking_number" placeholder="กรอกหมายเลขคำสั่งซื้อ" value="<?php echo isset($bookingNumber) ? htmlspecialchars($bookingNumber) : ''; ?>" required>
            <button type="submit"><i class="fas fa-search me-2"></i>ค้นหา</button>
        </form>

        <?php if ($order): ?>
            <div class="order-details">
                <h4>รายละเอียดคำสั่งซื้อ: #<?php echo htmlspecialchars($order['BookingNumber']); ?></h4>
                <p><strong>วันที่สั่งซื้อ:</strong> <?php echo date('d/m/Y H:i', strtotime($order['PostingDate'])); ?></p>
                <p><strong>ดอกไม้:</strong> <?php echo htmlspecialchars($order['flower_name'] ?? 'ไม่ระบุ'); ?></p>
                <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($order['Quantity']); ?> ชิ้น</p>
                <p><strong>วันที่จัดส่ง:</strong> <?php echo $order['DeliveryDate'] ? date('d/m/Y', strtotime($order['DeliveryDate'])) : 'ไม่ระบุ'; ?></p>
                <p><strong>สถานะ:</strong>
                    <span class="status-label <?php
                                                $statusClasses = [
                                                    0 => 'status-awaiting',
                                                    1 => 'status-paid',
                                                    2 => 'status-edited',
                                                    3 => 'status-processing',
                                                    4 => 'status-completed',
                                                    5 => 'status-new-slip'
                                                ];
                                                $statusTexts = [
                                                    0 => 'รอแจ้งชำระเงิน',
                                                    1 => 'การชำระเงินสำเร็จ',
                                                    2 => 'แก้ไขการชำระเงิน',
                                                    3 => 'กำลังดำเนินการ',
                                                    4 => 'จัดส่งสำเร็จ',
                                                    5 => 'แนบสลิปใหม่'
                                                ];
                                                echo $statusClasses[$order['Status']] ?? 'status-awaiting';
                                                ?>">
                        <i class="fas <?php
                                        $statusIcons = [
                                            0 => 'fa-clock',
                                            1 => 'fa-check',
                                            2 => 'fa-edit',
                                            3 => 'fa-truck',
                                            4 => 'fa-check-circle',
                                            5 => 'fa-upload'
                                        ];
                                        echo $statusIcons[$order['Status']] ?? 'fa-clock';
                                        ?> me-1"></i>
                        <?php echo $statusTexts[$order['Status']] ?? 'ไม่ระบุ'; ?>
                    </span>
                </p>
                <?php if (!empty($order['flower_image']) && file_exists("admin/uploads/flowers/" . $order['flower_image'])): ?>
                    <p><strong>รูปภาพดอกไม้:</strong></p>
                    <div class="order-image">
                        <img src="admin/uploads/flowers/<?php echo htmlspecialchars($order['flower_image']); ?>" alt="<?php echo htmlspecialchars($order['flower_name'] ?? 'ดอกไม้'); ?>">
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
    <!-- Tracking Section Ends -->

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends -->

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (!empty($message)): ?>
            Swal.fire({
                icon: '<?php echo $messageType; ?>',
                title: '<?php echo $messageType == "error" ? "ข้อผิดพลาด" : "สำเร็จ"; ?>',
                text: '<?php echo htmlspecialchars($message); ?>',
                timer: 3000,
                showConfirmButton: false
            });
        <?php endif; ?>
    </script>
</body>

</html>