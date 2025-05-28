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
$stmt = $conn->prepare("SELECT UserName FROM admin WHERE id = :id");
$stmt->bindParam(':id', $admin_id);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle category deletion
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $deletestmt = $conn->query("DELETE FROM tbl_category WHERE ID = $delete_id");
    $deletestmt->execute();

    if ($deletestmt) {
        $_SESSION['success'] = "ลบประเภทเรียบร้อยแล้ว!";
        // Check if the table is empty after deletion
        $checkEmpty = $conn->query("SELECT COUNT(*) FROM tbl_category");
        $rowCount = $checkEmpty->fetchColumn();
        // If the table is empty, reset the AUTO_INCREMENT value
        if ($rowCount == 0) {
            $conn->query("ALTER TABLE tbl_category AUTO_INCREMENT = 1");
        }
        header("refresh:.5; url=category.php");
        exit();
    }
}

// Handle category addition
if (isset($_POST['submit'])) {
    $FlowerType = $_POST['FlowerType'];

    // Check for duplicate FlowerType
    $checkstmt = $conn->prepare("SELECT * FROM tbl_category WHERE FlowerType = :FlowerType");
    $checkstmt->bindParam(":FlowerType", $FlowerType);
    $checkstmt->execute();

    if ($checkstmt->rowCount() > 0) {
        $_SESSION['error'] = "มีชื่อประเภทนี้อยู่แล้ว กรุณาตั้งชื่อใหม่!";
        header("location: category.php");
        exit();
    } else {
        // Insert new category
        $sql = $conn->prepare("INSERT INTO tbl_category(FlowerType, CreationDate) VALUES(:FlowerType, NOW())");
        $sql->bindParam(":FlowerType", $FlowerType);

        if ($sql->execute()) {
            $_SESSION['success'] = "เพิ่มประเภทสินค้าเรียบร้อยแล้ว";
            header("location: category.php");
            exit();
        } else {
            $_SESSION['error'] = "เพิ่มข้อมูลไม่สำเร็จ";
            header("location: category.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <title>Admin - Category</title>
    <link rel="icon" href="img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
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
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">ประเภทสินค้า</h1>
                        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-pink shadow-sm" data-toggle="modal" data-target="#addCategoryModal">
                            <i class="fas fa-plus fa-sm text-white"></i> เพิ่มประเภท
                        </a>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">ตาราง ประเภทสินค้า</h6>
                        </div>
                        <div class="card-body">
                            <!-- Add Category Modal -->
                            <div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title text-primary" id="addCategoryModalLabel">เพิ่มประเภทสินค้า</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="" method="post">
                                                <div class="form-group">
                                                    <label for="FlowerType">ชื่อประเภทดอกไม้ :</label>
                                                    <input type="text" required class="form-control" name="FlowerType" id="FlowerType" placeholder="กรุณากรอกชื่อประเภทดอกไม้">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" name="submit" class="btn btn-success">
                                                        <i class="fas fa-save"></i> บันทึก
                                                    </button>
                                                    <button type="button" class="btn btn-danger" data-dismiss="modal">
                                                        <i class="fas fa-times"></i> ยกเลิก
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ไอดี</th>
                                            <th>ประเภท</th>
                                            <th>วันที่เพิ่ม</th>
                                            <th>วันที่แก้ไข</th>
                                            <th class="no-sort text-center">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $conn->query("SELECT * FROM tbl_category");
                                        $stmt->execute();
                                        $categories = $stmt->fetchAll();

                                        if (!$categories) {
                                            echo "<tr><td colspan='5' class='text-center'>No data available</td></tr>";
                                        } else {
                                            foreach ($categories as $category) {
                                        ?>
                                                <tr>
                                                    <td class="col-1 fw-bold"><?php echo $category['ID']; ?></td>
                                                    <td class="col-3 text-primary fw-bold"><?php echo htmlspecialchars($category['FlowerType']); ?></td>
                                                    <td class="col-2 fw-bold"><?php echo $category['CreationDate']; ?></td>
                                                    <td class="col-2 fw-bold"><?php echo $category['UpdationDate'] ?: '-'; ?></td>
                                                    <td class="col-2 text-center">
    <a href="<?php echo checkPageExists('edit-category.php', ['id' => $category['ID']]); ?>" 
       class="btn btn-warning btn-sm">
        <i class="fas fa-edit"></i> แก้ไข
    </a>
    <a href="#" class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $category['ID']; ?>">
        <i class="fas fa-trash"></i> ลบ
    </a>
</td>
                                                </tr>
                                        <?php
                                            }
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

        // Handle delete confirmation with SweetAlert2
        $(document).ready(function() {
            $('.delete-btn').click(function(e) {
                e.preventDefault();
                const deleteId = $(this).data('id');
                Swal.fire({
                    title: 'คุณแน่ใจหรือไม่?',
                    text: "คุณต้องการลบประเภทนี้หรือไม่? การดำเนินการนี้ไม่สามารถย้อนกลับได้!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ใช่, ลบเลย!',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '?delete=' + deleteId;
                    }
                });
            });
        });
    </script>
</body>

</html>