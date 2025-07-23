<?php
$accounts = json_decode(file_get_contents('config.json'), true);
if (!$accounts) {
    die("Failed to load config.json or invalid format.\n");
}

foreach ($accounts as $account) {
    $email = $account['email'];
    $password = $account['app_password'];
    echo "Processing account: $email\n";

    // Define the folders to clean
    $folders = [
        'INBOX' => 'Inbox',
        '[Gmail]/Sent Mail' => 'Sent Mail'
    ];

    foreach ($folders as $folder => $label) {
        echo " Deleting all emails in $label...\n";
        $imapPath = "{imap.gmail.com:993/imap/ssl}$folder";

        $mailbox = @imap_open($imapPath, $email, $password);
        if (!$mailbox) {
            echo "  Failed to connect to $label for $email: " . imap_last_error() . "\n";
            continue;
        }

        $emails = imap_search($mailbox, 'ALL');

        if ($emails) {
            foreach ($emails as $msgNum) {
                imap_delete($mailbox, $msgNum);
            }
            imap_expunge($mailbox);
            echo "  Deleted " . count($emails) . " messages in $label.\n";
        } else {
            echo "  No messages found in $label.\n";
        }

        imap_close($mailbox);
    }

    echo "Completed cleanup for: $email\n\n";
}
?>