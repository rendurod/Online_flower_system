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
        <!-- font awesome cde link -->
        <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- header section starts -->

    <?php include("includes/navbar.php"); ?>

    <!-- header section ends -->
     
    <!-- home section starts-->

    <section class="home" id="home">
        <div class="content">
            <h3>Indira Gift flowers Shop</h3>
            <span>I will always be your flower.</span>
            <p>The most presented scents are often many. People think of the time when they want to find a gift on a
                special day,
                each type of fragrance has a different meaning, different flowers for that special person can be used to
                give.</p>
            <a href="#" class="btn">shop now</a>
        </div>
    </section>
    <!-- home section ends-->

    <!-- home section starts-->

    <section class="about" id="about">

        <h1 class="heading"><span> about </span> us </h1>

        <div class="row">
            <div class="video-container">
                <video src="../user/flower1.mp4" loop autoplay muted></video>
                <h3>best flower sellers</h3>
            </div>

            <div class="content">
                <h3>why choose us?</h3>
                <p>หากคุณกำลังมองหาร้านดอกไม้ออนไลน์คุณภาพ ให้บริการอย่างมือย่างชีพ และจัดส่งตรงเวลา Indira Gift flowers
                    คือคำตอบที่คุณกำลังตามหา
                    สินค้าดอกไม้ของเราจัดทำด้วยความใส่ใจ พิถีพิถันในทุกกรายละเอียด เพื่อให้ดอกไม้ออกมาสวยงานมากที่สุด
                    เพราะเรารู้ดีว่า ดอกไม้ คือตัวตัวแทนของ
                    ความรักและความห่วงใยจากผู้ให้ถึงผู้รับ หากเลือก Indira Gift flowers มั่นใจได้เลยว่า
                    ผู้รับจะต้องประกับใจกับของขวัญสุดพิเศษนี้อย่างแน่นอนค่ะ
                </p>
                <a href="#" class="btn">learn more</a>
            </div>

        </div>


    </section>

    <!-- home section ends-->

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
            <?php
    $sql = "SELECT ProductName, price, Image FROM product";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<div class="box">';
            echo '    <div class="image">';
            echo '        <img src="../admin/uploads/' . htmlspecialchars($row["Image"]) . '" alt="product image" style="max-width: 100%; max-height: 100%; object-fit: contain;">';
          
            echo '        <div class="icons">';
            // echo '            <a href="add.html" class="fas fa-heart"></a>';
            echo '            <a href="add.html" class="cart-btn">Add to Cart</a>';
            echo '        </div>';
            echo '    </div>';
            echo '    <div class="content">';
            echo '        <h3>' . htmlspecialchars($row["ProductName"]) . '</h3>';
            echo '        <div class="price">฿' . number_format($row["price"], 2) . '</div>';
            echo '    </div>';
            echo '</div>'; // ปิด div ของ box
        }
    } else {
        echo "No products found.";
    }
    ?>
        </div> <!-- ปิด box-container -->

</body>
</html>