<?php
require "../includes/auth.php";
require "../includes/db.php";
require "../includes/header.php";

$totalUsers = 0;
$totalFaculties = 0;
$totalAcademicYears = 0;
$totalContributions = 0;
$totalPending = 0;
$totalSelected = 0;
$totalRejected = 0;


$result = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($result) {
    $totalUsers = $result->fetch_assoc()['total'];
}


$result = $conn->query("SELECT COUNT(*) AS total FROM faculties");
if ($result) {
    $totalFaculties = $result->fetch_assoc()['total'];
}


$result = $conn->query("SELECT COUNT(*) AS total FROM academic_years");
if ($result) {
    $totalAcademicYears = $result->fetch_assoc()['total'];
}


$result = $conn->query("SELECT COUNT(*) AS total FROM contributions");
if ($result) {
    $totalContributions = $result->fetch_assoc()['total'];
}


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM contributions
    WHERE status_id = (
        SELECT status_id
        FROM contribution_status
        WHERE status_name = 'Pending'
        LIMIT 1
    )
");
if ($result) {
    $totalPending = $result->fetch_assoc()['total'];
}


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM contributions
    WHERE status_id = (
        SELECT status_id
        FROM contribution_status
        WHERE status_name = 'Selected'
        LIMIT 1
    )
");
if ($result) {
    $totalSelected = $result->fetch_assoc()['total'];
}


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM contributions
    WHERE status_id = (
        SELECT status_id
        FROM contribution_status
        WHERE status_name = 'Rejected'
        LIMIT 1
    )
");
if ($result) {
    $totalRejected = $result->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard | UniMag</title>
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
    <span class="faculty-tag">Administrator</span>
    <a href="../includes/logout.php" class="btn ghost">Logout</a>
</div>
</header>

<div class="container">

<aside class="sidebar">
    <a href="dashboard.php" class="active">
        <i class="fa-solid fa-gauge"></i> Dashboard
    </a>

    <a href="academic-years.php">
        <i class="fa-solid fa-calendar-days"></i> Academic Years
    </a>

    <a href="faculties.php">
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
    <h1 class="page-title">Admin Dashboard</h1>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Users</h3>
            <span><?= $totalUsers ?></span>
        </div>

        <div class="stat-card">
            <h3>Total Faculties</h3>
            <span><?= $totalFaculties ?></span>
        </div>

        <div class="stat-card">
            <h3>Academic Years</h3>
            <span><?= $totalAcademicYears ?></span>
        </div>

        <div class="stat-card">
            <h3>Total Contributions</h3>
            <span><?= $totalContributions ?></span>
        </div>

        <div class="stat-card warning">
            <h3>Pending</h3>
            <span><?= $totalPending ?></span>
        </div>

        <div class="stat-card success">
            <h3>Selected</h3>
            <span><?= $totalSelected ?></span>
        </div>

        <div class="stat-card danger">
            <h3>Rejected</h3>
            <span><?= $totalRejected ?></span>
        </div>
    </div>
</main>

</div>

</body>
</html>