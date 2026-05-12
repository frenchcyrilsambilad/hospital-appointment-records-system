<?php
// Doctor sidebar partial
$currentPage = basename($_SERVER['PHP_SELF']);
$pendingCount = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = " . (int)$_SESSION['doctor_id'] . " AND status='Pending'")->fetch_assoc()['c'];
?>
<aside class="admin-sidebar">
  <div class="logo">
    <div class="logo-icon">
      <svg width="14" height="14" fill="none" viewBox="0 0 16 16"><path d="M8 1v14M1 8h14" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>
    </div>
    MediCare HMS
  </div>
  <nav>
    <div class="nav-section">
      <div class="nav-section-title">Main</div>
      <a href="dashboard.php" class="nav-item <?= $currentPage==='dashboard.php'?'active':'' ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Dashboard
      </a>
      <a href="calendar.php" class="nav-item <?= $currentPage==='calendar.php'?'active':'' ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        Calendar
      </a>
      <a href="appointments.php" class="nav-item <?= $currentPage==='appointments.php'?'active':'' ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        My Appointments
        <?php if ($pendingCount > 0): ?><span class="badge"><?= $pendingCount ?></span><?php endif; ?>
      </a>
    </div>
    <div class="nav-section">
      <div class="nav-section-title">Settings</div>
      <a href="profile.php" class="nav-item <?= $currentPage==='profile.php'?'active':'' ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        My Profile
      </a>
    </div>
    <div class="nav-section">
      <div class="nav-section-title">Clinical</div>
      <a href="patients.php" class="nav-item <?= $currentPage==='patients.php'?'active':'' ?>">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
        My Patients
      </a>
    </div>
  </nav>
  <div class="sidebar-footer">
    <?php $s_pic = $_SESSION['profile_pic'] ?? null; $s_name = $_SESSION['name'] ?? 'D'; ?>
    <div class="avatar" <?= $s_pic ? 'style="background-image:url(../uploads/'.htmlspecialchars($s_pic).');background-size:cover;background-position:center;color:transparent"' : '' ?>><?= strtoupper(substr($s_name,0,1)) ?></div>
    <div class="info" style="flex:1"><?= htmlspecialchars($_SESSION['name']) ?><span>Doctor</span></div>
    <a href="../logout.php" class="logout-btn" title="Log out">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
    </a>
  </div>
</aside>
