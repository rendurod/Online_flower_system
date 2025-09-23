<?php
session_start();
include('config/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_login'])) {
    header("Location: login.php?return_to=cart-checkout.php");
    exit();
}

$user_id = $_SESSION['user_login'];

// Fetch user data
$user_query = "SELECT FirstName, LastName, EmailId, ContactNo, Address, Validate FROM tbl_members WHERE ID = :id";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
$user_stmt->execute();
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    $_SESSION['error'] = "ไม่พบข้อมูลผู้ใช้";
    header("Location: login.php");
    exit();
}

// Fetch payment details
$payment_query = "SELECT QRCodeImage, AccountName, BankName, BankAccountNumber FROM tbl_payment ORDER BY CreatedAt DESC LIMIT 1";
$payment_stmt = $conn->prepare($payment_query);
$payment_stmt->execute();
$payment_data = $payment_stmt->fetch(PDO::FETCH_ASSOC);

$qrCodeImagePath = !empty($payment_data['QRCodeImage']) && file_exists("admin/uploads/qrcodes/" . $payment_data['QRCodeImage'])
    ? "admin/uploads/qrcodes/" . htmlspecialchars($payment_data['QRCodeImage'])
    : "assets/img/default-qrcode.jpg";
$bankName = $payment_data ? htmlspecialchars($payment_data['BankName']) : 'ชื่อธนาคาร (ไม่พบข้อมูล)';
$accountName = $payment_data ? htmlspecialchars($payment_data['AccountName']) : 'ชื่อบัญชี (ไม่พบข้อมูล)';
$bankAccountNumber = $payment_data ? htmlspecialchars($payment_data['BankAccountNumber']) : 'เลขบัญชี (ไม่พบข้อมูล)';

// Get selected items from cart.php
$selected_items = isset($_GET['selected_items']) && is_array($_GET['selected_items']) ? $_GET['selected_items'] : [];

