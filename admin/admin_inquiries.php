<?php
session_start();
require_once "../database/config.php";
require_once "../database/mail_config.php";
require  '../database/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["id"] ?? 0;
$admin_name = $_SESSION["full_name"] ?? 'School Administration';
$errors = [];
$success_message = "";

// Check if we are viewing a single inquiry or the list
$inquiry_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// --- Handle Reply Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_reply'])) {
    $reply_inquiry_id = (int)$_POST['inquiry_id'];
    $reply_message = trim($_POST['reply_message']);
    $recipient_email = trim($_POST['recipient_email']);
    $recipient_name = trim($_POST['recipient_name']);
    $original_subject = trim($_POST['original_subject']);

    if (empty($reply_message)) {
        $errors[] = "Reply message cannot be empty.";
    } else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(MAIL_FROM_EMAIL, 'Mednova School Support');
            $mail->addAddress($recipient_email, $recipient_name); 

            $mail->isHTML(true);
            $mail->Subject = 'Re: ' . $original_subject;
            $mail->Body    = "<p>Dear " . htmlspecialchars($recipient_name) . ",</p>"
                           . "<p>Thank you for your inquiry. Please find our response below:</p>"
                           . "<div style='padding:15px; border-left: 4px solid #ccc; background-color: #f9f9f9;'>"
                           . nl2br(htmlspecialchars($reply_message))
                           . "</div>"
                           . "<p>Sincerely,<br>" . htmlspecialchars($admin_name) . "<br>Mednova School</p>";

            $mail->send();
            
            $sql_update = "UPDATE contact_inquiries SET status = 'Replied', reply_message = ?, replied_by_admin_id = ?, replied_at = NOW() WHERE id = ?";
            if ($stmt = mysqli_prepare($link, $sql_update)) {
                mysqli_stmt_bind_param($stmt, "sii", $reply_message, $admin_id, $reply_inquiry_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            $_SESSION['success_message'] = "Your reply has been sent successfully.";
            header("Location: admin_inquiries.php"); // Redirect to the main list after replying
            exit();

        } catch (Exception $e) {
            $errors[] = "Could not send reply email. Mailer Error: {$mail->ErrorInfo}";
        }
    }
}

// Check for success message from session after a redirect
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}


