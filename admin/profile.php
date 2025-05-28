<?php
session_start();
require_once('config/db.php');
require_once('includes/functions.php');

// ตรวจสอบว่ามี session adminid หรือไม่
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

// ดึงข้อมูล admin จากฐานข้อมูล
$admin_id = $_SESSION['adminid'];
try {
    $stmt = $conn->prepare("SELECT id, UserName FROM admin WHERE id = :id");
    $stmt->bindParam(':id', $admin_id);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        $_SESSION['error'] = 'ไม่พบข้อมูลผู้ดูแลระบบ';
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลผู้ดูแล: ' . $e->getMessage();
    header("Location: login.php");
    exit();
}

// Handle form submission for updating admin profile
if (isset($_POST['submit'])) {
    $username = trim($_POST['username']);

    if (!empty($username)) {
        try {
            $stmt = $conn->prepare("UPDATE admin SET UserName = ? WHERE id = ?");
            $stmt->execute([$username, $admin_id]);

            $_SESSION['success'] = 'อัพเดทข้อมูลโปรไฟล์เรียบร้อยแล้ว';
            header("Location: profile.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = 'เกิดข้อผิดพลาดในการอัพเดทข้อมูล: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'กรุณากรอกชื่อผู้ใช้';
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>แก้ไขโปรไฟล์ - FlowerShop Admin</title>

    <!-- LOGO -->
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        /* Disable button style */
        .btn-disabled {
            cursor: not-allowed !important;
            opacity: 0.65;
        }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include("includes/sidebar.php"); ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include("includes/header.php"); ?>
                <div class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">แก้ไขโปรไฟล์: <?php echo htmlspecialchars($admin['UserName']); ?></h1>
                        <a href="index.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white"></i> กลับไปยังหน้าหลัก
                        </a>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ฟอร์มแก้ไขข้อมูลโปรไฟล์</h6>
                        </div>
                        <div class="card-body">
                            <form action="" method="post" class="needs-validation" novalidate>
                                <!-- Username -->
                                <div class="form-group text-center">
                                    <label for="username" class="font-weight-bold text-gray-700">
                                        <i class="fas fa-user text-pink mr-2"></i>ชื่อผู้ใช้
                                    </label>
                                    <input type="text" class="form-control <?php echo empty($admin['UserName']) ? 'text-danger' : 'text-dark'; ?> mx-auto" 
                                           name="username" id="username" 
                                           value="<?php echo htmlspecialchars($admin['UserName'] ?? ''); ?>" 
                                           placeholder="<?php echo empty($admin['UserName']) ? 'ไม่มีข้อมูล' : ''; ?>" 
                                           style="max-width: 400px;" required>
                                </div>

                                <!-- Save Button -->
                                <div class="form-group d-flex justify-content-center">
                                    <button type="submit" name="submit" id="saveButton" 
                                            class="btn btn-primary btn-disabled" disabled>
                                        <i class="fas fa-save mr-2"></i> บันทึกการเปลี่ยนแปลง
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php include("includes/footer.php"); ?>
        </div>
    </div>

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Show SweetAlert2 for success/error messages
        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Enable/disable save button based on changes
        $(document).ready(function() {
            const originalUsername = $('#username').val();
            const saveButton = $('#saveButton');

            function checkChanges() {
                const currentUsername = $('#username').val();
                if (currentUsername !== originalUsername) {
                    saveButton.removeClass('btn-disabled').removeAttr('disabled').css('cursor', 'pointer');
                } else {
                    saveButton.addClass('btn-disabled').attr('disabled', 'disabled').css('cursor', 'not-allowed');
                }
            }

            $('#username').on('input change', checkChanges);
        });
    </script>
</body>
</html>