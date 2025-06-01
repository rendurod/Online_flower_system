<?php
// ดึงข้อมูลล่าสุดจาก tbl_contact
require_once('config/db.php');
$contact_info = [];
try {
    $stmt = $conn->prepare("SELECT * FROM tbl_contact ORDER BY creationDate DESC LIMIT 1");
    $stmt->execute();
    $contact_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $contact_info = [
        'email' => 'contact@flowershop.com',
        'nameteam' => 'Flower Shop Team',
        'address' => '123 ถนนดอกไม้ กรุงเทพฯ',
        'tel' => '02-123-4567',
        'business_hours' => 'เปิดทุกวัน 08:00 - 20:00 น.'
    ];
}
?>

<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
    <!-- Sidebar Toggle (Topbar) -->
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Topbar Navbar -->
    <ul class="navbar-nav ml-auto">
        <!-- Nav Item - Store Info -->
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle" href="#" id="storeInfoDropdown" role="button"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-store text-gray-400"></i>
                <!-- Counter - Store Info -->
                <span class="badge badge-danger badge-counter">+</span>
            </a>
            <!-- Dropdown - Store Info -->
            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="storeInfoDropdown">
                <h6 class="dropdown-header" style="background-color: #ff6f91; border-color: #ff6f91;">
                    ข้อมูลร้านดอกไม้
                </h6>
                <div class="dropdown-item d-flex align-items-center">
                    <div class="mr-3">
                        <div class="icon-circle" style="background-color: #ffe6ec;">
                            <i class="fas fa-map-marker-alt text-pink"></i>
                        </div>
                    </div>
                    <div>
                        <div class="small text-gray-500">ที่อยู่</div>
                        <span><?php echo htmlspecialchars($contact_info['address']); ?></span>
                    </div>
                </div>
                <div class="dropdown-item d-flex align-items-center">
                    <div class="mr-3">
                        <div class="icon-circle" style="background-color: #ffe6ec;">
                            <i class="fas fa-phone text-pink"></i>
                        </div>
                    </div>
                    <div>
                        <div class="small text-gray-500">เบอร์โทรศัพท์</div>
                        <span><?php echo htmlspecialchars($contact_info['tel']); ?></span>
                    </div>
                </div>
                <div class="dropdown-item d-flex align-items-center">
                    <div class="mr-3">
                        <div class="icon-circle" style="background-color: #ffe6ec;">
                            <i class="fas fa-envelope text-pink"></i>
                        </div>
                    </div>
                    <div>
                        <div class="small text-gray-500">อีเมล</div>
                        <span><?php echo htmlspecialchars($contact_info['email']); ?></span>
                    </div>
                </div>
                <div class="dropdown-item d-flex align-items-center">
                    <div class="mr-3">
                        <div class="icon-circle" style="background-color: #ffe6ec;">
                            <i class="fas fa-clock text-pink"></i>
                        </div>
                    </div>
                    <div>
                        <div class="small text-gray-500">เวลาทำการ</div>
                        <span><?php echo htmlspecialchars($contact_info['business_hours']); ?></span>
                    </div>
                </div>
                <!-- <a class="dropdown-item text-center small text-gray-500" href="contact.php">ดูรายละเอียดเพิ่มเติม</a> -->
            </div>
        </li>

        <div class="topbar-divider d-none d-sm-block"></div>

        <!-- Nav Item - User Information -->
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="mr-2 d-none d-lg-inline text-gray-600 small">Admin : <?php echo htmlspecialchars($admin['UserName']); ?></span>
                <img class="img-profile rounded-circle" src="img/undraw_profile.svg">
            </a>
            <!-- Dropdown - User Information -->
            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="userDropdown">
                <a class="dropdown-item" href="profile.php">
                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                    โปร์ไฟล์
                </a>
                <a class="dropdown-item" href="address-store.php">
                    <i class="fas fa-store fa-sm fa-fw mr-2 text-gray-400"></i>
                    ที่อยู่ของร้านค้า
                </a>
                <a class="dropdown-item" href="../index.php">
                    <i class="fas fa-shopping-basket fa-sm fa-fw mr-2 text-gray-400"></i>
                    ไปยังหน้าผู้ใช้งาน
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                    ออกจากระบบ
                </a>
            </div>
        </li>
    </ul>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">คุณจะออกจากระบบใช่หรือไม่?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">เลือก "ออกจากระบบ" ข้างล่างหากคุณพร้อมที่จะสิ้นสุดเซสชันปัจจุบันของคุณ</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">ยกเลิก</button>
                    <a class="btn btn-danger" href="logout.php">ออกจากระบบ</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<style>
    .text-pink {
        color: #ff6f91 !important; /* สีชมพูเข้มสำหรับไอคอน */
    }
    .dropdown-header {
        background-color: #ff6f91 !important; /* สีพื้นหลัง header dropdown */
        border-color: #ff6f91 !important;
    }
    .icon-circle {
        background-color: #ffe6ec !important; /* สีพื้นหลังวงกลมไอคอน */
    }
    .dropdown-item:hover {
        background-color: #fff5f7 !important; /* สีพื้นหลังเมื่อ hover */
    }
</style>