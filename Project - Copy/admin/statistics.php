<?php
require "../includes/auth.php";
require "../includes/db.php";

// Contribution status counts
$totalsubmitted = $totalSelected = $totalRejected = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total FROM contributions
    WHERE status_id = (SELECT status_id FROM contribution_status WHERE status_name = 'submitted' LIMIT 1)
");
if ($result) $totalsubmitted = $result->fetch_assoc()['total'];

$result = $conn->query("
    SELECT COUNT(*) AS total FROM contributions
    WHERE status_id = (SELECT status_id FROM contribution_status WHERE status_name = 'Selected' LIMIT 1)
");
if ($result) $totalSelected = $result->fetch_assoc()['total'];

$result = $conn->query("
    SELECT COUNT(*) AS total FROM contributions
    WHERE status_id = (SELECT status_id FROM contribution_status WHERE status_name = 'Rejected' LIMIT 1)
");
if ($result) $totalRejected = $result->fetch_assoc()['total'];

// Contributions per faculty
$facultyLabels = [];
$facultyData = [];
$result = $conn->query("
    SELECT f.faculty_name, COUNT(c.contribution_id) AS total
    FROM faculties f
    LEFT JOIN users u ON u.faculty_id = f.faculty_id
    LEFT JOIN contributions c ON c.student_id = u.user_id
    GROUP BY f.faculty_name
    ORDER BY total DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $facultyLabels[] = $row['faculty_name'];
        $facultyData[]   = (int)$row['total'];
    }
}

// Contributions per academic year
$yearLabels = [];
$yearData = [];
$result = $conn->query("
    SELECT ay.year_name, COUNT(c.contribution_id) AS total
    FROM academic_years ay
    LEFT JOIN contributions c ON c.academic_year_id = ay.academic_year_id
    GROUP BY ay.year_name
    ORDER BY ay.year_name ASC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $yearLabels[] = $row['year_name'];
        $yearData[]   = (int)$row['total'];
    }
}
// Users by role
$roleLabels = [];
$roleData = [];
$roleColors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#ec4899'];

$result = $conn->query("
    SELECT r.role_name, COUNT(u.user_id) AS total
    FROM roles r
    LEFT JOIN users u ON u.role_id = r.role_id
    GROUP BY r.role_name
    ORDER BY total DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $roleLabels[] = $row['role_name'];
        $roleData[]   = (int)$row['total'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Admin Statistics | UniMag</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="stylesheet" href="../style.css" />
<link rel="stylesheet" href="admin.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
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
    <a href="academic-years.php"><i class="fa-solid fa-calendar-days"></i> Academic Years</a>
    <a href="faculties.php"><i class="fa-solid fa-building-columns"></i> Faculties</a>
    <a href="users.php"><i class="fa-solid fa-users"></i> Users</a>
    <a href="statistics.php" class="active"><i class="fa-solid fa-chart-line"></i> Statistics</a>
  </aside>

  <main class="feed">
    <h1 class="page-title">System Statistics</h1>

    <div class="charts-grid">

      <!-- Contribution Status Doughnut -->
      <div class="chart-card">
        <h3>Contribution Status Breakdown</h3>
        <canvas id="statusChart"></canvas>
      </div>

      <!-- Users Active vs Inactive Pie -->
      <div class="chart-card">
        <h3>Users </h3>
        <canvas id="usersChart"></canvas>
      </div>

      <!-- Contributions per Faculty Bar -->
      <div class="chart-card">
        <h3>Contributions per Faculty</h3>
        <canvas id="facultyChart"></canvas>
      </div>

      <!-- Contributions per Academic Year Line -->
      <div class="chart-card">
        <h3>Contributions per Academic Year</h3>
        <canvas id="yearChart"></canvas>
      </div>

    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Contribution Status 
  new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
      labels: ['submitted', 'Selected', 'Rejected'],
      datasets: [{
        data: [<?= $totalsubmitted ?>, <?= $totalSelected ?>, <?= $totalRejected ?>],
        backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom' },
        tooltip: {
          callbacks: {
            label: function(context) {
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
              return ` ${context.label}: ${context.parsed} (${pct}%)`;
            }
          }
        }
      }
    }
  });
//users pie
 new Chart(document.getElementById('usersChart'), {
    type: 'pie',
    data: {
      labels: <?= json_encode($roleLabels) ?>,
      datasets: [{
        data: <?= json_encode($roleData) ?>,
        backgroundColor: <?= json_encode(array_slice($roleColors, 0, count($roleLabels))) ?>,
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom' },
        tooltip: {
          callbacks: {
            label: function(context) {
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
              return ` ${context.label}: ${context.parsed} (${pct}%)`;
            }
          }
        }
      }
    }
});
  // Contributions per Faculty Bar
  new Chart(document.getElementById('facultyChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($facultyLabels) ?>,
      datasets: [{
        label: 'Contributions',
        data: <?= json_encode($facultyData) ?>,
        backgroundColor: '#6366f1',
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });

  // Contributions per Academic Year Line
  new Chart(document.getElementById('yearChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode($yearLabels) ?>,
      datasets: [{
        label: 'Contributions',
        data: <?= json_encode($yearData) ?>,
        borderColor: '#6366f1',
        backgroundColor: 'rgba(99, 102, 241, 0.15)',
        borderWidth: 2,
        pointBackgroundColor: '#6366f1',
        fill: true,
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });
</script>
</body>
</html>