<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') { header('Location: ../index.php'); exit; }

$doctor_id = (int)$_SESSION['doctor_id'];
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    header('Location: patients.php');
    exit;
}

// Fetch patient info only if this patient has an appointment with this doctor.
$stmt = $conn->prepare("
    SELECT u.name, u.email, p.birthdate, p.gender, p.contact, p.address
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

// Fetch medical history for this patient
$stmt = $conn->prepare("
    SELECT mr.record_id, mr.diagnosis, mr.prescription, mr.record_date, u.name as doctor_name, d.specialization
    FROM medical_records mr
    JOIN doctors d ON mr.doctor_id = d.doctor_id
    JOIN users u ON d.user_id = u.user_id
    WHERE mr.patient_id = ?
    ORDER BY mr.record_date DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$records = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Patient History — MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.history-timeline {
    position: relative;
    padding-left: 30px;
    margin-top: 20px;
}
.history-timeline::before {
    content: '';
    position: absolute;
    left: 11px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border);
}
.timeline-item {
    position: relative;
    margin-bottom: 25px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    box-shadow: var(--shadow-sm);
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: -24px;
    top: 20px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--accent);
    border: 2px solid var(--background);
}
.timeline-date {
    font-size: 13px;
    color: var(--muted-foreground);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.timeline-doctor {
    font-weight: 600;
    margin-bottom: 15px;
    display: inline-block;
    padding: 4px 10px;
    background: var(--muted);
    border-radius: 6px;
    font-size: 13px;
}
.timeline-section {
    margin-bottom: 12px;
}
.timeline-section:last-child {
    margin-bottom: 0;
}
.timeline-section h4 {
    font-size: 13px;
    text-transform: uppercase;
    color: var(--muted-foreground);
    margin-bottom: 4px;
    letter-spacing: 0.5px;
}
.timeline-section p {
    font-size: 14px;
    line-height: 1.5;
    color: var(--foreground);
    white-space: pre-line;
}
.patient-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}
</style>
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <div>
        <a href="patients.php" style="color:var(--muted-foreground); text-decoration:none; font-size:14px;">&larr; Back to Patients</a>
        <h1 style="margin-top:5px">Patient Medical History</h1>
      </div>
      <a href="add_record.php?patient_id=<?= $patient_id ?>" class="btn btn-primary">Add New Record</a>
    </div>
    <div class="admin-content" style="max-width: 800px;">
      
      <div class="card">
        <div class="patient-header">
            <div>
                <h3 style="margin-bottom:5px; font-size: 20px;"><?= htmlspecialchars($patient['name']) ?></h3>
                <div style="color:var(--muted-foreground); font-size:14px; display: flex; gap: 15px; flex-wrap: wrap;">
                    <span><strong>Email:</strong> <?= htmlspecialchars($patient['email']) ?></span>
                    <span><strong>Contact:</strong> <?= htmlspecialchars($patient['contact'] ?? 'N/A') ?></span>
                    <span><strong>Gender:</strong> <?= $patient['gender'] ?? 'N/A' ?></span>
                    <span><strong>DOB:</strong> <?= $patient['birthdate'] ? date('M j, Y', strtotime($patient['birthdate'])) : 'N/A' ?></span>
                </div>
            </div>
        </div>

        <h4>Medical Records</h4>
        <?php if ($records->num_rows > 0): ?>
            <div class="history-timeline">
                <?php while ($record = $records->fetch_assoc()): ?>
                <div class="timeline-item">
                    <div class="timeline-date">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <?= date('F j, Y, g:i a', strtotime($record['record_date'])) ?>
                    </div>
                    <div class="timeline-doctor">
                        By <?= htmlspecialchars($record['doctor_name']) ?> <span style="opacity:0.7; font-weight:normal">(<?= htmlspecialchars($record['specialization']) ?>)</span>
                    </div>
                    
                    <div class="timeline-section">
                        <h4>Diagnosis</h4>
                        <p><?= htmlspecialchars($record['diagnosis']) ?></p>
                    </div>
                    
                    <?php if (!empty($record['prescription'])): ?>
                    <div class="timeline-section">
                        <h4>Prescription / Recommendations</h4>
                        <p><?= htmlspecialchars($record['prescription']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding: 40px 20px; color:var(--muted-foreground);">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px;opacity:0.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                <p>No medical records found for this patient.</p>
            </div>
        <?php endif; ?>
      </div>
      
    </div>
  </div>
</div>
</body>
</html>
