<?php
session_start();
include('config/db.php');

// ดึงหมวดหมู่ทั้งหมดสำหรับ filter
$categories_query = "SELECT DISTINCT flower_category FROM tbl_flowers ORDER BY flower_category";
$categories_stmt = $conn->prepare($categories_query);
$categories_stmt->execute();
$categories_result = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// จัดการการกรองสินค้า
$where_clause = "WHERE 1=1";
$params = [];

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $where_clause .= " AND flower_category = :category";
    $params[':category'] = $_GET['category'];
}

if (isset($_GET['min_price']) && !empty($_GET['min_price'])) {
    $where_clause .= " AND price >= :min_price";
    $params[':min_price'] = floatval($_GET['min_price']);
}

if (isset($_GET['max_price']) && !empty($_GET['max_price'])) {
    $where_clause .= " AND price <= :max_price";
    $params[':max_price'] = floatval($_GET['max_price']);
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_clause .= " AND (flower_name LIKE :search OR flower_description LIKE :search_desc)";
    $search_term = '%' . $_GET['search'] . '%';
    $params[':search'] = $search_term;
    $params[':search_desc'] = $search_term;
}

// จัดการการเรียงลำดับ
$order_by = "ORDER BY creation_date DESC";
if (isset($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'price_asc':
            $order_by = "ORDER BY price ASC";
            break;
        case 'price_desc':
            $order_by = "ORDER BY price DESC";
            break;
        case 'name_asc':
            $order_by = "ORDER BY flower_name ASC";
            break;
        case 'name_desc':
            $order_by = "ORDER BY flower_name DESC";
            break;
        case 'newest':
            $order_by = "ORDER BY creation_date DESC";
            break;
        case 'oldest':
            $order_by = "ORDER BY creation_date ASC";
            break;
    }
}

// ดึงข้อมูลสินค้า
$query = "SELECT * FROM tbl_flowers $where_clause $order_by";
$stmt = $conn->prepare($query);

// Bind parameters
foreach ($params as $key => $value) {
    $paramType = is_float($value) ? PDO::PARAM_STR : PDO::PARAM_STR;
    $stmt->bindValue($key, $value, $paramType);
}

