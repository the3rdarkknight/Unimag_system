<?php
/*
 * includes/email.php
 * 
 * Central email helper for UniMag.
 * Uses PHPMailer with Gmail SMTP.
 *
 * SETUP:
 *   1. Run: composer require phpmailer/phpmailer
 *   2. Set your Gmail + App Password below
 *   3. Include this file wherever you need to send email
 *
 * FUNCTIONS:
 *   sendEmail($to, $toName, $subject, $htmlBody)       — base sender
 *   notifyCoordinator($contribution_id, $conn)          — new submission alert
 *   notifyCoordinatorEdit($contribution_id, $conn)      — edited submission alert
 *   notifyStudent($contribution_id, $comment, $conn)    — comment alert
 *   notifyAllUsers($subject, $message, $conn)           — bulk announcement
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer via Composer autoloader
require_once __DIR__ . "/../vendor/autoload.php";

// Load SMTP credentials from environment variables
$mailFrom     = $_ENV['MAIL_FROM']      ?? '';
$mailFromName = $_ENV['MAIL_FROM_NAME'] ?? '';
$mailPassword = $_ENV['MAIL_PASSWORD']  ?? '';


/**
 * Base email sender.
 *
 * @param string $to       Recipient email
 * @param string $toName   Recipient display name
 * @param string $subject  Email subject line
 * @param string $htmlBody HTML email body
 * @return bool            true on success, false on failure
 */
