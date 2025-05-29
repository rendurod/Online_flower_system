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
    <div class="sidebar-heading">
        Addons
    </div>

    <!-- Nav Item - Pages Collapse Menu -->
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePages" aria-expanded="true"
            aria-controls="collapsePages">
            <i class="fas fa-fw fa-folder"></i>
            <span>Pages</span>
        </a>
        <div id="collapsePages" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <h6 class="collapse-header">Login Screens:</h6>
                <a class="collapse-item" href="login.php">Login</a>
                <a class="collapse-item" href="register.php">Register</a>
                <a class="collapse-item" href="forgot-password.html">Forgot Password</a>
                <div class="collapse-divider"></div>
                <h6 class="collapse-header">Other Pages:</h6>
                <a class="collapse-item" href="404.html">404 Page</a>
                <a class="collapse-item" href="blank.html">Blank Page</a>
            </div>
        </div>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="charts.html">
            <i class="fas fa-fw fa-chart-area"></i>
            <span>Charts</span></a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

    <!-- Sidebar Message -->


</ul>