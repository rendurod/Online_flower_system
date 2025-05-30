<?php
session_start();
include('config/db.php');

// ตรวจสอบว่าผู้ใช้ล็อกอินแล้วหรือยัง
if (!isset($_SESSION['user_login'])) {
    header("location: login.php");
    exit;
}

// Fetch flower data from tbl_flowers
$flowers = [];
$message = '';
$messageType = '';

try {
    $stmt = $conn->prepare("SELECT ID, flower_name, flower_description, price, image FROM tbl_flowers WHERE stock_quantity > 0 ORDER BY creation_date DESC");
    $stmt->execute();
    $flowers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "เกิดข้อผิดพลาดในการดึงข้อมูลดอกไม้: " . htmlspecialchars($e->getMessage());
    $messageType = "danger";
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยินดีต้อนรับ - FlowerShop</title>
    <!-- LOGO -->
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Swiper Slider -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/flowerPHP.css">
   
</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- home section starts-->
    <section class="home" id="home">
        <div class="content">
            <h3>ยินดีต้อนรับกลับมา!</h3>
            <span>Indira Gift flowers Shop</span>
            <p>คุณได้เข้าสู่ระบบเรียบร้อยแล้ว :) สนุกกับการเลือกชมดอกไม้และของขวัญสุดพิเศษได้เลย!</p>
            <a href="#flower" class="btn">เลือกซื้อเลย</a>
        </div>
    </section>
    <!-- home section ends-->

    <!-- Flower Section -->
    <section class="flower-section" id="flower">
        <div class="container">
            <h2 class="section-title text-center mb-4">ดอกไม้แนะนำ</h2>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                    <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($flowers)): ?>
                <!-- Swiper Slider -->
                <div class="swiper flower-slider">
                    <div class="swiper-wrapper">
                        <?php foreach ($flowers as $flower): ?>
                            <div class="swiper-slide">
                                <div class="flower-card">
                                    <img src="<?php echo !empty($flower['image']) && file_exists("admin/uploads/flowers/" . $flower['image']) ? "admin/uploads/flowers/" . htmlspecialchars($flower['image']) : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($flower['flower_name']); ?>" class="flower-image">
                                    <div class="flower-content">
                                        <div class="flower-id">A<?php echo htmlspecialchars($flower['ID']); ?></div>
                                        <h3 class="flower-name"><?php echo htmlspecialchars($flower['flower_name']); ?></h3>
                                        <p class="flower-description"><?php echo htmlspecialchars($flower['flower_description'] ?? 'ไม่มีรายละเอียด'); ?></p>
                                        <div class="flower-price"><?php echo number_format($flower['price'], 2); ?> บาท</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Add Pagination -->
                    <div class="swiper-pagination"></div>
                    <!-- Add Navigation -->
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
            <?php else: ?>
                <div class="no-data-message">ไม่มีดอกไม้ในสต็อกขณะนี้</div>
            <?php endif; ?>
        </div>
    </section>

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends-->

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Swiper Slider -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <!-- Initialize Swiper -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const flowerSlider = new Swiper('.flower-slider', {
                slidesPerView: 'auto',
                spaceBetween: 30,
                loop: true,
                autoplay: {
                    delay: 3500,
                    disableOnInteraction: false,
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                    dynamicBullets: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                breakpoints: {
                    576: {
                        spaceBetween: 20,
                    },
                    768: {
                        spaceBetween: 30,
                    },
                    1200: {
                        spaceBetween: 40,
                    },
                },
                slideToClickedSlide: true,
            });
        });
    </script>
</body>

</html>