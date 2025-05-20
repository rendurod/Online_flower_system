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

    <div class="container">
        <div class="login-container">
            <div class="nav-tabs">
                <div class="nav-item">
                    <a class="nav-link active" href="#register" data-bs-toggle="tab">สมัครสมาชิก</a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="#login" data-bs-toggle="tab">เข้าสู่ระบบ</a>
                </div>
            </div>
            
            <div class="tab-content">
                <div class="tab-pane fade show active" id="register">
                    
                    <div class="divider">
                        <div class="divider-line"></div>
                        <div class="divider-text">สมัครสมาชิกฟรี</div>
                        <div class="divider-line"></div>
                    </div>
                    
                    <form>
                        <input type="text" class="form-control" placeholder="ชื่อผู้ใช้งาน ภาษาอังกฤษตัวพิมพ์เล็ก *" required>
                        
                        <input type="email" class="form-control" placeholder="อีเมล *" required>
                        
                        <div class="password-toggle">
                            <input type="password" class="form-control" placeholder="รหัสผ่าน *" required>
                            <i class="toggle-password far fa-eye-slash"></i>
                        </div>
                        
                        <div class="password-toggle">
                            <input type="password" class="form-control" placeholder="ยืนยันรหัสผ่าน *" required>
                            <i class="toggle-password far fa-eye-slash"></i>
                        </div>
                        
                        <button type="submit" class="btn-register mt-3">สมัครสมาชิกฟรี</button>
                    </form>
                </div>
                
                <div class="tab-pane fade" id="login">
                    <form>
                        <input type="text" class="form-control" placeholder="ชื่อผู้ใช้งาน" required>
                        
                        <div class="password-toggle">
                            <input type="password" class="form-control" placeholder="รหัสผ่าน" required>
                            <i class="toggle-password far fa-eye-slash"></i>
                        </div>
                        
                        <div class="text-end mb-3">
                            <a href="#" class="text-decoration-none text-muted">ลืมรหัสผ่าน?</a>
                        </div>
                        
                        <button type="submit" class="btn-register">เข้าสู่ระบบ</button>
                    </form>
                </div>
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