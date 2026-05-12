<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: ../index.php'); exit; }

function statusPill($s) {
    $map = ['Confirmed'=>'green','Pending'=>'amber','Completed'=>'blue','Cancelled'=>'red'];
    return '<span class="pill pill-'.($map[$s]??'amber').'">'.htmlspecialchars($s).'</span>';
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appt_id = (int)$_POST['appt_id'];
    $new_status = $_POST['new_status'] ?? '';
    $allowedStatuses = ['Confirmed', 'Cancelled', 'Completed'];

    if (!in_array($new_status, $allowedStatuses, true) || $appt_id <= 0) {
        header('Location: appointments.php?msg=update_failed'); exit;
    }

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE appt_id = ?");
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param("si", $new_status, $appt_id);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();

        $res = $conn->query("SELECT patient_id, doctor_id, status FROM appointments WHERE appt_id = $appt_id");
        if (!$res || !($apptRow = $res->fetch_assoc())) {
            throw new Exception('Appointment was not updated.');
        }
        if ($apptRow['status'] !== $new_status) {
            throw new Exception('Appointment status did not change.');
        }

        if ($new_status === 'Completed') {
            $diag = trim($_POST['diagnosis'] ?? '');
            $pres = trim($_POST['prescription'] ?? '');
            $mstmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, diagnosis, prescription) VALUES (?, ?, ?, ?)");
            if (!$mstmt) {
                throw new Exception($conn->error);
            }
            $mstmt->bind_param("iiss", $apptRow['patient_id'], $apptRow['doctor_id'], $diag, $pres);
            if (!$mstmt->execute()) {
                throw new Exception($mstmt->error);
            }
            $mstmt->close();
        }

        $conn->commit();

        try {
            $eres = $conn->query("SELECT u.email, u.name, a.appt_date FROM appointments a JOIN patients p ON a.patient_id = p.patient_id JOIN users u ON p.user_id = u.user_id WHERE a.appt_id = $appt_id");
            if ($eres && $erow = $eres->fetch_assoc()) {
                send_mock_email($erow['email'], $erow['name'], $new_status, $erow['appt_date']);
            }
        } catch (Throwable $mailError) {
            error_log('Appointment email log failed: ' . $mailError->getMessage());
        }

        header('Location: appointments.php?msg=' . strtolower($new_status)); exit;
    } catch (Throwable $e) {
        $conn->rollback();
        header('Location: appointments.php?msg=update_failed'); exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    $appt_id = (int)$_GET['id'];
    $stmt = $conn->prepare("UPDATE appointments SET status='Cancelled' WHERE appt_id=?");
    $stmt->bind_param("i", $appt_id);
    $stmt->execute();
    $stmt->close();
    
    $eres = $conn->query("SELECT u.email, u.name, a.appt_date FROM appointments a JOIN patients p ON a.patient_id = p.patient_id JOIN users u ON p.user_id = u.user_id WHERE a.appt_id = $appt_id");
    if ($erow = $eres->fetch_assoc()) {
        send_mock_email($erow['email'], $erow['name'], 'Cancelled', $erow['appt_date']);
    }
    header('Location: appointments.php?msg=email_sent'); exit;
}

// Fetch all rows (DataTables will handle pagination on frontend)
$sql = "SELECT a.appt_id, u.name AS patient_name, du.name AS doctor_name,
        d.dept_name, a.appt_date, a.status, a.notes, a.created_at
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.user_id
    JOIN doctors doc ON a.doctor_id = doc.doctor_id
    JOIN users du ON doc.user_id = du.user_id
    JOIN departments d ON doc.dept_id = d.dept_id
    ORDER BY a.created_at DESC, a.appt_id DESC";
