<?php
require "../includes/auth.php";
require "../includes/db.php";

// SEARCH USER BY EMAIL
$search = "";
if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $stmt = $conn->prepare("
        SELECT users.user_id,
               users.name,
               users.email,
               roles.role_name,
               faculties.faculty_name
        FROM users
        LEFT JOIN roles ON users.role_id = roles.role_id
        LEFT JOIN faculties ON users.faculty_id = faculties.faculty_id
        WHERE users.email LIKE ?
        ORDER BY users.user_id DESC
    ");
    $like = "%$search%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "
        SELECT users.user_id,
               users.name,
               users.email,
               roles.role_name,
               faculties.faculty_name
        FROM users
        LEFT JOIN roles ON users.role_id = roles.role_id
        LEFT JOIN faculties ON users.faculty_id = faculties.faculty_id
        ORDER BY users.user_id DESC
    ";
    $result = $conn->query($sql);
}

// FETCH ROLES FOR CREATE USER
$roles = $conn->query("SELECT * FROM roles");

// FETCH FACULTIES
$faculties = $conn->query("SELECT * FROM faculties");

// Initialize error variable
$error = "";
$success = "";

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;
    $faculty_id = isset($_POST['faculty_id']) && !empty($_POST['faculty_id']) ? intval($_POST['faculty_id']) : null;

    // Basic validation
    if (empty($name) || empty($email) || empty($password) || $role_id === 0) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Prepare insert statement
            $stmt_insert = $conn->prepare("INSERT INTO users (name, email, password_hash, role_id, faculty_id) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("sssii", $name, $email, $hashed_password, $role_id, $faculty_id);

            if ($stmt_insert->execute()) {
                // Redirect to user list or login after success
                header("Location: users.php?success=1");
                exit();
            } else {
                $error = "Registration failed: " . $stmt_insert->error;
            }

            $stmt_insert->close();
        }
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Management | UniMag</title>
<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="admin.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>


<body>

<header class="topbar">
<div class="left">
    <button class="burger" id="burger"><i class="fa-solid fa-bars"></i></button>
    <a href="index.php" style="display:flex; align-items:center; gap:6px; text-decoration:none; color:inherit;">
        <i class="fa-brands fa-reddit-alien logo"></i>
        <span class="title">UniMag</span>
    </a>
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

<h1 class="page-title">User Management</h1>

<!-- SEARCH -->
<form method="GET" style="margin-bottom:20px;">
    <input type="text" name="search" placeholder="Search by email" value="<?= htmlspecialchars($search) ?>">
    <button type="submit" class="btn small">Search</button>
    <a href="users.php" class="btn small ghost">Reset</a>
</form>

<!-- CREATE USER -->
<h2>Create New User</h2>
 <!-- ── Alerts ── -->
<?php if ($error !== ""): ?>
    <div class="alert error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($success !== ""): ?>
    <div class="alert success">
        <i class="fa-solid fa-circle-check"></i>
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<form method="POST"  class="form">
    <input type="text" name="name" placeholder="Full Name" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>

    <select name="role_id" required>
        <option value="">Select Role</option>
        <?php while ($role = $roles->fetch_assoc()): ?>
            <option value="<?= $role['role_id'] ?>">
                <?= htmlspecialchars($role['role_name']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <select name="faculty_id">
        <option value="">Select Faculty (Optional)</option>
        <?php while ($fac = $faculties->fetch_assoc()): ?>
            <option value="<?= $fac['faculty_id'] ?>">
                <?= htmlspecialchars($fac['faculty_name']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <button type="submit" class="btn primary">Create User</button>
   
</form>

<br><br>

<table class="data-table">
<thead>
<tr>

<th>Name</th>
<th>Email</th>
<th>Role</th>
<th>Faculty</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php if ($result && $result->num_rows > 0): ?>
    <?php while ($user = $result->fetch_assoc()): ?>
        <tr>
            
            <td><?= htmlspecialchars($user['name']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= htmlspecialchars($user['role_name']) ?></td>
            <td><?= htmlspecialchars($user['faculty_name'] ?? 'N/A') ?></td>
            <td>
                <a href="edit-user.php?id=<?= $user['user_id'] ?>" class="btn small">Edit</a>
                <a href="delete-user.php?id=<?= $user['user_id'] ?>" 
                   class="btn small danger"
                   onclick="return confirm('Delete this user?')">
                   Delete
                </a>
            </td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr>
        <td colspan="6">No users found.</td>
    </tr>
<?php endif; ?>

</tbody>
</table>

</main>
</div>
</body>
</html>