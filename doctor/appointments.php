<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') { header('Location: ../index.php'); exit; }

$doctor_id = (int)$_SESSION['doctor_id'];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['appt_id'])) {
    $appt_id = (int)$_POST['appt_id'];
    $action = $_POST['action'];
    $new_status = '';

    if ($action === 'confirm') $new_status = 'Confirmed';
    elseif ($action === 'cancel') $new_status = 'Cancelled';
    elseif ($action === 'complete') $new_status = 'Completed';

    if ($new_status) {
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE appt_id = ? AND doctor_id = ?");
        $stmt->bind_param("sii", $new_status, $appt_id, $doctor_id);
        $stmt->execute();
        $updated = $stmt->affected_rows > 0;
        $stmt->close();
        
        if ($updated) {
            $estmt = $conn->prepare("
                SELECT u.email, u.name, a.appt_date
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                JOIN users u ON p.user_id = u.user_id
                WHERE a.appt_id = ? AND a.doctor_id = ?
            ");
            $estmt->bind_param("ii", $appt_id, $doctor_id);
            $estmt->execute();
            $erow = $estmt->get_result()->fetch_assoc();
            $estmt->close();
            if ($erow) {
            send_mock_email($erow['email'], $erow['name'], $new_status, $erow['appt_date']);
            }
        }

        // Redirect to avoid form resubmission
        header("Location: appointments.php?msg=email_sent");
        exit;
    }
}

$summary = [
    'total' => 0,
    'today' => 0,
    'pending' => 0,
    'upcoming' => 0,
];
$summary['total'] = (int)$conn->query("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = $doctor_id")->fetch_assoc()['c'];
$summary['today'] = (int)$conn->query("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = $doctor_id AND DATE(appt_date) = CURDATE()")->fetch_assoc()['c'];
$summary['pending'] = (int)$conn->query("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = $doctor_id AND status = 'Pending'")->fetch_assoc()['c'];
$summary['upcoming'] = (int)$conn->query("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = $doctor_id AND appt_date >= NOW() AND status IN ('Pending','Confirmed')")->fetch_assoc()['c'];

// Fetch all appointments for this doctor
$result = $conn->query("
    SELECT a.appt_id, a.appt_date, a.status, a.notes, p.patient_id, p.contact,
           u.name AS patient_name, u.email, u.profile_pic,
           (
             SELECT mr.record_id
             FROM medical_records mr
             WHERE mr.patient_id = a.patient_id
               AND mr.doctor_id = a.doctor_id
               AND DATE(mr.record_date) = DATE(a.appt_date)
             LIMIT 1
           ) AS record_id
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.user_id
    WHERE a.doctor_id = $doctor_id
    ORDER BY a.appt_date DESC
");

function statusPill($s) {
    $map = ['Confirmed'=>'green','Pending'=>'amber','Completed'=>'blue','Cancelled'=>'red'];
    return '<span class="pill pill-'.($map[$s]??'amber').'">'.htmlspecialchars($s).'</span>';
}
function initials($name) {
    $parts = explode(' ', $name);
    return strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
}
$colors = ['#1A6B4A','#1E40AF','#B45309','#7C3AED','#C0392B'];
function avatarColor($name) { global $colors; return $colors[crc32($name) % count($colors)]; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Appointments — MediCare HMS</title>
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.action-btn { background: none; border: 1px solid var(--c-border); padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; color: var(--c-text); transition: 0.2s; }
.action-btn:hover { background: var(--c-surface2); }
.action-btn.confirm:hover { border-color: #10B981; color: #10B981; }
.action-btn.cancel:hover { border-color: #EF4444; color: #EF4444; }
.action-btn.complete:hover { border-color: #3B82F6; color: #3B82F6; }
.action-btn.record:hover { border-color: #8B5CF6; color: #8B5CF6; }
.doctor-page-head { display:flex; justify-content:space-between; gap:1rem; align-items:flex-end; margin-bottom:1.5rem; flex-wrap:wrap; }
.doctor-page-head p { color:var(--muted-foreground); font-size:14px; margin-top:6px; }
.doctor-stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.doctor-stat { background:var(--card); border:1px solid var(--border); border-radius:16px; padding:1rem; box-shadow:var(--shadow-sm); }
.doctor-stat span { display:block; color:var(--muted-foreground); font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; }
.doctor-stat strong { display:block; font-family:'Calistoga',serif; font-size:2rem; line-height:1.1; margin-top:6px; }
.table-tools { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1rem; flex-wrap:wrap; }
.status-filter { max-width:220px; height:42px; border-radius:10px; }
.patient-cell { display:flex; align-items:center; gap:10px; min-width:220px; }
.row-actions { display:flex; justify-content:flex-end; gap:6px; flex-wrap:wrap; }
</style>
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>My Appointments</h1>
    </div>
    <div class="admin-content">
      <div class="doctor-page-head">
        <div>
          <h2 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.25rem;margin:0">Appointment Worklist</h2>
          <p>Review patient requests, confirm schedules, complete visits, and add records after consultations.</p>
        </div>
        <a href="calendar.php" class="btn btn-outline btn-sm">Open Calendar</a>
      </div>

      <div class="doctor-stat-grid">
        <div class="doctor-stat"><span>Total</span><strong><?= $summary['total'] ?></strong></div>
        <div class="doctor-stat"><span>Today</span><strong><?= $summary['today'] ?></strong></div>
        <div class="doctor-stat"><span>Pending</span><strong><?= $summary['pending'] ?></strong></div>
        <div class="doctor-stat"><span>Upcoming</span><strong><?= $summary['upcoming'] ?></strong></div>
      </div>

      <?php if (isset($_GET['msg']) && $_GET['msg'] === 'email_sent'): ?>
      <script>
        Swal.fire({
          icon: 'success',
          title: 'Status Updated',
          text: 'An email notification has been sent to the patient!',
          timer: 3000,
          showConfirmButton: false
        });
      </script>
      <?php elseif (isset($_GET['msg'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['msg']) ?></div>
      <?php endif; ?>

      <div class="card" style="padding: 2.5rem;">
        <div class="table-tools">
          <div>
            <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;margin:0">All Appointments</h3>
            <span class="text-muted text-sm">Use the actions at the right to move each appointment forward.</span>
          </div>
          <select id="statusFilter" class="form-input status-filter" aria-label="Filter appointments by status">
            <option value="">All statuses</option>
            <option value="Pending">Pending</option>
            <option value="Confirmed">Confirmed</option>
            <option value="Completed">Completed</option>
            <option value="Cancelled">Cancelled</option>
          </select>
        </div>
        <table id="docApptTable" class="table">
          <thead>
            <tr>
              <th>Patient</th>
              <th>Date & Time</th>
              <th>Status</th>
              <th>Notes</th>
              <th style="text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="avatar avatar-sm" style="background:<?= avatarColor($row['patient_name']) ?>;<?= $row['profile_pic'] ? 'background-image:url(../uploads/'.htmlspecialchars($row['profile_pic']).');background-size:cover;background-position:center;color:transparent' : '' ?>"><?= $row['profile_pic'] ? '' : initials($row['patient_name']) ?></div>
                  <div>
                    <strong><?= htmlspecialchars($row['patient_name']) ?></strong><br>
                    <small style="color:var(--muted-foreground)"><?= htmlspecialchars($row['contact'] ?: $row['email']) ?></small>
                  </div>
                </div>
              </td>
              <td>
                <?= date('M j, Y', strtotime($row['appt_date'])) ?><br>
                <small style="color:var(--muted-foreground)"><?= date('h:i A', strtotime($row['appt_date'])) ?></small>
              </td>
              <td><?= statusPill($row['status']) ?></td>
              <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($row['notes']) ?>">
                <?= htmlspecialchars($row['notes'] ?: '-') ?>
              </td>
              <td style="text-align:right">
                <form method="POST" class="row-actions">
                  <input type="hidden" name="appt_id" value="<?= $row['appt_id'] ?>">
                  
                  <?php if ($row['status'] === 'Pending'): ?>
                    <button type="submit" name="action" value="confirm" class="action-btn confirm">Confirm</button>
                    <button type="submit" name="action" value="cancel" class="action-btn cancel">Cancel</button>
                  <?php elseif ($row['status'] === 'Confirmed'): ?>
                    <button type="submit" name="action" value="complete" class="action-btn complete">Complete</button>
                    <button type="submit" name="action" value="cancel" class="action-btn cancel">Cancel</button>
                  <?php elseif ($row['status'] === 'Completed'): ?>
                    <?php if (!$row['record_id']): ?>
                      <a href="add_record.php?patient_id=<?= $row['patient_id'] ?>&appt_id=<?= $row['appt_id'] ?>" class="action-btn record" style="text-decoration:none">Add Record</a>
                    <?php else: ?>
                      <a href="patient_history.php?patient_id=<?= $row['patient_id'] ?>" class="action-btn record" style="text-decoration:none">View Record</a>
                    <?php endif; ?>
                  <?php else: ?>
                    <a href="patient_history.php?patient_id=<?= $row['patient_id'] ?>" class="action-btn record" style="text-decoration:none">History</a>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    var table = $('#docApptTable').DataTable({
        "order": [[1, "desc"]],
        "language": {
            "lengthMenu": "Rows per page _MENU_",
            "search": "Search",
            "info": "Showing _START_ to _END_ of _TOTAL_ appointments",
            "infoEmpty": "No appointments to show",
            "infoFiltered": "(filtered from _MAX_ total)",
            "emptyTable": "No appointments found."
        }
    });
    $('#statusFilter').on('change', function() {
        table.column(2).search(this.value).draw();
    });
});
</script>
</body>
</html>
