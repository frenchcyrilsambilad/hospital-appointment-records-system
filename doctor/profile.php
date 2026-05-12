<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') { header('Location: ../index.php'); exit; }

$doctor_id = (int)$_SESSION['doctor_id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $schedule = trim($_POST['schedule']);

    $conn->begin_transaction();
    try {
        // Update users table
        $s1 = $conn->prepare("UPDATE users SET name=?, email=? WHERE user_id = (SELECT user_id FROM doctors WHERE doctor_id=?)");
        $s1->bind_param("ssi", $name, $email, $doctor_id);
        $s1->execute();
        
        // Update password if provided
        if (!empty($_POST['password'])) {
            $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
            $sp = $conn->prepare("UPDATE users SET password=? WHERE user_id = (SELECT user_id FROM doctors WHERE doctor_id=?)");
            $sp->bind_param("si", $password, $doctor_id);
            $sp->execute();
        }

        // Update profile picture if provided
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $profile_pic = uniqid('pic_') . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], __DIR__ . '/../uploads/' . $profile_pic)) {
                    $spic = $conn->prepare("UPDATE users SET profile_pic=? WHERE user_id = (SELECT user_id FROM doctors WHERE doctor_id=?)");
                    $spic->bind_param("si", $profile_pic, $doctor_id);
                    $spic->execute();
                    $_SESSION['profile_pic'] = $profile_pic; // Update session
                }
            }
        }

        // Update doctors table (schedule)
        $s2 = $conn->prepare("UPDATE doctors SET schedule=? WHERE doctor_id=?");
        $s2->bind_param("si", $schedule, $doctor_id);
        $s2->execute();
        
        $conn->commit();
        $_SESSION['name'] = $name; // Update session
        $success = "Profile and schedule updated successfully!";
    } catch(Exception $e) {
        $conn->rollback();
        $error = "Failed to update profile.";
    }
}

// Fetch current data
$u = $conn->prepare("SELECT u.name, u.email, u.profile_pic, d.specialization, d.schedule, dept.dept_name 
                     FROM doctors d 
                     JOIN users u ON d.user_id = u.user_id 
                     JOIN departments dept ON d.dept_id = dept.dept_id
                     WHERE d.doctor_id=?");
$u->bind_param("i", $doctor_id); $u->execute();
$user = $u->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Profile — MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>My Profile & Schedule</h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">🔔</div>
      </div>
    </div>
    <div class="admin-content" style="max-width:600px">
      
      <?php if(isset($success)): ?>
      <script>Swal.fire({icon: 'success', title: 'Updated!', text: <?= json_encode($success) ?>});</script>
      <?php endif; ?>
      <?php if(isset($error)): ?>
      <script>Swal.fire({icon: 'error', title: 'Oops...', text: <?= json_encode($error) ?>});</script>
      <?php endif; ?>

      <div class="card" style="padding: 2.5rem;">
        <form method="POST" enctype="multipart/form-data">
          <div style="display:flex;align-items:center;gap:16px;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid var(--border)">
            <?php $bg = $user['profile_pic'] ? 'background-image:url(../uploads/'.htmlspecialchars($user['profile_pic']).');background-size:cover;background-position:center;color:transparent' : 'background:var(--accent);color:#fff'; ?>
            <div class="avatar avatar-lg" style="<?= $bg ?>;font-size:32px;width:80px;height:80px">
              <?= $user['profile_pic'] ? '' : strtoupper(substr($user['name'],0,1)) ?>
            </div>
            <div style="flex:1">
              <label style="display:block;font-weight:500;margin-bottom:8px">Profile Picture</label>
              <label class="upload-picker">
                <input type="file" name="profile_pic" accept="image/*">
                <span class="upload-picker-action">Choose image</span>
                <span class="upload-picker-name">No image selected</span>
              </label>
            </div>
          </div>

          <div class="form-group">
            <label>Department & Specialization (Read-only)</label>
            <input type="text" class="form-input" value="<?= htmlspecialchars($user['dept_name']) ?> — <?= htmlspecialchars($user['specialization']) ?>" disabled style="background:var(--muted); opacity:0.7">
          </div>

          <div class="form-group"><label>Full Name</label><input type="text" name="name" class="form-input" value="<?= htmlspecialchars($user['name']) ?>" required></div>
          <div class="form-group"><label>Email Address</label><input type="email" name="email" class="form-input" value="<?= htmlspecialchars($user['email']) ?>" required></div>
          
          <div class="form-group">
            <label>My Availability Schedule</label>
            <input type="text" name="schedule" class="form-input" value="<?= htmlspecialchars($user['schedule']) ?>" placeholder="e.g. Mon, Wed, Fri (09:00 AM - 04:00 PM)" required>
            <small style="color:var(--muted-foreground); margin-top:4px;">Patients will see this schedule when booking.</small>
          </div>

          <div class="form-group"><label>New Password (Optional)</label><input type="password" name="password" class="form-input" placeholder="Leave blank to keep current password"></div>
          
          <button type="submit" class="btn btn-primary" style="width:100%;margin-top:1rem;height:44px">Save Changes</button>
        </form>
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
