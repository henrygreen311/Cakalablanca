<?php
// Define files
$emailFile = 'emails.txt';
$lastFile = 'lastgmail.txt';

// Check file existence
if (!file_exists($emailFile)) {
    die("emails.txt not found.\n");
}

if (!file_exists($lastFile)) {
    die("lastgmail.txt not found.\n");
}

// Read and sanitize
$emails = file($emailFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$emails = array_map(fn($line) => trim(mb_convert_encoding($line, 'UTF-8')), $emails);

$lastGmail = trim(mb_convert_encoding(file_get_contents($lastFile), 'UTF-8'));

if (empty($lastGmail)) {
    die("lastgmail.txt is empty.\n");
}

// Find the position of the last Gmail
$foundIndex = array_search($lastGmail, $emails);

if ($foundIndex === false) {
    echo "Email '$lastGmail' not found in emails.txt. No changes made.\n";
    exit;
}

// Slice from next position
$remainingEmails = array_slice($emails, $foundIndex + 1);

// Write back to emails.txt
file_put_contents($emailFile, implode(PHP_EOL, $remainingEmails) . PHP_EOL);

echo "Trimmed emails.txt. Removed up to: $lastGmail (line " . ($foundIndex + 1) . ")\n";
echo "Remaining emails: " . count($remainingEmails) . "\n";
