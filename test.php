<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

// Load email list
$emailFile = 'emails.txt';
$emails = file($emailFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$sentCount = 0;
$maxToSend = 450;

foreach ($emails as $index => $emailAddress) {
    if ($sentCount >= $maxToSend) {
        break;
    }

    $mail = new PHPMailer(true);

    try {
    $mail->isSMTP();
    $mail->Host = 'smtp.zoho.com';                                 //  Zoho SMTP server
    $mail->SMTPAuth = true;
    $mail->Username = 'google-career@wixnation.com';               //  Your full Zoho Mail email
    $mail->Password = 'Edmond99@';                          //  Your Zoho mailbox password or app-specific password
    $mail->SMTPSecure = 'ssl';                                     //  Use 'ssl' or 'tls'
    $mail->Port = 465;                                             //  465 for SSL; 587 for TLS

    $mail->setFrom('google-career@wixnation.com', 'G-Careers Outreach Team'); //  Branded sender
    $mail->addAddress($emailAddress);                              //  Dynamic recipient variable

    //  Save a copy in the "Sent" folder
    $mail->addCustomHeader('X-ZohoMail', 'SaveInSent');

    $mail->isHTML(true);
    $mail->Subject = 'Google Careers: Remote Position';

// Email body
$mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; background-color: #ffffff;">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQlEKQ5YFzrU0QBQjOcIP5NJbIaBjVyu8YxWFqjqv7o4eITtbnj3cgGuOc&s=10" alt="Logo" width="120" style="display: block; margin: 0 auto 20px;" />

   <h2 style="color: #202124; text-align: center;">You\'re Invited to Explore Remote Roles at Google Careers</h2>

            <p>Dear Candidate,</p>

            <p>This message is intended to inform you of potential remote career openings connected to the Google Careers platform. If you are open to exploring available positions, we invite your response.</p>

            <p><strong>You may be considered for remote opportunities associated with Google Careers, offering flexibility and the ability to contribute from anywhere in the world.</strong></p>

            <p>Opportunities span multiple departments such as Engineering, Marketing, Support, HR, Product, and others, with flexibility depending on role requirements.</p>

            <p style="text-align: center; margin: 20px 0;">
                <a href="https://rb.gy/ehsmip" target="_blank" style="background-color: #1a73e8; color: #ffffff; padding: 12px 24px; border-radius: 4px; text-decoration: none; font-weight: bold;">
                    Learn More
                </a>
            </p>

            <p>If you are interested in proceeding, kindly reply with your availability. A small A brief applicant confirmation step may be requested to ensure genuine interest and streamline onboarding procedures.</p>

            <p>We look forward to welcoming you to the Google Careers team.</p>

            <br/>

            <p>Warm regards,<br/>
            <strong>Google Hiring Team</strong><br/>
            careers@google.com</p>

            <hr style="margin: 30px 0;" />

            <p style="font-size: 0.85rem; color: #666;">
                Please visit <a href="https://careers.google.com/" target="_blank">careers.google.com</a> to verify official roles and learn more about our hiring policies.
            </p>
        </div>
    ' . 
        $mail->AltBody = "";

        $mail->send();
        echo "[✓] Sent to: {$emailAddress}\n";

        // Remove sent email from the file immediately
        unset($emails[$index]);
        file_put_contents($emailFile, implode(PHP_EOL, $emails) . PHP_EOL);

        $sentCount++;
        sleep(1); // avoid triggering Gmail throttling

    } catch (Exception $e) {
        echo "❌ Failed to send to {$emailAddress} - Error: {$mail->ErrorInfo}\n";
        // Email remains in the file for retry later
    }
}

echo "[✓] Process completed. Total sent: {$sentCount}\n";
?>