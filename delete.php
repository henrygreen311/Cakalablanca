<?php
$accounts = json_decode(file_get_contents('config.json'), true);
if (!$accounts) {
    die("Failed to load config.json or invalid format.\n");
}

foreach ($accounts as $account) {
    $email = $account['email'];
    $password = $account['app_password'];
    echo " Deleting all sent emails for: $email\n";

    // IMAP path to Gmail's Sent folder
    $imapPath = '{imap.gmail.com:993/imap/ssl}[Gmail]/Sent Mail';

    $inbox = @imap_open($imapPath, $email, $password);
    if (!$inbox) {
        echo " Failed to connect to $email: " . imap_last_error() . "\n";
        continue;
    }

    // Search for all messages in Sent Mail
    $all = imap_search($inbox, 'ALL');

    if ($all) {
        foreach ($all as $msgNum) {
            imap_delete($inbox, $msgNum);
        }
        imap_expunge($inbox);
        echo " Deleted " . count($all) . " sent messages for $email.\n";
    } else {
        echo " No messages found in Sent Mail for $email.\n";
    }

    imap_close($inbox);
}
?>