$rows = $conn->query($sql);
$departmentsForFilter = $conn->query("SELECT dept_name FROM departments ORDER BY dept_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Appointments — MediCare HMS</title>
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Appointments</h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">🔔<span class="dot"></span></div>
      </div>
    </div>
    <div class="admin-content">
      <?php
        $statusMessages = [
          'email_sent' => [
            'title' => 'Status Updated',
            'text' => 'An email notification has been sent to the patient!'
          ],
          'confirmed' => [
            'title' => 'Appointment Confirmed',
            'text' => 'The appointment is now confirmed and the patient has been notified.'
          ],
          'cancelled' => [
            'title' => 'Appointment Cancelled',
            'text' => 'The appointment is now cancelled and the patient has been notified.'
          ],
          'completed' => [
            'title' => 'Appointment Completed',
            'text' => 'The appointment has been marked complete and added to the patient record.'
          ]
        ];
        $currentMessage = $_GET['msg'] ?? '';
      ?>
      <?php if (isset($statusMessages[$currentMessage])): ?>
      <script>
        Swal.fire({
          icon: 'success',
          title: <?= json_encode($statusMessages[$currentMessage]['title']) ?>,
          text: <?= json_encode($statusMessages[$currentMessage]['text']) ?>,
          timer: 3000,
          showConfirmButton: false
        });
      </script>
      <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'update_failed'): ?>
      <script>
        Swal.fire({
          icon: 'error',
          title: 'Update Failed',
          text: 'The appointment status was not changed. Please try again.',
          timer: 3000,
          showConfirmButton: false
        });
      </script>
      <?php endif; ?>

      <div class="card" style="padding: 2.5rem;">
        <div class="table-filter-panel" aria-label="Appointment filters">
          <div class="table-filter-field">
            <label for="statusFilter">Status</label>
            <select id="statusFilter" class="form-input">
              <option value="">All statuses</option>
              <option value="Pending">Pending</option>
              <option value="Confirmed">Confirmed</option>
              <option value="Completed">Completed</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>
          <div class="table-filter-field">
            <label for="departmentFilter">Department</label>
            <select id="departmentFilter" class="form-input">
              <option value="">All departments</option>
              <?php while ($dept = $departmentsForFilter->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($dept['dept_name']) ?>"><?= htmlspecialchars($dept['dept_name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="table-filter-field">
            <label for="sortFilter">Sort by</label>
            <select id="sortFilter" class="form-input">
              <option value="booked-desc">Recently booked</option>
              <option value="booked-asc">Oldest booked</option>
              <option value="date-desc">Latest appointment date</option>
              <option value="date-asc">Earliest appointment date</option>
              <option value="patient-asc">Patient A to Z</option>
              <option value="doctor-asc">Doctor A to Z</option>
            </select>
          </div>
          <div class="table-filter-actions">
            <button type="button" class="btn btn-primary btn-sm" id="applyFilters">Apply filter</button>
            <button type="button" class="btn btn-outline btn-sm" id="clearFilters">Clear</button>
          </div>
        </div>
        <table id="appointmentsTable" class="data-table">
          <thead>
            <tr><th>ID</th><th>Patient</th><th>Doctor</th><th>Department</th><th>Date & time</th><th>Status</th><th>Actions</th><th>Booked</th></tr>
          </thead>
          <tbody>
          <?php while ($r = $rows->fetch_assoc()): ?>
            <tr>
              <td>#<?= $r['appt_id'] ?></td>
              <td><?= htmlspecialchars($r['patient_name']) ?></td>
              <td><?= htmlspecialchars($r['doctor_name']) ?></td>
              <td><?= htmlspecialchars($r['dept_name']) ?></td>
              <td data-sort="<?= $r['appt_date'] ?>"><?= date('M j, Y · g:i A', strtotime($r['appt_date'])) ?></td>
              <td><?= statusPill($r['status']) ?></td>
              <td class="appointment-actions-cell">
                <div class="appointment-actions">
                <?php if ($r['status'] === 'Pending'): ?>
                <form method="POST" class="appointment-action-form">
                  <input type="hidden" name="update_status" value="1">
                  <input type="hidden" name="appt_id" value="<?= $r['appt_id'] ?>">
                  <input type="hidden" name="new_status" value="Confirmed">
                  <button type="submit" class="btn btn-confirm btn-sm appointment-action-btn">Confirm</button>
                </form>
                <?php endif; ?>
                <?php if ($r['status'] === 'Confirmed'): ?>
                <button type="button" class="btn btn-edit btn-sm appointment-action-btn" onclick="completeAppt(<?= $r['appt_id'] ?>)">Done</button>
                <?php endif; ?>
                <?php if (in_array($r['status'], ['Pending','Confirmed'])): ?>
                <form method="POST" class="appointment-action-form">
                  <input type="hidden" name="update_status" value="1">
                  <input type="hidden" name="appt_id" value="<?= $r['appt_id'] ?>">
                  <input type="hidden" name="new_status" value="Cancelled">
                  <button type="submit" class="btn btn-cancel btn-sm appointment-action-btn">Cancel</button>
                </form>
                <?php endif; ?>
                <?php if ($r['status'] === 'Completed' || $r['status'] === 'Cancelled'): ?>
                <span class="text-muted text-sm">—</span>
                <?php endif; ?>
                </div>
              </td>
              <td><?= htmlspecialchars($r['created_at']) ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Complete Appointment Modal -->
<div class="modal-overlay" id="completeModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Complete Appointment</h3>
      <button class="modal-close" onclick="closeCompleteModal()">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="update_status" value="1">
      <input type="hidden" name="appt_id" id="comp_appt_id">
      <input type="hidden" name="new_status" value="Completed">
      <div class="form-group">
        <label>Diagnosis</label>
        <textarea name="diagnosis" class="form-input" rows="3" style="height:auto;padding:12px 16px" placeholder="Enter patient diagnosis..." required></textarea>
      </div>
      <div class="form-group">
        <label>Prescription (Optional)</label>
        <textarea name="prescription" class="form-input" rows="3" style="height:auto;padding:12px 16px" placeholder="Enter prescribed medications..."></textarea>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:1rem">Complete & Save Record</button>
    </form>
  </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    var table = $('#appointmentsTable').DataTable({
        "order": [[7, "desc"]],
        "columnDefs": [
            { "targets": [7], "visible": false, "searchable": false }
        ],
        "language": {
            "lengthMenu": "Rows per page _MENU_",
            "search": "Search",
            "info": "Showing _START_ to _END_ of _TOTAL_ appointments",
            "infoEmpty": "No appointments to show",
            "infoFiltered": "(filtered from _MAX_ total)"
        }
    });

    function applyAppointmentFilters() {
        var status = $('#statusFilter').val();
        var department = $('#departmentFilter').val();
        var sort = $('#sortFilter').val();

        table.column(5).search(status ? '^' + status + '$' : '', true, false);
        table.column(3).search(department ? '^' + department + '$' : '', true, false);

        if (sort === 'booked-asc') {
            table.order([7, 'asc']);
        } else if (sort === 'date-desc') {
            table.order([4, 'desc']);
        } else if (sort === 'date-asc') {
            table.order([4, 'asc']);
        } else if (sort === 'patient-asc') {
            table.order([1, 'asc']);
        } else if (sort === 'doctor-asc') {
            table.order([2, 'asc']);
        } else {
            table.order([7, 'desc']);
        }

        table.draw();
    }

    $('#applyFilters').on('click', applyAppointmentFilters);
    $('#statusFilter, #departmentFilter, #sortFilter').on('change', applyAppointmentFilters);
    $('#clearFilters').on('click', function() {
        $('#statusFilter').val('');
        $('#departmentFilter').val('');
        $('#sortFilter').val('booked-desc');
        table.search('');
        table.columns().search('');
        table.order([7, 'desc']).draw();
    });
});

function completeAppt(id) {
    document.getElementById('comp_appt_id').value = id;
    document.getElementById('completeModal').classList.add('active');
}
function closeCompleteModal() {
    document.getElementById('completeModal').classList.remove('active');
}
</script>
</body>
</html>
