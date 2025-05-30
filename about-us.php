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
    <?php include("includes/navbar.php"); ?>
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

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends-->

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>