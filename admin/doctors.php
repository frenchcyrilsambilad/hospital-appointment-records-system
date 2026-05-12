<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: ../index.php'); exit; }

// Handle add doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']) !== '' ? password_hash(trim($_POST['password']), PASSWORD_DEFAULT) : password_hash('doctor123', PASSWORD_DEFAULT);
    $dept_id = (int)$_POST['dept_id'];
    $spec = trim($_POST['specialization']);
    $sched = trim($_POST['schedule']);
    $role = 'doctor';

    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif'])) {
            $profile_pic = uniqid('pic_') . '.' . $ext;
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], __DIR__ . '/../uploads/' . $profile_pic);
        }
    }

    $conn->begin_transaction();
    try {
        $s1 = $conn->prepare("INSERT INTO users (name, email, password, role, profile_pic) VALUES (?,?,?,?,?)");
        $s1->bind_param("sssss", $name, $email, $password, $role, $profile_pic);
        $s1->execute();
        $uid = $conn->insert_id;
        $s1->close();

        $s2 = $conn->prepare("INSERT INTO doctors (user_id, dept_id, specialization, schedule) VALUES (?,?,?,?)");
        $s2->bind_param("iiss", $uid, $dept_id, $spec, $sched);
        $s2->execute();
        $s2->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
    header('Location: doctors.php'); exit;
}

// Handle edit doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_doctor'])) {
    $did = (int)$_POST['doctor_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $dept_id = (int)$_POST['dept_id'];
    $spec = trim($_POST['specialization']);
    $sched = trim($_POST['schedule']);

    $conn->begin_transaction();
    try {
        $s1 = $conn->prepare("UPDATE users SET name=?, email=? WHERE user_id = (SELECT user_id FROM doctors WHERE doctor_id=?)");
        $s1->bind_param("ssi", $name, $email, $did);
        $s1->execute();
        $s1->close();

        if (!empty($_POST['password'])) {
            $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
            $sp = $conn->prepare("UPDATE users SET password=? WHERE user_id = (SELECT user_id FROM doctors WHERE doctor_id=?)");
            $sp->bind_param("si", $password, $did);
            $sp->execute(); $sp->close();
        }

        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $profile_pic = uniqid('pic_') . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], __DIR__ . '/../uploads/' . $profile_pic)) {
                    $spic = $conn->prepare("UPDATE users SET profile_pic=? WHERE user_id = (SELECT user_id FROM doctors WHERE doctor_id=?)");
                    $spic->bind_param("si", $profile_pic, $did);
                    $spic->execute(); $spic->close();
                }
            }
        }

        $s2 = $conn->prepare("UPDATE doctors SET dept_id=?, specialization=?, schedule=? WHERE doctor_id=?");
        $s2->bind_param("issi", $dept_id, $spec, $sched, $did);
        $s2->execute();
        $s2->close();
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
    header('Location: doctors.php'); exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $s = $conn->prepare("SELECT user_id FROM doctors WHERE doctor_id = ?");
    $s->bind_param("i", $did);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    if ($r) {
        $s2 = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $s2->bind_param("i", $r['user_id']);
        $s2->execute();
        $s2->close();
    }
    header('Location: doctors.php'); exit;
}

