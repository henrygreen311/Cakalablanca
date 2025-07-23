<?php

function extractEmailsFromText($text)
{
    preg_match_all('/[a-zA-Z0-9._%+-]+@gmail\.com/', $text, $matches);
    return array_map('strtolower', $matches[0]);
}

// Load config
$accounts = json_decode(file_get_contents('config.json'), true);
if (!$accounts) {
    die("Failed to load config.json\n");
}

$allValidEmails = [];
$allInvalidEmails = [];

foreach ($accounts as $account) {
    $email = $account['email'];
    $password = $account['app_password'];
    echo "Checking account: $email\n";

    $imap_host = 'imap.gmail.com:993/imap/ssl';

    // 1. Connect to INBOX to find bounce messages
    $inbox = @imap_open("{" . $imap_host . "}INBOX", $email, $password);
    if (!$inbox) {
        echo "Failed to connect to inbox: " . imap_last_error() . "\n";
        continue;
    }

    // Search for bounce messages from Gmail daemon
    $bounces = imap_search($inbox, 'FROM "mailer-daemon@googlemail.com"');
    if ($bounces) {
        foreach ($bounces as $mail_id) {
            $body = imap_fetchbody($inbox, $mail_id, 1.1);
            if (empty($body)) {
                $body = imap_fetchbody($inbox, $mail_id, 1);
            }

            $foundEmails = extractEmailsFromText($body);
            foreach ($foundEmails as $invalidEmail) {
                $bounceMessage = "Your message wasn't delivered to $invalidEmail";

                //  Only add to invalid list if Gmail explicitly confirms bounce
                if (stripos($body, $bounceMessage) !== false) {
                    if (!isset($allInvalidEmails[$invalidEmail])) {
                        $allInvalidEmails[$invalidEmail] = true;
                        //echo "Marked as invalid: $invalidEmail\n";
                    }
                }
            }
        }
    }

    imap_close($inbox);

    // 2. Connect to Sent Mail to find valid recipients
    $sentbox = @imap_open("{" . $imap_host . "}[Gmail]/Sent Mail", $email, $password);
    if (!$sentbox) {
        echo "Failed to connect to Sent Mail: " . imap_last_error() . "\n";
        continue;
    }

    $emails = imap_search($sentbox, 'ALL');
    if ($emails) {
        foreach ($emails as $mail_id) {
            $header = imap_headerinfo($sentbox, $mail_id);
            $toList = $header->to ?? [];

            // Skip if likely a bounce structure (2+ recipients)
            if (count($toList) > 1) {
                continue;
            }

            foreach ($toList as $to) {
                $recipient = strtolower(trim($to->mailbox . '@' . $to->host));

                // Skip if tagged with "(inbox)" or invalid pattern
                if (strpos($recipient, '(inbox)') !== false ||
                    (!empty($to->personal) && stripos($to->personal, 'inbox') !== false)) {
                    continue;
                }

                // Skip if already marked as invalid
                if (isset($allInvalidEmails[$recipient])) {
                    continue;
                }

                $allValidEmails[$recipient] = true;
            }
        }
    } else {
        echo "No emails found in sent folder for $email.\n";
    }

    imap_close($sentbox);
}

// 3. Write invalid emails to file
file_put_contents('invalid.txt', implode("\n", array_keys($allInvalidEmails)));
echo "Invalid emails saved to invalid.txt: " . count($allInvalidEmails) . "\n";

// 4. Write valid emails to file, excluding any invalid ones
$existingValid = [];
if (file_exists('valid_emails.txt')) {
    $existingValid = file('valid_emails.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

$combined = array_merge($existingValid, array_keys($allValidEmails));
$filteredUnique = array_unique(array_diff(
    array_map('strtolower', array_map('trim', $combined)),
    array_keys($allInvalidEmails)
));
sort($filteredUnique);

file_put_contents('valid_emails.txt', implode("\n", $filteredUnique));
echo "Valid emails saved to valid_emails.txt: " . count($filteredUnique) . "\n";