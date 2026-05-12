<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'patient') { header('Location: ../index.php'); exit; }
$pid = $_SESSION['patient_id'] ?? 0;

// Handle booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    $doctor_id = (int)$_POST['doctor_id'];
    $appt_date = $_POST['appt_date'] . ' ' . $_POST['time_slot'];
    $notes = trim($_POST['notes'] ?? '');

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appt_date, status, notes) VALUES (?, ?, ?, 'Pending', ?)");
        $stmt->bind_param("iiss", $pid, $doctor_id, $appt_date, $notes);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        header('Location: appointments.php?booked=1'); exit;
    } catch (Exception $e) {
        $conn->rollback();
        $bookError = 'Booking failed. Please try again.';
    }
}

$depts = $conn->query("SELECT d.dept_id, d.dept_name, d.description, COUNT(doc.doctor_id) as doc_count FROM departments d LEFT JOIN doctors doc ON d.dept_id=doc.dept_id GROUP BY d.dept_id ORDER BY d.dept_name");
$allDoctors = $conn->query("SELECT doc.doctor_id, u.name, u.profile_pic, doc.specialization, doc.dept_id, doc.schedule FROM doctors doc JOIN users u ON doc.user_id=u.user_id ORDER BY u.name");

$doctorsByDept = [];
while ($d = $allDoctors->fetch_assoc()) {
    $doctorsByDept[$d['dept_id']][] = $d;
}

$takenSlots = [];
$ts = $conn->query("SELECT doctor_id, DATE(appt_date) as d, TIME_FORMAT(appt_date, '%H:%i') as t FROM appointments WHERE status IN ('Pending','Confirmed') AND appt_date >= CURDATE()");
while ($r = $ts->fetch_assoc()) {
    $takenSlots[$r['doctor_id']][$r['d']][] = $r['t'];
}

