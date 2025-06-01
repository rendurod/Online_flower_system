<?php
session_start();
include('config/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_login'])) {
    header("Location: login.php"); // Redirect to login page if not logged in
    exit();
}

// Get product ID from URL
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);
    $query = "SELECT * FROM tbl_flowers WHERE ID = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $flower = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$flower) {
        // If no flower is found, redirect to products page with an error message
        $_SESSION['error'] = "ไม่พบสินค้าที่เลือก";
        header("Location: products.php");
        exit();
    }
} else {
    // If no ID is provided, redirect to products page
    $_SESSION['error'] = "กรุณาเลือกสินค้า";
    header("Location: products.php");
    exit();
}

// Fetch related flowers (same category, excluding current product)
$related_query = "SELECT ID, flower_name, flower_description, price, image, stock_quantity FROM tbl_flowers WHERE flower_category = :category AND ID != :id AND stock_quantity > 0 ORDER BY creation_date DESC";
$related_stmt = $conn->prepare($related_query);
$related_stmt->bindValue(':category', $flower['flower_category'], PDO::PARAM_STR);
$related_stmt->bindValue(':id', $id, PDO::PARAM_INT);
$related_stmt->execute();
$related_flowers = $related_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all flowers with available stock
$all_flowers_query = "SELECT ID, flower_name, flower_description, price, image, stock_quantity FROM tbl_flowers WHERE stock_quantity > 0 ORDER BY creation_date DESC";
$all_flowers_stmt = $conn->prepare($all_flowers_query);
$all_flowers_stmt->execute();
$all_flowers = $all_flowers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user data from tbl_members
$user_id = $_SESSION['user_login'];
$user_query = "SELECT FirstName, LastName, EmailId, ContactNo, Address, Validate FROM tbl_members WHERE ID = :id";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
$user_stmt->execute();
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Fallback data if user is not found (for debugging)
if (!$user_data) {
    $user_data = [
        'FirstName' => 'ชื่อ (ไม่พบข้อมูล)',
        'LastName' => 'นามสกุล (ไม่พบข้อมูล)',
        'EmailId' => 'email@example.com (ไม่พบข้อมูล)',
        'ContactNo' => '01234567890 (ไม่พบข้อมูล)',
        'Address' => 'ที่อยู่ที่มีอยู่แล้ว (ไม่พบข้อมูล)',
        'Validate' => 'ยังไม่ยืนยัน'
    ];
}

// Fetch payment details from tbl_payment
$payment_query = "SELECT QRCodeImage, AccountName, BankAccountNumber FROM tbl_payment ORDER BY CreatedAt DESC LIMIT 1";
$payment_stmt = $conn->prepare($payment_query);
$payment_stmt->execute();
$payment_data = $payment_stmt->fetch(PDO::FETCH_ASSOC);

// Check if QR Code image exists, otherwise use default
$qrCodeImagePath = !empty($payment_data['QRCodeImage']) && file_exists("uploads/qrcodes/" . $payment_data['QRCodeImage'])
    ? "uploads/qrcodes/" . htmlspecialchars($payment_data['QRCodeImage'])
    : "assets/img/default-qrcode.jpg";
