<?php
session_start();
include('config/db.php');

// Get product ID from URL
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = "SELECT * FROM tbl_flowers WHERE ID = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $flower = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$flower) {
        header("Location: products.php");
        exit();
    }
} else {
    header("Location: products.php");
    exit();
}

// Fetch related flowers (same category, excluding current product)
$related_query = "SELECT ID, flower_name, flower_description, price, image, stock_quantity FROM tbl_flowers WHERE flower_category = :category AND ID != :id AND stock_quantity > 0 ORDER BY creation_date DESC";
$related_stmt = $conn->prepare($related_query);
$related_stmt->bindValue(':category', $flower['flower_category'], PDO::PARAM_STR);
$related_stmt->bindValue(':id', $id, PDO::PARAM_INT);
$related_stmt->execute();
$related_flowers = $related_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all flowers with available stock
$all_flowers_query = "SELECT ID, flower_name, flower_description, price, image, stock_quantity FROM tbl_flowers WHERE stock_quantity > 0 ORDER BY creation_date DESC";
$all_flowers_stmt = $conn->prepare($all_flowers_query);
$all_flowers_stmt->execute();
$all_flowers = $all_flowers_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดสินค้า - <?php echo htmlspecialchars($flower['flower_name']); ?> - FlowerShop</title>
    <!-- LOGO -->
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Swiper Slider -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/productPHP.css">
    <link rel="stylesheet" href="assets/css/productDetail.css">
