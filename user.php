<?php
session_start();
include('config/db.php');

// ตรวจสอบว่าผู้ใช้ล็อกอินแล้วหรือยัง
if (!isset($_SESSION['user_login'])) {
    header("location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User - Flower_PHP</title>
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

    <!-- home section starts-->
    <section class="home" id="home">
        <div class="content">
            <h3>ยินดีต้อนรับกลับมา!</h3>
            <span>Indira Gift flowers Shop</span>
            <p>คุณได้เข้าสู่ระบบเรียบร้อยแล้ว :) สนุกกับการเลือกชมดอกไม้และของขวัญสุดพิเศษได้เลย!</p>
            <a href="admin/index.php" class="btn btn-danger">เข้าสู่หน้าแอดมิน</a>
        </div>
    </section>
    <!-- home section ends-->

    <!-- about section starts-->
    <section class="about" id="about">
        <h1 class="heading"><span> about </span> us </h1>
        <div class="row">
            <div class="video-container">
                <video src="assets/video/flower1.mp4" loop autoplay muted></video>
                <h3>best flower sellers</h3>
            </div>
            <div class="content">
                <h3>why choose us?</h3>
                <p>Indira Gift flowers คือร้านดอกไม้ออนไลน์ที่ให้บริการด้วยใจ
                    ดอกไม้ทุกช่อของเราถูกจัดขึ้นอย่างประณีต เพื่อสื่อสารความรู้สึกแท้จริงของคุณ
                    ส่งมอบความประทับใจอย่างมืออาชีพในทุกโอกาสพิเศษ.</p>
                <a href="#" class="btn">learn more</a>
            </div>
        </div>
    </section>
    <!-- about section ends-->



    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
