<?php
session_start();
require "includes/db.php";

$error = "";




if (isset($_GET['guest'])) {
    $_SESSION['role']     = 5;
    $_SESSION['is_guest'] = true;
    $_SESSION['name']     = 'Guest';
    header("Location: index.php");
    exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email    = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password_hash"])) {

            $_SESSION["user_id"]    = $user["user_id"];
            $_SESSION["role"]       = $user["role_id"];
            $_SESSION["name"]       = $user["name"];
            $_SESSION['faculty_id'] = $user['faculty_id'];

            unset($_SESSION['is_guest']);
            unset($_SESSION['guest_faculty_id']);

             $userId = $user["user_id"];

    // Get last login BEFORE updating
    $stmt = $conn->prepare("SELECT last_login FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $lastLoginResult = $stmt->get_result();
    $lastLoginRow = $lastLoginResult->fetch_assoc();

    // Store in session
    $_SESSION['last_login'] = $lastLoginRow['last_login'];

    // Update login time to NOW
    $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $update->bind_param("i", $userId);
    $update->execute();

            if ($user["role_id"] == 1)      header("Location: student/dashboard.php");
            elseif ($user["role_id"] == 2)  header("Location: coordinator/dashboard.php");
            elseif ($user["role_id"] == 3)  header("Location: manager/dashboard.php");
            elseif ($user["role_id"] == 4)  header("Location: admin/dashboard.php");
            elseif ($user["role_id"] == 5)  header("Location: index.php");
            exit();

        } else {
            $error = "Incorrect password";
        }
    } else {
        $error = "User not found";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>UniMag | Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="style.css">

<!-- pop up bunner --> 
<?php if (isset($_GET['registered'])): ?>
<div id="successBanner">
    <i class="fa-solid fa-circle-check"></i>
    Account created successfully. Please login.
</div>
<script>
    setTimeout(() => {
        const banner = document.getElementById('successBanner');
        banner.style.opacity = '0';
        setTimeout(() => banner.remove(), 800);
    }, 3000);
</script>
<?php endif; ?>
</head>


<body class="auth-body">

<header class="topbar auth-topbar">
    <div class="left">
        <i class="fa-brands fa-reddit-alien logo"></i>
        <span class="title">UniMag</span>
    </div>
</header>

<main class="auth-center">
    <div class="auth-card">
        <h1>Login</h1>
        <p class="auth-sub">Sign in using your university account</p>

        <form method="POST" action="">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="user@unimag.edu" required>

            <label>Password</label>
            <input type="password" name="password" placeholder="........" required>

            <p class="error-message"><?php if ($error) echo htmlspecialchars($error); ?></p>

            <button type="submit" class="btn primary full">Login</button>
        </form>

        <div class="divider">or</div>

        <a href="login.php?guest=1" class="btn guest">
            <i class="fa-solid fa-eye"></i> Continue as Guest
        </a>
          <a href="forgot_password.php" class="btn guest">
            <i class="fa-solid fa-eye"></i> forgotten pass
        </a>

        <p class="auth-footer">
            Don't have an account? <a href="register.php">Register here</a>
        </p>
    </div>
</main>

</body>
</html>