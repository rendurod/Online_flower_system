<?php
session_start();
include('config/db.php');

// ตรวจสอบว่าผู้ใช้ล็อกอินหรือไม่ ถ้าไม่ ให้ redirect ไปหน้า login
if (!isset($_SESSION['user_login'])) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['user_login'];

// --- การสร้าง Query แบบไดนามิกสำหรับการค้นหาและกรอง ---

// รับค่าจาก GET parameters
$search_term = $_GET['search'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'newest';

// เริ่มต้น query และ array สำหรับ parameters
$sql = "SELECT ID, flower_name, flower_description, price, image, stock_quantity FROM tbl_flowers WHERE stock_quantity > 0";
$params = [];
$types = ''; // String สำหรับ bind_param types (ถ้าใช้ mysqli)

// 1. กรองด้วยคำค้นหา (Search)
if (!empty($search_term)) {
    $sql .= " AND (flower_name LIKE ? OR flower_description LIKE ?)";
    $like_term = "%" . $search_term . "%";
    $params[] = $like_term;
    $params[] = $like_term;
}

// 2. กรองด้วยราคาขั้นต่ำ (Min Price)
if (is_numeric($min_price) && $min_price >= 0) {
    $sql .= " AND price >= ?";
    $params[] = $min_price;
}

// 3. กรองด้วยราคาขั้นสูง (Max Price)
if (is_numeric($max_price) && $max_price > 0) {
    $sql .= " AND price <= ?";
    $params[] = $max_price;
}

// 4. จัดเรียงข้อมูล (Sort)
switch ($sort_by) {
    case 'price_asc':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY price DESC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY flower_name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY flower_name DESC";
        break;
    default: // newest
        $sql .= " ORDER BY creation_date DESC";
        break;
}

// --- ดึงข้อมูลจากฐานข้อมูล ---
$flowers = [];
$message = '';
$messageType = 'danger';

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $flowers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "เกิดข้อผิดพลาดในการดึงข้อมูลสินค้า: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สินค้าดอกไม้ - FlowerShop</title>
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
    <link rel="stylesheet" href="assets/css/productPHP.css">
    <link rel="stylesheet" href="assets/css/flowerPHP.css"> <!-- ใช้ CSS ร่วมกับ user.php เพื่อสไตล์ที่สอดคล้องกัน -->

</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- Hero Section -->
    <section class="products-hero">
        <div class="container">
            <div class="text-center">
                <h1 class="heading mb-3">สินค้า<span>ทั้งหมด</span></h1>
                <p style="font-size: 1.8rem; color: var(--text-light); max-width: 600px; margin: 0 auto;">
                    ค้นพบความงามของดอกไม้สดใหม่ คัดสรรมาเป็นพิเศษเพื่อคุณ
                </p>
            </div>
        </div>
    </section>
    <!-- Hero Section Ends-->

    <!-- Products Section -->
    <section class="products-section py-5">
        <div class="container">
            <!-- Filter and Search Form -->
            <form action="products.php" method="GET" class="filter-form mb-5 p-4 rounded-3 shadow-sm">
                <div class="row g-3 align-items-end">
                    <!-- Search Input -->
                    <div class="col-lg-4 col-md-6">
                        <label for="search" class="form-label">ค้นหาสินค้า</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="ชื่อดอกไม้, รายละเอียด..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <!-- Price Range -->
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label">ช่วงราคา</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="min_price" placeholder="ต่ำสุด" min="0" value="<?php echo htmlspecialchars($min_price); ?>">
                            <span class="input-group-text">-</span>
                            <input type="number" class="form-control" name="max_price" placeholder="สูงสุด" min="0" value="<?php echo htmlspecialchars($max_price); ?>">
                        </div>
                    </div>
                    <!-- Sort By -->
                    <div class="col-lg-2 col-md-6">
                        <label for="sort_by" class="form-label">จัดเรียงตาม</label>
                        <select class="form-select" id="sort_by" name="sort_by">
                            <option value="newest" <?php if ($sort_by == 'newest') echo 'selected'; ?>>มาใหม่ล่าสุด</option>
                            <option value="price_asc" <?php if ($sort_by == 'price_asc') echo 'selected'; ?>>ราคา: น้อยไปมาก</option>
                            <option value="price_desc" <?php if ($sort_by == 'price_desc') echo 'selected'; ?>>ราคา: มากไปน้อย</option>
                            <option value="name_asc" <?php if ($sort_by == 'name_asc') echo 'selected'; ?>>ชื่อ: A-Z</option>
                            <option value="name_desc" <?php if ($sort_by == 'name_desc') echo 'selected'; ?>>ชื่อ: Z-A</option>
                        </select>
                    </div>
                    <!-- Submit Button -->
                    <div class="col-lg-2 col-md-6 d-grid">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-2"></i>กรองข้อมูล</button>
                    </div>
                </div>
            </form>

            <!-- Display Messages -->
            <?php if (!empty($message)) : ?>
                <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Product Grid -->
            <div class="row g-4">
                <?php if (!empty($flowers)) : ?>
                    <?php foreach ($flowers as $flower) : ?>
                        <div class="col-12 col-sm-6 col-lg-4 col-xl-3 d-flex align-items-stretch">
                            <div class="flower-card w-100">
                                <div class="flower-image">
                                    <img src="<?php echo !empty($flower['image']) && file_exists("admin/uploads/flowers/" . $flower['image']) ? "admin/uploads/flowers/" . htmlspecialchars($flower['image']) : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($flower['flower_name']); ?>" class="flower-image">
                                </div>
                                <div class="flower-content">
                                    <div class="flower-id">A<?php echo htmlspecialchars($flower['ID']); ?></div>
                                    <h3 class="flower-name"><?php echo htmlspecialchars($flower['flower_name']); ?></h3>
                                    <p class="flower-description"><?php echo htmlspecialchars($flower['flower_description'] ?? 'ไม่มีรายละเอียด'); ?></p>
                                    <div class="flower-price"><?php echo number_format($flower['price'], 2); ?> บาท</div>
                                    <?php if ($flower['stock_quantity'] <= 5 && $flower['stock_quantity'] > 0) : ?>
                                        <span class="stock-status low-stock">เหลือน้อย</span>
                                    <?php elseif ($flower['stock_quantity'] > 5) : ?>
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
                <?php else : ?>
                    <div class="col-12">
                        <div class="no-data-message text-center p-5">
                            <i class="fas fa-search fa-3x mb-3"></i>
                            <h4>ไม่พบสินค้าที่ตรงกับเงื่อนไขของคุณ</h4>
                            <p>ลองปรับเปลี่ยนคำค้นหาหรือตัวกรองของคุณ</p>
                            <a href="products.php" class="btn btn-secondary mt-3">ล้างตัวกรองทั้งหมด</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends-->

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // SweetAlert2 สำหรับปุ่มเพิ่มลงตะกร้า
            document.querySelectorAll('.add-to-cart-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const flowerId = this.getAttribute('data-id');
                    fetch('add_to_cart.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
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
                                    timer: 2000,
                                    timerProgressBar: true
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
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้',
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

            // จัดการฟอร์มกรองข้อมูล: ไม่ส่งค่าว่างไปใน URL
            const filterForm = document.querySelector('.filter-form');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    const inputs = this.querySelectorAll('input, select');
                    inputs.forEach(input => {
                        if (input.value === '') {
                            input.name = ''; // เอา name ออกเพื่อไม่ให้ส่งไปกับ URL
                        }
                    });
                });
            }
        });
    </script>
</body>

</html>
