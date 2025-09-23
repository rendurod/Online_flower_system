<?php
session_start();
include('config/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_login'])) {
    header("Location: login.php?return_to=product-order.php");
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
        $_SESSION['error'] = "ไม่พบสินค้าที่เลือก";
        header("Location: products.php");
        exit();
    }
} else {
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

// Fallback data if user is not found
if (!$user_data) {
    $_SESSION['error'] = "ไม่พบข้อมูลผู้ใช้";
    header("Location: login.php");
    exit();
}

// Fetch payment details from tbl_payment
$payment_query = "SELECT QRCodeImage, AccountName, BankName, BankAccountNumber FROM tbl_payment ORDER BY CreatedAt DESC LIMIT 1";
$payment_stmt = $conn->prepare($payment_query);
$payment_stmt->execute();
$payment_data = $payment_stmt->fetch(PDO::FETCH_ASSOC);

// Check if QR Code image exists, otherwise use default
$qrCodeImagePath = !empty($payment_data['QRCodeImage']) && file_exists("admin/uploads/qrcodes/" . $payment_data['QRCodeImage'])
    ? "admin/uploads/qrcodes/" . htmlspecialchars($payment_data['QRCodeImage'])
    : "assets/img/default-qrcode.jpg";
$bankName = $payment_data ? htmlspecialchars($payment_data['BankName']) : 'ชื่อธนาคาร (ไม่พบข้อมูล)';
$accountName = $payment_data ? htmlspecialchars($payment_data['AccountName']) : 'ชื่อบัญชี (ไม่พบข้อมูล)';
$bankAccountNumber = $payment_data ? htmlspecialchars($payment_data['BankAccountNumber']) : 'เลขบัญชี (ไม่พบข้อมูล)';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flower_id = $flower['ID'];
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $user_email = $user_data['EmailId'];
    $delivery_date = isset($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
    $total_amount = $flower['price'] * $quantity;

    // Ensure quantity is at least 1
    if ($quantity < 1) {
        $quantity = 1;
        $total_amount = $flower['price'];
    }

    // Validate quantity against stock
    if ($quantity > $flower['stock_quantity']) {
        $_SESSION['error'] = "จำนวนสินค้าที่เลือกเกินกว่าที่มีอยู่ในสต็อก";
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
        $upload_dir = 'Uploads/slips/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_name = uniqid() . '-' . basename($_FILES['payment_slip']['name']);
        $file_path = $upload_dir . $file_name;

        if (!move_uploaded_file($_FILES['payment_slip']['tmp_name'], $file_path)) {
            $_SESSION['error'] = "ไม่สามารถอัพโหลดสลิปโอนเงินได้";
            header("Location: product-order.php?id=" . $flower_id);
            exit();
        }
        $slip_image = $file_name;
    } else {
        $_SESSION['error'] = "กรุณาอัพโหลดสลิปโอนเงิน";
        header("Location: product-order.php?id=" . $flower_id);
        exit();
    }

    // Generate BookingNumber
    $booking_number = rand(1000000000, 9999999999);

    // Begin transaction
    try {
        $conn->beginTransaction();

        // Insert order into tbl_orders
        $order_query = "INSERT INTO tbl_orders (BookingNumber, UserEmail, DeliveryDate, Image, SumTotal, Status, PostingDate) 
                        VALUES (:booking_number, :user_email, :delivery_date, :image, :total_amount, 0, NOW())";
        $order_stmt = $conn->prepare($order_query);
        $order_stmt->bindValue(':booking_number', $booking_number, PDO::PARAM_INT);
        $order_stmt->bindValue(':user_email', $user_email, PDO::PARAM_STR);
        $order_stmt->bindValue(':delivery_date', $delivery_date, PDO::PARAM_STR);
        $order_stmt->bindValue(':image', $slip_image, PDO::PARAM_STR);
        $order_stmt->bindValue(':total_amount', $total_amount, PDO::PARAM_STR);
        $order_stmt->execute();

        $order_id = $conn->lastInsertId();

        // Insert into tbl_order_details
        $detail_query = "INSERT INTO tbl_order_details (OrderId, FlowerId, Quantity, Price) 
                         VALUES (:order_id, :flower_id, :quantity, :price)";
        $detail_stmt = $conn->prepare($detail_query);
        $detail_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
        $detail_stmt->bindValue(':flower_id', $flower_id, PDO::PARAM_INT);
        $detail_stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $detail_stmt->bindValue(':price', $flower['price'], PDO::PARAM_STR);
        $detail_stmt->execute();

        // Update stock quantity
        $stock_stmt = $conn->prepare("UPDATE tbl_flowers SET stock_quantity = stock_quantity - :quantity WHERE ID = :flower_id");
        $stock_stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $stock_stmt->bindValue(':flower_id', $flower_id, PDO::PARAM_INT);
        $stock_stmt->execute();

        $conn->commit();
        $_SESSION['success'] = "สั่งซื้อสำเร็จ! สต็อกสินค้าถูกลดลงแล้ว รอการยืนยันจากแอดมิน";
        header("Location: product-finish.php?order_id=$order_id");
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการสั่งซื้อ: " . htmlspecialchars($e->getMessage());
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
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/productPHP.css">
    <link rel="stylesheet" href="assets/css/productDetail.css">
    <link rel="stylesheet" href="assets/css/productOrder.css">
    <style>
        .copy-btn {
            background-color: #4CAF50;
            color: #ffffff;
            border: 1px solid #4CAF50;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .copy-btn:hover {
            background-color: #45a049;
            border-color: #45a049;
        }

        .copy-btn:active {
            background-color: #3d8b40;
            border-color: #3d8b40;
        }
    </style>
</head>

<body>
    <?php include("includes/navbar.php"); ?>

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
            <form method="POST" enctype="multipart/form-data" id="order-form">
                <div class="order-form-left">
                    <div class="order-form-group">
                        <h3>1. ข้อมูลผู้รับ</h3>
                        <div class="data-display-box">
                            ชื่อ: <?php echo htmlspecialchars($user_data['FirstName'] . ' ' . $user_data['LastName']); ?><br>
                            Email: <?php echo htmlspecialchars($user_data['EmailId']); ?><br>
                            โทร: <?php echo htmlspecialchars($user_data['ContactNo']); ?>
                        </div>
                    </div>

                    <div class="order-form-group">
                        <h3>2. ข้อมูลจัดส่ง</h3>
                        <div class="data-display-box">
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

                    <div class="order-form-group">
                        <h3>3. เลือกวันที่จัดส่ง</h3>
                        <div class="delivery-date-section">
                            <input type="date" name="delivery_date" id="delivery_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        </div>
                    </div>

                    <div class="order-form-group">
                        <h3>4. ชำระเงิน</h3>
                        <div class="payment-section">
                            <img src="<?php echo $qrCodeImagePath; ?>" alt="QR Code Payment">
                            <p><strong>ชื่อธนาคาร:</strong> <?php echo $bankName; ?></p>
                            <p><strong>เลขที่บัญชี:</strong> <?php echo $bankAccountNumber; ?></p>
                            <p><strong>ชื่อบัญชี:</strong> <?php echo $accountName; ?></p>
                            <button type="button" class="copy-btn" onclick="copyText('<?php echo $bankAccountNumber; ?>')">คัดลอกเลขที่บัญชี</button>
                            <div class="upload-slip">
                                <label for="payment_slip" class="upload-btn">
                                    <i class="fas fa-upload"></i> อัพโหลดสลิปโอนเงิน
                                </label>
                                <input type="file" id="payment_slip" name="payment_slip" accept="image/*" style="display: none;" required>
                                <div class="slip-preview-container">
                                    <img id="slip_preview" class="slip-preview" src="#" alt="Slip Preview" style="display: none;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="quantity" id="quantity_hidden" value="1">
                </div>
            </form>

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
                <div class="total-price">
                    ราคาทั้งหมด: <span id="total_price">฿<?php echo number_format($flower['price'], 2); ?></span>
                </div>
                <button type="button" class="proceed-btn" onclick="validateOrder()">ดำเนินการสั่งซื้อ</button>
            </div>
        </div>
    </section>

    <?php include("includes/footer.php"); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        const pricePerItem = <?php echo $flower['price']; ?>;
        const maxQuantity = <?php echo $flower['stock_quantity']; ?>;
        const userAddress = "<?php echo addslashes($user_data['Address']); ?>";
        const addressValidate = "<?php echo addslashes($user_data['Validate']); ?>";

        document.getElementById('quantity_hidden').value = 1;
        document.getElementById('selected_quantity').textContent = 1;
        document.getElementById('total_item_price').textContent = `฿${pricePerItem.toFixed(2)}`;
        document.getElementById('total_price').textContent = `฿${pricePerItem.toFixed(2)}`;

        document.getElementById('quantity').addEventListener('input', function() {
            let quantity = parseInt(this.value) || 1;
            if (quantity < 1) quantity = 1;
            if (quantity > maxQuantity) quantity = maxQuantity;
            this.value = quantity;

            const total = pricePerItem * quantity;
            document.getElementById('selected_quantity').textContent = quantity;
            document.getElementById('total_item_price').textContent = `฿${total.toFixed(2)}`;
            document.getElementById('total_price').textContent = `฿${total.toFixed(2)}`;
            document.getElementById('quantity_hidden').value = quantity;
        });

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

        function copyText(text) {
            navigator.clipboard.writeText(text).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'คัดลอกสำเร็จ',
                    text: 'ข้อมูลถูกคัดลอกไปยังคลิปบอร์ดแล้ว!',
                    timer: 1500,
                    showConfirmButton: false
                });
            }).catch(err => {
                console.error('Copy text failed:', err);
            });
        }

        function validateOrder() {
            const quantity = parseInt(document.getElementById('quantity').value);
            if (quantity > maxQuantity) {
                Swal.fire({
                    icon: 'error',
                    title: 'จำนวนสินค้าเกินสต๊อก',
                    text: `สินค้ามีในสต๊อกเพียง ${maxQuantity} ชิ้น กรุณาเลือกจำนวนใหม่`,
                });
                return;
            }

            const deliveryDate = document.getElementById('delivery_date').value;
            if (!deliveryDate) {
                Swal.fire({
                    icon: 'error',
                    title: 'กรุณาเลือกวันที่จัดส่ง',
                    text: 'คุณต้องเลือกวันที่จัดส่งก่อนดำเนินการสั่งซื้อ',
                });
                return;
            }

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

            const paymentSlip = document.getElementById('payment_slip');
            if (!paymentSlip.files.length) {
                Swal.fire({
                    icon: 'error',
                    title: 'กรุณาแนบสลิปโอนเงิน',
                    text: 'ต้องแนบรูปภาพสลิปโอนเงินก่อนดำเนินการสั่งซื้อ',
                });
                return;
            }

            const file = paymentSlip.files[0];
            if (!file.type.startsWith('image/')) {
                Swal.fire({
                    icon: 'error',
                    title: 'ไฟล์ไม่ถูกต้อง',
                    text: 'กรุณาแนบไฟล์ที่เป็นรูปภาพเท่านั้น',
                });
                return;
            }

            Swal.fire({
                icon: 'question',
                title: 'ยืนยันการสั่งซื้อ',
                text: 'กรุณาตรวจสอบข้อมูลให้ครบถ้วนก่อนยืนยันการสั่งซื้อ สต็อกจะถูกลดลงทันที',
                showCancelButton: true,
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('order-form').submit();
                }
            });
        }

        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
                confirmButtonText: 'ตกลง'
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'สั่งซื้อสำเร็จ',
                text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
                confirmButtonText: 'ตกลง'
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
    </script>
</body>

</html>