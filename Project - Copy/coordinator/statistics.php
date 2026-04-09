<?php
require "../includes/auth.php";
require "../includes/db.php";

if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

$userId = $_SESSION['user_id'];
$facultyName = "Faculty";
$facultyId = null;


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


$totalSubmissions = 0;
$totalContributors = 0;
$totalSelected = 0;
$totalPending = 0;
$totalRejected = 0;

if ($facultyId !== null) {
    
    $stmt1 = $conn->prepare("SELECT COUNT(*) AS total FROM contributions WHERE faculty_id = ?");
    $stmt1->bind_param("i", $facultyId);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $row1 = $result1->fetch_assoc();
    $totalSubmissions = $row1['total'];
    $stmt1->close();

    
    $stmt2 = $conn->prepare("SELECT COUNT(DISTINCT student_id) AS total FROM contributions WHERE faculty_id = ?");
    $stmt2->bind_param("i", $facultyId);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $row2 = $result2->fetch_assoc();
    $totalContributors = $row2['total'];
    $stmt2->close();

    
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
    $stmt3->close();

  
    $stmt4 = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM contributions
        WHERE faculty_id = ?
          AND status_id = (
              SELECT status_id FROM contribution_status WHERE status_name = 'Pending' LIMIT 1
          )
    ");
    $stmt4->bind_param("i", $facultyId);
    $stmt4->execute();
    $result4 = $stmt4->get_result();
    $row4 = $result4->fetch_assoc();
    $totalPending = $row4['total'];
    $stmt4->close();

    // Added rejected count query
    $stmt5 = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM contributions
        WHERE faculty_id = ?
          AND status_id = (
              SELECT status_id FROM contribution_status WHERE status_name = 'Rejected' LIMIT 1
          )
    ");
    $stmt5->bind_param("i", $facultyId);
    $stmt5->execute();
    $result5 = $stmt5->get_result();
    $row5 = $result5->fetch_assoc();
    $totalRejected = $row5['total'];
    $stmt5->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Coordinator | Statistics</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="coordinator.css">
<link rel="stylesheet" href="statistics.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

<header class="topbar">
    <div class="left">
        <i class="fa-brands fa-reddit-alien logo"></i>
        <span class="title">UniMag — Coordinator</span>
    </div>

    <div class="right">
        <span class="faculty-tag"><?= htmlspecialchars($facultyName) ?></span>
        <a href="../includes/logout.php" class="btn ghost">Logout</a>
    </div>
</header>

<div class="container">

<aside class="sidebar">
    <a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    <a href="contributions.php"><i class="fa-solid fa-folder-open"></i> Contributions</a>
    <a href="review.php"><i class="fa-solid fa-clock"></i> Pending Reviews</a>
    <a href="selected.php"><i class="fa-solid fa-check"></i> Selected</a>
    <a href="statistics.php" class="active"><i class="fa-solid fa-chart-column"></i> Statistics</a>
</aside>

<main class="feed">
    <h1>Faculty Statistics</h1>
    
    <?php if ($facultyId !== null): ?>
        <div class="stats-grid">
            <div class="stat-card primary">
                <h3>Total Submissions</h3>
                <span><?= $totalSubmissions ?></span>
            </div>

            <div class="stat-card info">
                <h3>Contributors</h3>
                <span><?= $totalContributors ?></span>
            </div>

            <div class="stat-card success">
                <h3>Selected</h3>
                <span><?= $totalSelected ?></span>
            </div>

            <div class="stat-card warning">
                <h3>Pending Reviews</h3>
                <span><?= $totalPending ?></span>
            </div>

            <div class="stat-card danger">
                <h3>Rejected</h3>
                <span><?= $totalRejected ?></span>
            </div>
        </div>

        <!-- Optional: Add a chart or additional stats -->
        <div class="summary-section">
            <h3>Summary</h3>
            <ul class="summary-list">
                <li>
                    <strong>Acceptance Rate:</strong> 
                    <?php 
                    if ($totalSubmissions > 0) {
                        $acceptanceRate = ($totalSelected / $totalSubmissions) * 100;
                        echo number_format($acceptanceRate, 2) . "%";
                    } else {
                        echo "N/A";
                    }
                    ?>
                </li>
                <li>
                    <strong>Rejection Rate:</strong> 
                    <?php 
                    if ($totalSubmissions > 0) {
                        $rejectionRate = ($totalRejected / $totalSubmissions) * 100;
                        echo number_format($rejectionRate, 2) . "%";
                    } else {
                        echo "N/A";
                    }
                    ?>
                </li>
                <li>
                    <strong>Pending Rate:</strong> 
                    <?php 
                    if ($totalSubmissions > 0) {
                        $pendingRate = ($totalPending / $totalSubmissions) * 100;
                        echo number_format($pendingRate, 2) . "%";
                    } else {
                        echo "N/A";
                    }
                    ?>
                </li>
            </ul>
        </div>
    <?php else: ?>
        <div class="no-faculty-message">
            <p>No faculty assigned to this coordinator.</p>
        </div>
    <?php endif; ?>
</main>

</div>
</body>
</html>