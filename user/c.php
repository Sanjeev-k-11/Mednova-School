<?php
// =================================================================================
// BACKEND LOGIC: DATABASE, EMAIL, AND DATA FETCHING (ALL IN ONE FILE)
// =================================================================================

// --- 1. INCLUDE DEPENDENCIES ---
// These MUST be at the very top, before any HTML output or other code.
require_once __DIR__ . '/../database/config.php'; // For MySQLi connection ($link)
require_once __DIR__ . '/../database/mail_config.php'; // For SMTP constants
require_once __DIR__ . '/../database/vendor/autoload.php'; // For PHPMailer classes

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- 2. FETCH DYNAMIC CONTACT SETTINGS FROM DATABASE ---
$contact_settings = [];
$sql_fetch_contact = "SELECT * FROM contact_settings WHERE id = 1";
if ($result = mysqli_query($link, $sql_fetch_contact)) {
    if (mysqli_num_rows($result) > 0) {
        $contact_settings = mysqli_fetch_assoc($result);
    }
    mysqli_free_result($result); // Free result set
}

// --- 3. HANDLE AJAX EMAIL FORM SUBMISSION (and save to DB) ---
// This block will execute if an AJAX POST request with action 'send_inquiry' is received.
if (isset($_POST['action']) && $_POST['action'] === 'send_inquiry' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // IMPORTANT: No HTML output should happen before this header.
    // If you uncommented header.php at the top, it would break this.
    header('Content-Type: application/json');
    
    function sendResponse($status, $message) {
        // Clean (erase) the output buffer before sending JSON
        if (ob_get_length()) ob_clean();
        echo json_encode(['status' => $status, 'message' => $message]);
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        sendResponse('error', 'Please fill out all required fields.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse('error', 'Please provide a valid email address.');
    }

    // --- NEW: Save Inquiry to `contact_inquiries` table ---
    $sql_insert_inquiry = "INSERT INTO contact_inquiries (name, email, subject, message) VALUES (?, ?, ?, ?)";
    if ($stmt_inquiry = mysqli_prepare($link, $sql_insert_inquiry)) {
        mysqli_stmt_bind_param($stmt_inquiry, "ssss", $name, $email, $subject, $message);
        if (!mysqli_stmt_execute($stmt_inquiry)) {
            error_log("Database Error saving inquiry: " . mysqli_error($link));
            sendResponse('error', 'Sorry, there was a problem saving your message. Please try again later.');
        }
        mysqli_stmt_close($stmt_inquiry);
    } else {
        error_log("Database Prepare Error for inquiry insert: " . mysqli_error($link));
        sendResponse('error', 'A server error occurred. Please try again.');
    }

    // --- Send Email Notification to Admin (Optional but Recommended) ---
    $mail = new PHPMailer(true);
    try {
        // PHPMailer Server settings (from mail_config.php)
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;

        // For localhost testing, uncomment these lines. REMOVE FOR PRODUCTION!
        /*
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        */

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress(MAIL_TO_EMAIL, MAIL_TO_NAME); 
        $mail->addReplyTo($email, $name);

        $safe_name = htmlspecialchars($name);
        $safe_email = htmlspecialchars($email);
        $safe_subject = htmlspecialchars($subject);
        $safe_message = nl2br(htmlspecialchars($message));

        $mail->isHTML(true);
        $mail->Subject = 'New Inquiry from Mednova School Website: ' . $safe_subject; 
        $mail->Body = "<div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>"
                    . "<h2>New Inquiry for Mednova School</h2>"
                    . "<p>A new inquiry has been submitted through the website contact form and saved to the admin panel.</p>"
                    . "<p><strong>Name:</strong> {$safe_name}</p>"
                    . "<p><strong>Email:</strong> {$safe_email}</p>"
                    . "<p><strong>Subject:</strong> {$safe_subject}</p>"
                    . "<h3>Message:</h3>"
                    . "<div style='padding: 15px; border-left: 4px solid #059669; background-color: #f9f9f9; border-radius: 5px;'>"
                    . "<p style='margin: 0;'>{$safe_message}</p>"
                    . "</div>"
                    . "<p style='font-size: 0.9em; color: #777; margin-top: 20px;'>Please log in to the admin panel to view and reply to this message.</p>"
                    . "</div>";
        $mail->AltBody = "New Inquiry for Mednova School\n\nName: {$name}\nEmail: {$email}\nSubject: {$subject}\n\nMessage:\n{$message}\n\nPlease log in to the admin panel to view and reply.";
        
        $mail->send();
        sendResponse('success', 'Thank you! Your message has been sent successfully.');
    } catch (Exception $e) {
        error_log("Email notification to admin failed: " . $mail->ErrorInfo);
        sendResponse('success', 'Thank you! Your message has been received, but we had trouble sending you an email confirmation. We will still get back to you shortly.'); // Still success, as it was saved
    }
}

// Close the database connection before including footer
mysqli_close($link);

// 4. INCLUDE THE SHARED HEADER (Conditional, as it would break AJAX if not handled)
// Ensure this header does not produce any output before the AJAX response.
// If your header.php always outputs, you need to call it AFTER the AJAX logic.
require_once __DIR__ . '/../header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Mednova School</title>
    <meta name="description" content="Contact Mednova School for admissions, general inquiries, and support. Find our address, phone number, email, and office hours.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        /* --- PREMIUM GREEN/TEAL THEME --- */
        :root {
            --bg-color: #F0FDFA; /* Very light teal */
            --card-bg: #FFFFFF;
            --text-dark: #0F766E; /* Deep teal */
            --text-medium: #115E59; /* Deeper teal */
            --text-light: #475569; /* slate-600 */
            --accent-primary: #059669; /* emerald-600 */
            --border-color: #CCFBF1; /* teal-100 */
            --hover-border-color: #5EEAD4; /* teal-300 */
            --shadow-color: rgba(13, 148, 136, 0.1);
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-light); }
        h1, h2, h3, h4 { font-family: 'Playfair Display', serif; color: var(--text-medium); }
        .section-padding { padding: 6rem 0; }
        .container-padding { max-width: 1300px; margin: auto; padding: 0 1.5rem; }
        @media (min-width: 1024px) { .container-padding { padding: 0 5rem; } }
        
        .text-gradient-primary { background-image: linear-gradient(90deg, var(--accent-primary), var(--text-dark)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 900; }
        .hero-section { background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.7)), url('https://placehold.co/1920x600/0D9488/F0FDFA?text=Contact+Mednova'); background-size: cover; background-position: center; color: white; padding: 8rem 0; text-shadow: 2px 2px 8px rgba(0,0,0,0.6); border-radius: 0 0 3rem 3rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2); text-align: center; }
        .hero-section h1 { font-size: 3.5rem; line-height: 1.2; margin-bottom: 1.5rem; font-weight: 900; }
        .hero-section p { font-size: 1.5rem; max-width: 800px; margin: 0 auto; }
        .animate-fade-in { animation: fadeIn 1.2s ease-out forwards; opacity: 0; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .info-card, .form-card { background-color: var(--card-bg); border-radius: 1.5rem; box-shadow: 0 10px 20px var(--shadow-color); border: 1px solid var(--border-color); transition: all 0.3s ease; }
        .info-card:hover { transform: translateY(-5px); box-shadow: 0 15px 25px var(--shadow-color); border-color: var(--hover-border-color); }
        
        .form-input, .form-textarea { padding: 1rem; border: 1px solid #d1d5db; border-radius: 0.75rem; color: #1f2937; background-color: #f9fafb; transition: all 0.3s ease; font-size: 1rem; }
        .form-input:focus, .form-textarea:focus { outline: none; border-color: var(--accent-primary); box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2); background-color: white; }
        
        .form-submit-btn { background-color: var(--accent-primary); transition: all 0.3s ease; box-shadow: 0 5px 15px var(--shadow-color); border: none; }
        .form-submit-btn:hover { transform: translateY(-3px) scale(1.01); box-shadow: 0 8px 20px var(--shadow-color); }
        .form-submit-btn:disabled { opacity: 0.7; cursor: not-allowed; transform: none; box-shadow: none; }
        
        #form-status { margin-top: 1.5rem; padding: 1rem; border-radius: 0.75rem; font-weight: 500; text-align: center; display: none; }
        #form-status.success { background-color: #DEF7EC; color: #047857; border: 1px solid #A7F3D0; }
        #form-status.error { background-color: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA; }
    </style>
</head>
<body>
    <section class="hero-section">
        <div class="container-padding animate-fade-in">
            <h1>Connect with Mednova School</h1>
            <p>We're here to answer your questions and provide the information you need. Reach out to us through our contact form or directly using the details below.</p>
        </div>
    </section>

    <section id="contact-details-form" class="section-padding">
        <div class="container-padding">
            <div class="text-center mb-16 animate-fade-in">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gradient-primary">Get in Touch with Mednova</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">Whether you have questions about admissions, programs, or general inquiries, our team is ready to assist you.</p>
            </div>

            <div class="grid lg:grid-cols-5 gap-12">
                <div class="lg:col-span-2 space-y-8">
                    <div class="info-card p-8 flex items-start gap-6 animate-fade-in" style="animation-delay: 0.2s;">
                        <div class="flex-shrink-0 bg-emerald-100 text-emerald-600 p-4 rounded-xl"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg></div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Our Location</h3>
                            <p class="text-gray-700 text-lg"><?php echo nl2br(htmlspecialchars($contact_settings['location_address'] ?? 'Address not set.')); ?></p>
                            <a href="<?php echo htmlspecialchars($contact_settings['location_map_url'] ?? '#'); ?>" target="_blank" class="mt-4 inline-flex items-center text-emerald-600 hover:text-emerald-700 font-semibold transition">View on Map</a>
                        </div>
                    </div>
                    <div class="info-card p-8 flex items-start gap-6 animate-fade-in" style="animation-delay: 0.3s;">
                        <div class="flex-shrink-0 bg-teal-100 text-teal-600 p-4 rounded-xl"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg></div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Contact Us</h3>
                            <p class="text-gray-700 text-lg">
                                General: <a href="tel:<?php echo htmlspecialchars($contact_settings['phone_general'] ?? ''); ?>" class="text-teal-600 hover:underline"><?php echo htmlspecialchars($contact_settings['phone_general'] ?? 'N/A'); ?></a><br>
                                Admissions: <a href="tel:<?php echo htmlspecialchars($contact_settings['phone_admissions'] ?? ''); ?>" class="text-teal-600 hover:underline"><?php echo htmlspecialchars($contact_settings['phone_admissions'] ?? 'N/A'); ?></a><br>
                                Email: <a href="mailto:<?php echo htmlspecialchars($contact_settings['email_general'] ?? ''); ?>" class="text-teal-600 hover:underline"><?php echo htmlspecialchars($contact_settings['email_general'] ?? 'N/A'); ?></a>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-3 form-card p-8 lg:p-10 animate-fade-in" style="animation-delay: 0.5s;">
                    <h3 class="text-3xl font-bold text-gray-900 mb-4 text-center lg:text-left">Send Us a Message</h3>
                    <p class="text-gray-600 mb-8 text-center lg:text-left">Use the form below for any queries, feedback, or to schedule an appointment.</p>
                    <form id="inquiry-form" class="space-y-6" method="POST" action="">
                        <input type="hidden" name="action" value="send_inquiry">
                        <div><label for="name" class="sr-only">Name</label><input type="text" name="name" id="name" class="form-input w-full" placeholder="Your Name" required></div>
                        <div><label for="email" class="sr-only">Email</label><input type="email" name="email" id="email" class="form-input w-full" placeholder="Your Email" required></div>
                        <div><label for="subject" class="sr-only">Subject</label><input type="text" name="subject" id="subject" class="form-input w-full" placeholder="Subject" required></div>
                        <div><label for="message" class="sr-only">Message</label><textarea name="message" id="message" class="form-textarea w-full" placeholder="Your Message" rows="6" required></textarea></div>
                        <button type="submit" class="form-submit-btn w-full text-white font-bold py-3 px-6 rounded-xl text-lg" id="submit-btn">Send Message</button>
                        <div id="form-status"></div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section id="location-map" class="section-padding">
        <div class="container-padding">
            <div class="w-full h-96 md:h-[500px] rounded-2xl overflow-hidden shadow-xl border-4 border-white animate-fade-in">
                <iframe 
                    src="<?php echo htmlspecialchars($contact_settings['location_map_url'] ?? ''); ?>" 
                    width="100%" 
                    height="100%" 
                    style="border:0;" 
                    allowfullscreen 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade" 
                    title="Mednova School Location">
                </iframe>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const inquiryForm = document.getElementById('inquiry-form');
            if (inquiryForm) {
                inquiryForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const submitBtn = document.getElementById('submit-btn');
                    const formStatus = document.getElementById('form-status');
                    submitBtn.textContent = 'Sending...'; // Changed from innerHTML to textContent
                    submitBtn.disabled = true;
                    formStatus.style.display = 'none';

                    fetch(window.location.href, { // Submits to this same page
                        method: 'POST',
                        body: new FormData(this)
                    })
                    .then(response => {
                        // Check if the response is valid JSON. PHPMailer debug output can prevent this.
                        const contentType = response.headers.get("content-type");
                        if (contentType && contentType.includes("application/json")) {
                            return response.json();
                        } else {
                            // If not JSON, it might be raw PHPMailer debug output or other error.
                            // Read as text to get the full response body for debugging.
                            return response.text().then(text => {
                                console.error('Server response was not JSON:', text);
                                throw new Error('Received non-JSON response from server.');
                            });
                        }
                    })
                    .then(data => {
                        formStatus.textContent = data.message; // Changed from innerHTML to textContent
                        formStatus.className = data.status; // 'success' or 'error'
                        formStatus.style.display = 'block';
                        if (data.status === 'success') inquiryForm.reset();
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        formStatus.textContent = 'An unexpected error occurred. Please try again.'; // Changed from innerHTML to textContent
                        formStatus.className = 'error';
                        formStatus.style.display = 'block';
                    })
                    .finally(() => {
                        submitBtn.textContent = 'Send Message'; // Changed from innerHTML to textContent
                        submitBtn.disabled = false;
                    });
                });
            }
        });
    </script>
</body>
</html>

<?php
require_once __DIR__ . '/../footer.php';
?>