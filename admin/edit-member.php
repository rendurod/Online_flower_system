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
$target_dir = "Uploads/members/";
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
    $stmt = $conn->prepare("SELECT ID, FirstName, LastName, EmailId, ContactNo, Address, Image, Validate FROM tbl_members WHERE ID = ?");
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

// Handle form submission for updating Validate field
if (isset($_POST['submit'])) {
    $validate_status = $_POST['validate_status'] ?? '';
    $validate_reason = trim($_POST['validate_reason'] ?? '');

    $validate = '';
    $errors = [];

    if ($validate_status === 'not_verified') {
        $validate = 'ยังไม่ยืนยัน';
    } elseif ($validate_status === 'incorrect') {
        if (empty($validate_reason)) {
            $errors[] = 'กรุณาระบุเหตุผลที่ที่อยู่ไม่ถูกต้อง';
        } else {
            $validate = $validate_reason;
        }
    } elseif ($validate_status === 'verified') {
        $validate = 'ที่อยู่ถูกต้อง';
    } else {
        $errors[] = 'กรุณาเลือกสถานะการตรวจสอบที่อยู่';
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE tbl_members SET Validate = ?, UpdationDate = CURRENT_TIMESTAMP WHERE ID = ?");
            $stmt->execute([$validate, $member_id]);

            $_SESSION['success'] = 'อัพเดทสถานะที่อยู่เรียบร้อยแล้ว';
            header("Location: members.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = 'เกิดข้อผิดพลาดในการอัพเดทสถานะ: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
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

    <title>ข้อมูลสมาชิก - FlowerShop Admin</title>

    <!-- LOGO -->
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">

    <!-- Custom CSS for radio buttons and validation -->
    <style>
        .disabled-field {
            background-color: #f8f9fa;
            color: #6c757d;
        }

        .validate-radio-group {
            background-color: #fff5f5;
            border: 2px solid #ff6f61;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .validate-radio-label {
            display: flex;
            align-items: center;
            font-size: 1rem;
            font-weight: 500;
            color: #333;
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .validate-radio-label:hover {
            background-color: #ffe5e5;
            color: #ff6f61;
        }

        .validate-radio-label input[type="radio"] {
            margin-right: 8px;
            cursor: pointer;
        }

        .validate-radio-label i {
            margin-right: 8px;
            font-size: 1.2rem;
        }

        .validate-radio-label.not-verified i {
            color: #6c757d;
        }

        .validate-radio-label.incorrect i {
            color: #dc3545;
        }

        .validate-radio-label.verified i {
            color: #28a745;
        }

        .validate-reason {
            display: none;
            margin-top: 10px;
            margin-left: 20px;
        }

        .validate-reason input {
            border-color: #ff6f61;
        }

        .validate-reason input:focus {
            box-shadow: 0 0 0 0.2rem rgba(255, 111, 97, 0.25);
            border-color: #e55a4f;
        }

        .form-text {
            font-size: 0.9rem;
            color: #ff6f61;
            font-style: italic;
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
                        <h1 class="h3 mb-0 text-gray-800">สมาชิก : <?php echo htmlspecialchars($member['FirstName'] . ' ' . $member['LastName']); ?></h1>
                        <a href="members.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white"></i> กลับไปยังรายการสมาชิก
                        </a>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ฟอร์มข้อมูลสมาชิก</h6>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">×</span>
                                    </button>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>

                            <form action="" method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="first_name" class="font-weight-bold text-gray-700">
                                                <i class="fas fa-user text-pink mr-2"></i>ชื่อ
                                            </label>
                                            <input type="text" class="form-control disabled-field" name="first_name" id="first_name"
                                                value="<?php echo htmlspecialchars($member['FirstName'] ?? ''); ?>"
                                                placeholder="ไม่มีข้อมูลนี้" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="last_name" class="font-weight-bold text-gray-700">
                                                <i class="fas fa-user text-pink mr-2"></i>นามสกุล
                                            </label>
                                            <input type="text" class="form-control disabled-field" name="last_name" id="last_name"
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
                                            <input type="email" class="form-control disabled-field" name="email" id="email"
                                                value="<?php echo htmlspecialchars($member['EmailId']); ?>"
                                                placeholder="ไม่มีข้อมูลนี้" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="contact_no" class="font-weight-bold text-gray-700">
                                                <i class="fas fa-phone text-pink mr-2"></i>เบอร์ติดต่อ
                                            </label>
                                            <input type="text" class="form-control disabled-field" name="contact_no" id="contact_no"
                                                value="<?php echo htmlspecialchars($member['ContactNo'] ?? ''); ?>"
                                                placeholder="ไม่มีข้อมูลนี้" disabled>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="address" class="font-weight-bold text-gray-700">
                                        <i class="fas fa-map-marker-alt text-pink mr-2"></i>ที่อยู่จัดส่งของลูกค้า
                                    </label>
                                    <textarea class="form-control disabled-field" name="address" id="address" rows="4"
                                        placeholder="ไม่มีข้อมูลนี้" disabled><?php echo htmlspecialchars($member['Address'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold text-gray-700">
                                        <i class="fas fa-check-circle text-pink mr-2"></i>การตรวจสอบที่อยู่
                                    </label>
                                    <div class="validate-radio-group">
                                        <label class="validate-radio-label not-verified" for="validate_not_verified">
                                            <input type="radio" name="validate_status" id="validate_not_verified" value="not_verified"
                                                <?php echo ($member['Validate'] === 'ยังไม่ยืนยัน' || empty($member['Validate'])) ? 'checked' : ''; ?>>
                                            <i class="fas fa-clock"></i> ยังไม่ยืนยัน
                                        </label>
                                        <label class="validate-radio-label incorrect" for="validate_incorrect">
                                            <input type="radio" name="validate_status" id="validate_incorrect" value="incorrect"
                                                <?php echo ($member['Validate'] !== 'ยังไม่ยืนยัน' && $member['Validate'] !== 'ที่อยู่ถูกต้อง' && $member['Validate'] !== null) ? 'checked' : ''; ?>>
                                            <i class="fas fa-times-circle"></i> ที่อยู่ไม่ถูกต้อง
                                        </label>
                                        <div class="validate-reason" id="validate-reason"
                                            style="<?php echo ($member['Validate'] !== 'ยังไม่ยืนยัน' && $member['Validate'] !== 'ที่อยู่ถูกต้อง' && $member['Validate'] !== null) ? 'display: block;' : ''; ?>">
                                            <input type="text" class="form-control" name="validate_reason"
                                                value="<?php echo ($member['Validate'] !== 'ยังไม่ยืนยัน' && $member['Validate'] !== 'ที่อยู่ถูกต้อง' && $member['Validate'] !== null) ? htmlspecialchars($member['Validate']) : ''; ?>"
                                                placeholder="ระบุเหตุผล เช่น 'รหัสไปรษณีย์ไม่ถูกต้อง แก้ไขใหม่อีกครั้ง'">
                                        </div>
                                        <label class="validate-radio-label verified" for="validate_verified">
                                            <input type="radio" name="validate_status" id="validate_verified" value="verified"
                                                <?php echo ($member['Validate'] === 'ที่อยู่ถูกต้อง') ? 'checked' : ''; ?>>
                                            <i class="fas fa-check-circle"></i> ยืนยันที่อยู่ถูกต้อง
                                        </label>
                                    </div>
                                    <small class="form-text">เลือกสถานะการตรวจสอบที่อยู่และระบุเหตุผลหากที่อยู่ไม่ถูกต้อง</small>
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
                                    <button type="submit" name="submit" class="btn btn-primary mr-2">
                                        <i class="fas fa-save mr-2"></i> บันทึกการตรวจสอบ
                                    </button>
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

        // Toggle validate reason input visibility
        $(document).ready(function() {
            $('input[name="validate_status"]').change(function() {
                if ($(this).val() === 'incorrect') {
                    $('#validate-reason').slideDown(300);
                    $('#validate-reason input').prop('required', true);
                } else {
                    $('#validate-reason').slideUp(300);
                    $('#validate-reason input').prop('required', false);
                }
            });
        });
    </script>
</body>
</html>