$accountName = $payment_data ? htmlspecialchars($payment_data['AccountName']) : 'ชื่อบัญชี (ไม่พบข้อมูล)';
$bankAccountNumber = $payment_data ? htmlspecialchars($payment_data['BankAccountNumber']) : 'เลขบัญชี (ไม่พบข้อมูล)';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flower_id = $flower['ID'];
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $user_email = $user_data['EmailId'];
    $delivery_date = isset($_POST['delivery_date']) ? $_POST['delivery_date'] : null;

    // Validate quantity against stock
    if ($quantity > $flower['stock_quantity']) {
        $_SESSION['error'] = "จำนวนสินค้าที่เลือกเกินกว่าที่มีอยู่ในสต๊อก";
        header("Location: product-order.php?id=" . $flower_id);
        exit();
    }

    // Validate delivery date
    if (empty($delivery_date)) {
        $_SESSION['error'] = "กรุณาเลือกวันที่จัดส่ง";
        header("Location: product-order.php?id=" . $flower_id);
        exit();
    }

    // Validate address
    if (empty($user_data['Address']) || $user_data['Validate'] !== 'ที่อยู่ถูกต้อง') {
        $_SESSION['error'] = "ที่อยู่ของคุณยังไม่ได้รับการยืนยัน กรุณาอัปเดตที่อยู่ในโปรไฟล์และรอการอนุมัติจากแอดมิน";
        header("Location: product-order.php?id=" . $flower_id);
        exit();
    }

    // Handle payment slip upload
    $slip_image = '';
    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/slips/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_name = uniqid() . '-' . basename($_FILES['payment_slip']['name']);
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $file_path)) {
            $slip_image = $file_name;
        } else {
            $_SESSION['error'] = "ไม่สามารถอัพโหลดสลิปโอนเงินได้";
            header("Location: product-order.php?id=" . $flower_id);
            exit();
        }
    }

    // Generate BookingNumber (random 10-digit number)
    $booking_number = rand(1000000000, 9999999999);

    // Insert order into tbl_orders
    $order_query = "INSERT INTO tbl_orders (BookingNumber, UserEmail, FlowerId, Quantity, DeliveryDate, Image, Status, PostingDate) 
                    VALUES (:booking_number, :user_email, :flower_id, :quantity, :delivery_date, :image, 0, NOW())";
    $order_stmt = $conn->prepare($order_query);
    $order_stmt->bindValue(':booking_number', $booking_number, PDO::PARAM_INT);
    $order_stmt->bindValue(':user_email', $user_email, PDO::PARAM_STR);
    $order_stmt->bindValue(':flower_id', $flower_id, PDO::PARAM_INT);
    $order_stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
    $order_stmt->bindValue(':delivery_date', $delivery_date, PDO::PARAM_STR);
    $order_stmt->bindValue(':image', $slip_image, PDO::PARAM_STR);

    if ($order_stmt->execute()) {
        $_SESSION['success'] = "สั่งซื้อสำเร็จ! รอการยืนยันจากแอดมิน";
        header("Location: products.php");
        exit();
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการสั่งซื้อ กรุณาลองใหม่";
        header("Location: product-order.php?id=" . $flower_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ซื้อสินค้า - <?php echo htmlspecialchars($flower['flower_name']); ?> - FlowerShop</title>
    <!-- LOGO -->
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Swiper Slider -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/productPHP.css">
    <link rel="stylesheet" href="assets/css/productDetail.css">
    <style>
        /* Existing styles remain the same */
        .step-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 40px 0;
            position: relative;
            gap: 3rem;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            width: 100px;
        }

        .circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e8e8e8;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            z-index: 1;
        }

        .step.active .circle {
            background-color: var(--primary-pink);
            color: var(--white);
        }

        .label {
            margin-top: 8px;
            font-size: 14px;
            color: #b0b0b0;
        }

        .active-label {
            color: var(--text-dark);
            font-weight: 500;
        }

        .line {
            position: absolute;
            top: 20px;
            left: -50%;
            width: 100%;
            height: 3px;
            background-color: #e8e8e8;
            z-index: 0;
        }

        .step:first-child .line {
            display: none;
        }

        .order-form-section {
            padding: 3rem 0;
            background: var(--white);
        }

        .order-form-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            gap: 2rem;
        }

        .order-form-left {
            flex: 2;
        }

        .order-form-right {
            flex: 1;
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: var(--border-radius);
        }

        .order-form-group {
            margin-bottom: 2rem;
        }

        .order-form-group h3 {
            font-size: 1.8rem;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .data-display-box {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-top: 0.5rem;
            font-size: 1.4rem;
            color: var(--text-dark);
        }

        .address-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            text-align: center;
            min-width: 120px;
            margin-top: 0.5rem;
        }

        .status-not-verified {
            background-color: #e0e0e0;
            color: #333;
        }

        .status-verified {
            background-color: #d4edda;
            color: #155724;
        }

        .status-incorrect {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-incorrect-text {
            margin-top: 8px;
            font-size: 1rem;
            color: #721c24;
            font-style: italic;
            padding-left: 25px;
        }

        .delivery-date-section input[type="date"] {
            width: 100%;
            padding: 1rem;
            border: 2px solid rgba(232, 67, 147, 0.2);
            border-radius: var(--border-radius);
            font-size: 1.4rem;
            transition: var(--transition);
            background: var(--white);
        }

        .delivery-date-section input[type="date"]:focus {
            outline: none;
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 3px rgba(232, 67, 147, 0.1);
        }

        .payment-section img {
            width: 100%;
            max-width: 200px;
            margin-bottom: 1rem;
        }

        .upload-slip {
            margin-top: 1rem;
        }

        .upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-pink);
            color: var(--white);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .upload-btn:hover {
            background: var(--dark-pink);
            transform: translateY(-1px);
        }

        .upload-btn i {
            font-size: 1.2rem;
        }

        .slip-preview-container {
            margin-top: 1rem;
            display: flex;
            justify-content: flex-start;
        }

        .slip-preview {
            max-width: 200px;
            max-height: 200px;
            border: 2px solid rgba(232, 67, 147, 0.2);
            border-radius: var(--border-radius);
            display: none;
        }

        .copy-btn {
            background: var(--primary-pink);
            color: var(--white);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
            margin: 0.5rem 0;
        }

        .copy-btn:hover {
            background: var(--dark-pink);
            transform: translateY(-1px);
        }

        .proceed-btn {
            background: #00b894;
            color: var(--white);
            border: none;
            padding: 1rem 2rem;
            border-radius: var(--border-radius);
            font-size: 1.4rem;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
        }

        .proceed-btn:hover {
            background: #009e7f;
            transform: translateY(-2px);
        }

        .product-summary {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .product-summary img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }

        .product-summary h4 {
            font-size: 1.4rem;
            color: var(--text-dark);
            margin: 0;
        }

        .product-summary p {
            font-size: 1.4rem;
            color: var(--primary-pink);
            margin: 0;
        }

        .product-summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .product-summary-item h4 {
            font-size: 1.4rem;
            color: var(--text-dark);
            margin: 0;
        }

        .product-summary-item p {
            font-size: 1.4rem;
            color: var(--primary-pink);
            margin: 0;
        }

        .total-price {
            font-size: 1.6rem;
            font-weight: 600;
            color: var(--primary-pink);
            text-align: right;
            margin-top: 1rem;
        }

        /* Adjusted quantity input */
        .quantity-input {
            width: 100px;
            height: 50px;
            font-size: 1.6rem;
            font-weight: bold;
            text-align: center;
            border: 3px solid var(--primary-pink);
            border-radius: 10px;
            background-color: #fff;
            color: var(--text-dark);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .quantity-input:focus {
            outline: none;
            border-color: #e84393;
            box-shadow: 0 0 0 4px rgba(232, 67, 147, 0.2);
        }

        @media (max-width: 768px) {
            .order-form-container {
                flex-direction: column;
            }

            .order-form-right {
                order: -1;
            }
        }
    </style>
</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- Product Order Section -->
    <section class="order-form-section">
        <div class="step-container">
            <div class="step">
                <div class="circle">1</div>
                <div class="label">เลือกสินค้า</div>
            </div>
            <div class="step active">
                <div class="line"></div>
                <div class="circle">2</div>
                <div class="label active-label">กรอกข้อมูล</div>
            </div>
            <div class="step">
                <div class="line"></div>
                <div class="circle">3</div>
                <div class="label">คำสั่งซื้อสำเร็จ</div>
            </div>
        </div>
        <div class="order-form-container">
            <!-- Left: Form Section -->
            <form method="POST" enctype="multipart/form-data">
                <div class="order-form-left">
                    <!-- 1. ข้อมูลผู้รับ -->
                    <div class="order-form-group">
                        <h3>1. ข้อมูลผู้รับ</h3>
                        <div class="data-display-box" id="existing_info_display">
                            ชื่อ: <?php echo htmlspecialchars($user_data['FirstName'] . ' ' . $user_data['LastName']); ?><br>
                            Email: <?php echo htmlspecialchars($user_data['EmailId']); ?><br>
                            โทร: <?php echo htmlspecialchars($user_data['ContactNo']); ?>
                        </div>
                    </div>

                    <!-- 2. ข้อมูลจัดส่ง -->
                    <div class="order-form-group">
                        <h3>2. ข้อมูลจัดส่ง</h3>
                        <div class="data-display-box" id="existing_address_display">
                            ที่อยู่: <?php echo htmlspecialchars($user_data['Address']) ?: 'ยังไม่ได้ระบุที่อยู่'; ?>
                        </div>
                        <?php
                        $addressStatus = '';
                        $statusClass = '';
                        $reasonText = '';
                        $iconClass = '';
                        if (empty($user_data['Address'])) {
                            $addressStatus = 'ยังไม่มีข้อมูล';
                            $statusClass = 'status-not-verified';
                            $iconClass = 'fa-clock';
                        } elseif ($user_data['Validate'] === 'ที่อยู่ถูกต้อง') {
                            $addressStatus = 'ที่อยู่ได้รับการยืนยัน';
                            $statusClass = 'status-verified';
                            $iconClass = 'fa-check-circle';
                        } elseif (!empty($user_data['Validate']) && $user_data['Validate'] !== 'ยังไม่ยืนยัน') {
                            $addressStatus = 'ที่อยู่ไม่ผ่านการตรวจสอบ';
                            $statusClass = 'status-incorrect';
                            $reasonText = "เหตุผล: " . htmlspecialchars($user_data['Validate']);
                            $iconClass = 'fa-times-circle';
                        } else {
                            $addressStatus = 'รอการตรวจสอบ';
                            $statusClass = 'status-not-verified';
                            $iconClass = 'fa-clock';
                        }
                        ?>
                        <div class="address-status <?php echo $statusClass; ?>">
                            <i class="fas <?php echo $iconClass; ?>"></i> <?php echo htmlspecialchars($addressStatus); ?>
                        </div>
                        <?php if ($statusClass === 'status-incorrect'): ?>
                            <div class="status-incorrect-text"><?php echo $reasonText; ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- 3. เลือกวันที่จัดส่ง -->
                    <div class="order-form-group">
                        <h3>3. เลือกวันที่จัดส่ง</h3>
                        <div class="delivery-date-section">
                            <input type="date" name="delivery_date" id="delivery_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        </div>
                    </div>

                    <!-- 4. ชำระเงิน -->
                    <div class="order-form-group">
                        <h3>4. ชำระเงิน</h3>
                        <div class="payment-section">
                            <img src="<?php echo $qrCodeImagePath; ?>" alt="QR Code Payment">
                            <p><strong>ชื่อบัญชี:</strong> <?php echo $accountName; ?></p>
                            <p><strong>เลขที่บัญชี:</strong> <?php echo $bankAccountNumber; ?></p>
                            <button type="button" class="copy-btn" onclick="copyText('<?php echo $bankAccountNumber; ?>')">คัดลอกเลขบัญชี</button>
                            <div class="upload-slip">
                                <label for="payment_slip" class="upload-btn">
                                    <i class="fas fa-upload"></i> อัพโหลดสลิปโอนเงิน
                                </label>
                                <input type="file" id="payment_slip" name="payment_slip" accept="image/*" style="display: none;">
                                <div class="slip-preview-container">
                                    <img id="slip_preview" class="slip-preview" src="#" alt="Slip Preview">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Hidden input for quantity -->
                    <input type="hidden" name="quantity" id="quantity_hidden">
                </div>
            </form>

            <!-- Right: Product Summary -->
            <div class="order-form-right">
                <div class="product-summary">
                    <img src="<?php echo !empty($flower['image']) && file_exists("admin/uploads/flowers/" . $flower['image']) ? "admin/uploads/flowers/" . htmlspecialchars($flower['image']) : "assets/img/default-flower.jpg"; ?>"
                        alt="<?php echo htmlspecialchars($flower['flower_name']); ?>">
                    <div>
                        <h4><?php echo htmlspecialchars($flower['flower_name']); ?></h4>
                        <p>จำนวนที่มีในสต๊อก: <span id="stock_quantity"><?php echo $flower['stock_quantity']; ?></span> ชิ้น</p>
                        <div>
                            <label for="quantity">เลือกจำนวน:</label>
                            <input type="number" class="quantity-input" id="quantity" value="1" min="1" max="<?php echo $flower['stock_quantity']; ?>">
                        </div>
                    </div>
                </div>
                <div class="product-summary-item">
                    <h4>มูลค่าสินค้าต่อชิ้น</h4>
                    <p>฿<?php echo number_format($flower['price'], 2); ?></p>
                </div>
                <div class="product-summary-item">
                    <h4>จำนวนสินค้า</h4>
                    <p id="selected_quantity">1</p>
                </div>
                <div class="product-summary-item">
                    <h4>มูลค่าสินค้าทั้งหมด</h4>
                    <p id="total_item_price">฿<?php echo number_format($flower['price'], 2); ?></p>
                </div>
                <div class="product-summary-item">
                    <h4>ค่าจัดส่ง</h4>
                    <p>฿0.00</p>
                </div>
                <div class="total-price">
                    ราคาทั้งหมด: <span id="total_price">฿<?php echo number_format($flower['price'], 2); ?></span>
                </div>
                <!-- Proceed Button -->
                <button type="button" class="proceed-btn" onclick="validateOrder()">ดำเนินการสั่งซื้อ</button>
            </div>
        </div>
    </section>

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends -->

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Swiper Slider -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Custom JS -->
    <script>
        const pricePerItem = <?php echo $flower['price']; ?>;
        const maxQuantity = <?php echo $flower['stock_quantity']; ?>;
        const userAddress = "<?php echo $user_data['Address']; ?>";
        const addressValidate = "<?php echo $user_data['Validate']; ?>";

        // Update total price and selected quantity display
        document.getElementById('quantity').addEventListener('input', function() {
            let quantity = parseInt(this.value) || 1;
            if (quantity < 1) quantity = 1;
            if (quantity > maxQuantity) quantity = maxQuantity;
            this.value = quantity;

            const total = pricePerItem * quantity;
            document.getElementById('selected_quantity').textContent = quantity;
            document.getElementById('total_item_price').textContent = `฿${total.toFixed(2)}`;
            document.getElementById('total_price').textContent = `฿${total.toFixed(2)}`;
            document.getElementById('quantity_hidden').value = quantity; // Update hidden input
        });

        // Image preview for payment slip
        document.getElementById('payment_slip').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('slip_preview');
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.src = '#';
                preview.style.display = 'none';
            }
        });

        // Copy text function
        function copyText(text) {
            navigator.clipboard.writeText(text).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'คัดลอกสำเร็จ',
                    text: 'ข้อมูลถูกคัดลอกไปยังคลิปบอร์ดแล้ว!',
                    timer: 1500,
                    showConfirmButton: false
                });
            });
        }

        // Validate order before proceeding
        function validateOrder() {
            // Check quantity against stock
            const quantity = parseInt(document.getElementById('quantity').value);
            if (quantity > maxQuantity) {
                Swal.fire({
                    icon: 'error',
                    title: 'จำนวนสินค้าเกินสต๊อก',
                    text: `สินค้ามีในสต๊อกเพียง ${maxQuantity} ชิ้น กรุณาเลือกจำนวนใหม่`,
                });
                return;
            }

            // Check delivery date
            const deliveryDate = document.getElementById('delivery_date').value;
            if (!deliveryDate) {
                Swal.fire({
                    icon: 'error',
                    title: 'กรุณาเลือกวันที่จัดส่ง',
                    text: 'คุณต้องเลือกวันที่จัดส่งก่อนดำเนินการสั่งซื้อ',
                });
                return;
            }

            // Check address and validation status
            if (!userAddress || userAddress === '' || addressValidate !== 'ที่อยู่ถูกต้อง') {
                let errorMessage = 'ที่อยู่ของคุณยังไม่ได้รับการยืนยัน กรุณาอัปเดตที่อยู่ในหน้าโปรไฟล์และรอการอนุมัติจากแอดมิน';
                if (addressValidate && addressValidate !== 'ยังไม่ยืนยัน' && addressValidate !== 'ที่อยู่ถูกต้อง') {
                    errorMessage += `\nเหตุผล: ${addressValidate}`;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'ที่อยู่ยังไม่ได้รับการยืนยัน',
                    text: errorMessage,
                    showCancelButton: true,
                    confirmButtonText: 'ไปที่หน้าโปรไฟล์',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'user-profile.php';
                    }
                });
                return;
            }

            // Check if payment slip is uploaded
            const paymentSlip = document.getElementById('payment_slip');
            if (!paymentSlip.files.length) {
                Swal.fire({
                    icon: 'error',
                    title: 'กรุณาแนบสลิปโอนเงิน',
                    text: 'คุณต้องแนบรูปภาพสลิปโอนเงินก่อนดำเนินการสั่งซื้อ',
                });
                return;
            }

            // Validate file type (must be an image)
            const file = paymentSlip.files[0];
            if (!file.type.startsWith('image/')) {
                Swal.fire({
                    icon: 'error',
                    title: 'ไฟล์ไม่ถูกต้อง',
                    text: 'กรุณาแนบไฟล์ที่เป็นรูปภาพเท่านั้น',
                });
                return;
            }

            // Show confirmation dialog
            Swal.fire({
                icon: 'question',
                title: 'ยืนยันการสั่งซื้อ',
                text: 'กรุณาตรวจสอบข้อมูลให้ครบถ้วนก่อนยืนยันการสั่งซื้อ',
                showCancelButton: true,
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.querySelector('form').submit(); // Submit the form
                }
            });
        }
    </script>
</body>

</html>