<?php
session_start();
include('config/db.php');
// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบแล้วหรือไม่

if (isset($_SESSION['user_login'])) {
    header("Location: user.php");
    exit();
}

// ดึงข้อมูลดอกไม้จาก tbl_flowers
$flowers = [];
$message = '';
$messageType = '';

try {
    $stmt = $conn->prepare("SELECT ID, flower_name, flower_description, price, image, stock_quantity FROM tbl_flowers WHERE stock_quantity > 0 ORDER BY creation_date DESC");
    $stmt->execute();
    $allFlowers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // สุ่มเลือก 6 รายการจากทั้งหมด
    if (count($allFlowers) > 0) {
        $randomKeys = array_rand($allFlowers, min(6, count($allFlowers)));
        if (!is_array($randomKeys)) {
            $randomKeys = [$randomKeys]; // กรณีมีรายการเดียว
        }
        foreach ($randomKeys as $key) {
            $flowers[] = $allFlowers[$key];
        }
    }
} catch (PDOException $e) {
    $message = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . htmlspecialchars($e->getMessage());
    $messageType = "danger";
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ร้านดอกไม้ - FlowerShop</title>
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/flowerPHP.css">
</head>

<body>
    <?php include("includes/navbar.php"); ?>
    <section class="home" id="home">
        <div class="swiper home-slider">
            <div class="swiper-wrapper">
                <div class="swiper-slide" style="background-image: url('assets/img/flower22.jpg');"></div>
                <div class="swiper-slide" style="background-image: url('assets/img/flower33.jpg');"></div>
                <div class="swiper-slide" style="background-image: url('assets/img/flower4.jpg');"></div>
                <div class="swiper-slide" style="background-image: url('assets/img/flower5.jpg');"></div>
            </div>
            <div class="swiper-pagination"></div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
        </div>
        <div class="content">
            <h3>Indira Gift Flowers Shop</h3>
            <span>ฉันจะเป็นดอกไม้ของคุณตลอดไป</span>
            <p>กลิ่นหอมที่ถูกนำเสนอมากที่สุดมักมีหลากหลาย ผู้คนมักนึกถึงช่วงเวลาที่ต้องการหาของขวัญในวันพิเศษ กลิ่นแต่ละประเภทมีความหมายที่แตกต่างกัน ดอกไม้ที่แตกต่างกันสามารถใช้มอบให้กับคนพิเศษนั้นได้</p>
            <a href="#flower" class="btn">ช็อปเลย</a>
        </div>
    </section>
    <section class="flower-section" id="flower">
        <div class="container">
            <h2 class="section-title text-center mb-4">ดอกไม้แนะนำ</h2>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($messageType); ?>" role="alert">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
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

    <?php include("includes/footer.php"); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Swiper === 'undefined') {
            console.error('Swiper library is not loaded');
            return;
        }

        const homeSlider = new Swiper('.home-slider', {
            loop: true,
            pagination: { el: '.home-slider .swiper-pagination', clickable: true },
            navigation: { nextEl: '.home-slider .swiper-button-next', prevEl: '.home-slider .swiper-button-prev' },
            effect: 'fade',
            fadeEffect: { crossFade: true },
        });

        const flowerSlider = new Swiper('.flower-slider', {
            slidesPerView: 'auto',
            spaceBetween: 30,
            loop: <?php echo count($flowers) > 1 ? 'true' : 'false'; ?>,
            pagination: { 
                el: '.flower-slider .swiper-pagination', 
                clickable: true, 
                dynamicBullets: true 
            },
            navigation: { 
                nextEl: '.flower-slider .swiper-button-next', 
                prevEl: '.flower-slider .swiper-button-prev' 
            },
            breakpoints: {
                576: { spaceBetween: 20 },
                768: { spaceBetween: 30 },
                1200: { spaceBetween: 40 },
            }
        });

        // ==========================================================
        // ======[ โค้ดที่เปลี่ยนแปลงอยู่ตรงนี้ ]======
        // ==========================================================
        
        // Add click event to all add-to-cart buttons
        document.querySelectorAll('.add-to-cart-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent any default action

                // Show login prompt, same style as products.php
                Swal.fire({
                    icon: 'info',
                    title: 'กรุณาล็อกอิน',
                    text: 'คุณต้องเข้าสู่ระบบก่อน จึงจะสามารถเพิ่มสินค้าลงตะกร้าได้',
                    showConfirmButton: true,
                    confirmButtonText: 'ไปที่หน้าล็อกอิน',
                    showCancelButton: false,
                    // cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Redirect to login page and pass the current URL to return to
                        window.location.href = 'login.php?return_url=' + encodeURIComponent(window.location.href);
                    }
                });
            });
        });

        // Prevent slide change on view details button click
        document.querySelectorAll('.btn[href^="product-detail.php"]').forEach(button => {
            button.addEventListener('click', function(e) {
                // This stops the swiper from sliding, but still lets the link work
                e.stopPropagation(); 
            });
        });
    });
    </script>
</body>

</html>