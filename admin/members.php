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
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>จัดการสมาชิก - FlowerShop</title>

    <!-- LOGO -->
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">

    <!-- Custom CSS for image preview modal -->
    <style>
        .image-preview-modal {
            max-width: 90% !important;
            padding: 20px;
        }

        .image-preview-modal .swal2-image {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
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
                        <h1 class="h3 mb-0 text-gray-800">จัดการสมาชิก</h1>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ตาราง: ข้อมูลสมาชิก</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ลำดับ</th>
                                            <th>รูปภาพ</th>
                                            <th>ชื่อ</th>
                                            <th>อีเมล</th>
                                            <th>วันที่สมัคร</th>
                                            <th>วันที่แก้ไข</th>
                                            <th class="no-sort text-center">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        try {
                                            $stmt = $conn->query("SELECT ID, FirstName, LastName, EmailId, Image, RegDate, UpdationDate FROM tbl_members ORDER BY ID DESC");
                                            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            if (!$members) {
                                                echo "<tr><td colspan='8' class='text-center'>ไม่พบข้อมูลสมาชิก</td></tr>";
                                            } else {
                                                $i = 1;
                                                foreach ($members as $member) {
                                        ?>
                                                    <tr>
                                                        <td class="col-1 fw-bold"><?php echo $i++; ?></td>
                                                        <td class="col-1 text-center">
                                                            <?php if (!empty($member['Image']) && file_exists($target_dir . $member['Image'])): ?>
                                                                <img src="<?php echo $target_dir . htmlspecialchars($member['Image']); ?>"
                                                                    alt="<?php echo htmlspecialchars($member['FirstName'] . ' ' . $member['LastName']); ?>"
                                                                    class="img-thumbnail image-preview"
                                                                    style="width: 60px; height: 60px; object-fit: cover; cursor: pointer;"
                                                                    data-image="<?php echo $target_dir . htmlspecialchars($member['Image']); ?>">
                                                            <?php else: ?>
                                                                <div class="bg-light d-flex align-items-center justify-content-center image-preview"
                                                                    style="width: 60px; height: 60px; border-radius: 5px; cursor: pointer;"
                                                                    data-image="">
                                                                    <i class="fas fa-image text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="col-2"><?php echo htmlspecialchars($member['FirstName'] ?? ''); ?></td>
                                                        <td class="col-2"><?php echo htmlspecialchars($member['EmailId']); ?></td>
                                                        <td class="col-1"><?php echo date('d/m/Y H:i', strtotime($member['RegDate'])); ?></td>
                                                        <td class="col-1"><?php echo $member['UpdationDate'] ? date('d/m/Y H:i', strtotime($member['UpdationDate'])) : '-'; ?></td>
                                                        <td class="col-1 text-center">
                                                            <a href="edit-member.php?id=<?php echo htmlspecialchars($member['ID']); ?>"
                                                                class="btn btn-info btn-sm">
                                                                <i class="fas fa-eye"></i> ดูเพิ่มเติม
                                                            </a>
                                                        </td>
                                                    </tr>
                                        <?php
                                                }
                                            }
                                        } catch (PDOException $e) {
                                            echo "<tr><td colspan='8' class='text-center text-danger'>เกิดข้อผิดพลาดในการดึงข้อมูล: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
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

        // Image preview in SweetAlert2
        $(document).ready(function() {
            $('.image-preview').click(function() {
                const imageUrl = $(this).data('image');
                if (imageUrl) {
                    Swal.fire({
                        imageUrl: imageUrl,
                        imageAlt: 'รูปภาพสมาชิก',
                        imageWidth: '80%',
                        imageHeight: 'auto',
                        showConfirmButton: false,
                        showCloseButton: true,
                        background: '#fff',
                        customClass: {
                            popup: 'image-preview-modal'
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'ไม่มีรูปภาพ',
                        text: 'สมาชิกนี้ยังไม่มีรูปภาพที่อัพโหลด',
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
            });
        });
    </script>
</body>

</html>