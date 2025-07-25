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
$bouncedUIDsPerAccount = []; // New structure

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

    $bounces = imap_search($inbox, 'FROM "mailer-daemon@googlemail.com"');
    if ($bounces) {
        foreach ($bounces as $mail_id) {
            $body = imap_fetchbody($inbox, $mail_id, 1.1);
if (empty($body)) {
    $body = imap_fetchbody($inbox, $mail_id, 1);
}
if (empty($body)) {
    echo "Empty body for bounce message ID $mail_id in $email\n";
    continue;
}

            $foundEmails = extractEmailsFromText($body);
            foreach ($foundEmails as $invalidEmail) {
                $bounceMessage = "Your message wasn't delivered to $invalidEmail";

                if (stripos($body, $bounceMessage) !== false) {
                    if (!isset($allInvalidEmails[$invalidEmail])) {
                        $allInvalidEmails[$invalidEmail] = true;
                        if (!isset($bouncedUIDsPerAccount[$email])) {
    $bouncedUIDsPerAccount[$email] = [];
}
$bouncedUIDsPerAccount[$email][] = imap_uid($inbox, $mail_id);
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

            // Skip if multiple recipients (likely bounce-structure)
            if (count($toList) > 1) {
                continue;
            }

            foreach ($toList as $to) {
                $recipient = strtolower(trim($to->mailbox . '@' . $to->host));

                if (strpos($recipient, '(inbox)') !== false ||
                    (!empty($to->personal) && stripos($to->personal, 'inbox') !== false)) {
                    continue;
                }

                if (isset($allInvalidEmails[$recipient])) {
                    continue;
                }

                $allValidEmails[$recipient] = true;
            }
        }
    } else {
        echo "No emails found in Sent Mail for $email.\n";
    }

    imap_close($sentbox);
}

// 3. Write invalid emails to file
file_put_contents('invalid.txt', implode("\n", array_keys($allInvalidEmails)));
echo "Invalid emails saved to invalid.txt: " . count($allInvalidEmails) . "\n";

// 4. Load existing valid emails, revalidate against current invalids
$existingValid = [];
if (file_exists('valid_emails.txt')) {
    $existingValid = file('valid_emails.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

$existingValid = array_map('strtolower', array_map('trim', $existingValid));
$mergedValid = array_merge($existingValid, array_keys($allValidEmails));

// Remove any emails marked as invalid
$finalValidEmails = array_unique(array_diff($mergedValid, array_keys($allInvalidEmails)));
sort($finalValidEmails);

// Save updated valid list
file_put_contents('valid_emails.txt', implode("\n", $finalValidEmails));
echo "Valid emails saved to valid_emails.txt: " . count($finalValidEmails) . "\n";

// 5. Final cleanup: delete processed bounce and sent messages per account
foreach ($accounts as $account) {
    $email = $account['email'];
    $password = $account['app_password'];

    $imap_host = 'imap.gmail.com:993/imap/ssl';

    // === Delete processed bounce messages from INBOX ===
    $inbox = @imap_open("{" . $imap_host . "}INBOX", $email, $password);
    if (!$inbox) {
        echo "Failed to reconnect to INBOX for deletion: " . imap_last_error() . "\n";
    } else {
        foreach ($bouncedUIDsPerAccount[$email] ?? [] as $uid) {
    $msgNo = @imap_msgno($inbox, $uid);
    if ($msgNo !== false && $msgNo > 0) {
        imap_delete($inbox, $msgNo);
        usleep(200000); // Wait 200ms to prevent IMAP overload
    }
}
        imap_expunge($inbox);
        imap_close($inbox);
        unset($inbox);
        echo "Deleted processed bounce messages for $email\n";
    }

    // === Delete all sent emails to avoid future reprocessing ===
    $sentbox = @imap_open("{" . $imap_host . "}[Gmail]/Sent Mail", $email, $password);
    if (!$sentbox) {
        echo "Failed to reconnect to Sent Mail for cleanup: " . imap_last_error() . "\n";
    } else {
        $sentEmails = imap_search($sentbox, 'ALL');
        if ($sentEmails) {
            foreach ($sentEmails as $mail_id) {
                imap_delete($sentbox, $mail_id);
                usleep(200000); // Pause between deletes
            }
            imap_expunge($sentbox);
            echo "Deleted all sent emails for $email\n";
        } else {
            echo "No sent emails to delete for $email\n";
        }
        imap_close($sentbox);
        unset($sentbox);
    }
}