<?php
require "../includes/auth.php";
require "../includes/db.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("User ID not provided.");
}

$userId = intval($_GET['id']);

/* Fetch roles */
$rolesResult = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_id ASC");

/* Fetch faculties */
$facultiesResult = $conn->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name ASC");

/* Handle form submission */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role_id = intval($_POST['role_id']);
    $faculty_id = !empty($_POST['faculty_id']) ? intval($_POST['faculty_id']) : null;

    if (empty($name) || empty($email) || empty($role_id)) {
        die("Please fill in all required fields.");
    }

    if ($faculty_id === null) {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role_id = ?, faculty_id = NULL WHERE user_id = ?");
        $stmt->bind_param("ssii", $name, $email, $role_id, $userId);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role_id = ?, faculty_id = ? WHERE user_id = ?");
        $stmt->bind_param("ssiii", $name, $email, $role_id, $faculty_id, $userId);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: users.php");
    exit;
}

/* Fetch current user details */
$stmt = $conn->prepare("SELECT user_id, name, email, role_id, faculty_id FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit User | UniMag</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="admin.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

<header class="topbar">
<div class="left">
    <i class="fa-solid fa-shield-halved logo"></i>
    <span class="title">UniMag Admin</span>
</div>

<div class="right">
    <a href="../includes/logout.php" class="btn ghost">Logout</a>
</div>
</header>

<div class="container">

<aside class="sidebar">
    <a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    <a href="academic-years.php"><i class="fa-solid fa-calendar-days"></i> Academic Years</a>
    <a href="faculties.php"><i class="fa-solid fa-building-columns"></i> Faculties</a>
    <a href="users.php" class="active"><i class="fa-solid fa-users"></i> Users</a>
    <a href="statistics.php"><i class="fa-solid fa-chart-line"></i> Statistics</a>
</aside>

<main class="feed">

<h1 class="page-title">Edit User</h1>

<div class="form-card">
    <form method="POST" action="edit-user.php?id=<?= $user['user_id'] ?>">

        <label>Full Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>

        <label>Email Address</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

        <label>Role</label>
        <select name="role_id" required>
            <option value="">Select Role</option>
            <?php if ($rolesResult && $rolesResult->num_rows > 0): ?>
                <?php while ($role = $rolesResult->fetch_assoc()): ?>
                    <option value="<?= $role['role_id'] ?>" <?= ($user['role_id'] == $role['role_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($role['role_name']) ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>

        <label>Faculty</label>
        <select name="faculty_id">
            <option value="">No Faculty</option>
            <?php if ($facultiesResult && $facultiesResult->num_rows > 0): ?>
                <?php while ($faculty = $facultiesResult->fetch_assoc()): ?>
                    <option value="<?= $faculty['faculty_id'] ?>" <?= ($user['faculty_id'] == $faculty['faculty_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($faculty['faculty_name']) ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>

        <div class="btn-row">
  <button type="submit" class="btn primary">Update User</button>
  <a href="users.php" class="btn ghost">Cancel</a>
</div>
    </form>
</div>

</main>

</div>

</body>
</html