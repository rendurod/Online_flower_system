<?php
session_start();
include('config/db.php');

// --- MOCK DATA FOR DEMONSTRATION ---
// ในการใช้งานจริง คุณจะต้องดึงข้อมูลตะกร้าสินค้าจาก session หรือ database
// This is placeholder data. In a real application, you would fetch cart data from the session or database.
$cart_items = [
    [
        'id' => 1,
        'name' => 'ช่อดอกไม้ The Princess',
        'price' => 1250.00,
        'quantity' => 1,
        'image' => 'https://placehold.co/100x100/ffe6ec/e84393?text=Flower1' // Placeholder image
    ],
    [
        'id' => 2,
        'name' => 'ดอกไม้ในกล่อง Love Letter',
        'price' => 1800.00,
        'quantity' => 2,
        'image' => 'https://placehold.co/100x100/ffe6ec/e84393?text=Flower2' // Placeholder image
    ],
    [
        'id' => 3,
        'name' => 'แจกันดอกไม้ Bright Day',
        'price' => 990.00,
        'quantity' => 1,
        'image' => 'https://placehold.co/100x100/ffe6ec/e84393?text=Flower3' // Placeholder image
    ]
];
// --- END OF MOCK DATA ---

// Calculate totals from cart items
$subtotal = 0;
// In a real scenario, this calculation would be done via JavaScript based on checked items
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping_cost = 50.00; // Example shipping cost
$total = $subtotal + $shipping_cost;

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
    <style>
        .cart-section {
            padding: 4rem 0;
            background-color: #fcf5f7; /* Light pink background */
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

        .quantity-selector {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .quantity-selector button {
            background: #f5f5f5;
            border: none;
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-size: 1.8rem;
        }

        .quantity-selector input {
            width: 50px;
            text-align: center;
            border: none;
            font-size: 1.6rem;
            -moz-appearance: textfield;
        }
        
        .quantity-selector input::-webkit-outer-spin-button,
        .quantity-selector input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
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

        .continue-shopping-btn {
            display: inline-block;
            margin-top: 2rem;
            padding: 1rem 3rem;
            font-size: 1.6rem;
            color: #fff;
            background: var(--text-dark);
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .continue-shopping-btn:hover {
            background: #555;
        }

        /* Custom Checkbox Styles */
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
                    ตรวจสอบรายการสินค้าและดำเนินการสั่งซื้อ
                </p>
            </div>
        </div>
    </section>
    <!-- Hero Section Ends-->

    <!-- Cart Section -->
    <section class="cart-section">
        <div class="container">
            <?php if (!empty($cart_items)): ?>
                <div class="row">
                    <!-- Cart Items -->
                    <div class="col-lg-8">
                        <div class="cart-item">
                            <div class="d-none d-md-flex row cart-header align-items-center">
                                <div class="col-md-1">
                                    <input class="form-check-input" type="checkbox" id="checkAll">
                                </div>
                                <div class="col-md-4">สินค้า</div>
                                <div class="col-md-2 text-center">ราคา</div>
                                <div class="col-md-3 text-center">จำนวน</div>
                                <div class="col-md-2 text-end">รวม</div>
                            </div>

                            <?php foreach ($cart_items as $item): ?>
                                <div class="row cart-item-row align-items-center">
                                    <!-- Checkbox + Product Info -->
                                    <div class="col-12 col-md-5 d-flex align-items-center product-info-container">
                                        <input class="form-check-input item-check me-3" type="checkbox" data-price="<?php echo $item['price']; ?>" data-quantity="<?php echo $item['quantity']; ?>">
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <div class="product-details">
                                            <h5><?php echo htmlspecialchars($item['name']); ?></h5>
                                        </div>
                                    </div>
                                    <!-- Price (Desktop) -->
                                    <div class="col-md-2 d-none d-md-block text-center price align-self-center">฿<?php echo number_format($item['price'], 2); ?></div>
                                    <!-- Quantity -->
                                    <div class="col-6 col-md-3 text-center align-self-center mt-3 mt-md-0">
                                        <div class="quantity-selector d-inline-flex">
                                            <button onclick="/* Add JS to decrease quantity */">-</button>
                                            <input type="number" value="<?php echo $item['quantity']; ?>" min="1" class="item-quantity">
                                            <button onclick="/* Add JS to increase quantity */">+</button>
                                        </div>
                                    </div>
                                    <!-- Total & Remove -->
                                    <div class="col-6 col-md-2 text-end d-flex align-items-center justify-content-end align-self-center mt-3 mt-md-0">
                                        <span class="price me-3">฿<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                        <button class="remove-btn" onclick="/* Add JS to remove item */"><i class="fas fa-trash-alt"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                             <a href="products.php" class="continue-shopping-btn mt-4"><i class="fas fa-arrow-left me-2"></i>เลือกซื้อสินค้าต่อ</a>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="col-lg-4">
                        <div class="order-summary">
                            <h3>สรุปคำสั่งซื้อ</h3>
                            <div class="summary-row">
                                <span>ราคารวม</span>
                                <span id="summary-subtotal">฿0.00</span>
                            </div>
                            <div class="summary-row">
                                <span>ค่าจัดส่ง</span>
                                <span id="summary-shipping">฿<?php echo number_format($shipping_cost, 2); ?></span>
                            </div>
                            <div class="summary-row total">
                                <span>ยอดสุทธิ</span>
                                <span id="summary-total">฿0.00</span>
                            </div>
                            <a href="checkout.php" class="checkout-btn mt-4">
                                ดำเนินการชำระเงิน<i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Empty Cart Display -->
                <div class="empty-cart">
                    <i class="fas fa-shopping-basket"></i>
                    <h3>ตะกร้าสินค้าของคุณว่างเปล่า</h3>
                    <p>ดูเหมือนว่าคุณยังไม่ได้เพิ่มสินค้าใดๆ ลงในตะกร้า</p>
                    <a href="products.php" class="btn">กลับไปเลือกสินค้า</a>
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
        const shippingEl = document.getElementById('summary-shipping');
        const totalEl = document.getElementById('summary-total');
        
        const shippingCost = parseFloat(<?php echo $shipping_cost; ?>);

        function updateCartSummary() {
            let currentSubtotal = 0;
            itemCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    const price = parseFloat(checkbox.dataset.price);
                    const quantity = parseInt(checkbox.closest('.cart-item-row').querySelector('.item-quantity').value);
                    currentSubtotal += price * quantity;
                }
            });

            const currentTotal = currentSubtotal + (currentSubtotal > 0 ? shippingCost : 0);
            
            subtotalEl.textContent = `฿${currentSubtotal.toFixed(2)}`;
            totalEl.textContent = `฿${currentTotal.toFixed(2)}`;
            if(currentSubtotal <= 0){
                shippingEl.textContent = '฿0.00';
            } else {
                shippingEl.textContent = `฿${shippingCost.toFixed(2)}`;
            }
        }

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                itemCheckboxes.forEach(checkbox => {
                    checkbox.checked = checkAll.checked;
                });
                updateCartSummary();
            });
        }

        itemCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function () {
                let allChecked = true;
                itemCheckboxes.forEach(cb => {
                    if (!cb.checked) {
                        allChecked = false;
                    }
                });
                if(checkAll) {
                   checkAll.checked = allChecked;
                }
                updateCartSummary();
            });
        });

        // Initial calculation on page load
        updateCartSummary();
    });
    </script>
</body>

</html>

