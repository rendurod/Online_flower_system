<?php
session_start();
include('config/db.php');

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Flower_PHP</title>
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- Hero Section -->
    <section class="products-hero">
        <div class="container">
            <div class="text-center">
                <h1 class="heading mb-3">เกี่ยวกับ<span>เรา</span></h1>
                <p style="font-size: 1.8rem; color: var(--text-light); max-width: 600px; margin: 0 auto;">
                    ค้นพบความงามของดอกไม้สดใหม่ คัดสรรมาเป็นพิเศษเพื่อคุณ
                </p>
            </div>
        </div>
    </section>
    <!-- Hero Section Ends-->
     <section class="about" id="about">

        <div class="row">
            <div class="video-container">
                <video src="assets/video/flower1.mp4" loop autoplay muted></video>
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
                <!-- <a href="#" class="btn">learn more</a> -->
            </div>

        </div>


    </section>

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends-->

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>