// Fetch cart items (only selected ones)
$cart_items = [];
$total = 0;
try {
    if (!empty($selected_items)) {
        $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
        $stmt = $conn->prepare("
            SELECT c.flower_id, c.quantity, f.flower_name, f.price, f.image, f.stock_quantity
            FROM tbl_cart c
            JOIN tbl_flowers f ON c.flower_id = f.ID
            WHERE c.user_id = ? AND c.flower_id IN ($placeholders)
        ");
        $params = array_merge([$user_id], $selected_items);
        $stmt->execute($params);
        $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cart_items as &$item) {
            if ($item['quantity'] > $item['stock_quantity']) {
                $_SESSION['error'] = "จำนวนสินค้าเกินสต็อก: " . htmlspecialchars($item['flower_name']);
                header("Location: cart.php");
                exit();
            }
            if ($item['quantity'] > 0) {
                $total += $item['price'] * $item['quantity'];
                $item['image'] = !empty($item['image']) && file_exists("admin/uploads/flowers/" . $item['image'])
                    ? "admin/uploads/flowers/" . htmlspecialchars($item['image'])
                    : "assets/img/default-flower.jpg";
            }
        }
        unset($item);
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการดึงข้อมูลตะกร้า: " . htmlspecialchars($e->getMessage());
    header("Location: cart.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_date = isset($_POST['delivery_date']) ? $_POST['delivery_date'] : null;

    // Validate delivery date
    if (empty($delivery_date)) {
        $_SESSION['error'] = "กรุณาเลือกวันที่จัดส่ง";
        header("Location: cart-checkout.php?selected_items=" . urlencode(implode(',', $selected_items)));
        exit();
    }

    // Validate address
    if (empty($user_data['Address']) || $user_data['Validate'] !== 'ที่อยู่ถูกต้อง') {
        $_SESSION['error'] = "ที่อยู่ของคุณยังไม่ได้รับการยืนยัน กรุณาอัปเดตที่อยู่ในโปรไฟล์และรอการอนุมัติจากแอดมิน";
        header("Location: cart-checkout.php?selected_items=" . urlencode(implode(',', $selected_items)));
        exit();
    }

    // Validate cart items
    if (empty($cart_items)) {
        $_SESSION['error'] = "ตะกร้าสินค้าของคุณว่างเปล่า";
        header("Location: cart-checkout.php?selected_items=" . urlencode(implode(',', $selected_items)));
        exit();
    }

    // Validate stock (already checked in fetch, but re-validate)
    $valid_items = [];
    $total_amount = 0;
    foreach ($cart_items as $item) {
        if ($item['quantity'] <= $item['stock_quantity']) {
            $valid_items[$item['flower_id']] = [
                'quantity' => $item['quantity'],
                'price' => $item['price']
            ];
            $total_amount += $item['price'] * $item['quantity'];
        } else {
            $_SESSION['error'] = "มีสินค้าบางรายการเกินสต็อก: " . htmlspecialchars($item['flower_name']);
            header("Location: cart-checkout.php?selected_items=" . urlencode(implode(',', $selected_items)));
            exit();
        }
    }

    if (empty($valid_items)) {
        $_SESSION['error'] = "ไม่มีรายการที่ถูกต้องในตะกร้า";
        header("Location: cart-checkout.php?selected_items=" . urlencode(implode(',', $selected_items)));
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
            header("Location: cart-checkout.php?selected_items=" . urlencode(implode(',', $selected_items)));
            exit();
        }
        $slip_image = $file_name;
    } else {
        $_SESSION['error'] = "กรุณาอัพโหลดสลิปโอนเงิน";
        header("Location: cart-checkout.php?selected_items=" . urlencode(implode(',', $selected_items)));
        exit();
    }

    // Generate BookingNumber
    $booking_number = rand(1000000000, 9999999999);

    // Begin transaction
    try {
        $conn->beginTransaction();

        $order_query = "INSERT INTO tbl_orders (BookingNumber, UserEmail, DeliveryDate, Image, SumTotal, Status, PostingDate) 
                VALUES (:booking_number, :user_email, :delivery_date, :image, :total_amount, 0, NOW())";
        $order_stmt = $conn->prepare($order_query);
        $order_stmt->bindValue(':booking_number', $booking_number, PDO::PARAM_INT);
        $order_stmt->bindValue(':user_email', $user_data['EmailId'], PDO::PARAM_STR);
        $order_stmt->bindValue(':delivery_date', $delivery_date, PDO::PARAM_STR);
        $order_stmt->bindValue(':image', $slip_image, PDO::PARAM_STR);
        $order_stmt->bindValue(':total_amount', $total_amount, PDO::PARAM_STR);
        $order_stmt->execute();

        $order_id = $conn->lastInsertId();

        // Insert into tbl_order_details
        $detail_query = "INSERT INTO tbl_order_details (OrderId, FlowerId, Quantity, Price) VALUES (:order_id, :flower_id, :quantity, :price)";
        $detail_stmt = $conn->prepare($detail_query);
        foreach ($valid_items as $flower_id => $item) {
            $detail_stmt->bindValue(':order_id', $order_id, PDO::PARAM_INT);
            $detail_stmt->bindValue(':flower_id', $flower_id, PDO::PARAM_INT);
            $detail_stmt->bindValue(':quantity', $item['quantity'], PDO::PARAM_INT);
            $detail_stmt->bindValue(':price', $item['price'], PDO::PARAM_STR);
            $detail_stmt->execute();
        }

        // Remove only selected items from cart
        $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
        $delete_stmt = $conn->prepare("DELETE FROM tbl_cart WHERE user_id = ? AND flower_id IN ($placeholders)");
        $delete_params = array_merge([$user_id], $selected_items);
        $delete_stmt->execute($delete_params);

        // Update stock quantities (ลดสต็อกทันที)
        foreach ($valid_items as $flower_id => $item) {
            $stock_stmt = $conn->prepare("UPDATE tbl_flowers SET stock_quantity = stock_quantity - :quantity WHERE ID = :flower_id");
            $stock_stmt->bindValue(':quantity', $item['quantity'], PDO::PARAM_INT);
            $stock_stmt->bindValue(':flower_id', $flower_id, PDO::PARAM_INT);
            $stock_stmt->execute();
        }

        $conn->commit();
        $_SESSION['success'] = "สั่งซื้อสำเร็จ! สต็อกสินค้าถูกลดลงแล้ว รอการยืนยันจากแอดมิน";
        header("Location: cart-finish.php?order_id=$order_id");
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการสั่งซื้อ: " . htmlspecialchars($e->getMessage());
        header("Location: cart-checkout.php?selected_items=" . urlencode(implode(',', $selected_items)));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบคำสั่งซื้อ - FlowerShop</title>
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- Checkout Section -->
    <section class="order-form-section">
        <div class="container">
            <div class="step-container">
                <div class="step">
                    <div class="circle">1</div>
                    <div class="label">สินค้าในตะกร้า</div>
                </div>
                <div class="step active">
                    <div class="line"></div>
                    <div class="circle">2</div>
                    <div class="label active-label">ตรวจสอบการซื้อ</div>
                </div>
                <div class="step">
                    <div class="line"></div>
                    <div class="circle">3</div>
                    <div class="label">คำสั่งซื้อสำเร็จ</div>
                </div>
            </div>
            <div class="order-form-container">
                <!-- Left: Form Section -->
                <form method="POST" enctype="multipart/form-data" id="checkout-form">
                    <div class="order-form-left">
                        <!-- 1. ข้อมูลผู้รับ -->
                        <div class="order-form-group">
                            <h3>1. ข้อมูลผู้รับ</h3>
                            <div class="data-display-box">
                                ชื่อ: <?php echo htmlspecialchars($user_data['FirstName'] . ' ' . $user_data['LastName']); ?><br>
                                Email: <?php echo htmlspecialchars($user_data['EmailId']); ?><br>
                                โทร: <?php echo htmlspecialchars($user_data['ContactNo']); ?>
                            </div>
                        </div>

                        <!-- 2. ข้อมูลจัดส่ง -->
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
                    </div>
                </form>

                <!-- Right: Product Summary -->
                <div class="order-form-right">
                    <h3>สรุปคำสั่งซื้อ</h3>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="product-summary">
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['flower_name']); ?>">
                            <div>
                                <h4><?php echo htmlspecialchars($item['flower_name']); ?></h4>
                                <p>จำนวน: <?php echo $item['quantity']; ?> ชิ้น</p>
                                <p>ราคา: ฿<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="product-summary-item">
                        <h4>มูลค่าสินค้าทั้งหมด</h4>
                        <p id="total_item_price">฿<?php echo number_format($total, 2); ?></p>
                    </div>
                    <div class="total-price">
                        ราคาทั้งหมด: <span id="total_price">฿<?php echo number_format($total, 2); ?></span>
                    </div>
                    <button type="button" class="proceed-btn" onclick="validateCheckout()">ดำเนินการสั่งซื้อ</button>
                </div>
            </div>
        </div>
    </section>

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends -->

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        // Ensure validateCheckout is globally accessible
        function validateCheckout() {
            const deliveryDate = document.getElementById('delivery_date').value;
            if (!deliveryDate) {
                Swal.fire({
                    icon: 'error',
                    title: 'กรุณาเลือกวันที่จัดส่ง',
                    text: 'คุณต้องเลือกวันที่จัดส่งก่อนดำเนินการสั่งซื้อ',
                });
                return;
            }

            const userAddress = "<?php echo addslashes($user_data['Address']); ?>";
            const addressValidate = "<?php echo addslashes($user_data['Validate']); ?>";
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
                    document.getElementById('checkout-form').submit();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const paymentSlipInput = document.getElementById('payment_slip');
            if (paymentSlipInput) {
                paymentSlipInput.addEventListener('change', function(e) {
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
            } else {
                console.error('Payment slip input not found');
            }

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

            window.copyText = copyText;

            <?php if (isset($_SESSION['error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
                    confirmButtonText: 'ตกลง'
                });
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            const proceedBtn = document.querySelector('.proceed-btn');
            if (proceedBtn) {
                // console.log('Proceed button found');
            } else {
                console.error('Proceed button not found');
            }
        });
    </script>
</body>

</html>