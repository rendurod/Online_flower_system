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

// Ensure uploads directory exists
$target_dir = "uploads/flowers/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ไม่พบรหัสดอกไม้ที่ต้องการแก้ไข';
    header("Location: flowers.php");
    exit();
}

$flower_id = intval($_GET['id']);

// Fetch flower data
try {
    $stmt = $conn->prepare("SELECT * FROM tbl_flowers WHERE ID = ?");
    $stmt->execute([$flower_id]);
    $flower = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$flower) {
        $_SESSION['error'] = 'ไม่พบข้อมูลดอกไม้ในระบบ';
        header("Location: flowers.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage();
    header("Location: flowers.php");
    exit();
}

// Fetch categories for dropdown
$categories = [];
try {
    $stmt = $conn->query("SELECT DISTINCT FlowerType FROM tbl_category ORDER BY FlowerType");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลประเภทดอกไม้: ' . $e->getMessage();
}

// Handle form submission for updating flower
if (isset($_POST['submit'])) {
    $flower_name = trim($_POST['flower_name']);
    $flower_category = trim($_POST['flower_category']);
    $flower_description = trim($_POST['flower_description']);
    $price = floatval($_POST['price']);
    $stock_quantity = intval($_POST['stock_quantity']);

    // Handle image upload
    $image = $flower['image']; // Keep existing image by default
    $max_file_size = 5 * 1024 * 1024; // 5MB limit
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        if ($_FILES['image']['size'] > $max_file_size) {
            $_SESSION['error'] = 'ขนาดไฟล์รูปภาพใหญ่เกินไป (สูงสุด 5MB)';
        } else {
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');

            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = uniqid() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    // Delete old image if exists
                    if (!empty($flower['image']) && file_exists($target_dir . $flower['image'])) {
                        unlink($target_dir . $flower['image']);
                    }
                    $image = $new_filename;
                } else {
                    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการอัพโหลดรูปภาพ';
                }
            } else {
                $_SESSION['error'] = 'รองรับเฉพาะไฟล์รูปภาพ (JPG, JPEG, PNG, GIF)';
            }
        }
    }

    if (!isset($_SESSION['error'])) {
        if (!empty($flower_name) && !empty($flower_category) && $price > 0) {
            try {
                $stmt = $conn->prepare("UPDATE tbl_flowers SET flower_name = ?, flower_category = ?, flower_description = ?, price = ?, image = ?, stock_quantity = ?, updation_date = CURRENT_TIMESTAMP WHERE ID = ?");
                $stmt->execute([$flower_name, $flower_category, $flower_description, $price, $image, $stock_quantity, $flower_id]);

                $_SESSION['success'] = 'อัพเดทข้อมูลดอกไม้เรียบร้อยแล้ว';
                header("Location: flowers.php");
                exit();
            } catch (PDOException $e) {
                $_SESSION['error'] = 'เกิดข้อผิดพลาดในการอัพเดทข้อมูล: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
        }
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

    <title>แก้ไขดอกไม้ - Flower Shop Admin</title>

    <!-- LOGO -->
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>

<body id="page-top">
    <div id="wrapper">
        <?php include("includes/sidebar.php"); ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include("includes/header.php"); ?>
                <div class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">แก้ไขดอกไม้: <?php echo htmlspecialchars($flower['flower_name']); ?></h1>
                        <a href="flowers.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white"></i> กลับไปยังรายการดอกไม้
                        </a>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ฟอร์มแก้ไขข้อมูลดอกไม้</h6>
                        </div>
                        <div class="card-body">
                            <form action="" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="flower_name" class="font-weight-bold text-gray-700">
                                                <i class="fas fa-flower text-pink mr-2"></i>ชื่อดอกไม้ <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" required class="form-control" name="flower_name" id="flower_name"
                                                value="<?php echo htmlspecialchars($flower['flower_name']); ?>" placeholder="กรุณากรอกชื่อดอกไม้">
                                            <div class="invalid-feedback">กรุณากรอกชื่อดอกไม้</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="flower_category" class="font-weight-bold text-gray-700">
                                                <i class="fas fa-tags text-pink mr-2"></i>ประเภทดอกไม้ <span class="text-danger">*</span>
                                            </label>
                                            <select required class="form-control" name="flower_category" id="flower_category">
                                                <option value="">-- เลือกประเภทดอกไม้ --</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo htmlspecialchars($category['FlowerType']); ?>"
                                                        <?php echo $category['FlowerType'] === $flower['flower_category'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['FlowerType']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">กรุณาเลือกประเภทดอกไม้</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="price" class="font-weight-bold text-gray-700">
                                                <i class="fas fa-money-bill text-pink mr-2"></i>ราคา (บาท) <span class="text-danger">*</span>
                                            </label>
                                            <input type="number"
                                                required
                                                class="form-control"
                                                name="price"
                                                id="price"
                                                value="<?php echo intval($flower['price']); ?>"
                                                placeholder="0"
                                                min="0"
                                                step="1"
                                                oninput="this.value = Math.floor(this.value);">
                                            <div class="invalid-feedback">กรุณากรอกราคาที่ถูกต้อง</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="stock_quantity" class="font-weight-bold text-gray-700">
                                                <i class="fas fa-boxes text-pink mr-2"></i>จำนวนในสต็อก
                                            </label>
                                            <input type="number" class="form-control" name="stock_quantity" id="stock_quantity"
                                                value="<?php echo htmlspecialchars($flower['stock_quantity']); ?>" placeholder="0" min="0">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="flower_description" class="font-weight-bold text-gray-700">
                                        <i class="fas fa-align-left text-pink mr-2"></i>รายละเอียด
                                    </label>
                                    <textarea class="form-control" name="flower_description" id="flower_description" rows="4"
                                        placeholder="กรุณากรอกรายละเอียดของดอกไม้"><?php echo htmlspecialchars($flower['flower_description'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="image" class="font-weight-bold text-gray-700">
                                        <i class="fas fa-image text-pink mr-2"></i>รูปภาพ
                                    </label>
                                    <div class="mb-2">
                                        <?php if (!empty($flower['image']) && file_exists($target_dir . $flower['image'])): ?>
                                            <img src="<?php echo $target_dir . htmlspecialchars($flower['image']); ?>"
                                                alt="<?php echo htmlspecialchars($flower['flower_name']); ?>"
                                                class="img-thumbnail"
                                                style="max-width: 200px; max-height: 200px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center"
                                                style="width: 200px; height: 200px; border-radius: 5px;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" name="image" id="image" accept="image/*">
                                        <label class="custom-file-label" for="image">เลือกรูปภาพใหม่...</label>
                                    </div>
                                    <small class="form-text text-muted">รองรับไฟล์: JPG, JPEG, PNG, GIF (สูงสุด 5MB)</small>
                                    <div class="mt-2" id="imagePreview"></div>
                                </div>

                                <div class="form-group d-flex justify-content-end">
                                    <button type="submit" name="submit" class="btn btn-pink mr-2">
                                        <i class="fas fa-save mr-2"></i>บันทึกการแก้ไข
                                    </button>
                                    <a href="flowers.php" class="btn btn-secondary">
                                        <i class="fas fa-times mr-2"></i>ยกเลิก
                                    </a>
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

        // Image file input handling
        $('#image').on('change', function() {
            const fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').html(fileName || 'เลือกรูปภาพใหม่...');

            // Image preview
            const file = this.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: 'ขนาดไฟล์รูปภาพใหญ่เกินไป (สูงสุด 5MB)',
                        timer: 3000,
                        showConfirmButton: false
                    });
                    $(this).val('');
                    $(this).next('.custom-file-label').html('เลือกรูปภาพใหม่...');
                    $('#imagePreview').empty();
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#imagePreview').html(`
                        <img src="${e.target.result}" 
                             class="img-thumbnail" 
                             style="max-width: 200px; max-height: 200px; object-fit: cover;">
                    `);
                };
                reader.readAsDataURL(file);
            } else {
                $('#imagePreview').empty();
            }
        });

        // Format price input
        $('#price').on('input', function() {
            let value = parseInt($(this).val());
            if (!isNaN(value)) {
                $(this).val(Math.floor(value));
            }
        });
    </script>
</body>

</html>