function sendEmail(string $to, string $toName, string $subject, string $htmlBody): bool {

    global $mailFrom, $mailFromName, $mailPassword;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailFrom;
        $mail->Password   = $mailPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($mailFrom, $mailFromName);
        $mail->addAddress($to, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody));

        return $mail->send();

    } catch (Exception $e) {
        error_log("Email could not be sent: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Shared internal helper — fetches contribution + student + faculty details
 * and the list of coordinators for that faculty.
 * Used by notifyCoordinator() and notifyCoordinatorEdit() to avoid duplicating queries.
 *
 * @param int    $contribution_id
 * @param object $conn
 * @return array|null  ['contribution' => [...], 'coordinators' => mysqli_result] or null on failure
 */
function _getContributionAndCoordinators(int $contribution_id, $conn): ?array {

    $stmt = $conn->prepare("
        SELECT
            c.title,
            c.submitted_at,
            u.name  AS student_name,
            f.faculty_name
        FROM contributions c
        JOIN users     u ON u.user_id    = c.student_id
        JOIN faculties f ON f.faculty_id = c.faculty_id
        WHERE c.contribution_id = ?
    ");
    $stmt->bind_param("i", $contribution_id);
    $stmt->execute();
    $contribution = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$contribution) return null;

    $stmt = $conn->prepare("
        SELECT name, email
        FROM users
        WHERE role_id = 2
          AND faculty_id = (
              SELECT faculty_id FROM contributions WHERE contribution_id = ?
          )
    ");
    $stmt->bind_param("i", $contribution_id);
    $stmt->execute();
    $coordinators = $stmt->get_result();
    $stmt->close();

    if (!$coordinators || $coordinators->num_rows === 0) return null;

    return [
        'contribution' => $contribution,
        'coordinators' => $coordinators,
    ];
}


/**
 * Notify the coordinator when a student submits a NEW contribution.
 *
 * Called from: student/submit.php
 *
 * @param int    $contribution_id  Newly inserted contribution ID
 * @param object $conn             MySQLi connection
 */
function notifyCoordinator(int $contribution_id, $conn): void {

    $data = _getContributionAndCoordinators($contribution_id, $conn);
    if (!$data) return;

    $contribution = $data['contribution'];
    $coordinators = $data['coordinators'];

    $subject = "New Submission: " . $contribution['title'];

    $body = emailTemplate(
        "New Contribution Submitted",
        "
        <p>A new contribution has been submitted in your faculty.</p>

        <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
            <tr>
                <td style='padding:8px;color:#999;width:140px;'>Student</td>
                <td style='padding:8px;font-weight:600;'>" . htmlspecialchars($contribution['student_name']) . "</td>
            </tr>
            <tr style='background:#1a1c22;'>
                <td style='padding:8px;color:#999;'>Title</td>
                <td style='padding:8px;font-weight:600;'>" . htmlspecialchars($contribution['title']) . "</td>
            </tr>
            <tr>
                <td style='padding:8px;color:#999;'>Faculty</td>
                <td style='padding:8px;'>" . htmlspecialchars($contribution['faculty_name']) . "</td>
            </tr>
            <tr style='background:#1a1c22;'>
                <td style='padding:8px;color:#999;'>Submitted</td>
                <td style='padding:8px;'>" . date("d M Y H:i", strtotime($contribution['submitted_at'])) . "</td>
            </tr>
        </table>

        <p>Please log in to review this submission within <strong>14 days</strong>.</p>

        <a href='http://localhost/EWSD PROJECT (1.0)\Project - Copy/login.php?id=" . $contribution_id . "'
           style='display:inline-block;padding:10px 20px;background:#ff4500;color:#fff;
                  border-radius:6px;text-decoration:none;margin-top:8px;'>
            Open Review
        </a>
        "
    );

    while ($coord = $coordinators->fetch_assoc()) {
        sendEmail($coord['email'], $coord['name'], $subject, $body);
    }
}


/**
 * Notify the coordinator when a student EDITS/UPDATES an existing contribution.
 *
 * Called from: student/edit_contribution.php
 *
 * @param int    $contribution_id  The contribution that was updated
 * @param object $conn             MySQLi connection
 */
function notifyCoordinatorEdit(int $contribution_id, $conn): void {

    $data = _getContributionAndCoordinators($contribution_id, $conn);
    if (!$data) return;

    $contribution = $data['contribution'];
    $coordinators = $data['coordinators'];

    $subject = "Contribution Updated: " . $contribution['title'];

    $body = emailTemplate(
        "A Student Has Revised Their Submission",
        "
        <p>A student has uploaded a new version of their contribution in your faculty.</p>

        <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
            <tr>
                <td style='padding:8px;color:#999;width:140px;'>Student</td>
                <td style='padding:8px;font-weight:600;'>" . htmlspecialchars($contribution['student_name']) . "</td>
            </tr>
            <tr style='background:#1a1c22;'>
                <td style='padding:8px;color:#999;'>Title</td>
                <td style='padding:8px;font-weight:600;'>" . htmlspecialchars($contribution['title']) . "</td>
            </tr>
            <tr>
                <td style='padding:8px;color:#999;'>Faculty</td>
                <td style='padding:8px;'>" . htmlspecialchars($contribution['faculty_name']) . "</td>
            </tr>
            <tr style='background:#1a1c22;'>
                <td style='padding:8px;color:#999;'>Originally Submitted</td>
                <td style='padding:8px;'>" . date("d M Y H:i", strtotime($contribution['submitted_at'])) . "</td>
            </tr>
            <tr>
                <td style='padding:8px;color:#999;'>Updated At</td>
                <td style='padding:8px;'>" . date("d M Y H:i") . "</td>
            </tr>
        </table>

        <p>Please log in to review the updated document.</p>

        <a a href='http://localhost/EWSD PROJECT (1.0)/Project - Copy/login.php?id=" . $contribution_id . "'
           style='display:inline-block;padding:10px 20px;background:#ff4500;color:#fff;
                  border-radius:6px;text-decoration:none;margin-top:8px;'>
            Open Review
        </a>
        "
    );

    while ($coord = $coordinators->fetch_assoc()) {
        sendEmail($coord['email'], $coord['name'], $subject, $body);
    }
}


/**
 * Notify the student when a coordinator comments on their contribution.
 *
 * Called from: coordinator/review.php
 *
 * @param int    $contribution_id  The contribution that was commented on
 * @param string $commentText      The comment that was just saved
 * @param object $conn            
 */
function notifyStudent(int $contribution_id, string $commentText, $conn): void {

    $stmt = $conn->prepare("
        SELECT
            c.title,
            u.name  AS student_name,
            u.email AS student_email,
            coord.name AS coordinator_name
        FROM contributions c
        JOIN users u ON u.user_id = c.student_id
        JOIN (
            SELECT name FROM users
            WHERE role_id = 2
              AND faculty_id = (SELECT faculty_id FROM contributions WHERE contribution_id = ?)
            LIMIT 1
        ) coord
        WHERE c.contribution_id = ?
    ");
    $stmt->bind_param("ii", $contribution_id, $contribution_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$data || empty($data['student_email'])) return;

    $subject = "New Comment on Your Contribution: " . $data['title'];

    $body = emailTemplate(
        "You Have a New Comment",
        "
        <p>Your coordinator has left a comment on your submission.</p>

        <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
            <tr>
                <td style='padding:8px;color:#999;width:140px;'>Contribution</td>
                <td style='padding:8px;font-weight:600;'>" . htmlspecialchars($data['title']) . "</td>
            </tr>
            <tr style='background:#1a1c22;'>
                <td style='padding:8px;color:#999;'>From</td>
                <td style='padding:8px;'>" . htmlspecialchars($data['coordinator_name']) . "</td>
            </tr>
        </table>

        <div style='background:#2a2d36;border-left:4px solid #ff4500;
                    padding:14px 16px;border-radius:4px;margin:16px 0;'>
            <p style='margin:0;line-height:1.6;'>" . nl2br(htmlspecialchars($commentText)) . "</p>
        </div>

       
        "
    );

    sendEmail($data['student_email'], $data['student_name'], $subject, $body);
}


/**
 * Send a bulk announcement to all active users.
 *
 * Called from: manager/bulk-notify.php
 *
 * @param string $subject   Email subject
 * @param string $message   Announcement message (plain text or HTML)
 * @param object $conn      MySQLi connection
 * @return int              Number of emails sent
 */
function notifyAllUsers(string $subject, string $message, $conn): int {

    $users = $conn->query("SELECT name, email FROM users ORDER BY name ASC");

    if (!$users || $users->num_rows === 0) return 0;

    $body = emailTemplate(
        "University Magazine Announcement",
        "
        <div style='background:#2a2d36;border-left:4px solid #ff4500;
                    padding:14px 16px;border-radius:4px;margin:16px 0;'>
            <p style='margin:0;line-height:1.8;'>" . nl2br(htmlspecialchars($message)) . "</p>
        </div>
        <p style='color:#aaa;font-size:13px;'>
            This is an official announcement from the University Magazine team.
        </p>
        "
    );

    $sent = 0;
    while ($user = $users->fetch_assoc()) {
        if (sendEmail($user['email'], $user['name'], $subject, $body)) {
            $sent++;
        }
    }

    return $sent;
}


/**
 * Shared HTML email template.
 * 
 *
 * @param string $heading  Bold heading at top of email
 * @param string $content  HTML content block
 * @return string          Full HTML email
 */
function emailTemplate(string $heading, string $content): string {
    return "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='margin:0;padding:0;background:#0f1115;font-family:system-ui,sans-serif;color:#e6e6e6;'>

        <div style='max-width:580px;margin:30px auto;background:#1a1c22;border-radius:12px;overflow:hidden;'>

            <!-- Header -->
            <div style='background:#ff4500;padding:20px 28px;'>
                <span style='font-size:20px;font-weight:700;color:#fff;'>UniMag</span>
            </div>

            <!-- Body -->
            <div style='padding:28px;'>
                <h2 style='margin:0 0 16px;font-size:20px;color:#fff;'>$heading</h2>
                $content
            </div>

            <!-- Footer -->
            <div style='padding:16px 28px;border-top:1px solid #2a2d36;font-size:12px;color:#666;'>
                This is an automated message from UniMag. Please do not reply to this email.
            </div>

        </div>

    </body>
    </html>
    ";
}