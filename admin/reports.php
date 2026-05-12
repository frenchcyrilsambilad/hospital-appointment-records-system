<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: ../index.php'); exit; }

function fetchRows(mysqli_result $result): array {
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function scalarQuery(mysqli $conn, string $sql): int {
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

function statusPill(string $status): string {
    $map = ['Confirmed'=>'green','Pending'=>'amber','Completed'=>'blue','Cancelled'=>'red'];
    $color = $map[$status] ?? 'amber';
    return '<span class="pill pill-' . $color . '">' . htmlspecialchars($status) . '</span>';
}

function excerpt(?string $text, int $limit): string {
    $text = trim((string)$text);
    if ($text === '') {
        return 'None';
    }
    return strlen($text) > $limit ? substr($text, 0, $limit - 3) . '...' : $text;
}

$totalPatients = scalarQuery($conn, "SELECT COUNT(*) AS c FROM patients");
$totalDoctors = scalarQuery($conn, "SELECT COUNT(*) AS c FROM doctors");
$totalDepartments = scalarQuery($conn, "SELECT COUNT(*) AS c FROM departments");
$totalAppointments = scalarQuery($conn, "SELECT COUNT(*) AS c FROM appointments");
$appointmentsToday = scalarQuery($conn, "SELECT COUNT(*) AS c FROM appointments WHERE DATE(appt_date) = CURDATE()");
$pendingAppointments = scalarQuery($conn, "SELECT COUNT(*) AS c FROM appointments WHERE status = 'Pending'");
$completedAppointments = scalarQuery($conn, "SELECT COUNT(*) AS c FROM appointments WHERE status = 'Completed'");
$recordsThisMonth = scalarQuery($conn, "
    SELECT COUNT(*) AS c
    FROM medical_records
    WHERE record_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
");
$patientsWithRecords = scalarQuery($conn, "SELECT COUNT(DISTINCT patient_id) AS c FROM medical_records");
$recordCoverage = $totalPatients > 0 ? round(($patientsWithRecords / $totalPatients) * 100) : 0;

$statusRows = fetchRows($conn->query("
    SELECT status, COUNT(*) AS total
    FROM appointments
    GROUP BY status
    ORDER BY FIELD(status, 'Pending', 'Confirmed', 'Completed', 'Cancelled')
"));
$maxStatus = max(1, ...array_map(fn($row) => (int)$row['total'], $statusRows ?: [['total' => 1]]));

$departmentRows = fetchRows($conn->query("
    SELECT d.dept_name,
           COUNT(DISTINCT doc.doctor_id) AS doctor_count,
           COUNT(a.appt_id) AS appointment_count,
           SUM(CASE WHEN a.status = 'Completed' THEN 1 ELSE 0 END) AS completed_count,
           SUM(CASE WHEN a.status = 'Pending' THEN 1 ELSE 0 END) AS pending_count
    FROM departments d
    LEFT JOIN doctors doc ON d.dept_id = doc.dept_id
    LEFT JOIN appointments a ON doc.doctor_id = a.doctor_id
    GROUP BY d.dept_id, d.dept_name
    ORDER BY appointment_count DESC, d.dept_name ASC
"));
$maxDepartmentAppointments = max(1, ...array_map(fn($row) => (int)$row['appointment_count'], $departmentRows ?: [['appointment_count' => 1]]));

$topDoctorRows = fetchRows($conn->query("
    SELECT u.name AS doctor_name,
           d.dept_name,
           doc.specialization,
           COUNT(a.appt_id) AS appointment_count,
           SUM(CASE WHEN a.status = 'Completed' THEN 1 ELSE 0 END) AS completed_count,
           SUM(CASE WHEN a.status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
           MAX(a.appt_date) AS latest_appointment
    FROM doctors doc
    JOIN users u ON doc.user_id = u.user_id
    JOIN departments d ON doc.dept_id = d.dept_id
    LEFT JOIN appointments a
      ON doc.doctor_id = a.doctor_id
     AND a.appt_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
     AND a.appt_date < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
    GROUP BY doc.doctor_id, u.name, d.dept_name, doc.specialization
    HAVING appointment_count > 0
    ORDER BY appointment_count DESC, completed_count DESC, doctor_name ASC
    LIMIT 10
"));
$maxDoctorAppointments = max(1, ...array_map(fn($row) => (int)$row['appointment_count'], $topDoctorRows ?: [['appointment_count' => 1]]));

$patientCoverageRows = fetchRows($conn->query("
    SELECT u.name AS patient_name,
           u.email,
           p.contact,
           COUNT(DISTINCT a.appt_id) AS appointment_count,
           COUNT(DISTINCT m.record_id) AS record_count,
           MAX(a.appt_date) AS latest_appointment
    FROM patients p
    JOIN users u ON p.user_id = u.user_id
    LEFT JOIN appointments a ON p.patient_id = a.patient_id
    LEFT JOIN medical_records m ON p.patient_id = m.patient_id
    GROUP BY p.patient_id, u.name, u.email, p.contact
    ORDER BY record_count ASC, appointment_count ASC, u.name ASC
"));

$idleDoctorRows = fetchRows($conn->query("
    SELECT u.name AS doctor_name, d.dept_name, doc.specialization, doc.schedule
    FROM doctors doc
    JOIN users u ON doc.user_id = u.user_id
    JOIN departments d ON doc.dept_id = d.dept_id
    WHERE NOT EXISTS (
        SELECT 1
        FROM appointments a
        WHERE a.doctor_id = doc.doctor_id
    )
    ORDER BY d.dept_name, u.name
"));

$recentRecordRows = fetchRows($conn->query("
    SELECT mr.record_date,
           pu.name AS patient_name,
           du.name AS doctor_name,
           d.dept_name,
           mr.diagnosis,
           mr.prescription
    FROM medical_records mr
    JOIN patients p ON mr.patient_id = p.patient_id
    JOIN users pu ON p.user_id = pu.user_id
    JOIN doctors doc ON mr.doctor_id = doc.doctor_id
    JOIN users du ON doc.user_id = du.user_id
    JOIN departments d ON doc.dept_id = d.dept_id
    ORDER BY mr.record_date DESC, mr.record_id DESC
    LIMIT 8
"));

$monthlyRows = fetchRows($conn->query("
    SELECT DATE_FORMAT(appt_date, '%Y-%m') AS month_key,
           DATE_FORMAT(appt_date, '%b %Y') AS month_label,
           COUNT(*) AS appointment_count,
           SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_count,
           SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_count
    FROM appointments
    WHERE appt_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
"));
$maxMonthlyAppointments = max(1, ...array_map(fn($row) => (int)$row['appointment_count'], $monthlyRows ?: [['appointment_count' => 1]]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Reports - MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Reports</h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">&#128276;<span class="dot"></span></div>
      </div>
    </div>
    <div class="admin-content reports-content">

      <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-label">Patients</div>
          <div class="stat-value"><?= $totalPatients ?></div>
          <div class="stat-delta"><?= $recordCoverage ?>% have records</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Doctors</div>
          <div class="stat-value"><?= $totalDoctors ?></div>
          <div class="stat-delta"><?= $totalDepartments ?> departments</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Appointments</div>
          <div class="stat-value"><?= $totalAppointments ?></div>
          <div class="stat-delta"><?= $appointmentsToday ?> today</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Pending review</div>
          <div class="stat-value"><?= $pendingAppointments ?></div>
          <div class="stat-delta" style="background:var(--c-amber-light); color:#92400E"><?= $completedAppointments ?> completed</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Records this month</div>
          <div class="stat-value"><?= $recordsThisMonth ?></div>
          <div class="stat-delta">Medical records added</div>
        </div>
      </div>

      <div class="two-col mb-6">
        <div class="card report-card">
          <div class="card-head">
            <h3>Appointment status mix</h3>
            <span class="pill pill-blue">Aggregate</span>
          </div>
          <div class="subtitle">Current totals from the appointment workflow.</div>
          <?php if (empty($statusRows)): ?>
            <p class="text-muted text-sm">No appointments have been booked yet.</p>
          <?php else: ?>
            <table class="data-table">
              <thead><tr><th>Status</th><th>Total</th><th>Share</th></tr></thead>
              <tbody>
              <?php foreach ($statusRows as $row): ?>
                <tr>
                  <td><?= statusPill($row['status']) ?></td>
                  <td style="font-weight:700"><?= (int)$row['total'] ?></td>
                  <td style="width:45%">
                    <div class="volume-bar"><div class="volume-bar-fill" style="width:<?= ((int)$row['total'] / $maxStatus) * 100 ?>%"></div></div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <div class="card report-card">
          <div class="card-head">
            <h3>Monthly appointment trend</h3>
            <span class="pill pill-green">Last 6 months</span>
          </div>
          <div class="subtitle">Bookings grouped by appointment month, including outcomes.</div>
          <?php if (empty($monthlyRows)): ?>
            <p class="text-muted text-sm">No monthly appointment activity yet.</p>
          <?php else: ?>
            <table class="data-table">
              <thead><tr><th>Month</th><th>Total</th><th>Completed</th><th>Cancelled</th><th>Volume</th></tr></thead>
              <tbody>
              <?php foreach ($monthlyRows as $row): ?>
                <tr>
                  <td style="font-weight:500"><?= htmlspecialchars($row['month_label']) ?></td>
                  <td><?= (int)$row['appointment_count'] ?></td>
                  <td><span class="pill pill-blue"><?= (int)$row['completed_count'] ?></span></td>
                  <td><span class="pill pill-red"><?= (int)$row['cancelled_count'] ?></span></td>
                  <td style="width:28%">
                    <div class="volume-bar"><div class="volume-bar-fill" style="width:<?= ((int)$row['appointment_count'] / $maxMonthlyAppointments) * 100 ?>%"></div></div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <div class="card report-card">
        <div class="card-head">
          <h3>Department workload</h3>
          <span class="pill pill-blue">LEFT JOIN</span>
        </div>
        <div class="subtitle">All departments are included, even departments with doctors or appointments still at zero.</div>
        <table class="data-table">
          <thead><tr><th>Department</th><th>Doctors</th><th>Appointments</th><th>Completed</th><th>Pending</th><th>Load</th></tr></thead>
          <tbody>
          <?php foreach ($departmentRows as $row): ?>
            <tr>
              <td style="font-weight:500"><?= htmlspecialchars($row['dept_name']) ?></td>
              <td><?= (int)$row['doctor_count'] ?></td>
              <td><span class="pill pill-blue"><?= (int)$row['appointment_count'] ?></span></td>
              <td><?= (int)$row['completed_count'] ?></td>
              <td><?= (int)$row['pending_count'] ?></td>
              <td style="width:28%">
                <div class="volume-bar"><div class="volume-bar-fill" style="width:<?= ((int)$row['appointment_count'] / $maxDepartmentAppointments) * 100 ?>%"></div></div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card report-card">
        <div class="card-head">
          <h3>Top doctors this month</h3>
          <span class="pill pill-amber">HAVING</span>
        </div>
        <div class="subtitle">Current-month workload with completed and cancelled counts.</div>
        <?php if (empty($topDoctorRows)): ?>
          <p class="text-muted text-sm">No doctors have appointments scheduled this month.</p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th>Doctor</th><th>Department</th><th>Specialization</th><th>Total</th><th>Completed</th><th>Cancelled</th><th>Latest</th><th>Volume</th></tr></thead>
            <tbody>
            <?php foreach ($topDoctorRows as $row): ?>
              <tr>
                <td style="font-weight:500"><?= htmlspecialchars($row['doctor_name']) ?></td>
                <td><?= htmlspecialchars($row['dept_name']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($row['specialization']) ?></td>
                <td><span class="pill pill-green"><?= (int)$row['appointment_count'] ?></span></td>
                <td><?= (int)$row['completed_count'] ?></td>
                <td><?= (int)$row['cancelled_count'] ?></td>
                <td><?= $row['latest_appointment'] ? date('M j, Y', strtotime($row['latest_appointment'])) : 'None' ?></td>
                <td style="width:22%">
                  <div class="volume-bar"><div class="volume-bar-fill" style="width:<?= ((int)$row['appointment_count'] / $maxDoctorAppointments) * 100 ?>%"></div></div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div class="two-col mb-6">
        <div class="card report-card">
          <div class="card-head">
            <h3>Patient record gaps</h3>
            <span class="pill pill-blue">Subquery coverage</span>
          </div>
          <div class="subtitle">All patients with appointment and medical-record coverage.</div>
          <?php if (empty($patientCoverageRows)): ?>
            <p class="text-muted text-sm">No patients are registered yet.</p>
          <?php else: ?>
            <table class="data-table">
              <thead><tr><th>Patient</th><th>Email</th><th>Appointments</th><th>Records</th><th>Last visit</th><th>Status</th></tr></thead>
              <tbody>
              <?php foreach ($patientCoverageRows as $row): ?>
                <tr>
                  <td style="font-weight:500"><?= htmlspecialchars($row['patient_name']) ?></td>
                  <td class="text-muted"><?= htmlspecialchars($row['email']) ?></td>
                  <td><?= (int)$row['appointment_count'] ?></td>
                  <td><span class="pill <?= (int)$row['record_count'] === 0 ? 'pill-amber' : 'pill-blue' ?>"><?= (int)$row['record_count'] ?></span></td>
                  <td><?= $row['latest_appointment'] ? date('M j, Y', strtotime($row['latest_appointment'])) : 'No visit' ?></td>
                  <td>
                    <?php if ((int)$row['appointment_count'] === 0): ?>
                      <span class="pill pill-red">No visits</span>
                    <?php elseif ((int)$row['record_count'] === 0): ?>
                      <span class="pill pill-amber">Needs record</span>
                    <?php else: ?>
                      <span class="pill pill-green">Covered</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <div class="card report-card">
          <div class="card-head">
            <h3>Doctors without bookings</h3>
            <span class="pill pill-red">NOT EXISTS</span>
          </div>
          <div class="subtitle">Useful after adding new doctors and departments.</div>
          <?php if (empty($idleDoctorRows)): ?>
            <p class="text-muted text-sm">Every doctor has at least one appointment.</p>
          <?php else: ?>
            <table class="data-table">
              <thead><tr><th>Doctor</th><th>Department</th><th>Specialization</th><th>Schedule</th></tr></thead>
              <tbody>
              <?php foreach ($idleDoctorRows as $row): ?>
                <tr>
                  <td style="font-weight:500"><?= htmlspecialchars($row['doctor_name']) ?></td>
                  <td><?= htmlspecialchars($row['dept_name']) ?></td>
                  <td class="text-muted"><?= htmlspecialchars($row['specialization']) ?></td>
                  <td><?= htmlspecialchars($row['schedule'] ?: 'Not set') ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <div class="card report-card">
        <div class="card-head">
          <h3>Recent medical records</h3>
          <span class="pill pill-blue">Latest</span>
        </div>
        <div class="subtitle">Records now come from completed admin appointments and doctor-entered patient visits.</div>
        <?php if (empty($recentRecordRows)): ?>
          <p class="text-muted text-sm">No medical records have been added yet.</p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th>Date</th><th>Patient</th><th>Doctor</th><th>Department</th><th>Diagnosis</th><th>Prescription</th></tr></thead>
            <tbody>
            <?php foreach ($recentRecordRows as $row): ?>
              <tr>
                <td><?= date('M j, Y', strtotime($row['record_date'])) ?></td>
                <td style="font-weight:500"><?= htmlspecialchars($row['patient_name']) ?></td>
                <td><?= htmlspecialchars($row['doctor_name']) ?></td>
                <td><?= htmlspecialchars($row['dept_name']) ?></td>
                <td><?= htmlspecialchars(excerpt($row['diagnosis'], 80)) ?></td>
                <td class="text-muted"><?= htmlspecialchars(excerpt($row['prescription'], 70)) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>
</body>
</html>