</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- Product Detail Section -->
    <section class="product-detail-section">
        <div class="step-container">
            <div class="step active">
                <div class="circle">1</div>
                <div class="label active-label">เลือกสินค้า</div>
            </div>
            <div class="step">
                <div class="line"></div>
                <div class="circle">2</div>
                <div class="label">กรอกข้อมูล</div>
            </div>
            <div class="step">
                <div class="line"></div>
                <div class="circle">3</div>
                <div class="label">คำสั่งซื้อสำเร็จ</div>
            </div>
        </div>
        <div class="container">
            <div class="product-detail">
                <div class="product-detail-image">
                    <img src="<?php echo !empty($flower['image']) && file_exists("admin/uploads/flowers/" . $flower['image']) ? "admin/uploads/flowers/" . htmlspecialchars($flower['image']) : "assets/img/default-flower.jpg"; ?>"
                        alt="<?php echo htmlspecialchars($flower['flower_name']); ?>">
                </div>
                <div class="product-detail-info">
                    <div class="product-category"><span>หมวดหมู่ </span><?php echo htmlspecialchars($flower['flower_category']); ?></div>
                    <h1 class="product-name"><?php echo htmlspecialchars($flower['flower_name']); ?></h1>
                    <div class="product-price"><span>ราคา </span>฿<?php echo number_format($flower['price'], 2); ?></div>
                    <div class="stock-box <?php echo $flower['stock_quantity'] > 5 ? 'in-stock' : ($flower['stock_quantity'] > 0 ? 'low-stock' : 'out-of-stock'); ?>">
                        <?php
                        if ($flower['stock_quantity'] > 0) {
                            echo "มีสต็อก: " . $flower['stock_quantity'] . " ชิ้น";
                        } else {
                            echo "สินค้าหมดสต็อก";
                        }
                        ?>
                    </div>
                    <?php if (!empty($flower['flower_description'])): ?>
                        <p class="product-description"><?php echo htmlspecialchars($flower['flower_description']); ?></p>
                    <?php else: ?>
                        <p class="product-description">ไม่มีคำอธิบายสำหรับสินค้านี้</p>
                    <?php endif; ?>
                    <a href="product-order.php?id=<?php echo $id; ?>" class="buy-btn">
                        <i class="fas fa-shopping-cart"></i> ซื้อสินค้า
                    </a>
                </div>
            </div>

            <!-- Related Flowers Slider -->
            <?php if (!empty($related_flowers)): ?>
                <div class="mt-5">
                    <h2 class="section-title text-center mb-4">ดอกไม้ในหมวดหมู่เดียวกัน</h2>
                    <div class="swiper related-slider">
                        <div class="swiper-wrapper">
                            <?php foreach ($related_flowers as $related_flower): ?>
                                <div class="swiper-slide">
                                    <div class="flower-card">
                                        <div class="flower-image">
                                            <img src="<?php echo !empty($related_flower['image']) && file_exists("admin/uploads/flowers/" . $related_flower['image']) ? "admin/uploads/flowers/" . htmlspecialchars($related_flower['image']) : "assets/img/default-flower.jpg"; ?>"
                                                alt="<?php echo htmlspecialchars($related_flower['flower_name']); ?>">
                                        </div>
                                        <div class="flower-content">
                                            <h3 class="flower-name"><?php echo htmlspecialchars($related_flower['flower_name']); ?></h3>
                                            <div class="flower-price">฿<?php echo number_format($related_flower['price'], 2); ?></div>
                                            <?php if ($related_flower['stock_quantity'] <= 5 && $related_flower['stock_quantity'] > 0): ?>
                                                <span class="stock-status low-stock">เหลือน้อย</span>
                                            <?php elseif ($related_flower['stock_quantity'] > 5): ?>
                                                <span class="stock-status in-stock">มีสินค้า</span>
                                            <?php endif; ?>
                                            <button class="buy-btn mt-2" onclick="window.location.href='product-detail.php?id=<?php echo $related_flower['ID']; ?>'">
                                                <i class="fas fa-eye"></i> ดูรายละเอียด
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="swiper-pagination"></div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-button-next"></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- All Flowers Slider -->
            <?php if (!empty($all_flowers)): ?>
                <div class="mt-5">
                    <h2 class="section-title text-center mb-4">ดอกไม้ทั้งหมด</h2>
                    <div class="swiper all-slider">
                        <div class="swiper-wrapper">
                            <?php foreach ($all_flowers as $all_flower): ?>
                                <div class="swiper-slide">
                                    <div class="flower-card">
                                        <div class="flower-image">
                                            <img src="<?php echo !empty($all_flower['image']) && file_exists("admin/uploads/flowers/" . $all_flower['image']) ? "admin/uploads/flowers/" . htmlspecialchars($all_flower['image']) : "assets/img/default-flower.jpg"; ?>"
                                                alt="<?php echo htmlspecialchars($all_flower['flower_name']); ?>">
                                        </div>
                                        <div class="flower-content">
                                            <h3 class="flower-name"><?php echo htmlspecialchars($all_flower['flower_name']); ?></h3>
                                            <div class="flower-price">฿<?php echo number_format($all_flower['price'], 2); ?></div>
                                            <?php if ($all_flower['stock_quantity'] <= 5 && $all_flower['stock_quantity'] > 0): ?>
                                                <span class="stock-status low-stock">เหลือน้อย</span>
                                            <?php elseif ($all_flower['stock_quantity'] > 5): ?>
                                                <span class="stock-status in-stock">มีสินค้า</span>
                                            <?php endif; ?>
                                            <button class="buy-btn mt-2" onclick="window.location.href='product-detail.php?id=<?php echo $all_flower['ID']; ?>'">
                                                <i class="fas fa-eye"></i> ดูรายละเอียด
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="swiper-pagination"></div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-button-next"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends -->

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Swiper Slider -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Initialize Swiper -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Related Slider
            if (document.querySelector('.related-slider')) {
                new Swiper('.related-slider', {
                    slidesPerView: 'auto',
                    spaceBetween: 30,
                    loop: true,
                    autoplay: {
                        delay: 3500,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true, // Pause autoplay on hover
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
                            spaceBetween: 20
                        },
                        768: {
                            spaceBetween: 30
                        },
                        1200: {
                            spaceBetween: 40
                        },
                    },
                    slideToClickedSlide: true,
                });
            }

            // Initialize All Slider
            if (document.querySelector('.all-slider')) {
                new Swiper('.all-slider', {
                    slidesPerView: 'auto',
                    spaceBetween: 30,
                    loop: true,
                    autoplay: {
                        delay: 3500,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true, // Pause autoplay on hover
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
                            spaceBetween: 20
                        },
                        768: {
                            spaceBetween: 30
                        },
                        1200: {
                            spaceBetween: 40
                        },
                    },
                    slideToClickedSlide: true,
                });
            }
        });
    </script>
</body>

</html>