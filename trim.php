<?php
$emailsFile = 'emails.txt';
$lastSentFile = 'lastgmail.txt';

if (!file_exists($emailsFile)) {
    die("emails.txt not found.\n");
}
if (!file_exists($lastSentFile)) {
    die("lastgmail.txt not found.\n");
}

$lastSent = trim(file_get_contents($lastSentFile));
if (!$lastSent || !filter_var($lastSent, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email in lastgmail.txt.\n");
}

$lines = file($emailsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$lines || count($lines) === 0) {
    die("emails.txt is empty.\n");
}

// Find the index of the last sent email
$cutIndex = array_search($lastSent, $lines);
if ($cutIndex === false) {
    die("Email '$lastSent' not found in emails.txt. No changes made.\n");
}

// Remove everything up to and including the last sent email
$remaining = array_slice($lines, $cutIndex + 1);
file_put_contents($emailsFile, implode("\n", $remaining));

echo "Trimmed emails.txt up to and including $lastSent. Remaining emails: " . count($remaining) . "\n";
