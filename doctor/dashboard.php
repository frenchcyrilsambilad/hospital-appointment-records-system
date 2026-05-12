<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') { header('Location: ../index.php'); exit; }

$doctor_id = (int)$_SESSION['doctor_id'];

// Stats for this doctor
$apptToday = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = $doctor_id AND DATE(appt_date) = CURDATE()")->fetch_assoc()['c'];
$pendingReview = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = $doctor_id AND status = 'Pending'")->fetch_assoc()['c'];
$completedAppts = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = $doctor_id AND status = 'Completed'")->fetch_assoc()['c'];
$uniquePatients = $conn->query("SELECT COUNT(DISTINCT patient_id) as c FROM appointments WHERE doctor_id = $doctor_id")->fetch_assoc()['c'];

// Today's appointments for this doctor
$todayAppts = $conn->query("
    SELECT a.appt_id, u.name AS patient_name, a.appt_date, a.status, p.contact
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.user_id
    WHERE a.doctor_id = $doctor_id AND DATE(a.appt_date) = CURDATE()
    ORDER BY a.appt_date ASC
");

// --- DATA FOR CHARTS ---

// 1. Patient Load Trends (Last 14 Days)
$trendQuery = $conn->query("
    SELECT DATE(appt_date) as date, COUNT(*) as count 
    FROM appointments 
    WHERE doctor_id = $doctor_id AND appt_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    GROUP BY DATE(appt_date)
    ORDER BY date ASC
");
$trendLabels = [];
$trendData = [];
while ($row = $trendQuery->fetch_assoc()) {
    $trendLabels[] = date('M j', strtotime($row['date']));
    $trendData[] = (int)$row['count'];
}

// 2. Appointment Status Distribution
$statusQuery = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM appointments 
    WHERE doctor_id = $doctor_id
    GROUP BY status
");
$statusLabels = [];
$statusData = [];
while ($row = $statusQuery->fetch_assoc()) {
    $statusLabels[] = $row['status'];
    $statusData[] = (int)$row['count'];
}

function statusPill($s) {
    $map = ['Confirmed'=>'green','Pending'=>'amber','Completed'=>'blue','Cancelled'=>'red'];
    return '<span class="pill pill-'.($map[$s]??'amber').'">'.htmlspecialchars($s).'</span>';
}
function initials($name) {
    $parts = explode(' ', $name);
    return strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
}
$colors = ['#6366F1','#EC4899','#B45309','#10B981','#F59E0B'];
function avatarColor($name) { global $colors; return $colors[crc32($name) % count($colors)]; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Doctor Dashboard — MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Welcome, Dr. <?= htmlspecialchars($_SESSION['name']) ?></h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">🔔<span class="dot"></span></div>
      </div>
    </div>
    <div class="admin-content">
      <div class="flex justify-between items-center mb-6">
        <div style="background: var(--card); padding: 16px; border-radius: 16px; font-size: 13px; color: var(--muted-foreground); border: 1px solid var(--border); box-shadow: var(--shadow-sm); width: 100%;">
          <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px; color:var(--foreground); font-weight:700;">
            <span style="font-size:18px;">🔑</span> System Sample Accounts
          </div>
          <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
            <div>Admin: <strong>admin</strong> / <strong>admin123</strong></div>
            <div>Doctor: <strong>emily@medicare.com</strong> / <strong>doctor123</strong></div>
            <div>Patient: <strong>alice@email.com</strong> / <strong>patient123</strong></div>
          </div>
        </div>
      </div>
      <!-- Stat cards -->
      <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-label">Today's Appointments</div>
            <div class="stat-value"><?= $apptToday ?></div>
            <div class="stat-delta" style="background:rgba(99,102,241,0.08); color:var(--accent)">Scheduled</div>
            <div style="position:absolute; bottom:-10px; right:-10px; opacity:0.05; font-size:80px">📅</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending Requests</div>
            <div class="stat-value"><?= $pendingReview ?></div>
            <div class="stat-delta" style="background:var(--c-amber-light); color:#92400E">Needs Action</div>
            <div style="position:absolute; bottom:-10px; right:-10px; opacity:0.05; font-size:80px">⏳</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Completed Visits</div>
            <div class="stat-value"><?= $completedAppts ?></div>
            <div class="stat-delta" style="background:rgba(16,185,129,0.08); color:#065F46">Total</div>
            <div style="position:absolute; bottom:-10px; right:-10px; opacity:0.05; font-size:80px">✅</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Your Patients</div>
            <div class="stat-value"><?= $uniquePatients ?></div>
            <div class="stat-delta" style="background:rgba(236,72,153,0.08); color:var(--accent-secondary)">Unique</div>
            <div style="position:absolute; bottom:-10px; right:-10px; opacity:0.05; font-size:80px">👥</div>
        </div>
      </div>

      <div class="two-col mb-6">
        <!-- Patient Trends -->
        <div class="card">
            <div class="card-head">
                <h3>Patient Load Trend</h3>
                <span class="text-muted text-sm">Last 14 days</span>
            </div>
            <div style="height: 300px; width: 100%; margin-top: 1rem;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
        
        <!-- Status Distribution -->
        <div class="card">
            <div class="card-head">
                <h3>Appointment Outcome</h3>
                <span class="text-muted text-sm">Personal Statistics</span>
            </div>
            <div style="height: 300px; width: 100%; margin-top: 1rem; display: flex; justify-content: center;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head">
            <h3>Today's Schedule</h3>
            <a href="calendar.php" class="btn btn-ghost btn-sm">Full Calendar</a>
        </div>
        <?php if ($todayAppts->num_rows > 0): ?>
          <table class="table">
            <thead>
              <tr>
                <th>Patient</th>
                <th>Time</th>
                <th>Contact</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $todayAppts->fetch_assoc()): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:12px">
                    <div class="avatar avatar-sm" style="background:<?= avatarColor($row['patient_name']) ?>"><?= initials($row['patient_name']) ?></div>
                    <div style="display:flex; flex-direction:column;">
                        <strong><?= htmlspecialchars($row['patient_name']) ?></strong>
                        <span class="text-muted text-sm">ID: #<?= $row['appt_id'] ?></span>
                    </div>
                  </div>
                </td>
                <td><div style="font-weight:600; color:var(--accent)"><?= date('h:i A', strtotime($row['appt_date'])) ?></div></td>
                <td><span class="text-muted"><?= htmlspecialchars($row['contact'] ?? 'N/A') ?></span></td>
                <td><?= statusPill($row['status']) ?></td>
                <td>
                  <a href="appointments.php" class="btn btn-edit btn-sm">Review</a>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div style="padding: 3rem; text-align: center;">
            <div style="font-size: 48px; margin-bottom: 1rem;">☕</div>
            <h4 style="color: var(--foreground)">No appointments today</h4>
            <p class="text-muted">Take a break or review your patient history.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
// Chart Configuration
const trendCtx = document.getElementById('trendChart').getContext('2d');
const statusCtx = document.getElementById('statusChart').getContext('2d');

const purple = '#6366F1';
const green = '#10B981';
const blue = '#3B82F6';
const red = '#EF4444';
const amber = '#F59E0B';

// 1. Trend Chart
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($trendLabels) ?>,
        datasets: [{
            label: 'Patients',
            data: <?= json_encode($trendData) ?>,
            borderColor: purple,
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            fill: true,
            tension: 0.4,
            borderWidth: 3,
            pointBackgroundColor: purple,
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { display: false } },
            x: { grid: { display: false } }
        }
    }
});

// 2. Status Chart
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($statusLabels) ?>,
        datasets: [{
            data: <?= json_encode($statusData) ?>,
            backgroundColor: [amber, green, blue, red],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 20, font: { family: 'Plus Jakarta Sans', weight: '600' }, usePointStyle: true }
            }
        }
    }
});
</script>
</body>
</html>

