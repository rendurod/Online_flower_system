<?php
session_start();
include('config/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_login'])) {
    header("location: login.php");
    exit;
}

$userId = $_SESSION['user_login'];
$message = '';
$messageType = '';

// Handle password update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $currentPassword = trim($_POST['current_password']);
    $newPassword = trim($_POST['new_password']);
    $confirmPassword = trim($_POST['confirm_password']);

    // Validation
    $errors = [];
    $minLength = 8;

    // Fetch current user data
    try {
        $stmt = $conn->prepare("SELECT Password FROM tbl_members WHERE ID = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $storedPassword = $user['Password'] ?? '';

        // Validate current password and required fields
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errors[] = "กรุณากรอกข้อมูลทุกช่อง";
        }

        if (!password_verify($currentPassword, $storedPassword)) {
            $errors[] = "รหัสผ่านเดิมไม่ถูกต้อง";
        }

        // New password validation (aligned with register.php logic)
        if (strlen($newPassword) < $minLength) {
            $errors[] = "รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร";
        } elseif (!preg_match('/[A-Z]/', $newPassword)) {
            $errors[] = "รหัสผ่านใหม่ต้องมีอักษรพิมพ์ใหญ่อย่างน้อยหนึ่งตัว";
        } elseif (!preg_match('/[a-z]/', $newPassword)) {
            $errors[] = "รหัสผ่านใหม่ต้องมีตัวพิมพ์เล็กอย่างน้อยหนึ่งตัว";
        } elseif (!preg_match('/\d/', $newPassword)) {
            $errors[] = "รหัสผ่านใหม่ต้องมีตัวเลขอย่างน้อยหนึ่งตัว";
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = "รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน";
        }

        // Update password if no errors
        if (empty($errors)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            try {
                $updateStmt = $conn->prepare("UPDATE tbl_members SET Password = ?, UpdationDate = CURRENT_TIMESTAMP WHERE ID = ?");
                $updateStmt->execute([$hashedPassword, $userId]);
                $message = "เปลี่ยนรหัสผ่านสำเร็จ คุณจะถูกออกจากระบบใน 3 วินาที";
                $messageType = "success";
            } catch (PDOException $e) {
                $errors[] = "เกิดข้อผิดพลาดในการอัปเดตรหัสผ่าน: " . htmlspecialchars($e->getMessage());
            }
        }

        if (!empty($errors)) {
            $message = implode('<br>', $errors);
            $messageType = "danger";
        }
    } catch (PDOException $e) {
        $message = "เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้: " . htmlspecialchars($e->getMessage());
        $messageType = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เปลี่ยนรหัสผ่าน - Flower Shop</title>
    <!-- LOGO -->
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/user-profile.css">
</head>

<body class="profile">
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <div class="profile-container">
        <div class="profile-card">

            <!-- Password Change Form -->
            <div class="tab-content">
                <h4 class="tab-title">เปลี่ยนรหัสผ่าน</h4>
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>" role="alert" id="message-alert">
                        <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="profile-form">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i>
                            <h5>รหัสผ่านเดิม <span class="text-danger">*</span></h5>
                        </label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i>
                            <h5>รหัสผ่านใหม่ <span class="text-danger">*</span></h5>
                        </label>
                        <input type="password" class="form-control" name="new_password" required>
                        <small class="text-muted d-block mt-1">
                            รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร, อักษรพิมพ์ใหญ่ 1 ตัว, อักษรพิมพ์เล็ก 1 ตัว, และตัวเลข 1 ตัว
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i>
                            <h5>ยืนยันรหัสผ่านใหม่ <span class="text-danger">*</span></h5>
                        </label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>

                    <button type="submit" name="update_password" class="btn-update">
                        <i class="fas fa-save me-2"></i>
                        อัปเดตรหัสผ่าน
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Handle automatic logout after successful password change
        document.addEventListener('DOMContentLoaded', function() {
            const messageAlert = document.getElementById('message-alert');
            if (messageAlert && messageAlert.classList.contains('alert-success')) {
                Swal.fire({
                    title: 'สำเร็จ!',
                    text: 'รหัสผ่านของคุณถูกเปลี่ยนสำเร็จ คุณจะถูกออกจากระบบใน 5 วินาที',
                    icon: 'success',
                    timer: 5000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                }).then(() => {
                    // Redirect to login page after 3 seconds
                    window.location.href = 'logout.php';
                });
            }
        });
    </script>
</body>
</html>