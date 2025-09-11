<?php
// It's a good practice to start the session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Assuming you have a db_connect.php or similar for your database connection
// require_once 'db_connect.php'; 
?>

<!-- 
  NOTE: For best practice, this <style> block should be moved to your main CSS file.
  I've placed it here for demonstration purposes to make the cart icon look good immediately.
-->
<style>
    .icons {
        display: flex;
        align-items: center;
    }

    .cart-icon-container {
        position: relative;
        color: #333;
        font-size: 1.8rem; /* Make icon larger */
        margin-left: 20px; /* Space between the cart and the previous element */
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .cart-icon-container:hover {
        color: #e84393; /* Same hover color as other icons */
    }

    .cart-counter {
        position: absolute;
        top: -8px;   /* Further adjusted position */
        right: -12px; /* Further adjusted position */
        background-color: #ff4d4d;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 0.75rem;
        font-weight: bold;
        display: flex;
        justify-content: center;
        align-items: center;
        border: 2px solid #fff; /* White border to stand out */
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    /* Style for when the navbar is scrolled */
    .modern-navbar.scrolled .cart-icon-container {
        color: #fff;
    }

    .modern-navbar.scrolled .cart-icon-container:hover {
        color: #f0f0f0; 
    }
</style>

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
            // This part assumes $conn is available. If not, you should include your DB connection file.
            if (isset($conn)) {
                $userId = $_SESSION['user_login'];
                $stmt = $conn->prepare("SELECT * FROM tbl_members WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $user = []; // Define user as empty array if no connection
            }
            ?>
            <div class="profile-dropdown">
                <div class="profile-btn" onclick="toggleProfileDropdown()">
                    <div class="profile-avatar">
                        <?php if (!empty($user['Image']) && file_exists("Uploads/imgprofile/" . $user['Image'])): ?>
                            <img src="Uploads/imgprofile/<?php echo htmlspecialchars($user['Image']); ?>" alt="Current Profile" class="current-image">
                        <?php else: ?>
                            <img src="assets/img/account.png" alt="Default Profile" class="current-image">
                        <?php endif; ?>
                        <div class="online-indicator"></div>
                    </div>
                    <div class="profile-info d-none d-md-block">
                        <span class="profile-name"><?php echo htmlspecialchars($user['FirstName'] ?? 'ผู้ใช้'); ?></span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>

                <div class="profile-dropdown-menu">
                    <div class="dropdown-header">
                        <div class="user-info">
                            <?php if (!empty($user['Image']) && file_exists("Uploads/imgprofile/" . $user['Image'])): ?>
                                <img src="Uploads/imgprofile/<?php echo htmlspecialchars($user['Image']); ?>" alt="Current Profile" class="current-image">
                            <?php else: ?>
                                <img src="assets/img/account.png" alt="Default Profile" class="current-image">
                            <?php endif; ?>
                            <div>
                                <div class="user-name"><?php echo htmlspecialchars($user['FirstName'] ?? 'ผู้ใช้'); ?> <?php echo htmlspecialchars($user['LastName'] ?? 'นามสกุล'); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($user['EmailId'] ?? 'อีเมลผู้ใช้?'); ?></div>
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

        <!-- Shopping Cart Icon - This will appear after login button or user profile -->
        <a href="cart.php" class="cart-icon-container" aria-label="Shopping Cart">
            <i class="fas fa-shopping-cart"></i>
            <span class="cart-counter">0</span> <!-- Placeholder for item count -->
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
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
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