$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    
</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- Hero Section -->
    <section class="products-hero">
        <div class="container">
            <div class="text-center">
                <h1 class="heading mb-3">สินค้า<span>ดอกไม้</span></h1>
                <p style="font-size: 1.8rem; color: var(--text-light); max-width: 600px; margin: 0 auto;">
                    ค้นพบความงามของดอกไม้สดใหม่ คัดสรรมาเป็นพิเศษเพื่อคุณ
                </p>
            </div>
        </div>
    </section>
    <!-- Hero Section Ends-->

    <!-- Filter Section -->
    <section class="container">
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="search">ค้นหาสินค้า</label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               class="filter-input" 
                               placeholder="ชื่อดอกไม้ หรือคำอธิบาย..."
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="category">หมวดหมู่</label>
                        <select id="category" name="category" class="filter-input">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($categories_result as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['flower_category']); ?>" 
                                        <?php echo (isset($_GET['category']) && $_GET['category'] == $category['flower_category']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['flower_category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="min_price">ราคาต่ำสุด</label>
                        <input type="number" 
                               id="min_price" 
                               name="min_price" 
                               class="filter-input" 
                               placeholder="0"
                               min="0"
                               step="0.01"
                               value="<?php echo isset($_GET['min_price']) ? htmlspecialchars($_GET['min_price']) : ''; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="max_price">ราคาสูงสุด</label>
                        <input type="number" 
                               id="max_price" 
                               name="max_price" 
                               class="filter-input" 
                               placeholder="5000"
                               min="0"
                               step="0.01"
                               value="<?php echo isset($_GET['max_price']) ? htmlspecialchars($_GET['max_price']) : ''; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-search"></i>
                            ค้นหา
                        </button>
                    </div>
                    
                    <div class="filter-group">
                        <a href="products.php" class="clear-btn">
                            <i class="fas fa-times"></i>
                            ล้างตัวกรอง
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Products Section -->
    <section class="container">
        <?php 
        $total_products = count($result);
        ?>
        
        <div class="results-info">
            <div class="results-count">
                <strong><?php echo $total_products; ?></strong> สินค้าที่พบ
            </div>
            
            <div class="sort-section">
                <form method="GET" style="display: inline;">
                    <!-- เก็บ parameter เดิม -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if ($key !== 'sort'): ?>
                            <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <select name="sort" class="sort-select" onchange="this.form.submit()">
                        <option value="newest" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'newest') ? 'selected' : ''; ?>>ใหม่ล่าสุด</option>
                        <option value="oldest" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'oldest') ? 'selected' : ''; ?>>เก่าที่สุด</option>
                        <option value="price_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_asc') ? 'selected' : ''; ?>>ราคา: ต่ำ - สูง</option>
                        <option value="price_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_desc') ? 'selected' : ''; ?>>ราคา: สูง - ต่ำ</option>
                        <option value="name_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'name_asc') ? 'selected' : ''; ?>>ชื่อ: A - Z</option>
                        <option value="name_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'name_desc') ? 'selected' : ''; ?>>ชื่อ: Z - A</option>
                    </select>
                </form>
            </div>
        </div>

        <?php if ($total_products > 0): ?>
            <div class="products-grid">
                <?php foreach ($result as $flower): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="<?php echo !empty($flower['image']) && file_exists("admin/uploads/flowers/" . $flower['image']) ? "admin/uploads/flowers/" . htmlspecialchars($flower['image']) : "assets/img/default-flower.jpg"; ?>" 
                                 alt="<?php echo htmlspecialchars($flower['flower_name']); ?>">
                        </div>
                        
                        <div class="product-info">
                            <div class="product-category"><?php echo htmlspecialchars($flower['flower_category']); ?></div>
                            <h3 class="product-name"><?php echo htmlspecialchars($flower['flower_name']); ?></h3>
                            
                            <?php if (!empty($flower['flower_description'])): ?>
                                <p class="product-description">
                                    <?php 
                                    $description = htmlspecialchars($flower['flower_description']);
                                    echo strlen($description) > 50 ? substr($description, 0, 50) . '...' : $description;
                                    ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="product-price">฿<?php echo number_format($flower['price'], 2); ?></div>
                            
                            <div class="product-actions">
                                <?php if ($flower['stock_quantity'] > 0): ?>
                                    <button class="select-btn" onclick="window.location.href='product-detail.php?id=<?php echo $flower['ID']; ?>'">
                                        <i class="fas fa-shopping-cart"></i>
                                        เลือกซื้อ
                                    </button>
                                    
                                    <?php if ($flower['stock_quantity'] <= 5): ?>
                                        <span class="stock-status low-stock">เหลือน้อย</span>
                                    <?php else: ?>
                                        <span class="stock-status in-stock">มีสินค้า</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="select-btn" disabled style="background: #bdc3c7; cursor: not-allowed;">
                                        <i class="fas fa-times"></i>
                                        สินค้าหมด
                                    </button>
                                    <span class="stock-status out-of-stock">หมดสต็อก</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-products">
                <i class="fas fa-search"></i>
                <h3>ไม่พบสินค้าที่ค้นหา</h3>
                <p>ลองเปลี่ยนเงื่อนไขการค้นหาหรือ <a href="products.php">ดูสินค้าทั้งหมด</a></p>
            </div>
        <?php endif; ?>
    </section>

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends-->

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <script>
        // การจัดการ responsive สำหรับ filter section
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    form.querySelector('.filter-btn').click();
                }
            });

            const sortSelect = document.querySelector('.sort-select');
            sortSelect.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>