$deptColors = ['#E4F2EC','#EFF6FF','#FEF3C7','#F3E8FF','#FDF0EF'];
$deptIcons  = ['❤️','🧠','🦴','👁️','🫁'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Book appointment — MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Book an appointment</h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">🔔<span class="dot"></span></div>
      </div>
    </div>
    <div class="admin-content">

      <?php if (isset($bookError)): ?><div class="alert alert-error"><?= $bookError ?></div><?php endif; ?>

      <!-- Progress Steps -->
      <div class="steps">
        <div class="step active" id="step1"><div class="step-circle">1</div> Department</div>
        <div class="step-line"></div>
        <div class="step" id="step2"><div class="step-circle">2</div> Doctor</div>
        <div class="step-line"></div>
        <div class="step" id="step3"><div class="step-circle">3</div> Schedule</div>
        <div class="step-line"></div>
        <div class="step" id="step4"><div class="step-circle">4</div> Confirm</div>
      </div>

      <div class="booking-cols">
        <!-- Left: Departments -->
        <div class="card" style="padding:1.5rem">
          <h3 style="font-size:14px;font-weight:600;margin-bottom:1rem">Select department</h3>
          <?php $idx=0; $depts->data_seek(0); while ($d = $depts->fetch_assoc()): ?>
          <div class="dept-item" data-dept="<?= $d['dept_id'] ?>">
            <div class="dept-icon" style="background:<?= $deptColors[$idx % count($deptColors)] ?>"><?= $deptIcons[$idx % count($deptIcons)] ?></div>
            <div>
              <div style="font-weight:500;font-size:14px"><?= htmlspecialchars($d['dept_name']) ?></div>
              <div class="text-sm text-muted"><?= $d['doc_count'] ?> doctor<?= $d['doc_count']!=1?'s':'' ?></div>
            </div>
            <div class="check">✓</div>
          </div>
          <?php $idx++; endwhile; ?>
        </div>

        <!-- Right: Doctors + Slots -->
        <div>
          <div class="card" style="margin-bottom:1rem;padding:1.5rem">
            <h3 style="font-size:14px;font-weight:600;margin-bottom:1rem">Select doctor</h3>
            <div id="doctorList"><p class="text-muted text-sm">Choose a department first</p></div>
          </div>
          <div class="card" id="slotCard" style="display:none;padding:1.5rem">
            <h3 style="font-size:14px;font-weight:600;margin-bottom:1rem">Pick a time slot</h3>
            <div class="form-group" style="margin-bottom:1rem">
              <label>Appointment date</label>
              <input type="date" id="apptDate" class="form-input" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+14 days')) ?>">
            </div>
            <div class="slot-grid" id="slotGrid"></div>
          </div>
        </div>
      </div>

      <!-- Confirm form (hidden) -->
      <div class="card" id="confirmCard" style="display:none;padding:1.5rem">
        <h3 style="font-size:14px;font-weight:600;margin-bottom:1rem">Confirm your booking</h3>
        <div id="confirmSummary" style="margin-bottom:1rem;background:var(--muted);padding:1rem;border-radius:12px;font-size:14px"></div>
        <form method="POST">
          <input type="hidden" name="doctor_id" id="fDoctorId">
          <input type="hidden" name="appt_date" id="fApptDate">
          <input type="hidden" name="time_slot" id="fTimeSlot">
          <div class="form-group" style="margin-bottom:1rem">
            <label>Notes (optional)</label>
            <textarea name="notes" class="form-input" rows="2" style="height:auto;padding:12px 16px" placeholder="Any symptoms or concerns…"></textarea>
          </div>
          <button type="submit" name="confirm_booking" class="btn btn-primary" style="width:100%">Confirm booking</button>
        </form>
      </div>

    </div>
  </div>
</div>

<script>
const doctorsByDept = <?= json_encode($doctorsByDept) ?>;
const takenSlots = <?= json_encode($takenSlots) ?>;
let selectedDept = null, selectedDoctor = null, selectedSlot = null;
const timeSlots = ['09:00','09:30','10:00','10:30','11:00','11:30','14:00','14:30','15:00','15:30','16:00','16:30'];

// Bind dept clicks via event delegation
document.querySelectorAll('.dept-item').forEach(function(el) {
    el.addEventListener('click', function() {
        const deptId = parseInt(this.dataset.dept);
        selectedDept = deptId;
        selectedDoctor = null;
        selectedSlot = null;
        document.querySelectorAll('.dept-item').forEach(function(d) { d.classList.remove('selected'); });
        el.classList.add('selected');
        updateStep(1);

        const docs = doctorsByDept[deptId] || [];
        let html = '';
        if (!docs.length) {
            html = '<p class="text-muted text-sm">No doctors in this department</p>';
        }
        docs.forEach(function(d) {
            const bg = d.profile_pic
                ? 'background-image:url(../uploads/' + d.profile_pic + ');background-size:cover;background-position:center;color:transparent'
                : 'background:var(--accent);color:#fff';
            html += '<div class="doctor-item" data-id="' + d.doctor_id + '" data-name="' + encodeURIComponent(d.name) + '" data-spec="' + encodeURIComponent(d.specialization) + '">'
                  + '<div class="avatar avatar-sm" style="' + bg + '">' + (d.profile_pic ? '' : d.name.charAt(0)) + '</div>'
                  + '<div><div style="font-weight:500;font-size:14px">' + d.name + '</div><div class="text-sm text-muted">' + d.specialization + '</div></div>'
                  + '</div>';
        });
        document.getElementById('doctorList').innerHTML = html;

        // Bind doctor clicks
        document.querySelectorAll('.doctor-item').forEach(function(docEl) {
            docEl.addEventListener('click', function() {
                var id = this.dataset.id;
                var name = decodeURIComponent(this.dataset.name);
                var spec = decodeURIComponent(this.dataset.spec);
                selectDoctor(this, id, name, spec);
            });
        });

        document.getElementById('slotCard').style.display = 'none';
        document.getElementById('confirmCard').style.display = 'none';
    });
});

function selectDoctor(el, id, name, spec) {
    selectedDoctor = { id: id, name: name, spec: spec };
    selectedSlot = null;
    document.querySelectorAll('.doctor-item').forEach(function(d) { d.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('slotCard').style.display = '';
    document.getElementById('apptDate').value = '';
    document.getElementById('slotGrid').innerHTML = '<p class="text-muted text-sm">Pick a date above</p>';
    document.getElementById('confirmCard').style.display = 'none';
    updateStep(2);
}

// Bind date change
document.getElementById('apptDate').addEventListener('change', function() {
    var date = this.value;
    if (!date || !selectedDoctor) return;
    var taken = (takenSlots[selectedDoctor.id] && takenSlots[selectedDoctor.id][date]) || [];
    var html = '';
    timeSlots.forEach(function(s) {
        var isTaken = taken.indexOf(s) !== -1;
        html += '<div class="slot' + (isTaken ? ' taken' : '') + '" data-time="' + s + '">' + s + '</div>';
    });
    document.getElementById('slotGrid').innerHTML = html;

    // Bind slot clicks
    document.querySelectorAll('.slot:not(.taken)').forEach(function(slotEl) {
        slotEl.addEventListener('click', function() {
            selectedSlot = this.dataset.time;
            document.querySelectorAll('.slot').forEach(function(s) { s.classList.remove('selected'); });
            this.classList.add('selected');
            updateStep(3);
            showConfirm();
        });
    });
    updateStep(2);
});

function showConfirm() {
    if (!selectedDoctor || !selectedSlot) return;
    var date = document.getElementById('apptDate').value;
    document.getElementById('fDoctorId').value = selectedDoctor.id;
    document.getElementById('fApptDate').value = date;
    document.getElementById('fTimeSlot').value = selectedSlot + ':00';
    document.getElementById('confirmSummary').innerHTML =
        '<p style="margin-bottom:4px"><strong>Doctor:</strong> ' + selectedDoctor.name + ' — ' + selectedDoctor.spec + '</p>'
      + '<p><strong>Date & Time:</strong> ' + date + ' at ' + selectedSlot + '</p>';
    document.getElementById('confirmCard').style.display = '';
    updateStep(4);
    document.getElementById('confirmCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function updateStep(n) {
    for (var i = 1; i <= 4; i++) {
        var el = document.getElementById('step' + i);
        el.className = i < n ? 'step done' : (i === n ? 'step active' : 'step');
        el.querySelector('.step-circle').textContent = i < n ? '✓' : i;
    }
}
</script>
</body>
</html>
