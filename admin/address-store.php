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

// ดึงข้อมูลล่าสุดจาก tbl_contact
$contact_data = [];
try {
    $stmt = $conn->prepare("SELECT * FROM tbl_contact ORDER BY creationDate DESC LIMIT 1");
    $stmt->execute();
    $contact_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลติดต่อ: ' . $e->getMessage();
}

// จัดการการเพิ่มหรืออัปเดตข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $nameteam = trim($_POST['nameteam']);
    $address = trim($_POST['address']);
    $tel = trim($_POST['tel']);
    $business_hours = trim($_POST['business_hours']);
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

    // ตรวจสอบข้อมูล
    $errors = [];
    if (empty($email)) {
        $errors[] = 'กรุณากรอกอีเมล';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }
    if (empty($nameteam)) {
        $errors[] = 'กรุณากรอกชื่อทีม';
    }
    if (empty($address)) {
        $errors[] = 'กรุณากรอกที่อยู่';
    }
    if (empty($tel)) {
        $errors[] = 'กรุณากรอกเบอร์โทรศัพท์';
    }
    if (empty($business_hours)) {
        $errors[] = 'กรุณากรอกเวลาทำการ';
    }

    // บันทึกหรืออัปเดตข้อมูลถ้าไม่มีข้อผิดพลาด
    if (empty($errors)) {
        try {
            if ($edit_id > 0) {
                // อัปเดตข้อมูล
                $stmt = $conn->prepare("UPDATE tbl_contact SET email = :email, nameteam = :nameteam, address = :address, tel = :tel, business_hours = :business_hours WHERE id = :id");
                $stmt->bindValue(':id', $edit_id, PDO::PARAM_INT);
            } else {
                // ลบข้อมูลเก่าก่อนเพิ่มข้อมูลใหม่ (เพื่อให้มีแถวเดียว)
                $conn->exec("DELETE FROM tbl_contact");
                // เพิ่มข้อมูลใหม่
                $stmt = $conn->prepare("INSERT INTO tbl_contact (email, nameteam, address, tel, business_hours) VALUES (:email, :nameteam, :address, :tel, :business_hours)");
            }
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->bindValue(':nameteam', $nameteam, PDO::PARAM_STR);
            $stmt->bindValue(':address', $address, PDO::PARAM_STR);
            $stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
            $stmt->bindValue(':business_hours', $business_hours, PDO::PARAM_STR);
            $stmt->execute();

            $_SESSION['success'] = $edit_id > 0 ? 'อัปเดตข้อมูลติดต่อสำเร็จ' : 'เพิ่มข้อมูลติดต่อสำเร็จ';
            header("Location: address-store.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
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
    <title>จัดการข้อมูลติดต่อ - FlowerShop</title>

    <!-- LOGO -->
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Custom fonts for this template -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <!-- Custom styles for this template -->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <!-- Custom CSS for form -->
    <style>
        .form-group label {
            font-weight: 600;
            color: #4e73df;
        }
        .btn-submit {
            background-color: #4e73df;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-submit:hover {
            background-color: #2e59d9;
        }
        .btn-back {
            background-color: #6c757d;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-back:hover {
            background-color: #5a6268;
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
                        <h1 class="h3 mb-0 text-gray-800">จัดการข้อมูลติดต่อ</h1>
                        <a href="index.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left fa-sm text-white-50"></i> กลับไปหน้าแดชบอร์ด
                        </a>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ฟอร์มข้อมูลติดต่อ</h6>
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

                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($_SESSION['success']); ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">×</span>
                                    </button>
                                </div>
                                <?php unset($_SESSION['success']); ?>
                            <?php endif; ?>

                            <form action="" method="POST">
                                <input type="hidden" name="edit_id" value="<?php echo $contact_data['id'] ?? 0; ?>">
                                <div class="form-group">
                                    <label for="email"><i class="fas fa-envelope"></i> อีเมล</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($contact_data['email'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="nameteam"><i class="fas fa-users"></i> ชื่อทีม</label>
                                    <input type="text" class="form-control" id="nameteam" name="nameteam" value="<?php echo htmlspecialchars($contact_data['nameteam'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="address"><i class="fas fa-map-marker-alt"></i> ที่อยู่</label>
                                    <textarea class="form-control" id="address" name="address" rows="4" required><?php echo htmlspecialchars($contact_data['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="tel"><i class="fas fa-phone"></i> เบอร์โทรศัพท์</label>
                                    <input type="text" class="form-control" id="tel" name="tel" value="<?php echo htmlspecialchars($contact_data['tel'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="business_hours"><i class="fas fa-clock"></i> เวลาทำการ</label>
                                    <input type="text" class="form-control" id="business_hours" name="business_hours" value="<?php echo htmlspecialchars($contact_data['business_hours'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group d-flex justify-content-end">
                                    <a href="index.php" class="btn-back mr-2">
                                        <i class="fas fa-arrow-left"></i> กลับ
                                    </a>
                                    <button type="submit" class="btn-submit">
                                        <i class="fas fa-save"></i> บันทึก
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

    <!-- Scripts -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>