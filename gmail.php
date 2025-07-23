<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

// Load Gmail accounts from config.json
$accounts = json_decode(file_get_contents('config.json'), true);
if (!$accounts) {
    die("Failed to load config.json or invalid format.\n");
}

$emailFile = 'emails.txt';

// Read email list
$targets = file($emailFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$targets) {
    die("emails.txt is missing or empty.\n");
}

$maxPerAccount = 105;
$totalSent = 0;
$totalToSend = count($targets);
$targetIndex = 0;

foreach ($accounts as $account) {
    $email = $account['email'];
    $password = $account['app_password'];
    $sentCount = 0;

    echo "\nSending from: $email\n";

    while ($sentCount < $maxPerAccount && isset($targets[$targetIndex])) {
        $recipient = trim($targets[$targetIndex]);

        // Skip invalid email
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            echo "Skipped invalid email: '$recipient'\n";
            $targetIndex++;
            continue;
        }

        $mail = new PHPMailer(true);
        $sendSuccess = false;

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $email;
            $mail->Password = $password;
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom($email, 'Validation Test');
            $mail->addAddress($recipient);

            $mail->isHTML(true);
            $mail->Subject = 'Validation Test';
            $mail->Body = 'This is a test email used to validate delivery to your address.';

            $mail->send();
            echo "Sent to: $recipient\n";

            $sentCount++;
            $totalSent++;
            $sendSuccess = true;

        } catch (Exception $e) {
            echo "Failed to send to $recipient: {$mail->ErrorInfo}\n";
        }

        if ($sendSuccess) {
    unset($targets[$targetIndex]);
    $targets = array_values($targets); // reindex numerically
    file_put_contents($emailFile, implode("\n", $targets));
    $targetIndex = 0; // restart from the top
} else {
    $targetIndex++;
}

        sleep(1); // throttle
    }

    if ($targetIndex >= count($targets)) {
        echo "All targets processed.\n";
        break;
    }
}