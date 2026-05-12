<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: ../index.php'); exit; }

// GROUP BY + COUNT — stat cards
$totalPatients = $conn->query("SELECT COUNT(*) as c FROM patients")->fetch_assoc()['c'];
$newPatientsWeek = $conn->query("SELECT COUNT(*) as c FROM patients p JOIN users u ON p.user_id=u.user_id WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];

$apptToday = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE DATE(appt_date) = CURDATE()")->fetch_assoc()['c'];
$totalAppointments = $conn->query("SELECT COUNT(*) as c FROM appointments")->fetch_assoc()['c'];

$pendingReview = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status = 'Pending'")->fetch_assoc()['c'];

$activeDoctors = $conn->query("SELECT COUNT(*) as c FROM doctors")->fetch_assoc()['c'];
$recordsThisMonth = $conn->query("
    SELECT COUNT(*) as c
    FROM medical_records
    WHERE record_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
")->fetch_assoc()['c'];

// Recent appointments
$recentAppts = $conn->query("
    SELECT a.appt_id, u.name AS patient_name, du.name AS doctor_name,
           d.dept_name, a.appt_date, a.status
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.user_id
    JOIN doctors doc ON a.doctor_id = doc.doctor_id
    JOIN users du ON doc.user_id = du.user_id
    JOIN departments d ON doc.dept_id = d.dept_id
    ORDER BY a.appt_date DESC LIMIT 6
");

// --- DATA FOR CHARTS ---

// 1. Appointment Trends (Last 14 Days)
$trendQuery = $conn->query("
    SELECT DATE(appt_date) as date, COUNT(*) as count 
    FROM appointments 
    WHERE appt_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    GROUP BY DATE(appt_date)
    ORDER BY date ASC
");
$trendLabels = [];
$trendData = [];
while ($row = $trendQuery->fetch_assoc()) {
    $trendLabels[] = date('M j', strtotime($row['date']));
    $trendData[] = (int)$row['count'];
}

// 2. Status Distribution
$statusQuery = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM appointments 
    GROUP BY status
");
$statusLabels = [];
$statusData = [];
while ($row = $statusQuery->fetch_assoc()) {
    $statusLabels[] = $row['status'];
    $statusData[] = (int)$row['count'];
}

$pendingBadge = $pendingReview;
$now = new DateTimeImmutable();
$nextReportAt = $now->modify('tomorrow')->setTime(0, 0);
$nextReportDiff = $now->diff($nextReportAt);
$nextReportText = sprintf('%02d hours %02d minutes', ($nextReportDiff->days * 24) + $nextReportDiff->h, $nextReportDiff->i);

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
<title>Dashboard — MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Dashboard</h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">🔔<span class="dot"></span></div>
      </div>
    </div>
    <div class="admin-content">
      <!-- Stat cards -->
      <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-label">Total patients</div>
            <div class="stat-value"><?= $totalPatients ?></div>
            <div class="stat-delta">+<?= $newPatientsWeek ?> this week</div>
            <div style="position:absolute; bottom:-10px; right:-10px; opacity:0.05; font-size:80px">👥</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Appointments today</div>
            <div class="stat-value"><?= $apptToday ?></div>
            <div class="stat-delta" style="background:rgba(16,185,129,0.08); color:#065F46"><?= date('l') ?></div>
            <div style="position:absolute; bottom:-10px; right:-10px; opacity:0.05; font-size:80px">📅</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending review</div>
            <div class="stat-value"><?= $pendingReview ?></div>
            <div class="stat-delta" style="background:var(--c-amber-light); color:#92400E">Needs attention</div>
            <div style="position:absolute; bottom:-10px; right:-10px; opacity:0.05; font-size:80px">⏳</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active doctors</div>
            <div class="stat-value"><?= $activeDoctors ?></div>
            <div class="stat-delta" style="background:rgba(99,102,241,0.08); color:var(--accent)">All departments</div>
            <div style="position:absolute; bottom:-10px; right:-10px; opacity:0.05; font-size:80px">🩺</div>
        </div>
      </div>

      <div class="two-col mb-6">
        <!-- Appointment Trends -->
        <div class="card">
            <div class="card-head">
                <h3>Appointment Trends</h3>
                <span class="text-muted text-sm">Last 14 days</span>
            </div>
            <div style="height: 300px; width: 100%; margin-top: 1rem;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
        
        <!-- Status Distribution -->
        <div class="card">
            <div class="card-head">
                <h3>Status Distribution</h3>
                <span class="text-muted text-sm">Overall</span>
            </div>
            <div style="height: 300px; width: 100%; margin-top: 1rem; display: flex; justify-content: center;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
      </div>

      <div class="two-col">
        <!-- Recent appointments -->
        <div class="card">
          <h3 style="font-size:14px;font-weight:500;margin-bottom:1rem">Recent appointments</h3>
          <?php while ($row = $recentAppts->fetch_assoc()): ?>
          <div class="appt-row">
            <div class="avatar avatar-sm" style="background:<?= avatarColor($row['patient_name']) ?>"><?= initials($row['patient_name']) ?></div>
            <div class="appt-info">
              <div class="name"><?= htmlspecialchars($row['patient_name']) ?></div>
              <div class="meta"><?= htmlspecialchars($row['doctor_name']) ?> · <?= htmlspecialchars($row['dept_name']) ?></div>
            </div>
            <?= statusPill($row['status']) ?>
          </div>
          <?php endwhile; ?>
          <a href="appointments.php" class="btn btn-ghost btn-sm" style="width:100%; margin-top:1rem">View All Appointments</a>
        </div>

        <div class="card-featured report-widget">
            <div class="card-inner">
                <div class="report-widget-top">
                    <div>
                        <div class="section-label report-label">
                            <span class="dot"></span>
                            <span class="text">System Notice</span>
                        </div>
                        <h3>Daily Hospital Report</h3>
                    </div>
                    <span class="pill pill-green">Ready</span>
                </div>

                <p class="text-muted report-copy">Download the latest operational summary for patient records, appointment outcomes, doctor workload, and department activity.</p>

                <div class="report-metrics">
                    <div>
                        <span class="report-metric-value"><?= $totalAppointments ?></span>
                        <span class="report-metric-label">Appointments</span>
                    </div>
                    <div>
                        <span class="report-metric-value"><?= $recordsThisMonth ?></span>
                        <span class="report-metric-label">Records this month</span>
                    </div>
                </div>

                <div class="report-schedule">
                    <div class="report-icon" aria-hidden="true">&#128197;</div>
                    <div>
                        <div class="report-schedule-label">Next automatic update</div>
                        <div class="report-schedule-time"><?= htmlspecialchars($nextReportText) ?></div>
                    </div>
                </div>

                <div class="report-actions">
                    <a href="reports.php?download=csv" class="btn btn-primary">
                        <span aria-hidden="true">&#128229;</span>
                        Download CSV Report
                    </a>
                    <a href="reports.php" class="btn btn-ghost btn-sm">View reports</a>
                </div>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Chart Configuration
const trendCtx = document.getElementById('trendChart').getContext('2d');
const statusCtx = document.getElementById('statusChart').getContext('2d');

// Shared Colors
const purple = '#6366F1';
const pink = '#EC4899';
const amber = '#F59E0B';
const green = '#10B981';
const blue = '#3B82F6';
const red = '#EF4444';

// 1. Trend Chart
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($trendLabels) ?>,
        datasets: [{
            label: 'Appointments',
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
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { display: false },
                ticks: { font: { family: 'Plus Jakarta Sans' } }
            },
            x: {
                grid: { display: false },
                ticks: { font: { family: 'Plus Jakarta Sans' } }
            }
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
                labels: {
                    padding: 20,
                    font: { family: 'Plus Jakarta Sans', size: 12, weight: '600' },
                    usePointStyle: true
                }
            }
        }
    }
});
</script>
</body>
</html>
