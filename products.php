<?php
session_start();
include('config/db.php');

// ตรวจสอบสถานะการล็อกอิน แต่ไม่บังคับ (สำหรับตะกร้าสินค้า)
$user_id = $_SESSION['user_login'] ?? null;

// --- ดึงข้อมูลหมวดหมู่ (Categories) สำหรับ Filter ---
$categories = [];
try {
    $cat_stmt = $conn->prepare("SELECT DISTINCT FlowerType FROM tbl_category WHERE FlowerType IS NOT NULL AND FlowerType != '' ORDER BY FlowerType ASC");
    $cat_stmt->execute();
    $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // สามารถบันทึก log error ไว้ได้ แต่ไม่จำเป็นต้องหยุดการทำงานของหน้าเว็บ
}

// --- การตั้งค่า Pagination ---
$items_per_page = 8;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// --- รับค่า Filter จาก GET Parameters ---
$category = $_GET['category'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'price_asc'; // ค่าเริ่มต้นคือ ราคาน้อยที่สุด
$stock_status = $_GET['stock_status'] ?? '';

// --- สร้าง Query แบบไดนามิก ---
$base_sql = "FROM tbl_flowers";
$where_conditions = [];
$params = [];

// 1. กรองด้วยหมวดหมู่ (Category)
if (!empty($category)) {
    $where_conditions[] = "flower_category = ?";
    $params[] = $category;
}

// 2. กรองด้วยสถานะสต็อก (Stock Status)
switch ($stock_status) {
    case 'in_stock':
        $where_conditions[] = "stock_quantity > 5";
        break;
    case 'low_stock':
        $where_conditions[] = "stock_quantity <= 5";
        break;
    default:
        // ถ้าไม่ได้เลือกสถานะ ให้แสดงเฉพาะสินค้าที่มีในสต็อก (ค่าเริ่มต้น)
        $where_conditions[] = "stock_quantity > 0";
        break;
}

// รวมเงื่อนไข WHERE ทั้งหมด
if (!empty($where_conditions)) {
    $base_sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// --- ดึงจำนวนสินค้าทั้งหมดสำหรับ Pagination ---
$total_items = 0;
try {
    $total_stmt = $conn->prepare("SELECT COUNT(ID) " . $base_sql);
    $total_stmt->execute($params);
    $total_items = $total_stmt->fetchColumn();
} catch (PDOException $e) {
    // จัดการ error แต่ไม่หยุดการทำงาน
}
$total_pages = ceil($total_items / $items_per_page);

// --- จัดเรียงข้อมูล (Sort) ---
$sql_order_by = '';
switch ($sort_by) {
    case 'price_desc':
        $sql_order_by = " ORDER BY price DESC";
        break;
    default: // price_asc
        $sql_order_by = " ORDER BY price ASC";
        break;
}

// --- ดึงข้อมูลสินค้าสำหรับหน้าที่เลือก ---
$sql_select = "SELECT ID, flower_name, flower_category, flower_description, price, image, stock_quantity ";
$sql_limit = " LIMIT ? OFFSET ?";
$limit_params = $params;
$limit_params[] = $items_per_page;
$limit_params[] = $offset;

$flowers = [];
$message = '';
$messageType = 'danger';

try {
    $stmt = $conn->prepare($sql_select . $base_sql . $sql_order_by . $sql_limit);
    foreach ($limit_params as $key => $value) {
        $param_type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key + 1, $value, $param_type);
    }
    $stmt->execute();
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
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/productPHP.css">
    <link rel="stylesheet" href="assets/css/flowerPHP.css">

    <!-- CSS สำหรับ Layout และ Filter ใหม่ -->
    <style>
        .filter-sidebar {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .filter-group {
            margin-bottom: 2rem;
        }

        .filter-group-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--black);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--pink);
        }

        .form-check-label {
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: var(--pink);
            border-color: var(--pink);
        }

        .product-count-display {
            font-size: 1.2rem;
            font-weight: 500;
        }

        .product-count-display span {
            color: var(--pink);
            font-weight: 700;
        }

        /* Custom Pagination Styling */
        .custom-pagination .page-item .page-link {
            color: var(--pink);
            border: 1px solid var(--pink);
            margin: 0 5px;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .custom-pagination .page-item.active .page-link {
            background-color: var(--pink);
            color: #fff;
            border-color: var(--pink);
        }

        .custom-pagination .page-item.disabled .page-link {
            color: #6c757d;
            background-color: #fff;
            border-color: #dee2e6;
        }

        .custom-pagination .page-item .page-link:hover {
            background-color: #f8d7da;
        }
    </style>
</head>

<body>
    <?php include("includes/navbar.php"); ?>

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

    <section class="products-section py-5">
        <div class="container">
            <div class="row">

                <!-- Filter Sidebar -->
                <aside class="col-lg-3">
                    <form action="products.php" method="GET" id="filter-form">
                        <div class="filter-sidebar">

                            <!-- Category Filter -->
                            <div class="filter-group">
                                <h5 class="filter-group-title">หมวดหมู่สินค้า</h5>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="category" id="cat-all" value="" <?php if (empty($category)) echo 'checked'; ?>>
                                    <label class="form-check-label" for="cat-all">ทั้งหมด</label>
                                </div>
                                <?php foreach ($categories as $cat) : ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="category" id="cat-<?php echo htmlspecialchars($cat); ?>" value="<?php echo htmlspecialchars($cat); ?>" <?php if ($category == $cat) echo 'checked'; ?>>
                                        <label class="form-check-label" for="cat-<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Price Filter (Sort) -->
                            <div class="filter-group">
                                <h5 class="filter-group-title">เรียงตามราคา</h5>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="sort_by" id="sort-asc" value="price_asc" <?php if ($sort_by == 'price_asc') echo 'checked'; ?>>
                                    <label class="form-check-label" for="sort-asc">ราคาน้อยที่สุด (ค่าเริ่มต้น)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="sort_by" id="sort-desc" value="price_desc" <?php if ($sort_by == 'price_desc') echo 'checked'; ?>>
                                    <label class="form-check-label" for="sort-desc">ราคามากที่สุด</label>
                                </div>
                            </div>

                            <!-- Stock Filter (Updated to Two Statuses) -->
                            <div class="filter-group">
                                <h5 class="filter-group-title">สถานะสินค้า</h5>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="stock_status" id="stock-all" value="" <?php if (empty($stock_status)) echo 'checked'; ?>>
                                    <label class="form-check-label" for="stock-all">สินค้าพร้อมส่ง</label>
                                </div>
                                <!-- <div class="form-check">
                                    <input class="form-check-input" type="radio" name="stock_status" id="stock-in" value="in_stock" <?php if ($stock_status == 'in_stock') echo 'checked'; ?>>
                                    <label class="form-check-label" for="stock-in">สินค้าพร้อมส่ง (มากกว่า 5)</label>
                                </div> -->
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="stock_status" id="stock-low" value="low_stock" <?php if ($stock_status == 'low_stock') echo 'checked'; ?>>
                                    <label class="form-check-label" for="stock-low">สินค้าใกล้หมด</label>
                                </div>
                            </div>

                            <!-- <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-2"></i>ใช้ตัวกรอง</button>
                                <a href="products.php" class="btn btn-outline-secondary">ล้างค่าทั้งหมด</a>
                            </div> -->
                        </div>
                    </form>
                </aside>

                <!-- Product Listing -->
                <main class="col-lg-9">
                    <!-- Product Count -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="product-count-display">
                            ทั้งหมด: <span><?php echo $total_items; ?></span> รายการ
                        </div>
                    </div>

                    <div class="row g-4">
                        <?php if (!empty($flowers)) : ?>
                            <?php foreach ($flowers as $flower) : ?>
                                <div class="col-12 col-sm-6 col-md-4 d-flex align-items-stretch">
                                    <div class="flower-card w-100">
                                        <div class="flower-image">
                                            <img src="<?php echo !empty($flower['image']) && file_exists("admin/uploads/flowers/" . $flower['image']) ? "admin/uploads/flowers/" . htmlspecialchars($flower['image']) : "assets/img/default-flower.jpg"; ?>" alt="<?php echo htmlspecialchars($flower['flower_name']); ?>" class="flower-image">
                                        </div>
                                        <div class="flower-content">
                                            <div class="flower-id">A<?php echo htmlspecialchars($flower['ID']); ?></div>
                                            <h3 class="flower-name"><?php echo htmlspecialchars($flower['flower_name']); ?></h3>
                                            <p class="flower-description"><?php echo htmlspecialchars($flower['flower_description'] ?? 'ไม่มีรายละเอียด'); ?></p>
                                            <div class="flower-price"><?php echo number_format($flower['price'], 2); ?> บาท</div>
                                            <?php if ($flower['stock_quantity'] > 5): ?>
                                                <span class="stock-status in-stock">สินค้าพร้อมส่ง</span>
                                            <?php elseif ($flower['stock_quantity'] > 0 && $flower['stock_quantity'] <= 5): ?>
                                                <span class="stock-status low-stock">สินค้าใกล้หมด</span>
                                            <?php else: ?>
                                                <span class="stock-status out-of-stock">สินค้าหมดสต๊อก</span>
                                            <?php endif; ?>
                                            <div class="flower-buttons">
                                                <a href="product-detail.php?id=<?php echo htmlspecialchars($flower['ID']); ?>" class="btn">
                                                    <i class="fas fa-search me-2"></i> ดูสินค้า
                                                </a>
                                                <button class="btn add-to-cart-btn" data-id="<?php echo htmlspecialchars($flower['ID']); ?>" <?php if ($flower['stock_quantity'] <= 0) echo 'disabled'; ?>>
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
                                    <p>ลองปรับเปลี่ยนตัวกรอง หรือล้างค่าทั้งหมด</p>
                                    <a href="products.php" class="btn btn-secondary mt-3">ล้างตัวกรองทั้งหมด</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Styled Pagination -->
                    <?php if ($total_pages > 1) : ?>
                        <nav aria-label="Page navigation" class="mt-5">
                            <ul class="pagination custom-pagination justify-content-center">
                                <?php
                                $query_params = $_GET;
                                unset($query_params['page']);
                                $query_string = http_build_query($query_params);
                                ?>
                                <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo $query_string; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                                    <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo $query_string; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo $query_string; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </main>
            </div>
        </div>
    </section>

    <?php include("includes/footer.php"); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const isLoggedIn = <?php echo json_encode($user_id !== null); ?>;

            document.querySelectorAll('.add-to-cart-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const flowerId = this.getAttribute('data-id');

                    if (isLoggedIn) {
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
                                    if (cartCounter) cartCounter.innerText = data.cartCount;
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'เพิ่มสินค้าสำเร็จ!',
                                        text: data.message,
                                        confirmButtonText: 'ตกลง'
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'ไม่สามารถเพิ่มสินค้าได้',
                                        text: data.message
                                    });
                                }
                            }).catch(error => console.error('Error:', error));
                    } else {
                        Swal.fire({
                            icon: 'info',
                            title: 'กรุณาล็อกอิน',
                            text: 'คุณต้องเข้าสู่ระบบก่อน จึงจะสามารถเพิ่มสินค้าลงตะกร้าได้',
                            confirmButtonText: 'ไปที่หน้าล็อกอิน',
                            showCancelButton: false,
                            // cancelButtonText: 'ยกเลิก'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'login.php?return_url=' + encodeURIComponent(window.location.href);
                            }
                        });
                    }
                });
            });

            // Auto-submit form when a radio button is clicked for instant filtering
            document.querySelectorAll('#filter-form input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    document.getElementById('filter-form').submit();
                });
            });
        });
    </script>
</body>

</html>