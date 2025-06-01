<?php
require_once('config/db.php');

// ดึงข้อมูลติดต่อล่าสุดจาก tbl_contact
$contact_info = [];
try {
    $stmt = $conn->prepare("SELECT * FROM tbl_contact ORDER BY creationDate DESC LIMIT 1");
    $stmt->execute();
    $contact_info = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ถ้ามีข้อผิดพลาด ให้ใช้ข้อมูลเริ่มต้น
    $contact_info = [
        'address' => '123 ถนนดอกไม้ กรุงเทพฯ',
        'tel' => '02-123-4567',
        'email' => 'contact@flowershop.com',
        'business_hours' => 'เปิดทุกวัน 08:00 - 20:00 น.'
    ];
}
?>

<footer class="footer bg-dark text-white py-5">
    <div class="container">
        <div class="row g-4">
            <!-- Shop Info -->
            <div class="col-lg-4 col-md-6">
                <h5 class="footer-title">Flower Shop</h5>
                <p class="text-white">ร้านดอกไม้ที่คุณไว้วางใจ บริการจัดส่งทั่วประเทศ</p>
                <div class="social-links mt-3">
                    <a href="#" class="btn btn-outline-light btn-sm me-2"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm me-2"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm me-2"><i class="fab fa-line"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-4 col-md-6">
                <h5 class="footer-title">เมนูลัด</h5>
                <ul class="list-unstyled footer-links">
                    <li><a href="index.php">หน้าแรก</a></li>
                    <li><a href="products.php">ร้านค้า</a></li>
                    <li><a href="about-us.php">เกี่ยวกับเรา</a></li>
                    <li><a href="contact.php">ติดต่อเรา</a></li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="col-lg-4 col-md-6">
                <h5 class="footer-title">ติดต่อเรา</h5>
                <ul class="list-unstyled footer-contact">
                    <li><i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars($contact_info['address']); ?></li>
                    <li><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($contact_info['tel']); ?></li>
                    <li><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($contact_info['email']); ?></li>
                    <li><i class="fas fa-clock me-2"></i> <?php echo htmlspecialchars($contact_info['business_hours']); ?></li>
                </ul>
            </div>
        </div>
    </div>
</footer>