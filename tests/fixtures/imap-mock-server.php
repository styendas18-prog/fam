<?php
declare(strict_types=1);

/**
 * Minimal scripted IMAP server used by tests/imap_client_test.php.
 *
 * Usage: php tests/fixtures/imap-mock-server.php [port]
 *
 * It speaks just enough of RFC 3501 to exercise ImapClient end to end:
 * greeting, CAPABILITY, ID, LOGIN, AUTHENTICATE XOAUTH2, SELECT, LIST,
 * UID SEARCH, UID FETCH with a binary literal, UID STORE and LOGOUT.
 */

$port = (int) ($argv[1] ?? 1143);
$server = @stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, "mock: cannot listen on {$port}: {$errstr}\n");
    exit(1);
}
fwrite(STDOUT, "READY {$port}\n");
fflush(STDOUT);

$appPassword = 'abcdefghijklmnop';
$oauthToken = 'ya29.mock-access-token';

$message = "From: FamPay Alerts <alerts@fampay.in>\r\n"
    . "To: merchant@gmail.com\r\n"
    . "Subject: Payment received on FamPay\r\n"
    . "Date: Mon, 16 Nov 2026 10:00:00 +0530\r\n"
    . "Message-ID: <mock-103@fampay.in>\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n"
    . "\r\n"
    . "Rs. 499.00 credited to your FamPay UPI ID maxx@fam.\r\n"
    . "UTR: 123456789012\r\n"
    . "Thanks for using FamPay.\r\n";

$connection = 0;
$maxConnections = (int) ($argv[2] ?? 6);
$deadline = time() + 60;

while (time() < $deadline) {
    $client = @stream_socket_accept($server, 5);
    if ($client === false) {
        continue;
    }
    $connection++;
    fwrite($client, "* OK [CAPABILITY IMAP4rev1 AUTH=PLAIN AUTH=XOAUTH2 SASL-IR UIDPLUS ID] Gmail mock ready\r\n");

    $selected = false;
    while (($line = fgets($client)) !== false) {
        $line = rtrim($line, "\r\n");
        if ($line === '') {
            continue;
        }
        $parts = explode(' ', $line, 2);
        $tag = $parts[0];
        $command = strtoupper(trim($parts[1] ?? ''));
        $args = trim($parts[1] ?? '');

        if (str_starts_with($command, 'CAPABILITY')) {
            fwrite($client, "* CAPABILITY IMAP4rev1 AUTH=PLAIN AUTH=XOAUTH2 SASL-IR UIDPLUS ID\r\n{$tag} OK CAPABILITY completed\r\n");
            continue;
        }
        if (str_starts_with($command, 'ID ')) {
            fwrite($client, "* ID (\"name\" \"mock\" \"version\" \"1.0\")\r\n{$tag} OK ID completed\r\n");
            continue;
        }
        if (str_starts_with($command, 'LOGIN ')) {
            $body = substr($args, 6);
            if (str_contains($body, '"' . $appPassword . '"')) {
                fwrite($client, "{$tag} OK LOGIN completed\r\n");
            } else {
                fwrite($client, "{$tag} NO [AUTHENTICATIONFAILED] Invalid credentials (Failure)\r\n");
            }
            continue;
        }
        if (str_starts_with($command, 'AUTHENTICATE XOAUTH2')) {
            $payload = trim(substr($args, strlen('AUTHENTICATE XOAUTH2')));
            $decoded = base64_decode($payload, true) ?: '';
            if (str_contains($decoded, $oauthToken)) {
                fwrite($client, "{$tag} OK AUTHENTICATE completed\r\n");
            } else {
                fwrite($client, "{$tag} NO [AUTHENTICATIONFAILED] Invalid credentials (Failure)\r\n");
            }
            continue;
        }
        if (str_starts_with($command, 'SELECT ')) {
            $mailbox = trim(substr($args, 7), '"');
            if ($mailbox !== 'INBOX') {
                fwrite($client, "{$tag} NO [NONEXISTENT] Unknown mailbox\r\n");
                continue;
            }
            $selected = true;
            fwrite($client, "* FLAGS (\\Answered \\Flagged \\Deleted \\Seen \\Draft)\r\n");
            fwrite($client, "* 3 EXISTS\r\n");
            fwrite($client, "* 0 RECENT\r\n");
            fwrite($client, "* OK [UIDVALIDITY 1234] UIDs valid\r\n");
            fwrite($client, "* OK [UIDNEXT 104] Predicted next UID\r\n");
            fwrite($client, "* OK [UNSEEN 2] First unseen\r\n");
            fwrite($client, "{$tag} OK [READ-WRITE] SELECT completed\r\n");
            continue;
        }
        if (str_starts_with($command, 'LIST ')) {
            fwrite($client, "* LIST (\\HasNoChildren) \"/\" \"INBOX\"\r\n");
            fwrite($client, "* LIST (\\HasNoChildren) \"/\" \"[Gmail]/All Mail\"\r\n");
            fwrite($client, "* LIST (\\HasNoChildren) \"/\" \"[Gmail]/Spam\"\r\n");
            fwrite($client, "{$tag} OK LIST completed\r\n");
            continue;
        }
        if (str_starts_with($command, 'UID SEARCH')) {
            $criteria = strtoupper(substr($command, strlen('UID SEARCH')));
            if (str_contains($criteria, '103')) {
                fwrite($client, "* SEARCH\r\n{$tag} OK SEARCH completed\r\n");
            } else {
                fwrite($client, "* SEARCH 101 103\r\n{$tag} OK SEARCH completed\r\n");
            }
            continue;
        }
        if (preg_match('/^UID FETCH (\d+)/', $command, $match) === 1) {
            $uid = (int) $match[1];
            if ($uid === 103) {
                fwrite($client, "* 3 FETCH (UID 103 BODY[] {" . strlen($message) . "}\r\n" . $message . ")\r\n");
                fwrite($client, "{$tag} OK FETCH completed\r\n");
            } elseif ($uid === 101) {
                fwrite($client, "* 1 FETCH (UID 101 BODY[] {0}\r\n)\r\n");
                fwrite($client, "{$tag} OK FETCH completed\r\n");
            } else {
                fwrite($client, "{$tag} OK FETCH completed\r\n");
            }
            continue;
        }
        if (str_starts_with($command, 'UID STORE')) {
            fwrite($client, "{$tag} OK STORE completed\r\n");
            continue;
        }
        if ($command === 'NOOP') {
            fwrite($client, "{$tag} OK NOOP completed\r\n");
            continue;
        }
        if (str_starts_with($command, 'LOGOUT')) {
            fwrite($client, "* BYE Logging out\r\n{$tag} OK LOGOUT completed\r\n");
            break;
        }
        fwrite($client, "{$tag} BAD Unsupported command\r\n");
    }

    fclose($client);
    if ($connection >= $maxConnections) {
        break;
    }
}

fclose($server);
