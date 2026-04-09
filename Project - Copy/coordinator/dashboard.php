<?php
require "../includes/auth.php";
require "../includes/db.php";
require "../includes/header.php";

if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

$userId = $_SESSION['user_id'];
$facultyName = "Faculty";
$facultyId = null;

$totalSubmissions = 0;
$totalPending = 0;
$totalSelected = 0;


$facultyQuery = "SELECT faculties.faculty_id, faculties.faculty_name
                 FROM users
                 LEFT JOIN faculties ON users.faculty_id = faculties.faculty_id
                 WHERE users.user_id = ?";

$stmtFaculty = $conn->prepare($facultyQuery);
$stmtFaculty->bind_param("i", $userId);
$stmtFaculty->execute();
$facultyResult = $stmtFaculty->get_result();

if ($facultyRow = $facultyResult->fetch_assoc()) {
    $facultyId = $facultyRow['faculty_id'];
    $facultyName = $facultyRow['faculty_name'];
}


if ($facultyId !== null) {
    $stmt1 = $conn->prepare("SELECT COUNT(*) AS total FROM contributions WHERE faculty_id = ?");
    $stmt1->bind_param("i", $facultyId);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $row1 = $result1->fetch_assoc();
    $totalSubmissions = $row1['total'];

    $stmt2 = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM contributions
        WHERE faculty_id = ?
          AND status_id = (
              SELECT status_id FROM contribution_status WHERE status_name = 'Pending' LIMIT 1
          )
    ");
    $stmt2->bind_param("i", $facultyId);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $row2 = $result2->fetch_assoc();
    $totalPending = $row2['total'];

    $stmt3 = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM contributions
        WHERE faculty_id = ?
          AND status_id = (
              SELECT status_id FROM contribution_status WHERE status_name = 'Selected' LIMIT 1
          )
    ");
    $stmt3->bind_param("i", $facultyId);
    $stmt3->execute();
    $result3 = $stmt3->get_result();
    $row3 = $result3->fetch_assoc();
    $totalSelected = $row3['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Coordinator Dashboard | UniMag</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="coordinator.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

<header class="topbar">
 <div class="left">
    <button class="burger" id="burger"><i class="fa-solid fa-bars"></i></button>
    <a href="../index.php" style="display:flex; align-items:center; gap:6px; text-decoration:none; color:inherit;">
        <i class="fa-brands fa-reddit-alien logo"></i>
        <span class="title">UniMag -- Coordinator</span>
    </a>
</div>

    <div class="right">
        <span class="faculty-tag"><?= htmlspecialchars($facultyName) ?></span>
        <a href="../includes/logout.php" class="btn ghost">Logout</a>
    </div>
</header>

<div class="container">

    <aside class="sidebar">
        <a href="dashboard.php" class="active"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="contributions.php"><i class="fa-solid fa-folder-open"></i> Contributions</a>
        <a href="review.php"><i class="fa-solid fa-clock"></i> Pending Reviews</a>
        <a href="selected.php"><i class="fa-solid fa-check"></i> Selected</a>
        <a href="statistics.php"><i class="fa-solid fa-chart-column"></i> Statistics</a>
    </aside>

    <main class="feed">
        <h1>Dashboard Overview</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Submissions</h3>
                <span><?= $totalSubmissions ?></span>
            </div>

            <div class="stat-card warning">
                <h3>Pending Reviews</h3>
                <span><?= $totalPending ?></span>
            </div>

            <div class="stat-card success">
                <h3>Selected</h3>
                <span><?= $totalSelected ?></span>
            </div>
        </div>
    </main>

</div>

</body>
</html>