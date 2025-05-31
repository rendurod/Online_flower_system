<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
        <div class="sidebar-brand-icon">
            <img src="img/LOGO_FlowerShopp.png"
                alt="FlowerShop Logo"
                class="rounded-circle"
                style="width: 50px; height: 50px; object-fit: cover;">
        </div>
        <div class="sidebar-brand-text mx-2">FlowerShop</div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Dashboard -->
    <li class="nav-item <?= ($currentPage == 'index.php') ? 'active' : '' ?>">
        <a class="nav-link" href="index.php">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>หน้าแรก</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        การจัดการข้อมูลสินค้า
    </div>

    <!-- Nav Item -->
    <li class="nav-item <?= ($currentPage == 'category.php') ? 'active' : '' ?>">
        <a class="nav-link" href="category.php">
            <i class="fas fa-fw fa-table"></i>
            <span>ประเภทสินค้า</span>
        </a>
    </li>

    <li class="nav-item <?= ($currentPage == 'flowers.php') ? 'active' : '' ?>">
        <a class="nav-link" href="flowers.php">
            <i class="fas fa-fw fa-table"></i>
            <span>ข้อมูลสินค้า</span>
        </a>
    </li>

    <!-- Heading -->
    <div class="sidebar-heading mt-2">
        การจัดการข้อมูลคำสั่งซื้อ
    </div>

    <!-- Nav Item -->
    <li class="nav-item <?= ($currentPage == 'orders.php') ? 'active' : '' ?>">
        <a class="nav-link" href="orders.php">
            <i class="fas fa-fw fa-table"></i>
            <span>คำสั่งซื้อสินค้า</span>
        </a>
    </li>
    <li class="nav-item <?= ($currentPage == 'history.php') ? 'active' : '' ?>">
        <a class="nav-link" href="history.php">
            <i class="fas fa-fw fa-table"></i>
            <span>ประวัติคำสั่งซื้อทั้งหมด</span>
        </a>
    </li>

    <!-- Heading -->
    <div class="sidebar-heading mt-2">
        การจัดการข้อมูลทั่วไป
    </div>

    <!-- Nav Item -->
    <li class="nav-item <?= ($currentPage == 'members.php') ? 'active' : '' ?>">
        <a class="nav-link" href="members.php">
            <i class="fas fa-fw fa-table"></i>
            <span>ข้อมูลสมาชิก</span>
        </a>
    </li>
    
    <!-- Divider -->
    <hr class="sidebar-divider">
    
    <!-- Heading -->
    <div class="sidebar-heading mt-2">
        การจัดการข้อมูลการชำระเงิน
    </div>

    <!-- Nav Item -->
    <li class="nav-item <?= ($currentPage == 'payment.php') ? 'active' : '' ?>">
        <a class="nav-link" href="payment.php">
            <i class="fas fa-fw fa-table"></i>
            <span>เพิ่มการชำระเงิน</span>
        </a>
    </li>
    


    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

    <!-- Sidebar Message -->


</ul>