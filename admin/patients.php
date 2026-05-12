<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: ../index.php'); exit; }

// Handle add patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_patient'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']) !== '' ? password_hash(trim($_POST['password']), PASSWORD_DEFAULT) : password_hash('patient123', PASSWORD_DEFAULT);
    $gender = trim($_POST['gender']);
    $contact = trim($_POST['contact']);
    $birthdate = trim($_POST['birthdate']) ?: null;
    
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $profile_pic = uniqid('pic_') . '.' . $ext;
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], __DIR__ . '/../uploads/' . $profile_pic);
        }
    }

    $conn->begin_transaction();
    try {
        $s1 = $conn->prepare("INSERT INTO users (name, email, password, role, profile_pic) VALUES (?,?,?,'patient',?)");
        $s1->bind_param("ssss", $name, $email, $password, $profile_pic);
        $s1->execute();
        $uid = $conn->insert_id;
        $s1->close();
        
        $s2 = $conn->prepare("INSERT INTO patients (user_id, gender, contact, birthdate) VALUES (?,?,?,?)");
        $s2->bind_param("isss", $uid, $gender, $contact, $birthdate);
        $s2->execute();
        $s2->close();
        $conn->commit();
    } catch(Exception $e) { $conn->rollback(); }
    header('Location: patients.php'); exit;
}

// Handle edit patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_patient'])) {
    $pid = (int)$_POST['patient_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $gender = trim($_POST['gender']);
    $contact = trim($_POST['contact']);
    $birthdate = trim($_POST['birthdate']) ?: null;

    $conn->begin_transaction();
    try {
        $s1 = $conn->prepare("UPDATE users SET name=?, email=? WHERE user_id = (SELECT user_id FROM patients WHERE patient_id=?)");
        $s1->bind_param("ssi", $name, $email, $pid);
        $s1->execute();
        $s1->close();

        if (!empty($_POST['password'])) {
            $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
            $sp = $conn->prepare("UPDATE users SET password=? WHERE user_id = (SELECT user_id FROM patients WHERE patient_id=?)");
            $sp->bind_param("si", $password, $pid);
            $sp->execute(); $sp->close();
        }

        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $profile_pic = uniqid('pic_') . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], __DIR__ . '/../uploads/' . $profile_pic)) {
                    $spic = $conn->prepare("UPDATE users SET profile_pic=? WHERE user_id = (SELECT user_id FROM patients WHERE patient_id=?)");
                    $spic->bind_param("si", $profile_pic, $pid);
                    $spic->execute(); $spic->close();
                }
            }
        }

        $s2 = $conn->prepare("UPDATE patients SET gender=?, contact=?, birthdate=? WHERE patient_id=?");
        $s2->bind_param("sssi", $gender, $contact, $birthdate, $pid);
        $s2->execute();
        $s2->close();
        $conn->commit();
    } catch(Exception $e) { $conn->rollback(); }
    header('Location: patients.php'); exit;
}

// Handle delete patient
if (isset($_GET['delete'])) {
    $pid = (int)$_GET['delete'];
    $s = $conn->prepare("SELECT user_id FROM patients WHERE patient_id = ?");
    $s->bind_param("i", $pid);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    if ($r) {
        $s2 = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $s2->bind_param("i", $r['user_id']);
        $s2->execute();
        $s2->close();
    }
    header('Location: patients.php'); exit;
}

