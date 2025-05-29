<?php

session_start();
include('config/db.php');

if (isset($_SESSION['user_login'])) {
    header("location: user.php");
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
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

    <!-- header section starts -->

    <?php include("includes/navbar.php"); ?>

    <!-- header section ends -->

    <!-- prodcuts section starts-->

    <section class="products" id="products">
        <h1 class="heading"> Latest <span>Products</span></h1>
        <!-- <div class="box-container">

            <div class="box">

                <div class="image">
                    <img src="images/flowers.jpg" alt="Flower Pot">
                    <div class="icons">
                        <a href="#" class="fas fa-heart"></a>
                        <a href="add.php" class="cart-btn">Add to Cart</a>
                    </div>
                </div>
                <div class="content">
                    <h3>Flower Pot</h3>
                    <div class="price">3990฿ </div>
                </div>
            </div>
            <div class="box">

                <div class="image">
                    <img src="images/flowers.jpg" alt="Flower Pot">
                    <div class="icons">
                        <a href="#" class="fas fa-heart"></a>
                        <a href="add.php" class="cart-btn">Add to Cart</a>
                    </div>
                </div>
                <div class="content">
                    <h3>Flower Pot</h3>
                    <div class="price">3990฿ </div>
                </div>
            </div> -->
        <div class="box-container">

        </div> <!-- ปิด box-container -->

        <!-- Bootstrap 5 JS Bundle with Popper -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>