<?php
session_start();
include('config/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_login'])) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['user_login'];

// Fetch flower data from tbl_flowers
$flowers = [];
$message = '';
$messageType = '';

try {
    $stmt = $conn->prepare("SELECT ID, flower_name, flower_description, price, image, stock_quantity FROM tbl_flowers WHERE stock_quantity > 0 ORDER BY stock_quantity DESC LIMIT 5");
    $stmt->execute();
    $flowers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . htmlspecialchars($e->getMessage());
    $messageType = "danger";
}

// Count cart items from tbl_cart
$cartCount = 0;
try {
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM tbl_cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $cartCount = $result['total'] ?? 0;
} catch (PDOException $e) {
    $message = "เกิดข้อผิดพลาดในการนับตะกร้า: " . htmlspecialchars($e->getMessage());
    $messageType = "danger";
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยินดีต้อนรับ - FlowerShop</title>
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/flowerPHP.css">
</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- home section starts-->
    <section class="home" id="home">
        <div class="swiper home-slider">
            <div class="swiper-wrapper">
                <div class="swiper-slide" style="background-image: url('assets/img/flower22.jpg');"></div>
                <div class="swiper-slide" style="background-image: url('assets/img/flower33.jpg');"></div>
            </div>
            <div class="swiper-pagination"></div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
        </div>
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
                <div class="swiper flower-slider">
                    <div class="swiper-wrapper">
                        <?php foreach ($flowers as $flower): ?>
                            <div class="swiper-slide">
                                <div class="flower-card">
                                    <div class="flower-image">
                                        <img src="<?php echo !empty($flower['image']) && file_exists("admin/uploads/flowers/" . $flower['image']) ? "admin/uploads/flowers/" . htmlspecialchars($flower['image']) : "assets/img/default-flower.jpg"; ?>"
                                            alt="<?php echo htmlspecialchars($flower['flower_name']); ?>"
                                            class="flower-image">
                                    </div>
                                    <div class="flower-content">
                                        <div class="flower-id">A<?php echo htmlspecialchars($flower['ID']); ?></div>
                                        <h3 class="flower-name"><?php echo htmlspecialchars($flower['flower_name']); ?></h3>
                                        <p class="flower-description"><?php echo htmlspecialchars($flower['flower_description'] ?? 'ไม่มีรายละเอียด'); ?></p>
                                        <div class="flower-price"><?php echo number_format($flower['price'], 2); ?> บาท</div>
                                        <?php if ($flower['stock_quantity'] <= 5 && $flower['stock_quantity'] > 0): ?>
                                            <span class="stock-status low-stock">เหลือน้อย</span>
                                        <?php elseif ($flower['stock_quantity'] > 5): ?>
                                            <span class="stock-status in-stock">มีสินค้า</span>
                                        <?php endif; ?>
                                        <div class="flower-buttons">
                                            <a href="product-detail.php?id=<?php echo htmlspecialchars($flower['ID']); ?>" class="btn" aria-label="ดูสินค้า <?php echo htmlspecialchars($flower['flower_name']); ?>" title="ดูสินค้า">
                                                <i class="fas fa-search me-2"></i> ดูสินค้า
                                            </a>
                                            <button class="btn add-to-cart-btn" data-id="<?php echo htmlspecialchars($flower['ID']); ?>" aria-label="เพิ่มลงตะกร้า <?php echo htmlspecialchars($flower['flower_name']); ?>" title="ตะกร้า">
                                                <i class="fas fa-cart-plus me-2"></i> ตะกร้า
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-pagination"></div>
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

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Swiper Slider -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swiper === 'undefined') {
                console.error('Swiper library is not loaded');
                return;
            }

            const homeSlider = new Swiper('.home-slider', {
                slidesPerView: 1,
                loop: false,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                },
                pagination: {
                    el: '.home-slider .swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '.home-slider .swiper-button-next',
                    prevEl: '.home-slider .swiper-button-prev',
                },
                effect: 'fade',
                fadeEffect: {
                    crossFade: true,
                },
            });

            const flowerSlider = new Swiper('.flower-slider', {
                slidesPerView: 'auto',
                spaceBetween: 30,
                loop: <?php echo count($flowers) > 1 ? 'true' : 'false'; ?>,
                autoplay: {
                    delay: 3500,
                    disableOnInteraction: false,
                },
                pagination: {
                    el: '.flower-slider .swiper-pagination',
                    clickable: true,
                    dynamicBullets: true,
                },
                navigation: {
                    nextEl: '.flower-slider .swiper-button-next',
                    prevEl: '.flower-slider .swiper-button-prev',
                },
                breakpoints: {
                    576: { spaceBetween: 20 },
                    768: { spaceBetween: 30 },
                    1200: { spaceBetween: 40 },
                },
                on: {
                    click: function (e) {
                        if (e.target.closest('.btn') || e.target.closest('.add-to-cart-btn')) {
                            e.preventDefault();
                        }
                    }
                }
            });

            // SweetAlert2 ป็อปอัปกลางจอ
            document.querySelectorAll('.add-to-cart-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const flowerId = this.getAttribute('data-id');
                    fetch('add_to_cart.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `flower_id=${flowerId}&quantity=1`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            const cartCounter = document.querySelector('.cart-counter');
                            if (cartCounter) {
                                cartCounter.innerText = data.cartCount;
                            }
                            Swal.fire({
                                icon: 'success',
                                title: 'เพิ่มสินค้าสำเร็จ!',
                                text: data.message,
                                confirmButtonText: 'ตกลง',
                                allowOutsideClick: false,
                                allowEscapeKey: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'ไม่สามารถเพิ่มสินค้าได้',
                                text: data.message,
                                confirmButtonText: 'ตกลง'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: 'ไม่สามารถเพิ่มสินค้าลงตะกร้าได้',
                            confirmButtonText: 'ปิด'
                        });
                    });
                });
            });

            // ปุ่มดูรายละเอียดสินค้า
            document.querySelectorAll('.btn[href^="product-detail.php"]').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = this.getAttribute('href');
                });
            });
        });
    </script>
</body>

</html>
