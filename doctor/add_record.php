<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') { header('Location: ../index.php'); exit; }

$doctor_id = (int)$_SESSION['doctor_id'];
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$appt_id = isset($_GET['appt_id']) ? (int)$_GET['appt_id'] : 0;

if (!$patient_id) {
    header('Location: appointments.php');
    exit;
}

// Fetch patient info only if this patient has an appointment with this doctor.
$stmt = $conn->prepare("
    SELECT u.name, p.birthdate, p.gender
    FROM patients p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.patient_id = ?
      AND EXISTS (
        SELECT 1
        FROM appointments a
        WHERE a.patient_id = p.patient_id AND a.doctor_id = ?
      )
");
$stmt->bind_param("ii", $patient_id, $doctor_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$patient) {
    die("Patient not found.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $prescription = trim($_POST['prescription'] ?? '');

    if (empty($diagnosis)) {
        $error = "Diagnosis is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, diagnosis, prescription, record_date) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $patient_id, $doctor_id, $diagnosis, $prescription);
        if ($stmt->execute()) {
            $success = "Medical record successfully added!";
            // Optional: redirect back to appointments after a few seconds or show success
        } else {
            $error = "Failed to add record.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Add Medical Record — MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <div>
        <a href="appointments.php" style="color:var(--muted-foreground); text-decoration:none; font-size:14px;">&larr; Back to Appointments</a>
        <h1 style="margin-top:5px">Add Medical Record</h1>
      </div>
    </div>
    <div class="admin-content" style="max-width: 600px;">
      
      <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success">
          <?= htmlspecialchars($success) ?><br><br>
          <a href="appointments.php" class="btn btn-primary" style="display:inline-block">Return to Appointments</a>
        </div>
      <?php else: ?>
      
      <div class="card">
        <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border)">
          <h3 style="margin-bottom:5px">Patient Information</h3>
          <strong><?= htmlspecialchars($patient['name']) ?></strong><br>
          <span style="color:var(--muted-foreground); font-size:13px">
            <?= $patient['gender'] ?? 'N/A' ?> · DOB: <?= $patient['birthdate'] ? date('M j, Y', strtotime($patient['birthdate'])) : 'N/A' ?>
          </span>
        </div>

        <form method="POST">
          <div class="form-group">
            <label>Diagnosis <span style="color:red">*</span></label>
            <textarea name="diagnosis" class="form-input" rows="4" placeholder="Enter clinical findings and diagnosis" required></textarea>
          </div>
          <div class="form-group">
            <label>Prescription / Recommendations</label>
            <textarea name="prescription" class="form-input" rows="3" placeholder="Medications, dosage, and advice..."></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Save Medical Record</button>
        </form>
      </div>

      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
