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
    <title>ซื้อสินค้า - <?php echo htmlspecialchars($flower['flower_name']); ?> - FlowerShop</title>
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
        <h1 class="heading m-0">กรอก<span>ข้อมูล</span></h1>
        
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
    
</body>

</html>