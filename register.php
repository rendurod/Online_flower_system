<?php
session_start();
include('config/db.php');

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
    <title>สมัครสมาชิก - Flower Shop</title>
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">

    <!-- CSS & JS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/login&register.css">

    <!-- SweetAlert2 & jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</head>

<body class="register">

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
                <div class="tab-pane fade show active" id="register">
                    <div class="divider">
                        <div class="divider-line"></div>
                        <div class="divider-text">สมัครสมาชิกฟรี</div>
                        <div class="divider-line"></div>
                    </div>

                    <form id="registerForm" method="POST" action="register_db.php">
                        <input type="text" class="form-control mb-2" name="firstname" placeholder="ชื่อจริง ภาษาอังกฤษตัวพิมพ์เล็ก *" required>
                        <input type="text" class="form-control mb-2" name="lastname" placeholder="นามสกุล ภาษาอังกฤษตัวพิมพ์เล็ก *" required>
                        <input type="email" class="form-control mb-2" name="email" placeholder="อีเมล *" required>

                        <div class="password-toggle mb-2 position-relative">
                            <input type="password" class="form-control password-input" name="password" placeholder="รหัสผ่าน *" required>
                            <i class="toggle-password far fa-eye-slash position-absolute top-50 end-0 translate-middle-y me-3" style="cursor:pointer;"></i>
                        </div>

                        <div class="password-toggle mb-2 position-relative">
                            <input type="password" class="form-control password-input" name="confirm_password" placeholder="ยืนยันรหัสผ่าน *" required>
                            <i class="toggle-password far fa-eye-slash position-absolute top-50 end-0 translate-middle-y me-3" style="cursor:pointer;"></i>
                        </div>

                        <!-- เพิ่มปุ่มแสดงข้อกำหนดรหัสผ่าน -->
                        <div class="password-info-wrapper mb-2">
                            <button type="button" class="btn-show-requirements">
                                <i class="fas fa-info-circle"></i>
                                แสดงข้อกำหนดรหัสผ่าน
                            </button>

                            <!-- Password Requirements Box (ซ่อนไว้) -->
                            <div class="password-requirements" style="display: none;">
                                <div class="requirements-title">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>ข้อกำหนดรหัสผ่าน:</span>
                                </div>
                                <ul class="requirements-list">
                                    <li><i class="fas fa-check-circle"></i> มีความยาวอย่างน้อย 8 ตัวอักษร</li>
                                    <li><i class="fas fa-check-circle"></i> มีตัวอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว</li>
                                    <li><i class="fas fa-check-circle"></i> มีตัวอักษรพิมพ์เล็กอย่างน้อย 1 ตัว</li>
                                    <li><i class="fas fa-check-circle"></i> มีตัวเลขอย่างน้อย 1 ตัว</li>
                                </ul>
                            </div>
                        </div>

                        <button type="submit" class="btn-register mt-3">สมัครสมาชิก</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script AJAX + SweetAlert2 -->
<script>
    $(document).ready(function () {
        $("#registerForm").submit(function (e) {
            e.preventDefault();

            let formUrl = $(this).attr("action");
            let reqMethod = $(this).attr("method");
            let formData = $(this).serialize();

            $.ajax({
                url: formUrl,
                type: reqMethod,
                data: formData,
                success: function (data) {
                    try {
                        let result = typeof data === 'object' ? data : JSON.parse(data);

                        Swal.fire({
                            title: result.status === "success" ? "สำเร็จ!" : "ล้มเหลว!",
                            text: result.msg,
                            icon: result.status,
                            confirmButtonText: 'ตกลง',
                            customClass: {
                                popup: 'swal2-popup',
                                title: 'swal2-title',
                                content: 'swal2-content',
                                confirmButton: 'swal2-confirm'
                            }
                        }).then(function () {
                            if (result.status === "success") {
                                window.location.href = "login.php";
                            }
                        });

                    } catch (err) {
                        console.error("JSON Parse Error:", err);
                        console.log("Raw Response:", data);
                        Swal.fire({
                            title: "ข้อผิดพลาด!",
                            text: "รูปแบบข้อมูลที่ได้รับไม่ถูกต้อง: " + err,
                            icon: "error"
                        });
                    }
                },
                error: function (xhr, status, error) {
                    Swal.fire({
                        title: "ข้อผิดพลาด!",
                        text: "ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้",
                        icon: "error",
                        confirmButtonText: 'ตกลง'
                    });
                    console.error("AJAX Error:", status, error);
                }
            });
        });

        $(".toggle-password").on("click", function () {
            const input = $(this).siblings("input");
            const type = input.attr("type") === "password" ? "text" : "password";
            input.attr("type", type);
            $(this).toggleClass("fa-eye fa-eye-slash");
        });

        $(".btn-show-requirements").on("click", function () {
            const requirements = $(this).siblings(".password-requirements");
            requirements.slideToggle(300).toggleClass("show");

            const buttonText = requirements.is(":visible") ?
                "ซ่อนข้อกำหนดรหัสผ่าน" :
                "แสดงข้อกำหนดรหัสผ่าน";
            $(this).html(`<i class="fas fa-info-circle"></i> ${buttonText}`);
        });

        $(document).on("click", function (event) {
            if (!$(event.target).closest('.password-info-wrapper').length) {
                $(".password-requirements").slideUp(300).removeClass("show");
                $(".btn-show-requirements").html('<i class="fas fa-info-circle"></i> แสดงข้อกำหนดรหัสผ่าน');
            }
        });
    });
</script>

</body>

</html>