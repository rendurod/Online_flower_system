<?php
session_start();
require_once('config/db.php');
require_once('includes/functions.php');

// ตรวจสอบว่ามี session adminid หรือไม่
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if (!$conn) {
    die("Connection failed: Unable to connect to the database.");
}

// ดึงข้อมูล username จากฐานข้อมูล
$admin_id = $_SESSION['adminid'];
try {
    $stmt = $conn->prepare("SELECT UserName FROM admin WHERE id = :id");
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

// Ensure uploads directory exists for member images
$target_dir = "uploads/members/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ไม่พบรหัสสมาชิกที่ต้องการแก้ไข';
    header("Location: members.php");
    exit();
}

$member_id = intval($_GET['id']);

// Fetch member data
try {
    $stmt = $conn->prepare("SELECT ID, FirstName, LastName, EmailId, ContactNo, Address, Image FROM tbl_members WHERE ID = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        $_SESSION['error'] = 'ไม่พบข้อมูลสมาชิกในระบบ';
        header("Location: members.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage();
    header("Location: members.php");
    exit();
}

// Handle form submission for updating member
if (isset($_POST['submit'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contact_no = trim($_POST['contact_no']);
    $address = trim($_POST['address']);

    // Handle image upload
    $image = $member['Image']; // Keep existing image by default
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
                    if (!empty($member['Image']) && file_exists($target_dir . $member['Image'])) {
                        unlink($target_dir . $member['Image']);
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
        if (!empty($first_name) && !empty($last_name) && !empty($email)) {
            try {
                $stmt = $conn->prepare("UPDATE tbl_members SET FirstName = ?, LastName = ?, EmailId = ?, ContactNo = ?, Address = ?, Image = ?, UpdationDate = CURRENT_TIMESTAMP WHERE ID = ?");
                $stmt->execute([$first_name, $last_name, $email, $contact_no, $address, $image, $member_id]);

                $_SESSION['success'] = 'อัพเดทข้อมูลสมาชิกเรียบร้อยแล้ว';
                header("Location: members.php");
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

    <title>แก้ไขสมาชิก - FlowerShop Admin</title>

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
                        <h1 class="h3 mb-0 text-gray-800">แก้ไขสมาชิก: <?php echo htmlspecialchars($member['FirstName'] . ' ' . $member['LastName']); ?></h1>
                        <a href="members.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white"></i> กลับไปยังรายการสมาชิก
                        </a>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ฟอร์มแก้ไขข้อมูลสมาชิก</h6>
                        </div>
                        <div class="card-body">
                            <form action="" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="first_name" class="font-weight-bold text-gray-700">
                                                <i class="fas fa-user text-pink mr-2"></i>ชื่อ
                                            </label>
                                            <input type="text" class="form-control  " name="first_name" id="first_name"
                                                value="<?php echo htmlspecialchars($member['FirstName'] ?? ''); ?>"
                                                placeholder="ไม่มีข้อมูลนี้" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="last_name" class="font-weight-bold text-gray-700">
                                                <i class="fas fa-user text-pink mr-2"></i>นามสกุล
                                            </label>
                                            <input type="text" class="form-control  " name="last_name" id="last_name"
                                                value="<?php echo htmlspecialchars($member['LastName'] ?? ''); ?>"
                                                placeholder="ไม่มีข้อมูลนี้" disabled>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email" class="font-weight-bold text-gray-700">
                                                <i class="fas fa-envelope text-pink mr-2"></i>อีเมล
                                            </label>
                                            <input type="email" class="form-control  " name="email" id="email"
                                                value="<?php echo htmlspecialchars($member['EmailId']); ?>"
                                                placeholder="ไม่มีข้อมูลนี้" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="contact_no" class="font-weight-bold text-gray-700">
                                                <i class="fas fa-phone text-pink mr-2"></i>เบอร์ติดต่อ
                                            </label>
                                            <input type="text" class="form-control  " name="contact_no" id="contact_no"
                                                value="<?php echo htmlspecialchars($member['ContactNo'] ?? ''); ?>"
                                                placeholder="ไม่มีข้อมูลนี้" maxlength="11" disabled>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="address" class="font-weight-bold text-gray-700">
                                        <i class="fas fa-map-marker-alt text-pink mr-2"></i>ที่อยู่จัดส่งของลูกค้า
                                    </label>
                                    <textarea class="form-control  " name="address" id="address" rows="4"
                                        placeholder="ไม่มีข้อมูลนี้" disabled><?php echo htmlspecialchars($member['Address'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="image" class="font-weight-bold text-gray-700">
                                        <i class="fas fa-image text-pink mr-2"></i>รูปโปรไฟล์
                                    </label>
                                    <div class="mb-2">
                                        <?php if (!empty($member['Image']) && file_exists($target_dir . $member['Image'])): ?>
                                            <img src="<?php echo $target_dir . htmlspecialchars($member['Image']); ?>"
                                                alt="<?php echo htmlspecialchars($member['FirstName'] . ' ' . $member['LastName']); ?>"
                                                class="img-thumbnail"
                                                style="max-width: 200px; max-height: 200px; object-fit: cover;">
                                        <?php else: ?>
                                            <img src="img/Notfound.jpg"
                                                alt="No profile image"
                                                class="img-thumbnail"
                                                style="max-width: 200px; max-height: 200px; object-fit: cover;">
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-group d-flex justify-content-end">
                                    <a href="members.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left mr-2"></i> กลับหน้ารายการสมาชิก
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
    </script>
</body>

</html>