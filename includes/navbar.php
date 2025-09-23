<?php
// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once 'config/db.php'; // Ensure database connection is included

// Fetch cart count for logged-in user
$cart_count = 0;
if (isset($_SESSION['user_login'])) {
    try {
        $cart_user_id = $_SESSION['user_login'];
        $cart_stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_cart WHERE user_id = ?");
        $cart_stmt->execute([$cart_user_id]);
        $cart_result = $cart_stmt->fetch(PDO::FETCH_ASSOC);
        $cart_count = $cart_result['total'] ?? 0;
        $cart_stmt = null; // ปิด statement
    } catch (PDOException $e) {
        // Log error if needed, but don't disrupt the page
        $cart_count = 0;
    }
}
?>

<div class="top-bar">
    <span class="top-bar-text">สินค้าทุกแบบส่งฟรี (จัดส่งแค่เฉพาะ อำเภอเมืองนครราชสีมา)</span>
</div>

<header class="modern-navbar">
    <input type="checkbox" name="" id="toggler">
    <label for="toggler" class="fas fa-bars navbar-toggler-custom"></label>

    <!-- Logo -->
    <a href="index.php" class="logo">flowerShop<span>.</span>
        <div class="logo-flower">🌸</div>
    </a>

    <!-- Navigation Menu -->
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    ?>

    <nav class="navbar">
        <a href="about-us.php" class="nav-link <?php echo ($currentPage == 'about-us.php') ? 'active' : ''; ?>">
            <i class="fas fa-info-circle nav-icon"></i>
            <span>About Us</span>
            <div class="nav-underline"></div>
        </a>
        <a href="products.php" class="nav-link <?php echo ($currentPage == 'products.php') ? 'active' : ''; ?>">
            <i class="fas fa-leaf nav-icon"></i>
            <span>Products</span>
            <div class="nav-underline"></div>
        </a>
        <a href="tracking.php" class="nav-link <?php echo ($currentPage == 'tracking.php') ? 'active' : ''; ?>">
            <i class="fas fa-solid fa-dolly nav-icon"></i>
            <span>Tracking ID</span>
            <div class="nav-underline"></div>
        </a>
        <a href="contact.php" class="nav-link <?php echo ($currentPage == 'contact.php') ? 'active' : ''; ?>">
            <i class="fas fa-envelope nav-icon"></i>
            <span>Contact</span>
            <div class="nav-underline"></div>
        </a>
    </nav>

    <!-- Icons Section: User Profile / Login / Cart -->
    <div class="icons">
        <?php if (isset($_SESSION['user_login'])): ?>
            <?php
            // Initialize user array to prevent undefined variable errors
            $navbar_user = [];
            try {
                $navbar_userId = $_SESSION['user_login'];
                $navbar_stmt = $conn->prepare("SELECT FirstName, LastName, EmailId, Image FROM tbl_members WHERE ID = ?");
                $navbar_stmt->execute([$navbar_userId]);
                $navbar_userResult = $navbar_stmt->fetch(PDO::FETCH_ASSOC);
                if ($navbar_userResult) {
                    $navbar_user = $navbar_userResult;
                }
                $navbar_stmt = null; // ปิด statement
            } catch (PDOException $e) {
                // Keep user as empty array if query fails
                $navbar_user = [];
            }
            ?>
            <div class="profile-dropdown">
                <div class="profile-btn" onclick="toggleProfileDropdown()">
                    <div class="profile-avatar">
                        <?php if (!empty($navbar_user['Image']) && file_exists("Uploads/imgprofile/" . $navbar_user['Image'])): ?>
                            <img src="Uploads/imgprofile/<?php echo htmlspecialchars($navbar_user['Image']); ?>" alt="Current Profile" class="current-image">
                        <?php else: ?>
                            <img src="assets/img/account.png" alt="Default Profile" class="current-image">
                        <?php endif; ?>
                        <div class="online-indicator"></div>
                    </div>
                    <div class="profile-info d-none d-md-block">
                        <span class="profile-name"><?php echo htmlspecialchars($navbar_user['FirstName'] ?? 'ผู้ใช้'); ?></span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>

                <div class="profile-dropdown-menu">
                    <div class="dropdown-header">
                        <div class="user-info">
                            <?php if (!empty($navbar_user['Image']) && file_exists("Uploads/imgprofile/" . $navbar_user['Image'])): ?>
                                <img src="Uploads/imgprofile/<?php echo htmlspecialchars($navbar_user['Image']); ?>" alt="Current Profile" class="current-image">
                            <?php else: ?>
                                <img src="assets/img/account.png" alt="Default Profile" class="current-image">
                            <?php endif; ?>
                            <div>
                                <div class="user-name"><?php echo htmlspecialchars($navbar_user['FirstName'] ?? 'ผู้ใช้'); ?> <?php echo htmlspecialchars($navbar_user['LastName'] ?? 'นามสกุล'); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($navbar_user['EmailId'] ?? 'อีเมลผู้ใช้'); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <ul class="dropdown-list">
                        <li>
                            <a href="user-profile.php" class="dropdown-item">
                                <i class="fas fa-user-circle"></i>
                                <span>โปรไฟล์ส่วนตัว</span>
                            </a>
                        </li>
                        <li>
                            <a href="user-order.php" class="dropdown-item">
                                <i class="fas fa-calendar-check"></i>
                                <span>คำสั่งซื้อของฉัน</span>
                            </a>
                        </li>
                        <li>
                            <a href="user-password.php" class="dropdown-item">
                                <i class="fas fa-key"></i>
                                <span>เปลี่ยนรหัสผ่าน</span>
                            </a>
                        </li>
                        <li class="dropdown-divider"></li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item logout-item" onclick="confirmLogout()">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>ออกจากระบบ</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <a href="login.php" class="auth-button">
                <i class="fas fa-user"></i>
                <span style="font-weight: 500;">Sign up for free/Log In</span>
            </a>
        <?php endif; ?>

        <!-- Shopping Cart Icon -->
        <a href="cart.php" class="cart-icon-container" aria-label="Shopping Cart">
            <i class="fas fa-shopping-cart"></i>
            <span class="cart-counter"><?php echo $cart_count; ?></span>
        </a>
    </div>
