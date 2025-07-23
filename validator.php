<?php

function extractEmailsFromHeader($header)
{
    preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $header, $matches);
    return $matches[0];
}

// Load config.json
$accounts = json_decode(file_get_contents('config.json'), true);
if (!$accounts) {
    die("Failed to load config.json\n");
}

$allValidEmails = [];

foreach ($accounts as $account) {
    $email = $account['email'];
    $password = $account['app_password'];
    echo "Checking sent mail for: $email\n";

    $imap_host = 'imap.gmail.com:993/imap/ssl';

    $inbox = @imap_open("{" . $imap_host . "}[Gmail]/Sent Mail", $email, $password);

    if (!$inbox) {
        echo "Failed to connect to mailbox: " . imap_last_error() . "\n";
        continue;
    }

    // Search for all emails in the Sent folder
    $emails = imap_search($inbox, 'ALL');
    if ($emails) {
        foreach ($emails as $mail_id) {
            $header = imap_headerinfo($inbox, $mail_id);
            $toList = $header->to ?? [];

            foreach ($toList as $to) {
                $recipient = strtolower(trim($to->mailbox . '@' . $to->host));
                $allValidEmails[$recipient] = true;
            }
        }
    } else {
        echo "No emails found in sent folder.\n";
    }

    imap_close($inbox);
}

// Save valid emails to a file
$existingEmails = [];
if (file_exists('valid_emails.txt')) {
    $existingEmails = file('valid_emails.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

$allEmails = array_merge($existingEmails, array_keys($allValidEmails));
$uniqueEmails = array_unique(array_map('strtolower', array_map('trim', $allEmails)));
sort($uniqueEmails);

file_put_contents('valid_emails.txt', implode("\n", $uniqueEmails));

echo "Extraction complete. Total valid emails: " . count($uniqueEmails) . "\n";