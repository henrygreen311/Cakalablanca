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

// Load target emails
$emailFile = 'emails.txt';
$targets = file($emailFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$targets || count($targets) === 0) {
    die("emails.txt is missing or empty.\n");
}

$maxPerAccount = 105;
$totalSent = 0;
$maxQuotaFailures = 3;

foreach ($accounts as $accountIndex => $account) {
    $email = $account['email'];
    $password = $account['app_password'];
    $sentCount = 0;
    $quotaFailures = 0;

    echo "\nUsing account [$accountIndex]: $email\n";

    // Test SMTP credentials
    $authTest = new PHPMailer(true);
    try {
        $authTest->isSMTP();
        $authTest->Host = 'smtp.gmail.com';
        $authTest->SMTPAuth = true;
        $authTest->Username = $email;
        $authTest->Password = $password;
        $authTest->SMTPSecure = 'tls';
        $authTest->Port = 587;
        $authTest->smtpConnect();
        $authTest->smtpClose();
    } catch (Exception $e) {
        echo "Authentication failed for $email. Skipping this account.\n";
        continue;
    }

    $targetIndex = 0;

    while (
        $sentCount < $maxPerAccount &&
        isset($targets[$targetIndex]) &&
        $quotaFailures < $maxQuotaFailures
    ) {
        $recipient = trim($targets[$targetIndex]);

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            echo "Skipped invalid email: '$recipient'\n";
            $targetIndex++;
            continue;
        }

        $mail = new PHPMailer(true);
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

            // Remove sent email from list and save file
            unset($targets[$targetIndex]);
            $targets = array_values($targets);
            file_put_contents($emailFile, implode("\n", $targets));
            $targetIndex = 0;

        } catch (Exception $e) {
            $error = $mail->ErrorInfo;
            echo "Failed to send to $recipient: $error\n";

            if (
                stripos($error, 'Daily user sending quota exceeded') !== false ||
                stripos($error, 'Quota exceeded') !== false ||
                stripos($error, 'Rate limit') !== false
            ) {
                $quotaFailures++;
                echo "Daily limit error detected ($quotaFailures of $maxQuotaFailures)\n";

                if ($quotaFailures >= $maxQuotaFailures) {
                    echo "Account $email exceeded quota error threshold. Skipping.\n";
                    break;
                }
            }

            $targetIndex++;
        }

        sleep(1);
    }

    echo "$sentCount emails sent from $email\n";

    if (empty($targets)) {
        echo "All targets processed.\n";
        break;
    }
}

echo "Total emails sent: $totalSent\n";
