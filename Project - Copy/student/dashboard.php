<?php
require "../includes/db.php";
require "../includes/auth.php";
require "../includes/header.php";


if ($_SESSION['role'] != 1) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// get latest academic year
$result = $conn->query("
SELECT submission_closure_date 
FROM academic_years 
ORDER BY academic_year_id DESC 
LIMIT 1
");

$year = $result->fetch_assoc();
$submission_date = date("d F Y", strtotime($year['submission_closure_date']));


//DASHBOARD STATISTICS

$stmt = $conn->prepare("
SELECT COUNT(*) as total
FROM contributions
WHERE student_id = ?");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];

//////
$stmt = $conn->prepare("
    SELECT u.name, f.faculty_name 
    FROM users u
    LEFT JOIN faculties f ON u.faculty_id = f.faculty_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$student_name = $user['name'] ?? 'Student';
$faculty_name = $user['faculty_name'] ?? 'your';



//Pending submissions (status_id = 1)

$stmt = $conn->prepare("
SELECT COUNT(*) as pending
FROM contributions
WHERE student_id = ? AND status_id = 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending = $stmt->get_result()->fetch_assoc()['pending'];


//Approved / Selected submissions (status_id = 3)

$stmt = $conn->prepare("
SELECT COUNT(*) as approved
FROM contributions
WHERE student_id = ? AND status_id = 3
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$approved = $stmt->get_result()->fetch_assoc()['approved'];


//RECENT CONTRIBUTIONS QUERY for comments: We get only the latest comment per contribution

$sql = "
SELECT 
c.contribution_id,
c.title,
f.faculty_name,
cs.status_name,

(
SELECT comment_text
FROM comments
WHERE contribution_id = c.contribution_id
ORDER BY created_at DESC
LIMIT 1
) AS latest_comment

FROM contributions c

JOIN faculties f 
ON c.faculty_id = f.faculty_id

JOIN contribution_status cs 
ON c.status_id = cs.status_id

WHERE c.student_id = ?

ORDER BY c.submitted_at DESC
LIMIT 5
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$contributions = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Dashboard | UniMag</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../style.css">
<link rel="stylesheet" href="student.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<script src="student.js" defer></script>
</head>

<body>

<header class="topbar">
<div class="left">
    <button class="burger" id="burger"><i class="fa-solid fa-bars"></i></button>
    <a href="../index.php" style="display:flex; align-items:center; gap:6px; text-decoration:none; color:inherit;">
        <i class="fa-brands fa-reddit-alien logo"></i>
        <span class="title">UniMag</span>
    </a></div>

   <div class="right">
    <form action="../includes/logout.php" method="POST" style="display:inline;">
        <button type="submit" class="btn ghost">Logout 🙅‍♂️</button>
    </form>
</div>
</header>

<div class="container">

<!-- SIDEBAR -->
<aside class="sidebar">

<a href="dashboard.php" class="active">
<i class="fa-solid fa-gauge"></i> Dashboard
</a>

<a href="submit.php">
<i class="fa-solid fa-file-circle-plus"></i> Submit Article
</a>

<a href="contributions.php">
<i class="fa-solid fa-folder-open"></i> My Contributions
</a>

<a href="terms.php">
<i class="fa-solid fa-scale-balanced"></i> Terms
</a>

</aside>


<!-- MAIN -->
<main class="feed">
<h1 class="page-title">
    Welcome <?php echo htmlspecialchars($faculty_name); ?> Student
</h1>

<div class="info-banner">
Submissions close on <strong><?php echo $submission_date; ?></strong>
</div>

<div class="dashboard-cards">

<div class="dash-card">
<i class="fa-solid fa-file-lines"></i>
<h3>Total Submissions</h3>
<p><?php echo $total; ?></p>
</div>

<div class="dash-card">
<i class="fa-solid fa-clock"></i>
<h3>Pending Review</h3>
<p><?php echo $pending; ?></p>
</div>

<div class="dash-card">
<i class="fa-solid fa-check"></i>
<h3>selected</h3>
<p><?php echo $approved; ?></p>

</div>

</div>


<h2 class="section-title">Recent Contributions</h2>

<table class="data-table">

<thead>
<tr>
<th>Title</th>
<th>Faculty</th>
<th>Status</th>
<th>Coordinator Comment</th>
<th>Action</th>
</tr>
</thead>


<tbody>
    <?php if ($contributions->num_rows > 0): ?>
        <?php while ($row = $contributions->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                <td>
                    <span class="badge">
                        <?php echo htmlspecialchars($row['status_name']); ?>
</span>
</td>
<td>
    <?php echo $row['latest_comment'] ? htmlspecialchars($row['latest_comment']) : "No comment yet";?>

</td>

<td>
   
    <a href="edit_contribution.php?id=<?php echo $row['contribution_id']; ?>" class="btn small ghost">Edit</a>

</td>

</tr>
<?php endwhile; ?>
<?php else: ?>
    <tr>
        <td colspan="5">No contributions submitted yet.</td>
    </tr>

<?php endif; ?>

</tbody>
</table>

</main>

</div>

</body>
</html>
