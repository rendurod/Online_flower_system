<?php
session_start();
require_once('config/db.php');

// ตรวจสอบว่ามี session adminid หรือไม่
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

// ดึงข้อมูล username จากฐานข้อมูล
$admin_id = $_SESSION['adminid'];
$stmt = $conn->prepare("SELECT UserName FROM admin WHERE id = :id");
$stmt->bindParam(':id', $admin_id);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Get category ID from URL parameter
$categoryId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle form submission
if (isset($_POST['submit'])) {
    $FlowerType = trim($_POST['FlowerType']);

    if (!empty($FlowerType)) {
        try {
            // Check for duplicate FlowerType (excluding current ID)
            $checkstmt = $conn->prepare("SELECT * FROM tbl_category WHERE FlowerType = :FlowerType AND ID != :ID");
            $checkstmt->bindParam(":FlowerType", $FlowerType);
            $checkstmt->bindParam(":ID", $categoryId);
            $checkstmt->execute();

            if ($checkstmt->rowCount() > 0) {
                $_SESSION['error'] = "มีชื่อประเภทนี้อยู่แล้ว กรุณาตั้งชื่อใหม่!";
            } else {
                // Update category in database
                $stmt = $conn->prepare("UPDATE tbl_category SET FlowerType = :FlowerType, UpdationDate = NOW() WHERE ID = :ID");
                $stmt->bindParam(':FlowerType', $FlowerType);
                $stmt->bindParam(':ID', $categoryId);
                $stmt->execute();

                $_SESSION['success'] = 'แก้ไขประเภทสินค้าเรียบร้อยแล้ว';
                header("Location: category.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'กรุณากรอกชื่อประเภทดอกไม้';
    }
}

// Fetch current category data
$category = null;
if ($categoryId > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM tbl_category WHERE ID = :ID");
        $stmt->bindParam(':ID', $categoryId);
        $stmt->execute();
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            $_SESSION['error'] = 'ไม่พบข้อมูลประเภทสินค้าที่ต้องการแก้ไข';
            header("Location: category.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage();
        header("Location: category.php");
        exit();
    }
} else {
    $_SESSION['error'] = 'ไม่พบรหัสประเภทสินค้า';
    header("Location: category.php");
    exit();
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
    <title>แก้ไขประเภทสินค้า - Flower Shop Admin</title>
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include("includes/sidebar.php"); ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include("includes/header.php"); ?>
                <div class="container-fluid">
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">แก้ไขประเภทสินค้า</h1>
                        <a href="category.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white"></i> กลับไปหน้ารายการ
                        </a>
                    </div>

                    <!-- Edit Category Form -->
                    <div class="row justify-content-center">
                        <div class="col-xl-8 col-lg-10">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 bg-gradient-pink">
                                    <h6 class="m-0 font-weight-bold text-white">
                                        <i class="fas fa-edit mr-2"></i>แก้ไขข้อมูลประเภทสินค้า
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form action="" method="post" class="needs-validation" novalidate>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="FlowerType" class="form-label font-weight-bold text-gray-700">
                                                        <i class="fas fa-seedling mr-2 text-pink"></i>ชื่อประเภทดอกไม้ :
                                                    </label>
                                                    <input type="text"
                                                        required
                                                        class="form-control form-control-lg"
                                                        name="FlowerType"
                                                        id="FlowerType"
                                                        placeholder="กรุณากรอกชื่อประเภทดอกไม้"
                                                        value="<?php echo htmlspecialchars($category['FlowerType'] ?? ''); ?>">
                                                    <div class="valid-feedback">
                                                        ดูดี!
                                                    </div>
                                                    <div class="invalid-feedback">
                                                        กรุณากรอกชื่อประเภทดอกไม้
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Current Information Display -->
                                        <div class="row mt-4">
                                            <div class="col-md-12">
                                                <div class="card bg-light border-left-primary">
                                                    <div class="card-body">
                                                        <h6 class="font-weight-bold text-primary mb-3">
                                                            <i class="fas fa-info-circle mr-2"></i>ข้อมูลปัจจุบัน
                                                        </h6>
                                                        <div class="row">
                                                            <div class="col-sm-6">
                                                                <p class="mb-2">
                                                                    <strong>รหัสประเภท:</strong>
                                                                    <span class="text-primary">#C00<?php echo htmlspecialchars($category['ID']); ?></span>
                                                                </p>
                                                                <p class="mb-2">
                                                                    <strong>ประเภทปัจจุบัน:</strong>
                                                                    <span class="text-info"><?php echo htmlspecialchars($category['FlowerType']); ?></span>
                                                                </p>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <p class="mb-2">
                                                                    <strong>วันที่สร้าง:</strong>
                                                                    <span class="text-success"><?php echo htmlspecialchars($category['CreationDate']); ?></span>
                                                                </p>
                                                                <p class="mb-2">
                                                                    <strong>แก้ไขล่าสุด:</strong>
                                                                    <span class="text-warning"><?php echo htmlspecialchars($category['UpdationDate'] ?: 'ยังไม่เคยแก้ไข'); ?></span>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="form-group mt-4">
                                            <div class="d-flex justify-content-between">
                                                <a href="category.php" class="btn btn-danger btn-lg">
                                                    <i class="fas fa-times mr-2"></i>ยกเลิก
                                                </a>
                                                <div>
                                                    <button type="submit" name="submit" class="btn btn-pink btn-lg">
                                                        <i class="fas fa-save mr-2"></i>บันทึกการแก้ไข
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include("includes/footer.php"); ?>
        </div>
    </div>

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Show SweetAlert2 for success/error messages
        <?php if (isset($_SESSION['success'])) { ?>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: '<?php echo $_SESSION['success']; ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['success']); ?>
        <?php } ?>

        <?php if (isset($_SESSION['error'])) { ?>
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: '<?php echo $_SESSION['error']; ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['error']); ?>
        <?php } ?>

            // Form validation
            (function() {
                'use strict';
                window.addEventListener('load', function() {
                    var forms = document.getElementsByClassName('needs-validation');
                    var validation = Array.prototype.filter.call(forms, function(form) {
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

        // Auto focus on input field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('FlowerType').focus();
            document.getElementById('FlowerType').select();
        });

        // Preview changes
        document.getElementById('FlowerType').addEventListener('input', function() {
            const currentValue = this.value;
            const preview = document.querySelector('.text-info');
            if (preview && currentValue.trim() !== '') {
                preview.textContent = currentValue;
                preview.style.color = '#28a745';
                preview.style.fontWeight = 'bold';
            } else {
                preview.textContent = '<?php echo htmlspecialchars($category['FlowerType']); ?>';
                preview.style.color = '#17a2b8';
                preview.style.fontWeight = 'normal';
            }
        });
    </script>
</body>

</html>