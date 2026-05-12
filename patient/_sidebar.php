<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <div class="logo-icon">+</div>
      MediCare <span style="font-weight:400;opacity:0.8;font-size:14px;margin-left:4px">Patient</span>
    </div>
  </div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="nav-item <?= $currentPage==='dashboard.php'?'active':'' ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
      Overview
    </a>
    <a href="book.php" class="nav-item <?= $currentPage==='book.php'?'active':'' ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
      Book appointment
    </a>
    <a href="appointments.php" class="nav-item <?= $currentPage==='appointments.php'?'active':'' ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      My appointments
    </a>
    <a href="history.php" class="nav-item <?= $currentPage==='history.php'?'active':'' ?>">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
      Medical history
    </a>
  </nav>

  <div class="sidebar-footer" style="flex-direction:column;align-items:stretch;padding:1.25rem 1rem">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
      <?php $s_pic = $_SESSION['profile_pic'] ?? null; $s_name = $_SESSION['name'] ?? 'A'; ?>
      <div class="avatar avatar-sm" style="background:var(--accent);color:#fff;<?= $s_pic ? 'background-image:url(../uploads/'.htmlspecialchars($s_pic).');background-size:cover;background-position:center;color:transparent' : '' ?>">
        <?= $s_pic ? '' : strtoupper(substr($s_name,0,1)) ?>
      </div>
      <div class="info" style="flex:1;overflow:hidden">
        <div style="font-weight:600;color:var(--foreground);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($_SESSION['name']) ?></div>
        <div class="text-muted text-sm">Patient</div>
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <a href="profile.php" class="btn btn-outline btn-sm" style="flex:1;justify-content:center;height:32px;font-size:12px;text-decoration:none">Edit profile</a>
      <a href="../logout.php" class="btn btn-ghost btn-sm" style="padding:0 8px;height:32px;color:var(--muted-foreground)" title="Log out">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      </a>
    </div>
  </div>
</aside>