</header>

<script>
    // Profile Dropdown Toggle
    function toggleProfileDropdown() {
        const dropdown = document.querySelector('.profile-dropdown');
        const menu = document.querySelector('.profile-dropdown-menu');
        const arrow = document.querySelector('.dropdown-arrow');

        dropdown.classList.toggle('active');
        arrow.classList.toggle('rotated');

        // Add ripple effect
        const btn = document.querySelector('.profile-btn');
        const ripple = document.createElement('div');
        ripple.classList.add('ripple-effect');
        btn.appendChild(ripple);

        setTimeout(() => {
            ripple.remove();
        }, 600);
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.querySelector('.profile-dropdown');
        if (dropdown && !dropdown.contains(event.target)) {
            dropdown.classList.remove('active');
            const arrow = document.querySelector('.dropdown-arrow');
            if (arrow) arrow.classList.remove('rotated');
        }
    });

    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.modern-navbar');
        const topBar = document.querySelector('.top-bar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
            topBar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
            topBar.classList.remove('scrolled');
        }
    });

    // Mobile menu toggle
    document.addEventListener('DOMContentLoaded', function() {
        const toggler = document.getElementById('toggler');
        const navbar = document.querySelector('.navbar');

        if (toggler && navbar) {
            toggler.addEventListener('change', function() {
                navbar.classList.toggle('active');
            });
        }
    });

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href*="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            const [targetPage, targetId] = href.split('#');
            const currentPage = '<?php echo $currentPage; ?>';

            if (targetPage === '' || targetPage === currentPage) {
                // Scroll within the same page
                const target = document.querySelector('#' + targetId);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            } else {
                // Navigate to another page
                window.location.href = href;
            }
        });
    });

    // SweetAlert2 Logout Confirmation
    function confirmLogout() {
        Swal.fire({
            title: 'คุณแน่ใจหรือไม่?',
            text: 'คุณต้องการออกจากระบบหรือไม่?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ออกจากระบบ',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
    }
</script>