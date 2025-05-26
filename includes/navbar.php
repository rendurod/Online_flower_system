<header class="modern-navbar">
    <div class="container-fluid px-4">
        <nav class="navbar navbar-expand-lg">
            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </button>

            <!-- Logo -->
            <a href="index.php" class="navbar-brand logo-container">
                <div class="logo-wrapper">
                    <span class="logo-text">flowerShop</span>
                    <span class="logo-dot">.</span>
                    <div class="logo-flower">🌸</div>
                </div>
            </a>

            <!-- Navigation Menu -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#home">
                            <i class="fas fa-home nav-icon"></i>
                            <span>Home</span>
                            <div class="nav-underline"></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#about">
                            <i class="fas fa-info-circle nav-icon"></i>
                            <span>About</span>
                            <div class="nav-underline"></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#products">
                            <i class="fas fa-leaf nav-icon"></i>
                            <span>Products</span>
                            <div class="nav-underline"></div>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#contact">
                            <i class="fas fa-envelope nav-icon"></i>
                            <span>Contact</span>
                            <div class="nav-underline"></div>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- User Profile / Login -->
            <div class="navbar-actions">
                <?php if (isset($_SESSION['user_login'])): ?>
                    <?php
                        $userId = $_SESSION['user_login'];
                        $stmt = $conn->prepare("SELECT * FROM tbl_members WHERE id = ?");
                        $stmt->execute([$userId]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        $userImage = $user['profile_img'] ?? 'default.png';
                    ?>
                    <div class="profile-dropdown">
                        <div class="profile-btn" onclick="toggleProfileDropdown()">
                            <div class="profile-avatar">
                                <img src="img/imageprofile/<?php echo htmlspecialchars($userImage); ?>" alt="profile">
                                <div class="online-indicator"></div>
                            </div>
                            <div class="profile-info d-none d-md-block">
                                <span class="profile-name"><?php echo htmlspecialchars($user['FirstName'] ?? 'ผู้ใช้'); ?></span>
                                <span class="profile-status">ออนไลน์</span>
                            </div>
                            <i class="fas fa-chevron-down dropdown-arrow"></i>
                        </div>

                        <div class="profile-dropdown-menu">
                            <div class="dropdown-header">
                                <div class="user-info">
                                    <img src="img/imageprofile/<?php echo htmlspecialchars($userImage); ?>" alt="profile">
                                    <div>
                                        <div class="user-name"><?php echo htmlspecialchars($user['FirstName'] ?? 'ผู้ใช้'); ?></div>
                                        <div class="user-email"><?php echo htmlspecialchars($user['Email'] ?? ''); ?></div>
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
                                    <a href="user-mybooking.php" class="dropdown-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <span>การจองของฉัน</span>
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
                                    <a href="logout.php" class="dropdown-item logout-item">
                                        <i class="fas fa-sign-out-alt"></i>
                                        <span>ออกจากระบบ</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="auth-btn">
                        <div class="auth-btn-content">
                            <i class="fas fa-user-plus"></i>
                            <span>เข้าสู่ระบบ</span>
                        </div>
                        <div class="auth-btn-bg"></div>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </div>

    <!-- Navigation Background Effect -->
    <div class="nav-bg-effect"></div>
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
        document.querySelector('.dropdown-arrow').classList.remove('rotated');
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

// Mobile menu toggle animation
document.addEventListener('DOMContentLoaded', function() {
    const toggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.getElementById('navbarNav');
    
    if (toggler && navbarCollapse) {
        toggler.addEventListener('click', function() {
            this.classList.toggle('active');
        });
        
        // Close mobile menu when clicking nav links
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    toggler.classList.remove('active');
                    navbarCollapse.classList.remove('show');
                }
            });
        });
    }
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
</script>