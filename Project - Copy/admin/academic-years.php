<?php
require "../includes/auth.php";
require "../includes/db.php";

// ensure only admin
if ($_SESSION['role'] != 4) {
    header("Location: ../login.php");
    exit();
}

// DELETE
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $stmt = $conn->prepare("DELETE FROM academic_years WHERE academic_year_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: academic-years.php");
    exit();
}

// CREATE
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $year_name = trim($_POST['year_name']);
    $submission_date = $_POST['submission_date'];
    $final_date = $_POST['final_date'];

    if (empty($year_name) || empty($submission_date) || empty($final_date)) {
        $message = "All fields are required.";
    } elseif ($final_date < $submission_date) {
        $message = "Final closure must be after submission closure.";
    } else {

        $stmt = $conn->prepare("
            INSERT INTO academic_years (year_name, submission_closure_date, final_closure_date)
            VALUES (?, ?, ?)
        ");

        $stmt->bind_param("sss", $year_name, $submission_date, $final_date);
        $stmt->execute();

        $message = "Academic year created successfully.";
    }
}

// FETCH YEARS
$years = $conn->query("SELECT * FROM academic_years ORDER BY academic_year_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Academic Years | UniMag</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="admin.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

<header class="topbar">
<div class="left">
    <button class="burger" id="burger"><i class="fa-solid fa-bars"></i></button>
    <a href="../index.php" style="display:flex; align-items:center; gap:6px; text-decoration:none; color:inherit;">
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
    <a href="academic-years.php" class="active"><i class="fa-solid fa-calendar-days"></i> Academic Years</a>
    <a href="faculties.php"><i class="fa-solid fa-building-columns"></i> Faculties</a>
    <a href="users.php"><i class="fa-solid fa-users"></i> Users</a>
    <a href="statistics.php"><i class="fa-solid fa-chart-line"></i> Statistics</a>
</aside>

<main class="feed">
    <h1 class="page-title">Academic Years</h1>

    <?php if ($message != ""): ?>
        <p class="success"><?php echo $message; ?></p>
    <?php endif; ?>

    <!-- CREATE FORM -->
    <div class="form-card">
        <h3>Create Academic Year</h3>

        <form method="POST">
            <label>Academic Year</label>
            <input type="text" name="year_name" placeholder="2025/2026" required>

            <label>Submission Closure Date</label>
            <input type="date" name="submission_date" required>

            <label>Final Closure Date</label>
            <input type="date" name="final_date" required>

            <button type="submit" class="btn primary">Create Academic Year</button>
        </form>
    </div>

    <br>

    <!-- TABLE -->
    <table class="data-table">
        <thead>
            <tr>
                <th>Year</th>
                <th>Submission Closure</th>
                <th>Final Closure</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
        <?php while ($row = $years->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['year_name']); ?></td>
                <td><?php echo date("d M Y", strtotime($row['submission_closure_date'])); ?></td>
                <td><?php echo date("d M Y", strtotime($row['final_closure_date'])); ?></td>
                <td>
                    <a href="?delete=<?php echo $row['academic_year_id']; ?>" class="btn small danger">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

</main>

</div>

</body>
</html>