$patients = $conn->query("
    SELECT p.patient_id, u.name, u.email, u.profile_pic, p.birthdate, p.gender, p.contact, u.created_at,
           COUNT(a.appt_id) AS appt_count
    FROM patients p
    JOIN users u ON p.user_id = u.user_id
    LEFT JOIN appointments a ON p.patient_id = a.patient_id
    GROUP BY p.patient_id
    ORDER BY u.name
");

$colors = ['#1A6B4A','#1E40AF','#B45309','#7C3AED','#C0392B','#0F766E'];
function avatarColor3($name) { global $colors; return $colors[abs(crc32($name)) % count($colors)]; }
function initials3($name) { $p = explode(' ',$name); return strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):'')); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Patients — MediCare HMS</title>
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<script>
function showAddModal() {
    document.getElementById('addModal').classList.add('active');
}
function hideAddModal() {
    document.getElementById('addModal').classList.remove('active');
}
function editPatient(id, name, email, gender, contact, birthdate) {
    document.getElementById('editModal').classList.add('active');
    document.getElementById('edit_patient_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_gender').value = gender;
    document.getElementById('edit_contact').value = contact;
    document.getElementById('edit_birthdate').value = birthdate;
    document.getElementById('edit_password').value = ''; // clear password field
}
function hideEditModal() {
    document.getElementById('editModal').classList.remove('active');
}
</script>
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Patients</h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">🔔<span class="dot"></span></div>
      </div>
    </div>
    <div class="admin-content">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
        <span class="text-muted text-sm"><?= $patients->num_rows ?> patients registered</span>
        <button class="btn btn-primary btn-sm" onclick="showAddModal()">+ Add patient</button>
      </div>

      <!-- ADD PATIENT MODAL -->
      <div class="modal-overlay" id="addModal">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Register New Patient</h3>
            <button class="modal-close" type="button" onclick="hideAddModal()">&times;</button>
          </div>
          <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
              <label>Profile Picture</label>
              <label class="upload-picker">
                <input type="file" name="profile_pic" accept="image/*">
                <span class="upload-picker-action">Choose image</span>
                <span class="upload-picker-name">No image selected</span>
              </label>
            </div>
            <div class="form-group"><label>Full name</label><input type="text" name="name" class="form-input" required></div>
            <div class="form-group"><label>Email address</label><input type="email" name="email" class="form-input" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" class="form-input" placeholder="Create a password" required></div>
            <div class="two-col" style="margin-bottom:0">
              <div class="form-group"><label>Gender</label>
                <select name="gender" class="form-input">
                  <option value="">Select</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="form-group"><label>Birthdate</label><input type="date" name="birthdate" class="form-input"></div>
            </div>
            <div class="form-group"><label>Contact number</label><input type="text" name="contact" class="form-input"></div>
            <button type="submit" name="add_patient" class="btn btn-primary" style="width:100%;margin-top:1rem">Complete registration</button>
          </form>
        </div>
      </div>

      <!-- EDIT PATIENT MODAL -->
      <div class="modal-overlay" id="editModal">
        <div class="modal-content" style="border: 2px solid var(--accent)">
          <div class="modal-header">
            <h3 style="color:var(--accent)">Edit Patient Info</h3>
            <button class="modal-close" type="button" onclick="hideEditModal()">&times;</button>
          </div>
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="patient_id" id="edit_patient_id">
            <div class="form-group">
              <label>Change Profile Picture</label>
              <label class="upload-picker">
                <input type="file" name="profile_pic" accept="image/*">
                <span class="upload-picker-action">Choose image</span>
                <span class="upload-picker-name">No image selected</span>
              </label>
            </div>
            <div class="form-group"><label>Full name</label><input type="text" name="name" id="edit_name" class="form-input" required></div>
            <div class="form-group"><label>Email address</label><input type="email" name="email" id="edit_email" class="form-input" required></div>
            <div class="form-group">
              <label>Update Password</label>
              <input type="password" name="password" id="edit_password" class="form-input" placeholder="Leave blank to keep current password">
            </div>
            <div class="two-col" style="margin-bottom:0">
              <div class="form-group"><label>Gender</label>
                <select name="gender" id="edit_gender" class="form-input">
                  <option value="">Select</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="form-group"><label>Birthdate</label><input type="date" name="birthdate" id="edit_birthdate" class="form-input"></div>
            </div>
            <div class="form-group"><label>Contact number</label><input type="text" name="contact" id="edit_contact" class="form-input"></div>
            <button type="submit" name="edit_patient" class="btn btn-primary" style="width:100%;margin-top:1rem">Update changes</button>
          </form>
        </div>
      </div>

      <!-- PATIENTS TABLE -->
      <div class="card" style="padding: 2.5rem;">
        <table id="patientsTable" class="data-table">
          <thead><tr><th>Patient</th><th>Email</th><th>Gender</th><th>Contact</th><th>Appointments</th><th>Registered</th><th>Actions</th></tr></thead>
          <tbody>
          <?php while($r = $patients->fetch_assoc()): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:12px;font-weight:500">
                <div class="avatar avatar-sm" style="background:<?= avatarColor3($r['name']) ?>;<?= $r['profile_pic'] ? 'background-image:url(../uploads/'.htmlspecialchars($r['profile_pic']).');background-size:cover;background-position:center;' : '' ?>">
                  <?= $r['profile_pic'] ? '' : initials3($r['name']) ?>
                </div>
                <?= htmlspecialchars($r['name']) ?>
              </div>
            </td>
            <td class="text-muted"><?= htmlspecialchars($r['email']) ?></td>
            <td><?= htmlspecialchars($r['gender'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['contact'] ?? '—') ?></td>
            <td><span class="pill pill-blue"><?= $r['appt_count'] ?></span></td>
            <td class="text-muted"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
            <td>
              <div style="display:flex;gap:6px;align-items:center">
                <button class="btn btn-edit btn-sm" onclick="editPatient(<?= $r['patient_id'] ?>, '<?= addslashes($r['name']) ?>', '<?= addslashes($r['email']) ?>', '<?= addslashes($r['gender']) ?>', '<?= addslashes($r['contact']) ?>', '<?= addslashes($r['birthdate']) ?>')">Edit</button>
                <a href="?delete=<?= $r['patient_id'] ?>" class="btn btn-delete btn-sm" onclick="return confirm('Delete this patient?')">Delete</a>
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
    $('#patientsTable').DataTable({
        "order": [[0, "asc"]],
        "language": {
            "lengthMenu": "Rows per page _MENU_",
            "search": "Search",
            "info": "Showing _START_ to _END_ of _TOTAL_ patients",
            "infoEmpty": "No patients to show",
            "infoFiltered": "(filtered from _MAX_ total)"
        }
    });
});
</script>
<script>
document.querySelectorAll('.upload-picker input[type="file"]').forEach(function(input) {
    input.addEventListener('change', function() {
        var name = input.closest('.upload-picker').querySelector('.upload-picker-name');
        var fileName = input.files.length ? input.files[0].name : 'No image selected';
        name.textContent = fileName;
        name.classList.toggle('has-file', input.files.length > 0);
    });
});
</script>
</body>
</html>
