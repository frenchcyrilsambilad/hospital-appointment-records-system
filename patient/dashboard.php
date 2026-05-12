<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') { header('Location: ../index.php'); exit; }
$pid = $_SESSION['patient_id'] ?? 0;

$upcoming = $conn->prepare("SELECT COUNT(*) as c FROM appointments WHERE patient_id=? AND status IN ('Pending','Confirmed') AND appt_date >= NOW()");
$upcoming->bind_param("i",$pid); $upcoming->execute();
$upcomingCount = $upcoming->get_result()->fetch_assoc()['c']; $upcoming->close();

$completed = $conn->prepare("SELECT COUNT(*) as c FROM appointments WHERE patient_id=? AND status='Completed'");
$completed->bind_param("i",$pid); $completed->execute();
$completedCount = $completed->get_result()->fetch_assoc()['c']; $completed->close();

$records = $conn->prepare("SELECT COUNT(*) as c FROM medical_records WHERE patient_id=?");
$records->bind_param("i",$pid); $records->execute();
$recordCount = $records->get_result()->fetch_assoc()['c']; $records->close();

// Next appointment
$next = $conn->prepare("
    SELECT a.appt_date, u.name AS doctor_name, u.profile_pic, d.dept_name, a.status
    FROM appointments a
    JOIN doctors doc ON a.doctor_id = doc.doctor_id
    JOIN users u ON doc.user_id = u.user_id
    JOIN departments d ON doc.dept_id = d.dept_id
    WHERE a.patient_id = ? AND a.status IN ('Pending','Confirmed') AND a.appt_date >= NOW()
    ORDER BY a.appt_date ASC LIMIT 1
");
$next->bind_param("i",$pid); $next->execute();
$nextAppt = $next->get_result()->fetch_assoc(); $next->close();

function statusPill($s) {
    $map = ['Confirmed'=>'green','Pending'=>'amber','Completed'=>'blue','Cancelled'=>'red'];
    return '<span class="pill pill-'.($map[$s]??'amber').'">'.htmlspecialchars($s).'</span>';
}

$colors = ['#1A6B4A','#1E40AF','#B45309','#7C3AED','#C0392B','#0F766E'];
function avatarColorP($name) { global $colors; return $colors[abs(crc32($name)) % count($colors)]; }
function initialsP($name) { $p = explode(' ',$name); return strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):'')); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Overview — MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Welcome, <?= htmlspecialchars(explode(' ',$_SESSION['name'])[0]) ?></h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">🔔<span class="dot"></span></div>
      </div>
    </div>
    <div class="admin-content">
      <div class="grid grid-cols-3 gap-6 mb-6">
        <div class="card">
          <div class="stat-label text-muted text-sm mb-2">Upcoming</div>
          <div style="font-size:32px;font-weight:700;font-family:'Calistoga',serif;color:var(--foreground)"><?= $upcomingCount ?></div>
          <div class="text-sm" style="color:var(--accent);margin-top:4px">Appointments</div>
        </div>
        <div class="card">
          <div class="stat-label text-muted text-sm mb-2">Completed</div>
          <div style="font-size:32px;font-weight:700;font-family:'Calistoga',serif;color:var(--foreground)"><?= $completedCount ?></div>
          <div class="text-sm" style="color:var(--accent);margin-top:4px">Past visits</div>
        </div>
        <div class="card">
          <div class="stat-label text-muted text-sm mb-2">Records</div>
          <div style="font-size:32px;font-weight:700;font-family:'Calistoga',serif;color:var(--foreground)"><?= $recordCount ?></div>
          <div class="text-sm" style="color:var(--accent);margin-top:4px">Medical entries</div>
        </div>
      </div>

      <?php if ($nextAppt): ?>
      <div class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg, var(--accent), #4f46e5);color:white;border:none">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.25rem">
          <h3 style="font-size:16px;font-weight:600;opacity:0.9">Next appointment</h3>
          <span class="pill" style="background:rgba(255,255,255,0.2);color:white;border:none">Confirmed</span>
        </div>
        <div style="display:flex;align-items:center;gap:16px">
          <div class="avatar avatar-lg" style="background:rgba(255,255,255,0.2);color:#fff;<?= $nextAppt['profile_pic'] ? 'background-image:url(../uploads/'.htmlspecialchars($nextAppt['profile_pic']).');background-size:cover;background-position:center;color:transparent' : '' ?>">
            <?= $nextAppt['profile_pic'] ? '' : initialsP(str_replace('Dr. ', '', $nextAppt['doctor_name'])) ?>
          </div>
          <div style="flex:1">
            <div style="font-weight:600;font-size:1.25rem"><?= htmlspecialchars(strpos($nextAppt['doctor_name'], 'Dr.') === 0 ? $nextAppt['doctor_name'] : 'Dr. ' . $nextAppt['doctor_name']) ?></div>
            <div style="opacity:0.9;font-size:14px;margin-top:4px;display:flex;align-items:center;gap:12px">
              <span style="display:flex;align-items:center;gap:4px">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <?= htmlspecialchars($nextAppt['dept_name']) ?>
              </span>
              <span>·</span>
              <span style="display:flex;align-items:center;gap:4px">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <?= date('M j, Y · g:i A', strtotime($nextAppt['appt_date'])) ?>
              </span>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div style="display:flex;gap:12px">
        <a href="book.php" class="btn btn-primary" style="text-decoration:none">Book new appointment</a>
        <a href="history.php" class="btn btn-outline" style="text-decoration:none">View medical history</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
