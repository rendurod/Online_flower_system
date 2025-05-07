<?php
session_start();
include('config/db.php');

// ตรวจสอบถ้าผู้ใช้ล็อกอินอยู่แล้ว
if (isset($_SESSION['user_login'])) {
    header("location: user.php");
    exit;
}

// ตรวจสอบการส่งฟอร์มล็อกอิน
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // เพิ่มโค้ดตรวจสอบล็อกอินที่นี่
    // ...
}

// ตรวจสอบการส่งฟอร์มสมัครสมาชิก
if (isset($_POST['register'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // เพิ่มโค้ดสำหรับการสมัครสมาชิกที่นี่
    // ...
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ/สมัครสมาชิก - Flower Shop</title>
    <!-- LOGO -->
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/login&register.css">

</head>

<body>

    <!-- include navbar -->
    <?php include("includes/navbar.php"); ?>

    <div class="auth-container">
        <!-- Tab Navigation -->
        <ul class="nav nav-tabs custom-tabs" id="authTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link register-tab active" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">สมัครสมาชิก</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link login-tab" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab">เข้าสู่ระบบ</button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="authTabsContent">
            <!-- สมัครสมาชิก Tab -->
            <div class="tab-pane fade show active" id="register" role="tabpanel">
                <div class="text-center mt-3 mb-3">
                    <h5>สมัครสมาชิกฟรีด้วย</h5>
                </div>

                <!-- Social Login Options -->
                <div class="social-login">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fas fa-envelope"></i></a>
                    <a href="#"><i class="fab fa-line"></i></a>
                </div>

                <div class="divider">
                    <span>สมัครสมาชิกฟรี</span>
                </div>

                <!-- Register Form -->
                <form action="" method="post">
                    <div class="mb-3">
                        <input type="text" class="form-control" name="username" placeholder="ชื่อผู้ใช้งาน ภาษาอังกฤษเท่านั้น *" required>
                    </div>
                    <div class="mb-3">
                        <input type="email" class="form-control" name="email" placeholder="อีเมล * " required>
                    </div>
                    <div class="mb-3">
                        <input type="password" class="form-control" name="password" placeholder="รหัสผ่าน *" required>
                    </div>
                    <div class="mb-3">
                        <input type="password" class="form-control" name="confirm_password" placeholder="ยืนยันรหัสผ่าน *" required>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" required>
                        <label class="form-check-label" for="terms">ยอมรับ เงื่อนไขการใช้บริการ</label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="privacy">
                        <label class="form-check-label" for="privacy">ยอมรับ เงื่อนไขความเป็นส่วนตัว</label>
                    </div>

                    <button type="submit" name="register" class="submit-btn">สมัครสมาชิกฟรี</button>
                </form>
            </div>

            <!-- เข้าสู่ระบบ Tab -->
            <div class="tab-pane fade" id="login" role="tabpanel">
                <div class="text-center mt-3 mb-3">
                    <h5>เข้าสู่ระบบด้วย</h5>
                </div>

                <!-- Social Login Options -->
                <div class="social-login">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fas fa-envelope"></i></a>
                    <a href="#"><i class="fab fa-line"></i></a>
                </div>

                <div class="divider">
                    <span>เข้าสู่ระบบ</span>
                </div>

                <!-- Login Form -->
                <form action="" method="post">
                    <div class="mb-3">
                        <input type="email" class="form-control" name="email" placeholder="อีเมล" required>
                    </div>
                    <div class="mb-3">
                        <input type="password" class="form-control" name="password" placeholder="รหัสผ่าน" required>
                    </div>
                    <div class="mb-3 text-end">
                        <a href="#" style="color: #666; font-size: 1.2rem;">ลืมรหัสผ่าน?</a>
                    </div>
                    <button type="submit" name="login" class="submit-btn">เข้าสู่ระบบ</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>