// LEFT JOIN — include doctors with 0 appointments
$doctors = $conn->query("
    SELECT doc.doctor_id, u.name, u.email, u.profile_pic, doc.specialization, d.dept_name, doc.dept_id,
           doc.schedule, COUNT(a.appt_id) AS appt_count
    FROM doctors doc
    JOIN users u ON doc.user_id = u.user_id
    JOIN departments d ON doc.dept_id = d.dept_id
    LEFT JOIN appointments a ON doc.doctor_id = a.doctor_id
    GROUP BY doc.doctor_id
    ORDER BY u.name
");

$depts = $conn->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name");
$deptsArray = [];
while ($d = $depts->fetch_assoc()) { $deptsArray[] = $d; }

$specializationOptions = [
    'Clinical Neurology',
    'Electrophysiology',
    'Family Medicine',
    'General Cardiology',
    'General Surgery',
    'Internal Medicine',
    'Interventional Cardiology',
    'Obstetrics and Gynecology',
    'Orthopedics',
    'Pediatrics',
    'Psychiatry',
    'Radiology'
];
$scheduleOptions = [
    'Mon,Tue,Wed,Thu,Fri 08:00-16:00',
    'Mon,Tue,Wed,Thu,Fri 09:00-17:00',
    'Mon,Wed,Fri 08:00-15:00',
    'Mon,Wed,Fri 09:00-13:00',
    'Tue,Thu 10:00-18:00',
    'Tue,Thu,Sat 08:00-14:00',
    'Sat,Sun 09:00-15:00'
];

$colors = ['#1A6B4A','#1E40AF','#B45309','#7C3AED','#C0392B','#0F766E'];
function avatarColor2($name) { global $colors; return $colors[abs(crc32($name)) % count($colors)]; }
function initials2($name) { $p = explode(' ',$name); return strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):'')); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Doctors — MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
<script>
function showAddModal() {
    document.getElementById('addModal').classList.add('active');
}
function hideAddModal() {
    document.getElementById('addModal').classList.remove('active');
}
function setSelectValue(selectId, value) {
    var select = document.getElementById(selectId);
    var hasOption = Array.from(select.options).some(function(option) {
        return option.value === value;
    });
    if (!hasOption && value) {
        var option = new Option(value, value);
        select.add(option);
    }
    select.value = value;
}
function editDoctor(id, name, email, deptId, spec, sched) {
    document.getElementById('editModal').classList.add('active');
    document.getElementById('edit_doctor_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_dept_id').value = deptId;
    setSelectValue('edit_spec', spec);
    setSelectValue('edit_sched', sched);
    document.getElementById('edit_password').value = '';
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
      <h1>Doctors</h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">🔔<span class="dot"></span></div>
      </div>
    </div>
    <div class="admin-content">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
        <span class="text-muted text-sm"><?= $doctors->num_rows ?> doctors registered</span>
        <button class="btn btn-primary btn-sm" onclick="showAddModal()">+ Add doctor</button>
      </div>

      <!-- ADD MODAL -->
      <div class="modal-overlay" id="addModal">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Register New Doctor</h3>
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
            <div class="form-group"><label>Department</label>
              <select name="dept_id" class="form-input" required>
                <option value="">Select</option>
                <?php foreach($deptsArray as $d): ?>
                <option value="<?= $d['dept_id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="two-col" style="margin-bottom:0">
              <div class="form-group"><label>Specialization</label>
                <select name="specialization" class="form-input" required>
                  <option value="">Select</option>
                  <?php foreach($specializationOptions as $spec): ?>
                  <option value="<?= htmlspecialchars($spec) ?>"><?= htmlspecialchars($spec) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Schedule</label>
                <select name="schedule" class="form-input" required>
                  <option value="">Select</option>
                  <?php foreach($scheduleOptions as $schedule): ?>
                  <option value="<?= htmlspecialchars($schedule) ?>"><?= htmlspecialchars($schedule) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button type="submit" name="add_doctor" class="btn btn-primary" style="width:100%;margin-top:1rem">Complete registration</button>
          </form>
        </div>
      </div>

      <!-- EDIT MODAL -->
      <div class="modal-overlay" id="editModal">
        <div class="modal-content" style="border:2px solid var(--accent)">
          <div class="modal-header">
            <h3 style="color:var(--accent)">Edit Doctor Profile</h3>
            <button class="modal-close" type="button" onclick="hideEditModal()">&times;</button>
          </div>
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="doctor_id" id="edit_doctor_id">
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
            <div class="form-group"><label>Department</label>
              <select name="dept_id" id="edit_dept_id" class="form-input" required>
                <option value="">Select</option>
                <?php foreach($deptsArray as $d): ?>
                <option value="<?= $d['dept_id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="two-col" style="margin-bottom:0">
              <div class="form-group"><label>Specialization</label>
                <select name="specialization" id="edit_spec" class="form-input" required>
                  <option value="">Select</option>
                  <?php foreach($specializationOptions as $spec): ?>
                  <option value="<?= htmlspecialchars($spec) ?>"><?= htmlspecialchars($spec) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Schedule</label>
                <select name="schedule" id="edit_sched" class="form-input" required>
                  <option value="">Select</option>
                  <?php foreach($scheduleOptions as $schedule): ?>
                  <option value="<?= htmlspecialchars($schedule) ?>"><?= htmlspecialchars($schedule) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button type="submit" name="edit_doctor" class="btn btn-primary" style="width:100%;margin-top:1rem">Update changes</button>
          </form>
        </div>
      </div>

      <!-- DOCTOR GRID -->
      <div class="doctor-grid">
        <?php while ($doc = $doctors->fetch_assoc()): ?>
        <div class="card doctor-card animate-fade-in-up">
          <div class="avatar avatar-md" style="width:80px;height:80px;font-size:24px;background:<?= avatarColor2($doc['name']) ?>;<?= $doc['profile_pic'] ? 'background-image:url(../uploads/'.htmlspecialchars($doc['profile_pic']).');background-size:cover;background-position:center;' : '' ?>">
            <?= $doc['profile_pic'] ? '' : initials2($doc['name']) ?>
          </div>
          <div class="name"><?= htmlspecialchars($doc['name']) ?></div>
          <div class="spec"><?= htmlspecialchars($doc['specialization']) ?></div>
          <span class="pill pill-blue" style="margin-bottom:6px"><?= htmlspecialchars($doc['dept_name']) ?></span>
          <div class="text-sm text-muted"><?= $doc['appt_count'] ?> appointment<?= $doc['appt_count']!==1?'s':'' ?></div>
          <div class="actions">
            <button class="btn btn-edit btn-sm" onclick="editDoctor(<?= $doc['doctor_id'] ?>, '<?= addslashes($doc['name']) ?>', '<?= addslashes($doc['email']) ?>', <?= $doc['dept_id'] ?>, '<?= addslashes($doc['specialization']) ?>', '<?= addslashes($doc['schedule']) ?>')">Edit profile</button>
            <a href="?delete=<?= $doc['doctor_id'] ?>" class="btn btn-delete btn-sm" onclick="return confirm('Delete this doctor?')">Delete</a>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
    </div>
  </div>
</div>
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
