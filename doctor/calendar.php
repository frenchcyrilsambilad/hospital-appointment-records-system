<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') { header('Location: ../index.php'); exit; }

$doctor_id = (int)$_SESSION['doctor_id'];

// Fetch appointments for calendar for this doctor ONLY
$sql = "SELECT a.appt_id, a.appt_date, a.status, u_patient.name AS patient_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN users u_patient ON p.user_id = u_patient.user_id
        WHERE a.doctor_id = $doctor_id";
$result = $conn->query($sql);

$events = [];
while ($row = $result->fetch_assoc()) {
    $color = '#f59e0b'; // Pending
    if ($row['status'] === 'Confirmed') $color = '#10b981';
    if ($row['status'] === 'Completed') $color = '#3b82f6';
    if ($row['status'] === 'Cancelled') $color = '#ef4444';

    $events[] = [
        'id' => $row['appt_id'],
        'title' => $row['patient_name'],
        'start' => date('Y-m-d\TH:i:s', strtotime($row['appt_date'])),
        'color' => $color,
        'url' => 'appointments.php'
    ];
}
$eventsJson = json_encode($events);
$eventCount = count($events);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Calendar — MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>My Calendar</h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">🔔</div>
      </div>
    </div>
    <div class="admin-content">
      <div class="card calendar-card">
        <div class="calendar-toolbar">
          <div class="calendar-summary">
            <strong><?= $eventCount ?> appointment<?= $eventCount === 1 ? '' : 's' ?> on your calendar</strong>
            <span>Your patient schedule</span>
          </div>
          <div class="calendar-legend" aria-label="Appointment status legend">
            <div class="calendar-legend-item"><span class="calendar-legend-dot pending"></span>Pending</div>
            <div class="calendar-legend-item"><span class="calendar-legend-dot confirmed"></span>Confirmed</div>
            <div class="calendar-legend-item"><span class="calendar-legend-dot completed"></span>Completed</div>
            <div class="calendar-legend-item"><span class="calendar-legend-dot cancelled"></span>Cancelled</div>
          </div>
        </div>
        <div class="calendar-shell">
          <div id="calendar"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: window.innerWidth < 720 ? 'timeGridDay' : 'timeGridWeek',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
      },
      buttonText: {
        today: 'Today',
        month: 'Month',
        week: 'Week',
        day: 'Day'
      },
      events: <?= $eventsJson ?>,
      height: 'auto',
      contentHeight: 700,
      slotMinTime: "08:00:00",
      slotMaxTime: "20:00:00",
      allDaySlot: false,
      dayMaxEvents: 3,
      nowIndicator: true,
      navLinks: true,
      eventTimeFormat: {
        hour: 'numeric',
        minute: '2-digit',
        meridiem: 'short'
      },
      windowResize: function() {
        if (window.innerWidth < 720 && calendar.view.type !== 'timeGridDay') {
          calendar.changeView('timeGridDay');
        }
      },
      eventDidMount: function(info) {
        info.el.title = info.event.title;
      },
      eventClick: function(info) {
        info.jsEvent.preventDefault();
        if (info.event.url) {
          window.location.href = info.event.url;
        }
      }
    });
    calendar.render();
  });
</script>
</body>
</html>
