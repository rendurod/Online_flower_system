<?php
session_start();
require_once('config/db.php');
require_once('includes/functions.php');

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

// Fetch existing payment data (latest record)
$payment_data = [];
$existing_qr_image = '';
try {
    $stmt = $conn->prepare("SELECT QRCodeImage, AccountName, BankAccountNumber FROM tbl_payment ORDER BY CreatedAt DESC LIMIT 1");
    $stmt->execute();
    $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($payment_data && !empty($payment_data['QRCodeImage']) && file_exists('uploads/qrcodes/' . $payment_data['QRCodeImage'])) {
        $existing_qr_image = 'uploads/qrcodes/' . $payment_data['QRCodeImage'];
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการดึงข้อมูลการชำระเงิน: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountName = trim($_POST['account_name']);
    $bankAccountNumber = trim($_POST['bank_account_number']);
    $qrCodeImage = '';

    // Validate inputs
    $errors = [];
    if (empty($accountName)) {
        $errors[] = 'กรุณากรอกชื่อบัญชี';
    }
    if (empty($bankAccountNumber)) {
        $errors[] = 'กรุณากรอกเลขที่บัญชีธนาคาร';
    }

    // Handle QR Code image upload
    if (!empty($_FILES['qr_code_image']['name'])) {
        $uploadDir = 'uploads/qrcodes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['qr_code_image']['name']);
        $targetFile = $uploadDir . $fileName;
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = 'รองรับเฉพาะไฟล์รูปภาพ JPG, JPEG, PNG, GIF เท่านั้น';
        } elseif ($_FILES['qr_code_image']['size'] > $maxSize) {
            $errors[] = 'ขนาดไฟล์ต้องไม่เกิน 5MB';
        } elseif (!move_uploaded_file($_FILES['qr_code_image']['tmp_name'], $targetFile)) {
            $errors[] = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ QR Code';
        } else {
            $qrCodeImage = $fileName;
        }
    } else {
        $errors[] = 'กรุณาอัปโหลดรูปภาพ QR Code';
    }

    // Insert or update into tbl_payment if no errors
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO tbl_payment (QRCodeImage, AccountName, BankAccountNumber) VALUES (:qr_code_image, :account_name, :bank_account_number)");
            $stmt->bindValue(':qr_code_image', $qrCodeImage ?: ($payment_data['QRCodeImage'] ?? ''), PDO::PARAM_STR);
            $stmt->bindValue(':account_name', $accountName, PDO::PARAM_STR);
            $stmt->bindValue(':bank_account_number', $bankAccountNumber, PDO::PARAM_STR);
            $stmt->execute();

            $_SESSION['success'] = 'เพิ่มข้อมูลการชำระเงินสำเร็จ';
            header("Location: payment.php");
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

    <title>เพิ่มการชำระเงิน - FlowerShop</title>

    <!-- LOGO -->
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">

    <!-- Custom CSS for image preview and form -->
    <style>
        .form-group label {
            font-weight: 600;
            color: #4e73df;
        }

        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border: 2px solid #ddd;
            border-radius: 5px;
            margin-top: 10px;
            display: <?php echo $existing_qr_image ? 'block' : 'none'; ?>;
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
                        <h1 class="h3 mb-0 text-gray-800">เพิ่มการชำระเงิน</h1>
                        <a href="index.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left fa-sm text-white-50"></i> กลับไปหน้าแดชบอร์ด
                        </a>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ฟอร์มเพิ่มข้อมูลการชำระเงิน</h6>
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

                            <form action="" method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="account_name"><i class="fas fa-user"></i> ชื่อบัญชี</label>
                                    <input type="text" class="form-control" id="account_name" name="account_name" value="<?php echo htmlspecialchars($payment_data['AccountName'] ?? ''); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="bank_account_number"><i class="fas fa-credit-card"></i> เลขที่บัญชีธนาคาร</label>
                                    <input type="text" class="form-control" id="bank_account_number" name="bank_account_number" value="<?php echo htmlspecialchars($payment_data['BankAccountNumber'] ?? ''); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="qr_code_image"><i class="fas fa-qrcode"></i> รูปภาพ QR Code</label>
                                    <input type="file" class="form-control-file" id="qr_code_image" name="qr_code_image" accept="image/*">
                                    <?php if ($existing_qr_image): ?>
                                        <img id="image_preview" class="image-preview" src="<?php echo htmlspecialchars($existing_qr_image); ?>" alt="Existing QR Code">
                                        <small class="form-text text-muted">อัปโหลดไฟล์ใหม่เพื่อแทนที่</small>
                                    <?php else: ?>
                                        <img id="image_preview" class="image-preview" src="#" alt="Preview QR Code">
                                        <small class="form-text text-muted">รองรับไฟล์ JPG, JPEG, PNG, GIF ขนาดไม่เกิน 5MB</small>
                                    <?php endif; ?>
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

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <script src="vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="js/demo/datatables-demo.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Image preview functionality for new upload
        document.getElementById('qr_code_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('image_preview');
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                <?php if ($existing_qr_image): ?>
                    preview.src = '<?php echo htmlspecialchars($existing_qr_image); ?>';
                    preview.style.display = 'block';
                <?php else: ?>
                    preview.src = '#';
                    preview.style.display = 'none';
                <?php endif; ?>
            }
        });
    </script>
</body>

</html>