<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') { header('Location: ../index.php'); exit; }

$doctor_id = (int)$_SESSION['doctor_id'];

$patientStats = [
    'total' => 0,
    'active' => 0,
    'completed' => 0,
    'records' => 0,
];
$patientStats['total'] = (int)$conn->query("SELECT COUNT(DISTINCT patient_id) AS c FROM appointments WHERE doctor_id = $doctor_id")->fetch_assoc()['c'];
$patientStats['active'] = (int)$conn->query("SELECT COUNT(DISTINCT patient_id) AS c FROM appointments WHERE doctor_id = $doctor_id AND status IN ('Pending','Confirmed')")->fetch_assoc()['c'];
$patientStats['completed'] = (int)$conn->query("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = $doctor_id AND status = 'Completed'")->fetch_assoc()['c'];
$patientStats['records'] = (int)$conn->query("SELECT COUNT(*) AS c FROM medical_records WHERE doctor_id = $doctor_id")->fetch_assoc()['c'];

// Get all patients that have an appointment with this doctor
$result = $conn->query("
    SELECT p.patient_id, u.name, u.email, u.profile_pic, p.contact, p.gender, p.birthdate,
           COUNT(a.appt_id) AS total_appointments,
           SUM(CASE WHEN a.status = 'Completed' THEN 1 ELSE 0 END) AS completed_visits,
           MAX(CASE WHEN a.status = 'Completed' THEN a.appt_date ELSE NULL END) AS last_visit,
           MIN(CASE WHEN a.appt_date >= NOW() AND a.status IN ('Pending','Confirmed') THEN a.appt_date ELSE NULL END) AS next_visit,
           (
             SELECT COUNT(*)
             FROM medical_records mr
             WHERE mr.patient_id = p.patient_id AND mr.doctor_id = $doctor_id
           ) AS record_count
    FROM patients p
    JOIN users u ON p.user_id = u.user_id
    JOIN appointments a ON p.patient_id = a.patient_id
    WHERE a.doctor_id = $doctor_id
    GROUP BY p.patient_id, u.name, u.email, u.profile_pic, p.contact, p.gender, p.birthdate
    ORDER BY u.name ASC
");

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
<title>My Patients — MediCare HMS</title>
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.doctor-page-head { display:flex; justify-content:space-between; gap:1rem; align-items:flex-end; margin-bottom:1.5rem; flex-wrap:wrap; }
.doctor-page-head p { color:var(--muted-foreground); font-size:14px; margin-top:6px; }
.doctor-stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.doctor-stat { background:var(--card); border:1px solid var(--border); border-radius:16px; padding:1rem; box-shadow:var(--shadow-sm); }
.doctor-stat span { display:block; color:var(--muted-foreground); font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; }
.doctor-stat strong { display:block; font-family:'Calistoga',serif; font-size:2rem; line-height:1.1; margin-top:6px; }
.patient-actions { display:flex; gap:8px; flex-wrap:wrap; }
.visit-meta { display:flex; flex-direction:column; gap:4px; min-width:180px; }
.visit-meta small { color:var(--muted-foreground); font-size:12px; }
</style>
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>My Patients</h1>
    </div>
    <div class="admin-content">
      <div class="doctor-page-head">
        <div>
          <h2 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.25rem;margin:0">Patient Panel</h2>
          <p>Patients appear here after they book with you. Open a chart, review history, or add a clinical record.</p>
        </div>
        <a href="appointments.php" class="btn btn-outline btn-sm">Review Appointments</a>
      </div>

      <div class="doctor-stat-grid">
        <div class="doctor-stat"><span>Total Patients</span><strong><?= $patientStats['total'] ?></strong></div>
        <div class="doctor-stat"><span>Active Cases</span><strong><?= $patientStats['active'] ?></strong></div>
        <div class="doctor-stat"><span>Completed Visits</span><strong><?= $patientStats['completed'] ?></strong></div>
        <div class="doctor-stat"><span>Records Written</span><strong><?= $patientStats['records'] ?></strong></div>
      </div>

      <div class="card" style="padding: 2.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem;flex-wrap:wrap">
          <div>
            <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;margin:0">Patient Directory</h3>
            <span class="text-muted text-sm">Search by name, email, contact number, or visit status.</span>
          </div>
        </div>
        <table id="docPatientsTable" class="table">
          <thead>
            <tr>
              <th>Patient Name</th>
              <th>Contact Info</th>
              <th>Demographics</th>
              <th>Visits</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="avatar avatar-sm" style="background:<?= avatarColor($row['name']) ?>;<?= $row['profile_pic'] ? 'background-image:url(../uploads/'.htmlspecialchars($row['profile_pic']).');background-size:cover;background-position:center;color:transparent' : '' ?>"><?= $row['profile_pic'] ? '' : initials($row['name']) ?></div>
                  <div>
                    <strong><?= htmlspecialchars($row['name']) ?></strong><br>
                    <small style="color:var(--muted-foreground)">Patient #<?= $row['patient_id'] ?></small>
                  </div>
                </div>
              </td>
              <td>
                <div><?= htmlspecialchars($row['email']) ?></div>
                <small style="color:var(--muted-foreground)"><?= htmlspecialchars($row['contact'] ?? 'N/A') ?></small>
              </td>
              <td>
                <?= htmlspecialchars($row['gender'] ?? 'N/A') ?><br>
                <small style="color:var(--muted-foreground)">DOB: <?= $row['birthdate'] ? date('M j, Y', strtotime($row['birthdate'])) : 'N/A' ?></small>
              </td>
              <td>
                <div class="visit-meta">
                  <strong><?= (int)$row['total_appointments'] ?> appointment<?= (int)$row['total_appointments'] === 1 ? '' : 's' ?></strong>
                  <small><?= (int)$row['completed_visits'] ?> completed, <?= (int)$row['record_count'] ?> record<?= (int)$row['record_count'] === 1 ? '' : 's' ?></small>
                  <small>Last: <?= $row['last_visit'] ? date('M j, Y', strtotime($row['last_visit'])) : 'No completed visit' ?></small>
                  <small>Next: <?= $row['next_visit'] ? date('M j, Y g:i A', strtotime($row['next_visit'])) : 'None scheduled' ?></small>
                </div>
              </td>
              <td>
                <div class="patient-actions">
                  <a href="patient_history.php?patient_id=<?= $row['patient_id'] ?>" class="btn btn-edit btn-sm">History</a>
                  <a href="add_record.php?patient_id=<?= $row['patient_id'] ?>" class="btn btn-primary btn-sm">Add Record</a>
                </div>
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
    $('#docPatientsTable').DataTable({
        "order": [[0, "asc"]],
        "language": {
            "lengthMenu": "Rows per page _MENU_",
            "search": "Search",
            "info": "Showing _START_ to _END_ of _TOTAL_ patients",
            "infoEmpty": "No patients to show",
            "infoFiltered": "(filtered from _MAX_ total)",
            "emptyTable": "No patients yet. Confirm or complete appointments first, then they will appear here."
        }
    });
});
</script>
</body>
</html>