// --- Fetch Data For Display ---
if ($inquiry_id) {
    // Fetch a single inquiry for the reply view
    $inquiry = null;
    $sql_fetch = "SELECT i.*, a.full_name as admin_replier_name 
                  FROM contact_inquiries i 
                  LEFT JOIN admins a ON i.replied_by_admin_id = a.id 
                  WHERE i.id = ?";
    if ($stmt = mysqli_prepare($link, $sql_fetch)) {
        mysqli_stmt_bind_param($stmt, "i", $inquiry_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $inquiry = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
} else {
    // Fetch all inquiries for the list view
    $inquiries = [];
    $sql = "SELECT id, name, email, subject, status, created_at FROM contact_inquiries ORDER BY created_at DESC";
    if ($result = mysqli_query($link, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $inquiries[] = $row;
        }
    }
}


require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Contact Inquiries</title>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f9; }
        .container { max-width: 1100px; margin: 2rem auto; background: #fff; border-radius: 12px; box-shadow: 0 6px 15px rgba(0,0,0,0.07); padding: 2rem; }
        h2, h3 { font-family: 'Playfair Display', serif; color: #2c3e50; }
        h2 { text-align: center; margin-bottom: 2rem; }
        h3 { border-bottom: 2px solid #3498db; padding-bottom: 0.5rem; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem 1rem; border-bottom: 1px solid #e1e8ed; text-align: left; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .status-pending { background-color: #fff3cd; color: #856404; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.8rem; font-weight: 500; }
        .status-replied { background-color: #d4edda; color: #155724; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.8rem; font-weight: 500; }
        .btn { display: inline-block; padding: 0.5rem 1rem; color: #fff; background-color: #3498db; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; text-align: center; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 6px 15px rgba(0,0,0,0.07); padding: 2rem; margin-bottom: 2rem; }
        .inquiry-details p { margin: 0 0 1rem 0; line-height: 1.7; }
        .inquiry-details strong { color: #34495e; }
        .message-box { background: #f8f9fa; border-left: 4px solid #3498db; padding: 1rem; border-radius: 6px; }
        textarea { width: 100%; padding: 0.75rem; border: 1px solid #e1e8ed; border-radius: 8px; min-height: 150px; }
        label { font-weight: 600; display: block; margin-bottom: 0.5rem; }
        .btn-reply { background-color: #2ecc71; }
    </style>
</head>
<body>
<div class="container mt-28">
    <h2>Contact Form Inquiries</h2>

    <?php if(!empty($errors)): ?><div class="alert alert-danger"><?php foreach($errors as $error) echo htmlspecialchars($error)."<br>"; ?></div><?php endif; ?>
    <?php if($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>

    <?php if ($inquiry_id && $inquiry): // --- SINGLE INQUIRY VIEW --- ?>

        <a href="admin_inquiries.php" class="btn" style="margin-bottom: 2rem; background-color:#95a5a6;">&laquo; Back to All Inquiries</a>

        <div class="card inquiry-details" id="reply-section">
            <h3>Inquiry Details</h3>
            <p><strong>From:</strong> <?php echo htmlspecialchars($inquiry['name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($inquiry['email']); ?></p>
            <p><strong>Subject:</strong> <?php echo htmlspecialchars($inquiry['subject']); ?></p>
            <p><strong>Received On:</strong> <?php echo date('d M Y, h:i A', strtotime($inquiry['created_at'])); ?></p>
            <p><strong>Message:</strong></p>
            <div class="message-box"><?php echo nl2br(htmlspecialchars($inquiry['message'])); ?></div>
        </div>

        <?php if ($inquiry['status'] == 'Replied'): ?>
            <div class="card">
                <h3>Previously Replied</h3>
                <p><strong>Replied By:</strong> <?php echo htmlspecialchars($inquiry['admin_replier_name'] ?? 'N/A'); ?></p>
                <p><strong>Replied On:</strong> <?php echo date('d M Y, h:i A', strtotime($inquiry['replied_at'])); ?></p>
                <p><strong>Reply Sent:</strong></p>
                <div class="message-box" style="border-left-color: #2ecc71;"><?php echo nl2br(htmlspecialchars($inquiry['reply_message'])); ?></div>
            </div>
        <?php else: ?>
            <div class="card">
                <h3>Send Reply</h3>
                <form action="admin_inquiries.php?id=<?php echo $inquiry_id; ?>" method="post">
                    <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['id']; ?>">
                    <input type="hidden" name="recipient_email" value="<?php echo htmlspecialchars($inquiry['email']); ?>">
                    <input type="hidden" name="recipient_name" value="<?php echo htmlspecialchars($inquiry['name']); ?>">
                    <input type="hidden" name="original_subject" value="<?php echo htmlspecialchars($inquiry['subject']); ?>">
                    <div class="form-group">
                        <label for="reply_message">Your Reply:</label>
                        <textarea id="reply_message" name="reply_message" required></textarea>
                    </div>
                    <button type="submit" name="send_reply" class="btn btn-reply">Send Reply</button>
                </form>
            </div>
        <?php endif; ?>

    <?php else: // --- INQUIRY LIST VIEW (DEFAULT) --- ?>
    
        <table>
            <thead>
                <tr>
                    <th>Received</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inquiries)): ?>
                    <tr><td colspan="6" style="text-align:center; padding: 2rem;">No inquiries found.</td></tr>
                <?php else: ?>
                    <?php foreach ($inquiries as $item): ?>
                        <tr>
                            <td><?php echo date('d M Y, h:i A', strtotime($item['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['email']); ?></td>
                            <td><?php echo htmlspecialchars($item['subject']); ?></td>
                            <td>
                                <span class="status-<?php echo strtolower($item['status']); ?>">
                                    <?php echo htmlspecialchars($item['status']); ?>
                                </span>
                            </td>
                            <td><a href="admin_inquiries.php?id=<?php echo $item['id']; ?>" class="btn">View & Reply</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    <?php endif; ?>
</div>

<script>
    // If the URL has an ID, scroll to the reply section for better UX
    if (window.location.search.includes('id=')) {
        const replySection = document.getElementById('reply-section');
        if (replySection) {
            replySection.scrollIntoView({ behavior: 'smooth' });
        }
    }
</script>

</body>
</html>