<?php
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header('Location: ../index.php'); exit; }

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_dept'])) {
    $name = trim($_POST['dept_name']);
    $desc = trim($_POST['description']);
    $stmt = $conn->prepare("INSERT INTO departments (dept_name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $desc);
    $stmt->execute();
    $stmt->close();
    header('Location: departments.php'); exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM departments WHERE dept_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: departments.php'); exit;
}

$depts = $conn->query("
    SELECT d.dept_id, d.dept_name, d.description, COUNT(doc.doctor_id) AS doc_count
    FROM departments d
    LEFT JOIN doctors doc ON d.dept_id = doc.dept_id
    GROUP BY d.dept_id
    ORDER BY d.dept_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Departments — MediCare HMS</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-main">
    <div class="admin-topbar">
      <h1>Departments</h1>
      <div class="topbar-right">
        <span><?= date('D, M j Y') ?></span>
        <div class="notif-bell">🔔<span class="dot"></span></div>
      </div>
    </div>
    <div class="admin-content">
      <div class="card" style="padding:0;overflow:hidden;margin-bottom:1rem">
        <table class="data-table">
          <thead><tr><th>Department</th><th>Description</th><th>Doctors</th><th>Actions</th></tr></thead>
          <tbody>
          <?php while ($d = $depts->fetch_assoc()): ?>
            <tr>
              <td style="font-weight:500"><?= htmlspecialchars($d['dept_name']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($d['description'] ?? '—') ?></td>
              <td><span class="pill pill-blue"><?= $d['doc_count'] ?></span></td>
              <td><a href="?delete=<?= $d['dept_id'] ?>" class="btn btn-red btn-sm" onclick="return confirm('Delete?')">Delete</a></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <div class="inline-form">
        <h3 style="font-size:14px;font-weight:500;margin-bottom:.75rem">Add department</h3>
        <form method="POST">
          <div class="form-row">
            <div class="form-group"><label>Department name</label><input type="text" name="dept_name" class="form-input" required></div>
            <div class="form-group"><label>Description</label><input type="text" name="description" class="form-input"></div>
            <div class="form-group"><label>&nbsp;</label><button type="submit" name="add_dept" class="btn btn-primary">Add</button></div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
</body>
</html>
