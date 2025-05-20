<?php
session_start();
include('config/db.php');

// ถ้ามีการ login อยู่แล้วให้ไปหน้า user.php
if (isset($_SESSION['user_login'])) {
    header("location: user.php");
    exit;
}

// ตรวจสอบการส่งฟอร์มสมัครสมาชิก
if (isset($_POST['register'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // TODO: เพิ่มการตรวจสอบและบันทึกข้อมูล
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - Flower Shop</title>
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
    <?php include("includes/navbar.php"); ?>

    <div class="container">
        <div class="login-container">
            <h2 class="text-center mb-4">สมัครสมาชิก</h2>
            <form method="POST" action="">
                <div class="mb-3">
                    <input type="text" class="form-control" name="username" placeholder="ชื่อผู้ใช้งาน ภาษาอังกฤษตัวพิมพ์เล็ก *" required>
                </div>
                <div class="mb-3">
                    <input type="email" class="form-control" name="email" placeholder="อีเมล *" required>
                </div>
                <div class="mb-3 password-toggle">
                    <input type="password" class="form-control" name="password" placeholder="รหัสผ่าน *" required>
                    <i class="toggle-password far fa-eye-slash"></i>
                </div>
                <div class="mb-3 password-toggle">
                    <input type="password" class="form-control" name="confirm_password" placeholder="ยืนยันรหัสผ่าน *" required>
                    <i class="toggle-password far fa-eye-slash"></i>
                </div>
                <button type="submit" name="register" class="btn-register">สมัครสมาชิก</button>
            </form>
            <div class="text-center mt-3">
                <p>มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordToggles = document.querySelectorAll('.toggle-password');
            passwordToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    if (input.type === 'password') {
                        input.type = 'text';
                        this.classList.remove('fa-eye-slash');
                        this.classList.add('fa-eye');
                    } else {
                        input.type = 'password';
                        this.classList.remove('fa-eye');
                        this.classList.add('fa-eye-slash');
                    }
                });
            });
        });
    </script>
</body>
</html>