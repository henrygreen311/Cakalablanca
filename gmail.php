<?php    
use PHPMailer\PHPMailer\PHPMailer;    
use PHPMailer\PHPMailer\Exception;    
    
require 'src/PHPMailer.php';    
require 'src/SMTP.php';    
require 'src/Exception.php';    
    
$accounts = json_decode(file_get_contents('config.json'), true);    
if (!$accounts || !is_array($accounts)) {    
    die("config.json is missing or invalid.\n");    
}    
    
$emailFile = 'emails.txt';    
if (!file_exists($emailFile)) {    
    die("emails.txt is missing.\n");    
}    
$allTargets = file($emailFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);    
if (!$allTargets || count($allTargets) === 0) {    
    die("emails.txt is empty.\n");    
}    
    
$maxPerAccount = 105;    
$maxQuotaFailures = 3;    
$totalSent = 0;    
    
foreach ($accounts as $accountIndex => $account) {    
    $email = trim($account['email'] ?? '');    
    $password = trim($account['app_password'] ?? '');    
    $sentCount = 0;    
    $quotaFailures = 0;    
    
    if (!$email || !$password) {    
        echo "Skipping account #$accountIndex due to missing credentials.\n";    
        continue;    
    }    
    
    echo "\nUsing account [$accountIndex]: $email\n";    
    
    // Auth check (optional pre-connect test)    
    $authCheck = new PHPMailer(true);    
    try {    
        $authCheck->isSMTP();    
        $authCheck->Host = 'smtp.gmail.com';    
        $authCheck->SMTPAuth = true;    
        $authCheck->Username = $email;    
        $authCheck->Password = $password;    
        $authCheck->SMTPSecure = 'tls';    
        $authCheck->Port = 587;    
        $authCheck->smtpConnect();    
        $authCheck->smtpClose();    
    } catch (Exception $e) {    
        echo "Authentication failed for $email. Skipping this account.\n";    
        continue;    
    }    
    
    $remainingTargets = $allTargets;    
    
    foreach ($remainingTargets as $targetIndex => $recipient) {    
        $recipient = trim($recipient);    
    
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {    
            echo "Invalid email: $recipient. Skipping.\n";    
            continue;    
        }    
    
        if ($sentCount >= $maxPerAccount) {    
            echo "Reached $maxPerAccount limit for $email\n";    
            break;    
        }    
    
        if ($quotaFailures >= $maxQuotaFailures) {    
            echo "Reached quota error threshold for $email\n";    
            break;    
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
    
            // Save last successful recipient
            file_put_contents('lastgmail.txt', $recipient);
    
            // Remove sent email from allTargets and update file    
            $key = array_search($recipient, $allTargets);    
            if ($key !== false) {    
                unset($allTargets[$key]);    
                file_put_contents($emailFile, implode("\n", $allTargets));    
            }    
    
            $sentCount++;    
            $totalSent++;    
            sleep(1);    
    
        } catch (Exception $e) {    
            $error = $mail->ErrorInfo;    
            echo "Failed to send to $recipient: $error\n";    
    
            if (    
                stripos($error, 'quota') !== false ||    
                stripos($error, 'rate limit') !== false    
            ) {    
                $quotaFailures++;    
                echo "Quota error $quotaFailures for $email\n";    
            }    
            continue;    
        }    
    
        if (count($allTargets) === 0) {    
            echo "All emails sent.\n";    
            break 2; // exits both loops    
        }    
    }    
    
    echo "Finished sending with $email. Total sent: $sentCount\n";    
}    
    
echo "Total emails sent across all accounts: $totalSent\n";
