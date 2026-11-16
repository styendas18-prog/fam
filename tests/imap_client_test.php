<?php
declare(strict_types=1);

/**
 * End-to-end test for App\Core\ImapClient against a scripted local server.
 *
 * Run: php tests/imap_client_test.php
 *
 * Needs no Gmail account, no network and no database. It proves the protocol
 * layer really works: greeting, capabilities, LOGIN, XOAUTH2, SELECT with
 * UIDVALIDITY, mailbox listing, UID SEARCH, binary literal fetch, STORE and
 * Gmail error hints.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\ImapClient;
use App\Services\EmailVerificationService;

$php = PHP_BINARY;
$serverScript = __DIR__ . '/fixtures/imap-mock-server.php';
$port = 1143 + random_int(0, 40);

$descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$process = proc_open(
    escapeshellarg($php) . ' ' . escapeshellarg($serverScript) . ' ' . $port . ' 4',
    $descriptors,
    $pipes,
    dirname(__DIR__)
);
if (!is_resource($process)) {
    fwrite(STDERR, "Unable to start the mock IMAP server.\n");
    exit(1);
}

$ready = false;
$deadline = microtime(true) + 10;
while (microtime(true) < $deadline) {
    $line = fgets($pipes[1]);
    if ($line !== false && str_starts_with($line, 'READY')) {
        $ready = true;
        break;
    }
}
if (!$ready) {
    fwrite(STDERR, "Mock IMAP server did not start.\n");
    proc_terminate($process);
    exit(1);
}

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    } else {
        $failed++;
        echo "  FAIL  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

echo "Mock IMAP server on 127.0.0.1:{$port}\n\n";

// 1. App Password (LOGIN) happy path.
echo "App Password flow\n";
$client = new ImapClient('127.0.0.1', $port, 'none', 5);
try {
    $client->connect();
    check('greeting', str_contains($client->greeting(), 'OK'), trim($client->greeting()));
    check('capabilities', $client->hasCapability('AUTH=XOAUTH2'), implode(' ', $client->capabilities()));
    $client->login('merchant@gmail.com', 'abcdefghijklmnop');
    check('LOGIN', $client->isAuthenticated(), 'method=' . $client->authMethod());
    $client->select('INBOX');
    $info = $client->selected();
    check('SELECT EXISTS', (int) $info['exists'] === 3, 'exists=' . $info['exists']);
    check('UIDVALIDITY', $client->uidValidity() === 1234, 'uidvalidity=' . $client->uidValidity());
    $mailboxes = $client->listMailboxes();
    check('LIST mailboxes', count($mailboxes) === 3, implode(', ', array_column($mailboxes, 'name')));
    $uids = $client->uidSearch('UNSEEN');
    check('UID SEARCH', $uids === ['101', '103'], implode(',', $uids));
    $raw = $client->fetchRaw('103');
    check('UID FETCH literal', strlen($raw) > 100 && str_contains($raw, 'UTR: 123456789012'), strlen($raw) . ' bytes');
    $client->markSeen('103');
    $client->logout();
    check('LOGOUT', true);
} catch (Throwable $e) {
    check('App Password flow', false, $e->getMessage());
    $client->close();
}

// 2. Parser on the fetched message.
echo "\nParser\n";
try {
    $parsed = EmailParser_parse($raw);
    $alert = EmailVerificationService::parseAlert($parsed['text'], 'maxx@fam');
    check('amount', $alert['amount'] === 499.0, 'amount=' . var_export($alert['amount'], true));
    check('UTR', $alert['utr'] === '123456789012', 'utr=' . var_export($alert['utr'], true));
    check('UPI', $alert['upi_id'] === 'maxx@fam', 'upi=' . var_export($alert['upi_id'], true));
    check('keywords', $alert['keywords'] === true);
} catch (Throwable $e) {
    check('parser', false, $e->getMessage());
}

// 3. Wrong App Password must fail with an actionable hint.
echo "\nGmail error hints\n";
$client = new ImapClient('127.0.0.1', $port, 'none', 5);
try {
    $client->connect();
    $client->login('merchant@gmail.com', 'wrongpassword');
    check('bad password rejected', false, 'server accepted a bad password');
} catch (Throwable $e) {
    check('bad password rejected', true);
    $hint = (string) $client->lastHint();
    check('hint mentions App Password', str_contains($hint, 'App Password'), $hint);
    $client->close();
}

// 4. XOAUTH2 flow.
echo "\nOAuth 2.0 (XOAUTH2) flow\n";
$client = new ImapClient('127.0.0.1', $port, 'none', 5);
try {
    $client->connect();
    $client->authenticateXoauth2('merchant@gmail.com', 'ya29.mock-access-token');
    check('XOAUTH2', $client->isAuthenticated() && $client->authMethod() === 'xoauth2');
    $client->select('INBOX');
    check('SELECT after OAuth', $client->uidValidity() === 1234);
    $client->logout();
} catch (Throwable $e) {
    check('XOAUTH2 flow', false, $e->getMessage());
    $client->close();
}

$client = new ImapClient('127.0.0.1', $port, 'none', 5);
try {
    $client->connect();
    $client->authenticateXoauth2('merchant@gmail.com', 'expired-token');
    check('expired OAuth rejected', false, 'server accepted an expired token');
} catch (Throwable $e) {
    check('expired OAuth rejected', true);
    $client->close();
}

foreach ($pipes as $pipe) {
    @fclose($pipe);
}
if (is_resource($process)) {
    $status = proc_get_status($process);
    if (!empty($status['running'])) {
        proc_terminate($process);
    }
    proc_close($process);
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

function EmailParser_parse(string $raw): array
{
    return App\Services\EmailParser::parse($raw);
}
