<?php
session_start();
include('config/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_login'])) {
    header("Location: login.php?return_to=cart.php");
    exit();
}

$user_id = $_SESSION['user_login'];

// Fetch cart items from tbl_cart
$cart_items = [];
$total = 0;

try {
    $stmt = $conn->prepare("
        SELECT c.flower_id, c.quantity, f.flower_name, f.price, f.image, f.stock_quantity
        FROM tbl_cart c
        JOIN tbl_flowers f ON c.flower_id = f.ID
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cart_items as &$item) {
        if ($item['quantity'] > $item['stock_quantity']) {
            $item['quantity'] = $item['stock_quantity'];
            $update_stmt = $conn->prepare("UPDATE tbl_cart SET quantity = ? WHERE user_id = ? AND flower_id = ?");
            $update_stmt->execute([$item['quantity'], $user_id, $item['flower_id']]);
        }
        if ($item['quantity'] > 0) {
            $total += $item['price'] * $item['quantity'];
            $item['image'] = !empty($item['image']) && file_exists("admin/uploads/flowers/" . $item['image'])
                ? "admin/uploads/flowers/" . htmlspecialchars($item['image'])
                : "assets/img/default-flower.jpg";
        } else {
            // Remove items with zero quantity
            $delete_stmt = $conn->prepare("DELETE FROM tbl_cart WHERE user_id = ? AND flower_id = ?");
            $delete_stmt->execute([$user_id, $item['flower_id']]);
        }
    }
    unset($item); // Unset reference to avoid issues
} catch (PDOException $e) {
    $message = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . htmlspecialchars($e->getMessage());
    $messageType = "danger";
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตะกร้าสินค้า - FlowerShop</title>
    <link rel="icon" href="assets/img/LOGO_FlowerShopp.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/productOrder.css">
    <style>
        .cart-section {
            padding: 4rem 0;
            background-color: #fcf5f7;
        }
        .cart-item, .order-summary {
            background: #fff;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            margin-bottom: 2rem;
        }
        .cart-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            color: var(--text-light);
            font-size: 1.4rem;
        }
        .cart-item-row {
            padding: 1.5rem 0;
            border-bottom: 1px solid #eee;
        }
        .cart-item-row:last-child {
            border-bottom: none;
        }
        .product-info-container {
            display: flex;
            align-items: center;
        }
        .product-info-container img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            margin-right: 1.5rem;
        }
        .product-details h5 {
            font-size: 1.7rem;
            color: var(--text-dark);
            margin: 0;
        }
        .product-details .price {
            font-size: 1.5rem;
            color: var(--text-light);
        }
        .product-details .stock {
            font-size: 1.4rem;
            color: #e84393;
        }
        .remove-btn {
            color: #e74c3c;
            font-size: 1.8rem;
            background: none;
            border: none;
            cursor: pointer;
            transition: color 0.3s;
        }
        .remove-btn:hover {
            color: #c0392b;
        }
        .order-summary h3 {
            font-size: 2.2rem;
            color: var(--text-dark);
            border-bottom: 1px solid #eee;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 1.6rem;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
        }
        .summary-row.total {
            font-size: 1.8rem;
            font-weight: 600;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        .checkout-btn {
            display: block;
            width: 100%;
            padding: 1.2rem;
            font-size: 1.7rem;
            font-weight: 600;
            background: var(--primary-pink);
            color: #fff;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: var(--transition);
        }
        .checkout-btn:hover {
            background: var(--dark-pink);
            transform: translateY(-2px);
        }
        .empty-cart {
            text-align: center;
            padding: 5rem 2rem;
            background: #fff;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }
        .empty-cart i {
            font-size: 6rem;
            color: #e84393;
            margin-bottom: 2rem;
        }
        .empty-cart h3 {
            font-size: 2.4rem;
            color: var(--text-dark);
        }
        .empty-cart p {
            font-size: 1.6rem;
            color: var(--text-light);
            margin-bottom: 2.5rem;
        }
        .form-check-input {
            width: 1.7em;
            height: 1.7em;
            cursor: pointer;
            border: 2px solid #ddd;
        }
        .form-check-input:checked {
            background-color: var(--primary-pink);
            border-color: var(--primary-pink);
        }
        .form-check-input:focus {
            border-color: var(--primary-pink);
            box-shadow: 0 0 0 0.25rem rgba(232, 67, 147, 0.25);
        }
        .quantity-control {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }
        .quantity-control button {
            background: #f8f9fa;
            border: none;
            width: 30px;
            height: 30px;
            font-size: 1.2rem;
            color: #333;
            cursor: pointer;
            transition: background 0.3s;
        }
        .quantity-control button:hover {
            background: #e0e0e0;
        }
        .quantity-control input {
            width: 40px;
            text-align: center;
            border: none;
            font-size: 1.4rem;
            color: var(--text-dark);
        }
        .quantity-control input:focus {
            outline: none;
        }
    </style>
</head>

<body>
    <!-- header section starts -->
    <?php include("includes/navbar.php"); ?>
    <!-- header section ends -->

    <!-- Hero Section -->
    <section>
        <div class="container">
            <div class="text-center pt-5">
                <h1 class="heading mb-3">ตะกร้า<span>สินค้า</span></h1>
                <p style="font-size: 1.8rem; color: var(--text-light); max-width: 600px; margin: 0 auto;">
                    ตรวจสอบและจัดการรายการสินค้าในตะกร้าของคุณ
                </p>
            </div>
        </div>
    </section>
    <!-- Hero Section Ends-->

    <!-- Cart Section -->
    <section class="cart-section">
        <div class="container">
            <?php if (!empty($cart_items)): ?>
                <form id="cart-form" action="cart-checkout.php" method="GET">
                    <div class="row">
                        <!-- Cart Items -->
                        <div class="col-lg-8">
                            <div class="cart-item">
                                <div class="d-none d-md-flex row cart-header align-items-center">
                                    <div class="col-md-1">
                                        <input class="form-check-input" type="checkbox" id="checkAll" checked>
                                    </div>
                                    <div class="col-md-5">สินค้า</div>
                                    <div class="col-md-2 text-center">ราคา</div>
                                    <div class="col-md-2 text-center">จำนวน</div>
                                    <div class="col-md-2 text-end">รวม</div>
                                </div>

                                <?php foreach ($cart_items as $item): ?>
                                    <div class="row cart-item-row align-items-center" data-id="<?php echo htmlspecialchars($item['flower_id']); ?>">
                                        <div class="col-12 col-md-6 d-flex align-items-center product-info-container">
                                            <input class="form-check-input item-check me-3" type="checkbox" name="selected_items[]" value="<?php echo htmlspecialchars($item['flower_id']); ?>" data-price="<?php echo $item['price']; ?>" data-quantity="<?php echo $item['quantity']; ?>" checked>
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['flower_name']); ?>">
                                            <div class="product-details">
                                                <h5><?php echo htmlspecialchars($item['flower_name']); ?></h5>
                                                <p class="price">฿<?php echo number_format($item['price'], 2); ?></p>
                                                <p class="stock">มีในสต็อก: <?php echo $item['stock_quantity']; ?> ชิ้น</p>
                                            </div>
                                        </div>
                                        <div class="col-md-2 d-none d-md-block text-center price align-self-center">฿<?php echo number_format($item['price'], 2); ?></div>
                                        <div class="col-6 col-md-2 text-center align-self-center mt-3 mt-md-0">
                                            <div class="quantity-control">
                                                <button type="button" class="minus-btn" data-id="<?php echo $item['flower_id']; ?>">-</button>
                                                <input type="number" class="quantity-input" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock_quantity']; ?>" data-id="<?php echo $item['flower_id']; ?>" readonly>
                                                <button type="button" class="plus-btn" data-id="<?php echo $item['flower_id']; ?>">+</button>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-2 text-end d-flex align-items-center justify-content-end align-self-center mt-3 mt-md-0">
                                            <span class="price me-3">฿<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                            <button type="button" class="remove-btn" data-id="<?php echo htmlspecialchars($item['flower_id']); ?>"><i class="fas fa-trash-alt"></i></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Order Summary -->
                        <div class="col-lg-4">
                            <div class="order-summary">
                                <h3>สรุปคำสั่งซื้อ</h3>
                                <div class="summary-row">
                                    <span>ราคารวม</span>
                                    <span id="summary-subtotal">฿<?php echo number_format($total, 2); ?></span>
                                </div>
                                <div class="summary-row total">
                                    <span>ยอดสุทธิ</span>
                                    <span id="summary-total">฿<?php echo number_format($total, 2); ?></span>
                                </div>
                                <button type="submit" class="checkout-btn mt-4" id="checkout-btn" <?php echo empty($cart_items) ? 'disabled' : ''; ?>>
                                    ดำเนินการชำระเงิน<i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-basket"></i>
                    <h3>ตะกร้าสินค้าของคุณว่างเปล่า</h3>
                    <p>ดูเหมือนว่าคุณยังไม่ได้เพิ่มสินค้าใดๆ ลงในตะกร้า</p>
                    <a href="products.php" class="btn">เลือกซื้อสินค้า</a>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <!-- Cart Section Ends -->

    <!-- footer -->
    <?php include("includes/footer.php"); ?>
    <!-- footer ends-->

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkAll = document.getElementById('checkAll');
            const itemCheckboxes = document.querySelectorAll('.item-check');
            const subtotalEl = document.getElementById('summary-subtotal');
            const totalEl = document.getElementById('summary-total');
            const checkoutBtn = document.getElementById('checkout-btn');
            const cartForm = document.getElementById('cart-form');

            function updateCartSummary() {
                let currentSubtotal = 0;
                itemCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        const price = parseFloat(checkbox.dataset.price);
                        const quantity = parseInt(checkbox.dataset.quantity);
                        currentSubtotal += price * quantity;
                    }
                });

                subtotalEl.textContent = `฿${currentSubtotal.toFixed(2)}`;
                totalEl.textContent = `฿${currentSubtotal.toFixed(2)}`;
                checkoutBtn.disabled = currentSubtotal <= 0;
            }

            checkAll.addEventListener('change', function () {
                itemCheckboxes.forEach(checkbox => {
                    checkbox.checked = checkAll.checked;
                });
                updateCartSummary();
            });

            itemCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    let allChecked = true;
                    itemCheckboxes.forEach(cb => {
                        if (!cb.checked) allChecked = false;
                    });
                    checkAll.checked = allChecked;
                    updateCartSummary();
                });
            });

            document.querySelectorAll('.quantity-control').forEach(control => {
                const minusBtn = control.querySelector('.minus-btn');
                const plusBtn = control.querySelector('.plus-btn');
                const input = control.querySelector('.quantity-input');
                const flowerId = input.dataset.id;

                minusBtn.addEventListener('click', function () {
                    let quantity = parseInt(input.value);
                    if (quantity > 1) {
                        quantity--;
                        updateQuantity(flowerId, quantity, input);
                    }
                });

                plusBtn.addEventListener('click', function () {
                    let quantity = parseInt(input.value);
                    if (quantity < parseInt(input.max)) {
                        quantity++;
                        updateQuantity(flowerId, quantity, input);
                    }
                });
            });

            function updateQuantity(flowerId, quantity, input) {
                fetch('update_cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `flower_id=${flowerId}&quantity=${quantity}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        input.value = quantity;
                        const row = document.querySelector(`.cart-item-row[data-id="${flowerId}"]`);
                        const checkbox = row.querySelector('.item-check');
                        checkbox.dataset.quantity = quantity;
                        row.querySelector('.price.me-3').textContent = `฿${(data.price * quantity).toFixed(2)}`;
                        const cartCounter = document.querySelector('.cart-counter');
                        if (cartCounter) cartCounter.innerText = data.cartCount;
                        updateCartSummary();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: data.message
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์'
                    });
                });
            }

            document.querySelectorAll('.remove-btn').forEach(button => {
                button.addEventListener('click', function (event) {
                    event.preventDefault(); // Prevent form submission
                    const flowerId = this.dataset.id;
                    Swal.fire({
                        icon: 'warning',
                        title: 'ยืนยันการลบ',
                        text: 'คุณต้องการลบสินค้านี้ออกจากตะกร้า?',
                        showCancelButton: true,
                        confirmButtonText: 'ลบ',
                        cancelButtonText: 'ยกเลิก'
                    }).then(result => {
                        if (result.isConfirmed) {
                            fetch('remove_from_cart.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `flower_id=${flowerId}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    const cartCounter = document.querySelector('.cart-counter');
                                    if (cartCounter) cartCounter.innerText = data.cartCount;
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'ลบสำเร็จ',
                                        text: 'สินค้าถูกลบออกจากตะกร้าแล้ว',
                                        showConfirmButton: false,
                                        timer: 1500
                                    }).then(() => {
                                        window.location.reload(); // Refresh page after successful removal
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'เกิดข้อผิดพลาด',
                                        text: data.message
                                    });
                                }
                            })
                            .catch(error => {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'เกิดข้อผิดพลาด',
                                    text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์'
                                });
                            });
                        }
                    });
                });
            });

            // Validate before form submission
            cartForm.addEventListener('submit', function (e) {
                const checkedItems = document.querySelectorAll('.item-check:checked');
                if (checkedItems.length === 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'ไม่มีสินค้าที่เลือก',
                        text: 'กรุณาเลือกอย่างน้อยหนึ่งรายการเพื่อดำเนินการชำระเงิน',
                    });
                }
            });

            updateCartSummary();
        });
    </script>
</body>
</html>