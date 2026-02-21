<div class="sidebar">
    <div class="logo-details">
        <i class='bx bx-store-alt'></i>
        <span class="logo_name">สินค้าคงคลัง</span>
    </div>
    <!-- User Badge -->
    <div style="padding:10px 18px 0; margin-bottom:4px;">
        <div style="background:rgba(255,255,255,0.08); border-radius:10px; padding:10px 12px; display:flex; align-items:center; gap:10px;">
            <i class='bx bx-user-circle' style="font-size:26px; color:#a0f0e0; flex-shrink:0;"></i>
            <div style="overflow:hidden;">
                <div style="font-size:13px; font-weight:600; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= htmlspecialchars($_SESSION['inv_fullname'] ?? 'ผู้ใช้') ?>
                </div>
                <div style="font-size:11px; color:rgba(255,255,255,0.45);">
                    <?= htmlspecialchars($_SESSION['inv_role'] ?? '') ?>
                </div>
            </div>
        </div>
    </div>
    <ul class="nav-links">
        <li>
            <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class='bx bx-grid-alt'></i>
                <span class="links_name">หน้าหลัก</span>
            </a>
        </li>
        <li>
            <a href="procurement.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'procurement.php' ? 'active' : ''; ?>">
                <i class='bx bx-cart-alt'></i>
                <span class="links_name">ควบคุมการจัดซื้อ</span>
            </a>
        </li>
        <li>
            <a href="asset.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'asset.php' ? 'active' : ''; ?>">
                <i class='bx bx-box'></i>
                <span class="links_name">ควบคุมทรัพย์สิน</span>
            </a>
        </li>
        <li>
            <a href="#">
                <i class='bx bx-package'></i>
                <span class="links_name">บัญชีวัสดุ</span>
            </a>
        </li>
        <!-- Logout -->
        <?php if (($_SESSION['inv_role'] ?? '') === 'admin'): ?>
        <li>
            <a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <i class='bx bx-user-cog'></i>
                <span class="links_name">จัดการผู้ใช้</span>
            </a>
        </li>
        <?php endif; ?>
        <li class="log_out">
            <a href="logout.php">
                <i class='bx bx-log-out'></i>
                <span class="links_name">ออกจากระบบ</span>
            </a>
        </li>
    </ul>
</div>
