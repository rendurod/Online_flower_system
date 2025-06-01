<?php
session_start();
require_once('config/db.php');

// ดึงข้อมูลล่าสุดจาก tbl_contact
$contact_info = [];
try {
    $stmt = $conn->prepare("SELECT * FROM tbl_contact ORDER BY creationDate DESC LIMIT 1");
    $stmt->execute();
    $contact_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $contact_info = [
        'email' => 'contact@flowershop.com',
        'nameteam' => 'Flower Shop Team',
        'address' => '123 ถนนดอกไม้ กรุงเทพฯ',
        'tel' => '02-123-4567',
        'business_hours' => 'เปิดทุกวัน 08:00 - 20:00 น.'
    ];
}

// จัดการฟอร์มติดต่อ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);

    $errors = [];
    if (empty($name)) {
        $errors[] = 'กรุณากรอกชื่อ';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'กรุณากรอกอีเมลที่ถูกต้อง';
    }
    if (empty($message)) {
        $errors[] = 'กรุณากรอกข้อความ';
    }

    if (empty($errors)) {
        // ที่นี่สามารถเพิ่มโค้ดเพื่อบันทึกข้อความลงฐานข้อมูลหรือส่งอีเมลได้
        $_SESSION['success'] = 'ส่งข้อความสำเร็จ! เราจะติดต่อกลับโดยเร็วที่สุด';
        header("Location: contact.php");
        exit();
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flower_PHP</title>
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
        .contact-info-section {
            padding: 60px 0;
            background-color: #f8f9fa;
        }

        .contact-info-box {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .contact-info-box:hover {
            transform: translateY(-5px);
        }

        .contact-info-box i {
            font-size: 2.5rem;
            color: #ff6f91;
            /* สีชมพูเข้มสำหรับไอคอน */
            margin-bottom: 15px;
        }

        .contact-info-box h5 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
        }

        .contact-info-box p {
            color: #666;
            font-size: 1rem;
        }

        .contact-form-section {
            padding: 60px 0;
            background: linear-gradient(135deg, #ffe6ec 0%, #fff 100%);
        }

        .contact-form-section h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 30px;
        }

        .contact-form-section .form-control {
            border-radius: 10px;
            border: 1px solid #ddd;
            padding: 12px;
            font-size: 1rem;
        }

        .contact-form-section .form-control:focus {
            border-color: #ff6f91;
            box-shadow: 0 0 5px rgba(255, 111, 145, 0.3);
        }

        .contact-form-section .btn-submit {
            background-color: #ff6f91;
            color: #fff;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        .contact-form-section .btn-submit:hover {
            background-color: #e55a7a;
        }

        @media (max-width: 767px) {
            .contact-info-box {
                margin-bottom: 20px;
            }

            .products-hero h1.heading {
                font-size: 2.5rem;
            }
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
                <h1 class="heading mb-3">ติดต่อ<span>พวกเรา</span></h1>
                <p style="font-size: 1.8rem; color: var(--text-light); max-width: 600px; margin: 0 auto;">
                    ค้นพบความงามของดอกไม้สดใหม่ คัดสรรมาเป็นพิเศษเพื่อคุณ
                </p>
            </div>
        </div>
    </section>
    <!-- Hero Section Ends-->

    <!-- Contact Info Section -->
    <section class="contact-info-section">
        <div class="container">
            <h2 class="text-center mb-5">ข้อมูลติดต่อร้านดอกไม้ของเรา</h2>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="contact-info-box">
                        <i class="fas fa-map-marker-alt"></i>
                        <h5>ที่อยู่</h5>
                        <p><?php echo htmlspecialchars($contact_info['address']); ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="contact-info-box">
                        <i class="fas fa-phone"></i>
                        <h5>เบอร์โทรศัพท์</h5>
                        <p><?php echo htmlspecialchars($contact_info['tel']); ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="contact-info-box">
                        <i class="fas fa-envelope"></i>
                        <h5>อีเมล</h5>
                        <p><?php echo htmlspecialchars($contact_info['email']); ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="contact-info-box">
                        <i class="fas fa-clock"></i>
                        <h5>เวลาทำการ</h5>
                        <p><?php echo htmlspecialchars($contact_info['business_hours']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends-->

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>