<?php
require "../includes/auth.php";
require "../includes/db.php";
require "../includes/header.php";

// ensure only marketing manager (role_id = 3)
if ($_SESSION['role'] != 3) {
    header("Location: ../login.php");
    exit();
}
// Total contributions
$total = $conn->query("SELECT COUNT(*) as total FROM contributions");
$total_submissions = $total->fetch_assoc()['total'];

// Selected (Approved)
$selected = $conn->query("SELECT COUNT(*) as total FROM contributions WHERE status_id = 3");
$total_selected = $selected->fetch_assoc()['total'];

// Pending
$pending = $conn->query("SELECT COUNT(*) as total FROM contributions WHERE status_id = 1");
$total_pending = $pending->fetch_assoc()['total'];

// Rejected
$rejected = $conn->query("SELECT COUNT(*) as total FROM contributions WHERE status_id = 4");
$total_rejected = $rejected->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Marketing Manager | Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="manager.css">
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
    <h1>Welcome 🐦‍🔥 <?php echo $_SESSION['name']; ?></h1>

 <form action="../includes/logout.php" method="POST" style="display:inline;">
            <button type="submit" class="btn ghost">Logout</button>
</header></form>

<div class="container">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <a href="dashboard.php" class="active">
            <i class="fa-solid fa-gauge"></i> Dashboard
        </a>
        <a href="contributions.php">
            <i class="fa-solid fa-folder-open"></i> Contributions
        </a>
        <a href="statistics.php">
            <i class="fa-solid fa-chart-line"></i> Statistics
        </a>
        <a href="bulk-notify.php"><i class="fa-solid fa-bell"></i> Notify Users</a>
    </aside>

    <!-- MAIN -->
    <main class="feed">

       

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $total_submissions; ?></h3>
                <p>Total Submissions</p>
            </div>

            <div class="stat-card">
                <h3><?php echo $total_selected; ?></h3>
                <p>Approved</p>
            </div>

            <div class="stat-card">
                <h3><?php echo $total_pending; ?></h3>
                <p>Pending</p>
            </div>

            <div class="stat-card danger">
                <h3><?php echo $total_rejected; ?></h3>
                <p>Rejected</p>
            </div>
        </div>

    </main>
</div>

</body>
</html>
