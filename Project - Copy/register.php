<?php
session_start();
require "includes/db.php";

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 1. CSRF Protection ──
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token.");
    }

    // ── 2. Sanitize & Validate Input ──
    $full_name  = trim($_POST['full_name']);
    $email      = trim(strtolower($_POST['email']));
    $faculty_id = $_POST['faculty'];
    $password   = $_POST['password'];
    $confirm    = $_POST['confirm_password'];

    // Name
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s'-]{2,100}$/", $full_name)) {
        $errors[] = "Name contains invalid characters.";
    }

    // Email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif (strlen($email) > 255) {
        $errors[] = "Email is too long.";
    }

    // Password strength
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }

    // Confirm password
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    // Faculty
    if (empty($faculty_id) || !filter_var($faculty_id, FILTER_VALIDATE_INT)) {
        $errors[] = "Please select a valid faculty.";
    } else {
        $fCheck = $conn->prepare("SELECT faculty_id FROM faculties WHERE faculty_id = ?");
        $fCheck->bind_param("i", $faculty_id);
        $fCheck->execute();
        $fCheck->store_result();
        if ($fCheck->num_rows === 0) {
            $errors[] = "Invalid faculty selected.";
        }
        $fCheck->close();
    }

    //  Check Email Already Exists
    if (empty($errors)) {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $errors[] = "Email already registered.";
        }
        $check->close();
    }

    //  Insert if No Errors 
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $role_id = 1;

        $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role_id, faculty_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssii", $full_name, $email, $hashed_password, $role_id, $faculty_id);

        if ($stmt->execute()) {
            session_regenerate_id(true);
            header("Location: login.php?registered=1");
            exit();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }

        $stmt->close();
    }
}

// Generate CSRF Token 
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UniMag | Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
   
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
        <h1>Create Account</h1>
        <p class="auth-sub">Register to submit contributions to the university magazine</p>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <!-- Error Box -->
            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <?php foreach ($errors as $e): ?>
                        <p><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($e) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Full Name -->
            <label>Full Name</label>
            <input
                type="text"
                name="full_name"
                placeholder="John Doe"
                value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                required>

            <!-- Email -->
            <label>University Email</label>
            <input
                type="email"
                name="email"
                placeholder="student@university.ac.uk"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required>

            <!-- Faculty -->
            <label>Faculty</label>
            <select name="faculty" required>
                <option value="">Select Faculty</option>
                <?php
                $result = $conn->query("SELECT faculty_id, faculty_name FROM faculties");
                while ($row = $result->fetch_assoc()):
                    $selected = (isset($_POST['faculty']) && $_POST['faculty'] == $row['faculty_id']) ? 'selected' : '';
                ?>
                    <option value="<?= $row['faculty_id'] ?>" <?= $selected ?>>
                        <?= htmlspecialchars($row['faculty_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Password -->
            <label>Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="********" required>
                <i class="fa-solid fa-eye toggle-pw" onclick="togglePassword('password', this)"></i>
            </div>
            <div class="strength-bar"><span id="strengthBar"></span></div>
            <p class="strength-label" id="strengthLabel"></p>

            <!-- Confirm Password -->
            <label>Confirm Password</label>
            <div class="password-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="********" required>
                <i class="fa-solid fa-eye toggle-pw" onclick="togglePassword('confirm_password', this)"></i>
            </div>

            <!-- Terms -->
            <label class="checkbox">
                <input type="checkbox" required>
                I agree to the <a href="terms.php">Terms & Conditions</a>
            </label>

            <button type="submit" class="btn primary full">Create Account</button>
        </form>

        <p class="auth-footer">
            Already have an account? <a href="login.php">Login here</a>
        </p>
    </div>
</main>

<script>
    // Toggle Password Visibility 
    function togglePassword(fieldId, icon) {
        const input = document.getElementById(fieldId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    //Password Strength Meter
    document.getElementById('password').addEventListener('input', function () {
        const val = this.value;
        const bar = document.getElementById('strengthBar');
        const label = document.getElementById('strengthLabel');

        let strength = 0;
        if (val.length >= 8)            strength++;
        if (/[A-Z]/.test(val))          strength++;
        if (/[0-9]/.test(val))          strength++;
        if (/[^a-zA-Z0-9]/.test(val))   strength++;

        const levels = [
            { width: '0%',   color: '#2a2d36', text: '' },
            { width: '25%',  color: '#e74c3c', text: 'Weak' },
            { width: '50%',  color: '#f39c12', text: 'Fair' },
            { width: '75%',  color: '#3498db', text: 'Good' },
            { width: '100%', color: '#27ae60', text: 'Strong' },
        ];

        bar.style.width      = levels[strength].width;
        bar.style.background = levels[strength].color;
        label.textContent    = levels[strength].text;
        label.style.color    = levels[strength].color;
    });
</script>

</body>
</html>