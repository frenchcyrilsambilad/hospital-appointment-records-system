<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') { header('Location: ../index.php'); exit; }
$pid = $_SESSION['patient_id'] ?? 0;

// Cancel appointment
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    $stmt = $conn->prepare("UPDATE appointments SET status='Cancelled' WHERE appt_id=? AND patient_id=?");
    $stmt->bind_param("ii", $_GET['id'], $pid);
    $stmt->execute(); $stmt->close();
    header('Location: appointments.php'); exit;
}

function statusPill($s) {
    $map = ['Confirmed'=>'green','Pending'=>'amber','Completed'=>'blue','Cancelled'=>'red'];
    return '<span class="pill pill-'.($map[$s]??'amber').'">'.htmlspecialchars($s).'</span>';
}
$colors = ['#1A6B4A','#1E40AF','#B45309','#7C3AED','#C0392B'];
function ac($n){global $colors;return $colors[abs(crc32($n))%count($colors)];}
function ini($n){$p=explode(' ',$n);return strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):''));}

// Upcoming
$up = $conn->prepare("
    SELECT a.appt_id,a.appt_date,a.status,a.notes,u.name AS doc, u.profile_pic, doc.specialization,d.dept_name
    FROM appointments a JOIN doctors doc ON a.doctor_id=doc.doctor_id
    JOIN users u ON doc.user_id=u.user_id JOIN departments d ON doc.dept_id=d.dept_id
    WHERE a.patient_id=? AND a.status IN('Pending','Confirmed')
    ORDER BY a.appt_date ASC");
$up->bind_param("i",$pid); $up->execute(); $upcoming=$up->get_result(); $up->close();

// Past
$pa = $conn->prepare("
    SELECT a.appt_id,a.appt_date,a.status,a.notes,u.name AS doc, u.profile_pic, doc.specialization,d.dept_name
    FROM appointments a JOIN doctors doc ON a.doctor_id=doc.doctor_id
    JOIN users u ON doc.user_id=u.user_id JOIN departments d ON doc.dept_id=d.dept_id
    WHERE a.patient_id=? AND a.status IN('Completed','Cancelled')
    ORDER BY a.appt_date DESC");
$pa->bind_param("i",$pid); $pa->execute(); $past=$pa->get_result(); $pa->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My appointments — MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>My appointments</h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">🔔<span class="dot"></span></div>
      </div>
    </div>
    <div class="admin-content">

      <?php if(isset($_GET['booked'])): ?><div class="alert alert-success" style="margin-bottom:1.5rem">Appointment booked successfully!</div><?php endif; ?>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
        <h3 style="font-size:18px;font-weight:600;font-family:'Inter',sans-serif">Upcoming</h3>
        <a href="book.php" class="btn btn-primary btn-sm">+ Book New</a>
      </div>
      
      <?php if($upcoming->num_rows===0): ?><p class="text-muted text-sm" style="margin-bottom:2rem">No upcoming appointments</p><?php endif; ?>
      <?php while($r=$upcoming->fetch_assoc()): ?>
      <div class="card" style="margin-bottom:1rem;display:flex;align-items:center;gap:16px">
        <div class="avatar avatar-lg" style="background:<?=ac($r['doc'])?>;color:#fff;<?= $r['profile_pic'] ? 'background-image:url(../uploads/'.htmlspecialchars($r['profile_pic']).');background-size:cover;background-position:center;color:transparent' : '' ?>">
          <?= $r['profile_pic'] ? '' : ini($r['doc']) ?>
        </div>
        <div style="flex:1">
          <div style="font-weight:600;font-size:1.125rem">Dr. <?=htmlspecialchars($r['doc'])?></div>
          <div class="text-muted"><?=htmlspecialchars($r['dept_name'])?> · <?=date('M j, Y · g:i A',strtotime($r['appt_date']))?></div>
        </div>
        <?=statusPill($r['status'])?>
        <?php if(in_array($r['status'],['Pending','Confirmed'])): ?>
        <form method="POST" style="margin-left:16px">
          <input type="hidden" name="appt_id" value="<?=$r['appt_id']?>">
          <input type="hidden" name="cancel_appt" value="1">
          <button type="submit" class="btn btn-cancel btn-sm">Cancel</button>
        </form>
        <?php endif; ?>
      </div>
      <?php endwhile; ?>

      <h3 style="font-size:18px;font-weight:600;margin:2.5rem 0 1rem;font-family:'Inter',sans-serif">Past appointments</h3>
      <?php if($past->num_rows===0): ?><p class="text-muted text-sm">No past appointments</p><?php endif; ?>
      <?php while($r=$past->fetch_assoc()): ?>
      <div class="card" style="margin-bottom:1rem;display:flex;align-items:center;gap:16px;opacity:.8">
        <div class="avatar avatar-lg" style="background:<?=ac($r['doc'])?>;color:#fff;<?= $r['profile_pic'] ? 'background-image:url(../uploads/'.htmlspecialchars($r['profile_pic']).');background-size:cover;background-position:center;color:transparent' : '' ?>">
          <?= $r['profile_pic'] ? '' : ini($r['doc']) ?>
        </div>
        <div style="flex:1">
          <div style="font-weight:600;font-size:1.125rem">Dr. <?=htmlspecialchars($r['doc'])?></div>
          <div class="text-muted"><?=htmlspecialchars($r['dept_name'])?> · <?=date('M j, Y · g:i A',strtotime($r['appt_date']))?></div>
        </div>
        <?=statusPill($r['status'])?>
      </div>
      <?php endwhile; ?>

    </div>
  </div>
</div>
</body>
</html>
