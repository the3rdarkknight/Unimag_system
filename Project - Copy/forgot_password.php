
<?php

// Load Composer autoloader — vendor/ is one level up
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables — .env is one level up
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Load email functions — includes/ is in the same folder
require_once __DIR__ . '/includes/email.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>UniMag | Forgot Password</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="style.css">
<style>
/* CODE INPUTS */
.code-inputs {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin: 15px 0;
}

.code-inputs input {
    width: 45px;
    height: 50px;
    text-align: center;
    font-size: 20px;
    border-radius: 6px;
    border: 1.5px solid transparent;
    background: #2a2d36;
    color: #fff;
    transition: border-color 0.2s;
    outline: none;
}

.code-inputs input:focus {
    border-color: #ff4500;
}

/* PASSWORD STEP */
#passwordStep {
    display: none;
    flex-direction: column;
}

#passwordStep label {
    display: block;
    margin-top: 10px;
    margin-bottom: 5px;
    font-size: 14px;
    color: #ccc;
}

#passwordStep input {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: none;
    background: #2a2d36;
    color: #fff;
    margin-bottom: 10px;
    box-sizing: border-box;
}

#passwordStep button {
    margin-top: 10px;
}

/* MESSAGE STYLING */
.message {
    padding: 10px 12px;
    border-radius: 6px;
    font-size: 13px;
    margin: 10px 0;
    display: none;
    font-weight: 500;
}

.message.success {
    background: rgba(76, 175, 80, 0.1);
    color: #4caf50;
    border-left: 3px solid #4caf50;
}

.message.error {
    background: rgba(255, 77, 77, 0.1);
    color: #ff4d4d;
    border-left: 3px solid #ff4d4d;
}

/* SPINNER ON BUTTON */
.btn.loading {
    opacity: 0.7;
    pointer-events: none;
}

/* RESEND LINK */
.resend-row {
    text-align: center;
    margin-top: 10px;
    font-size: 13px;
    color: #aaa;
}

.resend-row a {
    color: #ff4500;
    cursor: pointer;
    text-decoration: none;
    font-weight: 600;
}

.resend-row a:hover {
    text-decoration: underline;
}
</style>
</head>

<body class="auth-body">

<header class="topbar auth-topbar">
    <div class="left">
        <a href="index.php" style="display:flex;align-items:center;gap:6px;text-decoration:none;color:inherit;">
            <i class="fa-brands fa-reddit-alien logo"></i>
            <span class="title">UniMag</span>
        </a>
    </div>
</header>

<main class="auth-center">
<div class="auth-card">

    <h1 id="title">Forgot Password</h1>
    <p class="auth-sub" id="subtitle">Enter your email and we'll send you a reset code.</p>

    <!--  Email -->
    <form id="emailStep">
        <label>Email Address</label>
        <input type="email" id="email" placeholder="user@unimag.edu" required>
        <p id="message" class="message"></p>
        <button type="submit" class="btn primary full" id="sendBtn">
            <i class="fa-solid fa-paper-plane"></i> Send Reset Code
        </button>
    </form>

    <!--OTP -->
    <div id="codeStep" style="display:none;">
        <div class="code-inputs">
            <input maxlength="1" inputmode="numeric">
            <input maxlength="1" inputmode="numeric">
            <input maxlength="1" inputmode="numeric">
            <input maxlength="1" inputmode="numeric">
            <input maxlength="1" inputmode="numeric">
            <input maxlength="1" inputmode="numeric">
        </div>
        <p id="codeMessage" class="message"></p>
        <div class="resend-row">
            Didn't get it? <a id="resendLink">Resend code</a>
        </div>
    </div>

    <!-- STEP 3: New Password -->
    <form id="passwordStep">
        <label>New Password</label>
        <input type="password" id="newPassword" placeholder="Min. 6 characters" required>

        <label>Confirm Password</label>
        <input type="password" id="confirmPassword" placeholder="Repeat password" required>

        <p id="passMessage" class="message"></p>

        <button type="submit" class="btn primary full" id="resetBtn">
            <i class="fa-solid fa-lock"></i> Reset Password
        </button>
    </form>

    <p class="auth-footer">
        <a href="login.php"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
    </p>

</div>
</main>

<script>
const emailForm    = document.getElementById('emailStep');
const codeStep     = document.getElementById('codeStep');
const passwordStep = document.getElementById('passwordStep');

const message     = document.getElementById('message');
const codeMessage = document.getElementById('codeMessage');
const passMessage = document.getElementById('passMessage');

const inputs    = document.querySelectorAll('.code-inputs input');
const title     = document.getElementById('title');
const subtitle  = document.getElementById('subtitle');
const sendBtn   = document.getElementById('sendBtn');
const resetBtn  = document.getElementById('resetBtn');
const resendLink = document.getElementById('resendLink');

