<?php
require "../includes/auth.php";
require "../includes/db.php";


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_faculty"])) {
    $faculty_name = trim($_POST["faculty_name"]);

    if (!empty($faculty_name)) {
        $stmt = $conn->prepare("INSERT INTO faculties (faculty_name) VALUES (?)");
        $stmt->bind_param("s", $faculty_name);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: faculties.php");
    exit;
}


$sql = "SELECT faculty_id, faculty_name
        FROM faculties
        ORDER BY faculty_id DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Faculties | UniMag</title>
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

<a href="dashboard.php">
<i class="fa-solid fa-gauge"></i> Dashboard
</a>

<a href="academic-years.php">
<i class="fa-solid fa-calendar-days"></i> Academic Years
</a>

<a href="faculties.php" class="active">
<i class="fa-solid fa-building-columns"></i> Faculties
</a>

<a href="users.php">
<i class="fa-solid fa-users"></i> Users
</a>

<a href="statistics.php">
<i class="fa-solid fa-chart-line"></i> Statistics
</a>

</aside>

<main class="feed">

<h1 class="page-title">Faculty Management</h1>

<div class="form-card">

<h3>Add Faculty</h3>

<form method="POST" action="faculties.php">

<label>Faculty Name</label>
<input type="text" name="faculty_name" placeholder="Faculty of Computing" required>

<button type="submit" name="add_faculty" class="btn primary">Add Faculty</button>

</form>

</div>

<br>

<table class="data-table">

<thead>
<tr>
<th>ID</th>
<th>Faculty</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php if ($result && $result->num_rows > 0): ?>
    <?php while ($faculty = $result->fetch_assoc()): ?>
        <tr>
            
            <td><?= htmlspecialchars($faculty['faculty_name']) ?></td>
            <td>
                <a href="delete-faculty.php?id=<?= $faculty['faculty_id'] ?>" class="btn small danger"
                   onclick="return confirm('Are you sure you want to delete this faculty?')">Delete</a>
            </td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr>
        <td colspan="3">No faculties found.</td>
    </tr>
<?php endif; ?>

</tbody>

</table>

</main>

</div>

</body>
</html>