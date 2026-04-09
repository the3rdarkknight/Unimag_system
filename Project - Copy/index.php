<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require "includes/db.php";
require "includes/header.php";


$isGuest    = isset($_SESSION['is_guest']) && $_SESSION['is_guest'] === true;
$isLoggedIn = isset($_SESSION['user_id']) && !$isGuest;

// Fetch name from DB if missing
if ($isLoggedIn && empty($_SESSION['name'])) {
    $s = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    $s->bind_param("i", $_SESSION['user_id']);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $_SESSION['name'] = $row['name'] ?? 'User';
    $s->close();
}

// Dashboard link map
$dashLinks = [
    1 => 'student/dashboard.php',
    2 => 'coordinator/dashboard.php',
    3 => 'manager/dashboard.php',
    4 => 'admin/dashboard.php',
];
$role          = $_SESSION['role'] ?? 0;
$dashboardLink = $dashLinks[$role] ?? null;

// Faculty filter
// Guests are locked to the faculty they chose — stored in session on guest login
$facultyFilter = isset($_GET['faculty_id']) ? intval($_GET['faculty_id']) : 0;

// If guest, lock them to their session faculty — they cannot browse others
if ($isGuest) {
    // On first visit as guest, let them pick via URL; then lock it in session
    if ($facultyFilter > 0) {
        $_SESSION['guest_faculty_id'] = $facultyFilter;
    }
    // Always use session faculty for guests
    $facultyFilter = $_SESSION['guest_faculty_id'] ?? 0;
}

$selectedFacultyName = "";
$search = trim($_GET['search'] ?? '');

// Guest with no faculty chosen yet = blocked
$guestBlocked = $isGuest && $facultyFilter === 0;

// Fetch ALL faculties (used for logged-in sidebar + guest pick page)
$allFaculties = [];
$fRes = $conn->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name ASC");
while ($f = $fRes->fetch_assoc()) {
    $allFaculties[] = $f;
}

// Resolve selected faculty name
foreach ($allFaculties as $f) {
    if ($f['faculty_id'] == $facultyFilter) {
        $selectedFacultyName = $f['faculty_name'];
        break;
    }
}