// ── STEP 1: Send OTP
emailForm.addEventListener('submit', function(e) {
    e.preventDefault();
    sendOtp();
});

function sendOtp() {
    const email = document.getElementById('email').value.trim();
    if (!email) { show(message, 'Email is required.', false); return; }

    sendBtn.classList.add('loading');
    sendBtn.textContent = 'Sending...';

    fetch('includes/reset_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=send_otp&email=' + encodeURIComponent(email)
    })
    .then(r => r.json())
    .then(data => {
        sendBtn.classList.remove('loading');
        sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Reset Code';

        if (data.success) {
            show(message, data.message, true);
            setTimeout(() => {
                emailForm.style.display = 'none';
                codeStep.style.display  = 'block';
                inputs[0].focus();
                title.innerText    = 'Verify Code';
                subtitle.innerText = 'Enter the 6-digit code sent to your email.';
            }, 800);
        } else {
            show(message, data.message, false);
        }
    })
    .catch(() => {
        sendBtn.classList.remove('loading');
        sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Reset Code';
        show(message, 'Something went wrong. Please try again.', false);
    });
}

//  OTP Inputs 
inputs.forEach((input, index) => {
    input.addEventListener('input', () => {
        // Allow only digits
        input.value = input.value.replace(/\D/g, '');
        if (input.value.length === 1 && index < inputs.length - 1) {
            inputs[index + 1].focus();
        }
        if (Array.from(inputs).every(i => i.value.length === 1)) {
            verifyOtp();
        }
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && index > 0) {
            inputs[index - 1].focus();
        }
    });

    input.addEventListener('paste', (e) => {
        e.preventDefault();
        const paste = e.clipboardData.getData('text').replace(/\D/g, '').split('');
        inputs.forEach((inp, i) => { inp.value = paste[i] || ''; });
        if (paste.length >= 6) verifyOtp();
    });
});

function verifyOtp() {
    const code = Array.from(inputs).map(i => i.value).join('');
    if (code.length < 6) return;

    show(codeMessage, 'Verifying...', true);

    fetch('includes/reset_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=verify_otp&code=' + encodeURIComponent(code)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            show(codeMessage, data.message, true);
            setTimeout(() => {
                codeStep.style.display          = 'none';
                passwordStep.style.display      = 'flex';
                title.innerText    = 'Reset Password';
                subtitle.innerText = 'Enter your new password below.';
                document.getElementById('newPassword').focus();
            }, 800);
        } else {
            show(codeMessage, data.message, false);
            // Clear inputs on wrong code
            inputs.forEach(i => i.value = '');
            inputs[0].focus();
        }
    })
    .catch(() => {
        show(codeMessage, 'Something went wrong. Please try again.', false);
    });
}

// Resend code
resendLink.addEventListener('click', () => {
    codeStep.style.display  = 'none';
    emailForm.style.display = 'block';
    inputs.forEach(i => i.value = '');
    title.innerText    = 'Forgot Password';
    subtitle.innerText = "Enter your email and we'll send you a reset code.";
    show(message, '', true);
});

//  Reset Password
passwordStep.addEventListener('submit', function(e) {
    e.preventDefault();

    const pass    = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;

    if (pass.length < 6) {
        show(passMessage, 'Password must be at least 6 characters.', false);
        return;
    }

    if (pass !== confirm) {
        show(passMessage, 'Passwords do not match.', false);
        return;
    }

    resetBtn.classList.add('loading');
    resetBtn.textContent = 'Resetting...';

    fetch('includes/reset_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=reset_password&password=' + encodeURIComponent(pass)
             + '&confirm=' + encodeURIComponent(confirm)
    })
    .then(r => r.json())
    .then(data => {
        resetBtn.classList.remove('loading');
        resetBtn.innerHTML = '<i class="fa-solid fa-lock"></i> Reset Password';

        if (data.success) {
            show(passMessage, data.message, true);
            title.innerText    = 'All Done!';
            subtitle.innerText = 'Your password has been updated. Redirecting to login...';
            setTimeout(() => { window.location.href = 'login.php'; }, 2000);
        } else {
            show(passMessage, data.message, false);
        }
    })
    .catch(() => {
        resetBtn.classList.remove('loading');
        resetBtn.innerHTML = '<i class="fa-solid fa-lock"></i> Reset Password';
        show(passMessage, 'Something went wrong. Please try again.', false);
    });
});

// 
function show(el, msg, success) {
    if (!msg) { el.style.display = 'none'; return; }
    el.style.display = 'block';
    el.innerText     = msg;
    el.classList.remove('success', 'error');
    el.classList.add(success ? 'success' : 'error');
}
</script>

</body>
</html>