<?php
session_start();
if (isset($_SESSION['user_login'])) {
    header("location: user.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - Flower Shop</title>
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/login&register.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="login">

    <!-- include navbar -->
    <?php include("includes/navbar.php"); ?>

    <div class="container">
        <div class="login-container">
            <div class="nav-tabs">
                <div class="nav-item">
                    <a class="nav-link active" href="register.php">สมัครสมาชิก</a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="login.php">เข้าสู่ระบบ</a>
                </div>
            </div>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="login">

                    <div class="divider">
                        <div class="divider-line"></div>
                        <div class="divider-text">เข้าสู่ระบบ</div>
                        <div class="divider-line"></div>
                    </div>

                    <form id="login-form">
                        <input type="email" class="form-control" name="email" placeholder="อีเมล *" required>

                        <div class="password-toggle">
                            <input type="password" class="form-control" name="password" placeholder="รหัสผ่าน *" required>
                            <i class="toggle-password far fa-eye-slash"></i>
                        </div>

                        <button type="submit" class="btn-login mt-5">เข้าสู่ระบบ</button>
                        <a href="admin/index.php" class="">admin</a>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // toggle password
        document.addEventListener('DOMContentLoaded', function () {
            const passwordToggles = document.querySelectorAll('.toggle-password');
            passwordToggles.forEach(toggle => {
                toggle.addEventListener('click', function () {
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

            // login ajax
            document.getElementById('login-form').addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(this);

                fetch('login_db.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            html: data.msg,
                            confirmButtonText: 'ตกลง'
                        }).then(() => {
                            window.location.href = 'user.php';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            html: data.msg,
                            confirmButtonText: 'ตกลง'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        });
    </script>

</body>

</html>