// Fetch contributions
$contributions = [];
if (!$guestBlocked) {
    $params = [];
    $types  = '';

    $sql = "
        SELECT c.contribution_id, c.title, c.submitted_at, c.description,
               u.name AS student_name, f.faculty_name,
               cs.status_name
        FROM contributions c
        LEFT JOIN users               u  ON u.user_id    = c.student_id
        LEFT JOIN faculties           f  ON f.faculty_id = c.faculty_id
        LEFT JOIN contribution_status cs ON cs.status_id = c.status_id
        WHERE c.status_id = 3
    ";

    if ($facultyFilter > 0) {
        $sql     .= " AND c.faculty_id = ?";
        $types   .= 'i';
        $params[] = $facultyFilter;
    }

    if ($search !== '') {
        $sql     .= " AND (c.title LIKE ? OR c.description LIKE ?)";
        $types   .= 'ss';
        $like     = "%$search%";
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY c.submitted_at DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $contributions[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>UniMag | University Magazine</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="index.css">
</head>

<body>

<!-- ── TOPBAR ── -->
<header class="topbar">
  <div class="left">
    <button class="burger" id="burger"><i class="fa-solid fa-bars"></i></button>
    <a href="index.php" style="display:flex; align-items:center; gap:6px; text-decoration:none; color:inherit;">
        <i class="fa-brands fa-reddit-alien logo"></i>
        <span class="title">UniMag</span>
    </a>
</div>
    <div class="right">
        <div class="profile-wrapper">
            <i class="fa-solid fa-user-circle profile-icon" id="profileIcon"></i>

            <div class="profile-menu" id="profileMenu">
                <div class="profile-header">
                    <span>Account</span>
                    <i class="fa-solid fa-xmark close-profile" id="closeProfile"></i>
                </div>

                <?php if ($isLoggedIn): ?>
                    <p style="padding:10px 14px; margin:0; font-weight:600;">
                        <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>
                    </p>
                    <?php if ($dashboardLink): ?>
                        <a href="<?= $dashboardLink ?>" class="profile-btn login">Dashboard</a>
                    <?php endif; ?>
                    <a href="includes/logout.php" class="profile-btn register">Logout</a>

                <?php elseif ($isGuest): ?>
                    <p style="padding:10px 14px; margin:0; color:#888; font-size:13px;">
                        <i class="fa-solid fa-eye"></i> Browsing as Guest
                        <?php if ($selectedFacultyName): ?>
                            — <?= htmlspecialchars($selectedFacultyName) ?>
                        <?php endif; ?>
                    </p>
                    <a href="login.php" class="profile-btn login">Login</a>
                    <a href="register.php" class="profile-btn register">Register</a>

                <?php else: ?>
                    <a href="login.php" class="profile-btn login">Login</a>
                    <a href="register.php" class="profile-btn register">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<!-- ── HERO ── -->
<div class="hero">
    <h1>University <span>Magazine</span></h1>
    <p>
        <?php if ($isGuest && $selectedFacultyName): ?>
            Browsing selected contributions from <strong style="color:white;"><?= htmlspecialchars($selectedFacultyName) ?></strong>
        <?php elseif ($isLoggedIn): ?>
            Welcome back, <strong style="color:white;"><?= htmlspecialchars($_SESSION['name'] ?? '') ?></strong>
        <?php else: ?>
            Discover selected student contributions from across all faculties
        <?php endif; ?>
    </p>

    <?php if (!$guestBlocked): ?>
    <form method="GET" action="index.php" class="hero-search">
        <?php if ($facultyFilter > 0): ?>
            <input type="hidden" name="faculty_id" value="<?= $facultyFilter ?>">
        <?php endif; ?>
        <input type="text" name="search"
               placeholder="Search by title or description..."
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
    </form>
    <?php endif; ?>
</div>

<div class="container">

    <!-- ── SIDEBAR ── -->
    <aside class="sidebar" id="sidebar">
        <a href="index.php" class="<?= ($facultyFilter === 0 && !$isGuest) ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i> Home
        </a>
        <a href="terms.php"><i class="fa-solid fa-scale-balanced"></i> Terms</a>

        <?php if (!$isGuest): ?>
            <!-- Logged-in users see all faculties -->
            <hr>
            <h4>Faculties</h4>
            <?php foreach ($allFaculties as $faculty): ?>
                <a href="index.php?faculty_id=<?= $faculty['faculty_id'] ?>"
                   class="<?= $facultyFilter == $faculty['faculty_id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($faculty['faculty_name']) ?>
                </a>
            <?php endforeach; ?>

        <?php elseif ($isGuest && $facultyFilter > 0): ?>
            <!-- Guests only see their chosen faculty -->
            <hr>
            <h4>Your Faculty</h4>
            <a href="index.php?faculty_id=<?= $facultyFilter ?>" class="active">
                <?= htmlspecialchars($selectedFacultyName) ?>
            </a>
        <?php endif; ?>
    </aside>

    <!-- ── MAIN FEED ── -->
    <main class="feed">

        <?php if ($guestBlocked): ?>
            <!-- Guest hasn't picked a faculty yet — show picker -->
            <div class="faculty-picker">
                <i class="fa-solid fa-building-columns" style="font-size:32px; color:#ff4500; margin-bottom:12px;"></i>
                <h2>Choose Your Faculty</h2>
                <p>As a guest you can only browse contributions from one faculty. Select yours below to get started.</p>
                <form method="GET" action="index.php">
                    <select name="faculty_id" required>
                        <option value="">— Select a faculty —</option>
                        <?php foreach ($allFaculties as $f): ?>
                            <option value="<?= $f['faculty_id'] ?>">
                                <?= htmlspecialchars($f['faculty_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Browse Contributions</button>
                </form>
                <p class="login-nudge">
                    Want to see all faculties?
                    <a href="login.php">Log in</a>
                </p>
            </div>

        <?php else: ?>

            <?php if ($isGuest): ?>
                <div class="guest-banner">
                    <span>
                        <i class="fa-solid fa-eye"></i>
                        Guest access — read only.
                        You are viewing <strong><?= htmlspecialchars($selectedFacultyName) ?></strong>.
                    </span>
                    <a href="login.php">Login for full access →</a>
                </div>
            <?php endif; ?>

            <?php if ($search !== ''): ?>
                <p class="search-meta">
                    Search results for "<strong><?= htmlspecialchars($search) ?></strong>"
                    — <?= count($contributions) ?> found
                    <a href="index.php<?= $facultyFilter > 0 ? '?faculty_id='.$facultyFilter : '' ?>">
                        <i class="fa-solid fa-xmark"></i> Clear
                    </a>
                </p>
            <?php endif; ?>

            <?php if (!empty($contributions)): ?>
    <?php foreach ($contributions as $c): ?>
        <article class="post">
            <div class="content">
                <div class="meta">
                    <?= htmlspecialchars($c['faculty_name'] ?? 'No Faculty') ?>
                    &nbsp;•&nbsp;
                    <?= date("d M Y", strtotime($c['submitted_at'])) ?>
                    &nbsp;•&nbsp;
                    <?= htmlspecialchars($c['status_name'] ?? 'Selected') ?>
                </div>
                <h2><?= htmlspecialchars($c['title']) ?></h2>
                <p>
                    <?= htmlspecialchars(
                        mb_strimwidth($c['description'] ?? 'No description available.', 0, 180, '...')
                    ) ?>
                </p>
                <small>
                    Submitted by <?= htmlspecialchars($c['student_name'] ?? 'Unknown') ?>
                </small>
            </div>
        </article>
        
        <?php endforeach; ?>

            <?php else: ?>
                <article class="post">
                    <div class="content">
                        <h2>No submissions found</h2>
                        <p>
                            There are no selected contributions to display
                            <?= $selectedFacultyName ? "for <strong>" . htmlspecialchars($selectedFacultyName) . "</strong>" : "" ?>.
                            <?= $search !== '' ? "Try a different search term." : "" ?>
                        </p>
                    </div>
                </article>
            <?php endif; ?>

        <?php endif; ?>

    </main>

    <!-- ── RIGHT BAR ── -->
    <aside class="rightbar">
        <div class="card">
            <h3>About UniMag</h3>
            <p>UniMag is a secure, role-based system for managing student contributions to the annual university magazine.</p>
            <?php if ($isGuest || !$isLoggedIn): ?>
                <p style="font-size:13px; color:#666; margin-top:8px;">
                    Guests can browse one faculty's selected contributions.
                    Log in for full access.
                </p>
                <a href="login.php" class="card-btn">
                    Login
                </a>
            <?php else: ?>
                <p style="font-size:13px; color:#666; margin-top:8px;">
                    You are logged in. Use your dashboard to manage contributions.
                </p>
                <?php if ($dashboardLink): ?>
                    <a href="<?= $dashboardLink ?>" class="card-btn">
                        Go to Dashboard
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

      
        </div>
        
    </aside>

</div>

<script src="app.js"></script>
</body>
</html>
