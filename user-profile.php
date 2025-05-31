<?php
session_start();
include('config/db.php');

// ตรวจสอบว่าผู้ใช้ล็อกอินแล้วหรือยัง
if (!isset($_SESSION['user_login'])) {
    header("location: login.php");
    exit;
}

$userId = $_SESSION['user_login'];
$message = '';
$messageType = '';

// ดึงข้อมูลผู้ใช้ปัจจุบัน
try {
    $stmt = $conn->prepare("SELECT * FROM tbl_members WHERE ID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("location: logout.php");
        exit;
    }
} catch (PDOException $e) {
    $message = "เกิดข้อผิดพลาดในการดึงข้อมูล";
    $messageType = "danger";
}

// จัดการการอัปเดตโปรไฟล์
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $contactNo = trim($_POST['contactNo']);
    $address = trim($_POST['address']);

    // Validation
    $errors = [];

    if (empty($firstName)) {
        $errors[] = "กรุณากรอกชื่อ";
    }

    if (empty($lastName)) {
        $errors[] = "กรุณากรอกนามสกุล";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "กรุณากรอกอีเมลที่ถูกต้อง";
    }

    if (!empty($contactNo) && !preg_match('/^[0-9]{10}$/', $contactNo)) {
        $errors[] = "กรุณากรอกเบอร์โทรศัพท์ที่ถูกต้อง (10 หลัก)";
    }

    // ตรวจสอบว่าอีเมลซ้ำหรือไม่ (ยกเว้นของตัวเอง)
    if (empty($errors)) {
        try {
            $checkEmail = $conn->prepare("SELECT ID FROM tbl_members WHERE EmailId = ? AND ID != ?");
            $checkEmail->execute([$email, $userId]);
            if ($checkEmail->fetch()) {
                $errors[] = "อีเมลนี้ถูกใช้แล้ว";
            }
        } catch (PDOException $e) {
            $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบอีเมล";
        }
    }

    // จัดการอัปโหลดรูปภาพ
    $imageName = $user['Image']; // ใช้รูปเดิม
    if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] == UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['profileImage']['type'], $allowedTypes)) {
            $errors[] = "รองรับเฉพาะไฟล์รูปภาพ JPG, JPEG, PNG, GIF เท่านั้น";
        } elseif ($_FILES['profileImage']['size'] > $maxSize) {
            $errors[] = "ขนาดไฟล์ต้องไม่เกิน 5MB";
        } else {
            $uploadDir = 'Uploads/imgprofile/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $imageExtension = pathinfo($_FILES['profileImage']['name'], PATHINFO_EXTENSION);
            $imageName = 'profile_' . $userId . '_' . time() . '.' . $imageExtension;
            $uploadPath = $uploadDir . $imageName;

            if (!move_uploaded_file($_FILES['profileImage']['tmp_name'], $uploadPath)) {
                $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ";
            } else {
                // ลบรูปเดิม (ถ้าไม่ใช่รูปเริ่มต้น)
                if ($user['Image'] && $user['Image'] != 'default.png' && file_exists($uploadDir . $user['Image'])) {
                    unlink($uploadDir . $user['Image']);
                }
            }
        }
    }

    // อัปเดตข้อมูล
    if (empty($errors)) {
        try {
            $updateStmt = $conn->prepare("UPDATE tbl_members SET FirstName = ?, LastName = ?, EmailId = ?, ContactNo = ?, Address = ?, Image = ?, UpdationDate = CURRENT_TIMESTAMP WHERE ID = ?");
            $updateStmt->execute([$firstName, $lastName, $email, $contactNo, $address, $imageName, $userId]);

            $message = "อัปเดตโปรไฟล์เรียบร้อยแล้ว";
            $messageType = "success";

            // รีเฟรชข้อมูลผู้ใช้
            $stmt = $conn->prepare("SELECT * FROM tbl_members WHERE ID = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $message = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
            $messageType = "danger";
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ส่วนตัว - FlowerShop</title>
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
    <!-- Custom CSS for Validation Status -->
    <style>
        .address-status {
            margin: 10px 0;
            padding: 12px 15px;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            border: 1px solid transparent;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .address-status i {
            margin-right: 10px;
            font-size: 1.4rem;
        }

        .status-not-verified {
            background-color: #f8f9fa;
            color: #6c757d;
            border-color: #6c757d;
        }

        .status-not-verified i {
            color: #6c757d;
        }

        .status-incorrect {
            background-color: #fff5f5;
            color: #dc3545;
            border-color: #dc3545;
        }

        .status-incorrect i {
            color: #dc3545;
        }

        .status-verified {
            background-color: #e6f4ea;
            color: #28a745;
            border-color: #28a745;
        }

        .status-verified i {
            color: #28a745;
        }

        .status-incorrect-text {
            margin-top: 8px;
            font-size: 1rem;
            color: #dc3545;
            font-style: italic;
            padding-left: 25px;
        }

        @media (max-width: 576px) {
            .address-status {
                font-size: 1rem;
                padding: 10px 12px;
            }
            .address-status i {
                font-size: 1.2rem;
            }
            .status-incorrect-text {
                font-size: 0.9rem;
            }
        }
    </style>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</head>

<body class="profile">
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <div class="profile-container">
        <div class="profile-card">
            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <div class="nav-item mt-5 ms-5">
                    <a class="nav-link active" href="user-profile.php">โปรไฟล์ส่วนตัว</a>
                </div>
                <div class="nav-item mt-5 me-5">
                    <a class="nav-link" href="user-order.php">ประวัติการสั่งซื้อ</a>
                </div>
            </div>

            <!-- Profile Form -->
            <div class="profile-form tab-content">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                        <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <!-- Image Upload Section -->
                    <div class="image-upload-section">
                        <?php if (!empty($user['Image']) && file_exists("Uploads/imgprofile/" . $user['Image'])): ?>
                            <img src="Uploads/imgprofile/<?php echo htmlspecialchars($user['Image']); ?>" alt="Current Profile" class="current-image">
                        <?php else: ?>
                            <img src="assets/img/account.png" alt="Default Profile" class="current-image">
                        <?php endif; ?>
                        <label class="file-input-wrapper">
                            <i class="fas fa-upload me-2"></i>เลือกรูปภาพใหม่
                            <input type="file" name="profileImage" accept="image/*">
                        </label>
                        <small class="text-muted d-block mt-2">รองรับไฟล์ JPG, JPEG, PNG, GIF ขนาดไม่เกิน 5MB</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user"></i>
                                    <h4>ชื่อ <span class="text-danger">*</span></h4>
                                </label>
                                <input type="text" class="form-control" name="firstName"
                                    value="<?php echo htmlspecialchars($user['FirstName'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user"></i>
                                    <h4>นามสกุล <span class="text-danger">*</span></h4>
                                </label>
                                <input type="text" class="form-control" name="lastName"
                                    value="<?php echo htmlspecialchars($user['LastName'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i>
                            <h4>อีเมล <span class="text-danger">*</span></h4>
                        </label>
                        <input type="email" class="form-control" name="email"
                            value="<?php echo htmlspecialchars($user['EmailId'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-phone"></i>
                            <h4>เบอร์โทรศัพท์</h4>
                        </label>
                        <input type="tel" class="form-control" name="contactNo"
                            value="<?php echo htmlspecialchars($user['ContactNo'] ?? ''); ?>"
                            placeholder="กรุณากรอกเบอร์โทร" maxlength="10">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-map-marker-alt"></i>
                            <h4>ที่อยู่จัดส่ง - ผู้รับ</h4>
                        </label>
                        <!-- Address Validation Status -->
                        <div class="address-status
                            <?php
                            if ($user['Validate'] === 'ที่อยู่ถูกต้อง') {
                                echo 'status-verified';
                            } elseif ($user['Validate'] !== 'ยังไม่ยืนยัน' && $user['Validate'] !== null && $user['Validate'] !== '') {
                                echo 'status-incorrect';
                            } else {
                                echo 'status-not-verified';
                            }
                            ?>">
                            <i class="fas
                                <?php
                                if ($user['Validate'] === 'ที่อยู่ถูกต้อง') {
                                    echo 'fa-check-circle';
                                } elseif ($user['Validate'] !== 'ยังไม่ยืนยัน' && $user['Validate'] !== null && $user['Validate'] !== '') {
                                    echo 'fa-times-circle';
                                } else {
                                    echo 'fa-clock';
                                }
                                ?>"></i>
                            <?php
                            if ($user['Validate'] === 'ที่อยู่ถูกต้อง') {
                                echo 'ยืนยันที่อยู่ถูกต้อง';
                            } elseif ($user['Validate'] !== 'ยังไม่ยืนยัน' && $user['Validate'] !== null && $user['Validate'] !== '') {
                                echo 'ที่อยู่ไม่ถูกต้อง';
                            } else {
                                echo 'ยังไม่ยืนยัน';
                            }
                            ?>
                        </div>
                        <?php if ($user['Validate'] !== 'ยังไม่ยืนยัน' && $user['Validate'] !== 'ที่อยู่ถูกต้อง' && $user['Validate'] !== null && $user['Validate'] !== ''): ?>
                            <div class="status-incorrect-text">
                                <strong>เหตุผล:</strong> <?php echo htmlspecialchars($user['Validate']); ?>
                            </div>
                        <?php endif; ?>
                        <textarea class="form-control" name="address" rows="3"
                            placeholder="ยังไม่มีที่อยู่ข้อมูล"><?php echo htmlspecialchars($user['Address'] ?? ''); ?></textarea>
                        <small class="text-muted d-block mt-2">กรุณากรอกที่อยู่ให้ครบถ้วน รวมถึงบ้านเลขที่, ถนน, หมู่บ้าน, ตำบล/แขวง, อำเภอ/เขต, จังหวัด, และรหัสไปรษณีย์ เพื่อให้การจัดส่งสะดวกและรวดเร็ว</small>
                        <!-- Address Requirements Button and List -->
                        <div class="address-info-wrapper mt-2">
                            <button type="button" class="btn-show-requirements">
                                <i class="fas fa-info-circle"></i>
                                แสดงข้อกำหนดที่อยู่
                            </button>
                            <div class="address-requirements" style="display: none;">
                                <div class="requirements-title">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>ข้อกำหนดที่อยู่:</span>
                                </div>
                                <ul class="requirements-list">
                                    <li><i class="fas fa-check-circle"></i> บ้านเลขที่</li>
                                    <li><i class="fas fa-check-circle"></i> ตำบล</li>
                                    <li><i class="fas fa-check-circle"></i> อำเภอ</li>
                                    <li><i class="fas fa-check-circle"></i> จังหวัด</li>
                                    <li><i class="fas fa-check-circle"></i> รหัสไปรษณีย์</li>
                                    <li><i class="fas fa-check-circle"></i> และอื่น ๆ (เช่น ถนน, หมู่บ้าน)</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="btn-update">
                        <i class="fas fa-save me-2"></i>
                        อัปเดตโปรไฟล์
                    </button>
                </form>

                <!-- Member Since Info -->
                <div class="member-since">
                    <i class="fas fa-calendar-alt"></i>
                    <div>
                        <strong>สมาชิกตั้งแต่:</strong>
                        <?php
                        if ($user['RegDate']) {
                            echo date('d/m/Y H:i', strtotime($user['RegDate']));
                        }
                        ?>
                    </div>
                    <?php if ($user['UpdationDate']): ?>
                        <div class="mt-1">
                            <small class="text-muted">
                                อัปเดตล่าสุด: <?php echo date('d/m/Y H:i', strtotime($user['UpdationDate'])); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Preview image before upload
        document.querySelector('input[name="profileImage"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.current-image').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const firstName = document.querySelector('input[name="firstName"]').value.trim();
            const lastName = document.querySelector('input[name="lastName"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const contactNo = document.querySelector('input[name="contactNo"]').value.trim();

            if (!firstName || !lastName || !email) {
                e.preventDefault();
                alert('กรุณากรอกข้อมูลที่จำเป็น (ชื่อ, นามสกุล, อีเมล)');
                return;
            }

            if (contactNo && !/^[0-9]{10}$/.test(contactNo)) {
                e.preventDefault();
                alert('กรุณากรอกเบอร์โทรศัพท์ที่ถูกต้อง (10 หลัก)');
                return;
            }
        });

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const formGroups = document.querySelectorAll('.form-group');
            formGroups.forEach((group, index) => {
                group.style.opacity = '0';
                group.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    group.style.transition = 'all 0.3s ease';
                    group.style.opacity = '1';
                    group.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Address requirements toggle with enhanced animations
            $('.btn-show-requirements').on('click', function() {
                const requirements = $(this).siblings('.address-requirements');
                requirements.slideToggle(300).toggleClass('show');

                const buttonText = requirements.is(':visible') ?
                    'ซ่อนข้อกำหนดที่อยู่' :
                    'แสดงข้อกำหนดที่อยู่';
                $(this).html(`<i class="fas fa-info-circle"></i> ${buttonText}`);

                // Animate list items when shown
                if (requirements.hasClass('show')) {
                    requirements.find('.requirements-list li').each(function(index) {
                        $(this).css({
                            opacity: 0,
                            transform: 'translateX(-20px)'
                        }).delay(index * 100).animate({
                            opacity: 1,
                            transform: 'translateX(0)'
                        }, 300);
                    });
                }
            });

            $(document).on('click', function(event) {
                if (!$(event.target).closest('.address-info-wrapper').length) {
                    $('.address-requirements').slideUp(300).removeClass('show');
                    $('.btn-show-requirements').html('<i class="fas fa-info-circle"></i> แสดงข้อกำหนดที่อยู่');
                    $('.requirements-list li').css({
                        opacity: 0,
                        transform: 'translateX(-20px)'
                    });
                }
            });
        });
    </script>
</body>
</html>