<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') { header('Location: ../index.php'); exit; }
$pid = $_SESSION['patient_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT mr.record_date, mr.diagnosis, mr.prescription,
           u.name AS doctor_name, u.profile_pic, d.dept_name
    FROM medical_records mr
    JOIN doctors doc ON mr.doctor_id = doc.doctor_id
    JOIN users u ON doc.user_id = u.user_id
    JOIN departments d ON doc.dept_id = d.dept_id
    WHERE mr.patient_id = ?
    ORDER BY mr.record_date DESC
");
$stmt->bind_param("i", $pid);
$stmt->execute();
$records = $stmt->get_result();
$stmt->close();

$colors = ['#1A6B4A','#1E40AF','#B45309','#7C3AED','#C0392B','#0F766E'];
function ac($n){global $colors;return $colors[abs(crc32($n))%count($colors)];}
function ini($n){$p=explode(' ',$n);return strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):''));}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Medical history — MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Medical history</h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">🔔<span class="dot"></span></div>
      </div>
    </div>
    <div class="admin-content">
      
      <?php if ($records->num_rows === 0): ?>
        <div class="card"><p class="text-muted">No medical records found.</p></div>
      <?php else: ?>
      <div class="timeline">
        <?php while ($r = $records->fetch_assoc()): ?>
        <div class="timeline-item">
          <div class="timeline-date"><?= date('F j, Y', strtotime($r['record_date'])) ?></div>
          <div class="card">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border)">
              <div class="avatar avatar-md" style="background:<?=ac($r['doctor_name'])?>;color:#fff;<?= $r['profile_pic'] ? 'background-image:url(../uploads/'.htmlspecialchars($r['profile_pic']).');background-size:cover;background-position:center;color:transparent' : '' ?>">
                <?= $r['profile_pic'] ? '' : ini($r['doctor_name']) ?>
              </div>
              <div style="flex:1">
                <div style="font-weight:600;font-size:15px">Dr. <?= htmlspecialchars($r['doctor_name']) ?></div>
                <div class="text-muted text-sm"><?= htmlspecialchars($r['dept_name']) ?></div>
              </div>
            </div>
            
            <div style="margin-bottom:12px">
              <div class="text-sm text-muted" style="margin-bottom:4px;font-weight:500">Diagnosis</div>
              <div style="font-size:15px;line-height:1.5"><?= htmlspecialchars($r['diagnosis']) ?></div>
            </div>
            
            <?php if ($r['prescription']): ?>
            <div style="background:rgba(0,82,255,0.03);padding:12px;border-radius:8px;border:1px solid rgba(0,82,255,0.1)">
              <div class="text-sm" style="color:var(--accent);margin-bottom:4px;font-weight:600;display:flex;align-items:center;gap:6px">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Prescription
              </div>
              <div style="font-size:14px;line-height:1.5"><?= htmlspecialchars($r['prescription']) ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
</body>
</html>
