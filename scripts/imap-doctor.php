<?php
declare(strict_types=1);

/**
 * Gmail IMAP doctor — real connection, real login, real alert parse.
 *
 * This is the fastest way to prove a mailbox works before trusting the cron.
 * It performs the exact same steps as the web UI: DNS, TLS, greeting,
 * capabilities, authentication, folder select, mailbox list, search and a
 * live parse of the newest unseen alert.
 *
 * Usage
 *   php scripts/imap-doctor.php --user=you@gmail.com --upi=name@fam --prompt
 *   php scripts/imap-doctor.php --merchant=12
 *   php scripts/imap-doctor.php --user=you@gmail.com --password=abcdefghijklmnop --upi=name@fam --json
 *
 * Options
 *   --user=EMAIL        Gmail address that receives FamPay alerts
 *   --password=PASS     Google App Password (16 chars) or leave it to --prompt / env
 *   --upi=UPI           FamPay UPI id, e.g. name@fam
 *   --host=HOST         default imap.gmail.com
 *   --port=PORT         default 993 for ssl, 143 for starttls
 *   --encryption=MODE   ssl | starttls | tls | none
 *   --folder=NAME       default INBOX (Gmail labels: "[Gmail]/All Mail")
 *   --sender=LIST       comma separated trusted sender domains
 *   --days=N            only search mail from the last N days (0 = all unseen)
 *   --merchant=ID       load the stored account of this merchant from the DB
 *   --save              save the credentials for --merchant before testing
 *   --limit=N           how many sample alerts to fetch (default 1)
 *   --prompt            ask for the App Password on stdin
 *   --json              machine readable output
 *   --help              show this help
 *
 * Environment: GMAIL_APP_PASSWORD can hold the App Password so it never
 * appears in the shell history.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Env;
use App\Services\EmailVerificationService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script only runs from the command line.\n");
}

$options = imap_doctor_options($argv);

if (isset($options['help'])) {
    imap_doctor_usage();
    exit(0);
}

$json = isset($options['json']);
$merchantId = isset($options['merchant']) ? (int) $options['merchant'] : 0;

$account = [
    'id' => 0,
    'merchant_id' => $merchantId,
    'gmail_address' => (string) ($options['user'] ?? ''),
    'app_password' => '',
    'upi_id' => (string) ($options['upi'] ?? ''),
    'imap_host' => (string) ($options['host'] ?? 'imap.gmail.com'),
    'imap_port' => (int) ($options['port'] ?? 0),
    'imap_encryption' => strtolower((string) ($options['encryption'] ?? 'ssl')),
    'folder' => (string) ($options['folder'] ?? 'INBOX'),
    'sender_whitelist' => (string) ($options['sender'] ?? EmailVerificationService::DEFAULT_SENDERS),
    'auth_method' => 'app_password',
    'search_days' => (int) ($options['days'] ?? 0),
    'mark_read' => 1,
];

// Load a stored account first so CLI flags only need to override what changed.
if ($merchantId > 0) {
    try {
        $stored = EmailVerificationService::accountForMerchant($merchantId);
    } catch (Throwable $e) {
        imap_doctor_fail('Unable to read the database: ' . $e->getMessage(), $json);
    }
    if (!$stored) {
        if (!isset($options['save']) && empty($options['user'])) {
            imap_doctor_fail('Merchant ' . $merchantId . ' has no Gmail IMAP account yet. Add one in the panel, pass --user/--password, or use --save to create it now.', $json);
        }
    } else {
        $account = array_merge($account, $stored);
    }
}

foreach (['user' => 'gmail_address', 'upi' => 'upi_id', 'host' => 'imap_host', 'folder' => 'folder', 'sender' => 'sender_whitelist'] as $flag => $field) {
    if (isset($options[$flag]) && $options[$flag] !== '') {
        $account[$field] = (string) $options[$flag];
    }
}
if (isset($options['port']) && (int) $options['port'] > 0) {
    $account['imap_port'] = (int) $options['port'];
}
if (isset($options['encryption'])) {
    $account['imap_encryption'] = strtolower((string) $options['encryption']);
}
if ($account['imap_port'] === 0) {
    $account['imap_port'] = $account['imap_encryption'] === 'starttls' ? 143 : 993;
}

$password = preg_replace('/\s+/', '', (string) ($options['password'] ?? '')) ?? '';
if ($password === '') {
    $password = preg_replace('/\s+/', '', (string) Env::get('GMAIL_APP_PASSWORD', '')) ?? '';
}
if ($password === '' && isset($options['prompt'])) {
    $password = preg_replace('/\s+/', '', imap_doctor_ask('Google App Password: ')) ?? '';
}
if ($password !== '') {
    $account['app_password'] = $password;
    $account['auth_method'] = 'app_password';
}

if ((string) $account['gmail_address'] === '') {
    imap_doctor_fail('Missing --user=your@gmail.com.', $json);
}
if ($account['auth_method'] === 'app_password' && (string) ($account['app_password'] ?? '') === '') {
    imap_doctor_fail('Missing the App Password. Use --prompt, --password=... or GMAIL_APP_PASSWORD.', $json);
}

if (isset($options['save']) && $merchantId > 0) {
    try {
        $saved = EmailVerificationService::saveAccount($merchantId, [
            'gmail_address' => $account['gmail_address'],
            'app_password' => $account['app_password'],
            'upi_id' => $account['upi_id'],
            'imap_host' => $account['imap_host'],
            'imap_port' => $account['imap_port'],
            'imap_encryption' => $account['imap_encryption'],
            'folder' => $account['folder'],
            'sender_whitelist' => $account['sender_whitelist'],
            'search_days' => $account['search_days'],
            'mark_read' => 1,
        ]);
        $account = array_merge($account, $saved);
        if (!$json) {
            echo "Saved credentials for merchant {$merchantId}.\n";
        }
    } catch (Throwable $e) {
        imap_doctor_fail('Unable to save the account: ' . $e->getMessage(), $json);
    }
}

$sampleLimit = max(0, min(10, (int) ($options['limit'] ?? 1)));
$diagnosis = EmailVerificationService::diagnose($account, $sampleLimit);
$diagnosis['account'] = [
    'gmail' => $account['gmail_address'],
    'upi' => $account['upi_id'],
    'host' => $account['imap_host'],
    'port' => (int) $account['imap_port'],
    'encryption' => $account['imap_encryption'],
    'folder' => $account['folder'],
    'auth' => $account['auth_method'] === 'oauth2' ? 'OAuth 2.0 (XOAUTH2)' : 'Google App Password',
];

if ($json) {
    echo json_encode($diagnosis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit($diagnosis['ok'] ? 0 : 1);
}

$line = str_repeat('=', 72);
echo "{$line}\n";
echo " MAXXfam Gmail IMAP doctor\n";
echo "{$line}\n";
printf(" Account   %s\n", $account['gmail_address']);
printf(" Server    %s:%d (%s), folder %s\n", $account['imap_host'], (int) $account['imap_port'], strtoupper((string) $account['imap_encryption']), $account['folder']);
printf(" Auth      %s\n", $diagnosis['account']['auth']);
printf(" FamPay    %s\n", $account['upi_id'] !== '' ? $account['upi_id'] : '(not set)');
echo "\n";

foreach ($diagnosis['steps'] as $step) {
    printf(" [%s] %-16s %5d ms  %s\n", $step['ok'] ? 'OK  ' : 'FAIL', $step['name'], (int) $step['ms'], $step['detail']);
    if (!$step['ok'] && !empty($step['hint'])) {
        echo "        fix: " . $step['hint'] . "\n";
    }
}

if (!empty($diagnosis['sample'])) {
    $sample = $diagnosis['sample'];
    echo "\n Sample alert (UID {$sample['uid']})\n";
    echo '   from:    ' . ($sample['from'] ?? '—') . "\n";
    echo '   subject: ' . ($sample['subject'] ?? '—') . "\n";
    echo '   amount:  ' . ($sample['amount'] !== null ? '₹' . number_format((float) $sample['amount'], 2) : '—') . "\n";
    echo '   utr:     ' . ($sample['utr'] ?? '—') . "\n";
    echo '   upi:     ' . ($sample['upi_id'] ?? '—') . "\n";
    echo '   trusted sender: ' . (!empty($sample['sender_trusted']) ? 'yes' : 'no') . "\n";
}

echo "\n{$line}\n";
printf(" %s  (%d ms)\n", $diagnosis['ok'] ? 'PASS — mailbox is ready' : 'FAILED — see the first failure above', (int) $diagnosis['duration_ms']);
echo "{$line}\n";
exit($diagnosis['ok'] ? 0 : 1);

/**
 * @param string[] $argv
 * @return array<string,string|true>
 */
function imap_doctor_options(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $arg = substr($arg, 2);
        if ($arg === '') {
            continue;
        }
        if (str_contains($arg, '=')) {
            [$key, $value] = explode('=', $arg, 2);
            $options[$key] = $value;
        } else {
            $options[$arg] = true;
        }
    }
    return $options;
}

function imap_doctor_usage(): void
{
    echo <<<TXT
MAXXfam Gmail IMAP doctor

  php scripts/imap-doctor.php --user=you@gmail.com --upi=name@fam --prompt
  php scripts/imap-doctor.php --merchant=12
  php scripts/imap-doctor.php --user=you@gmail.com --password=APP_PASSWORD --upi=name@fam --json

Options
  --user=EMAIL        Gmail address that receives FamPay alerts
  --password=PASS     Google App Password (16 characters)
  --prompt            type the App Password instead of passing it on the command line
  --upi=UPI           FamPay UPI id, e.g. name@fam
  --host=HOST         IMAP host (default imap.gmail.com)
  --port=PORT         993 for ssl, 143 for starttls
  --encryption=MODE   ssl | starttls | tls | none
  --folder=NAME       INBOX or a Gmail label such as "[Gmail]/All Mail"
  --sender=LIST       comma separated trusted sender domains
  --days=N            only look at the last N days of mail (0 = all unseen)
  --merchant=ID       use the stored account of this merchant
  --save              save the credentials for --merchant before testing
  --limit=N           number of sample alerts to parse (default 1)
  --json              machine readable output
  --help              this help

Exit code 0 means the mailbox is healthy and the cron can use it.

TXT;
}

function imap_doctor_fail(string $message, bool $json): never
{
    if ($json) {
        echo json_encode(['ok' => false, 'message' => $message], JSON_PRETTY_PRINT) . "\n";
    } else {
        fwrite(STDERR, "ERROR: {$message}\n");
    }
    exit(2);
}

function imap_doctor_ask(string $prompt): string
{
    if (function_exists('shell_exec') && DIRECTORY_SEPARATOR === '\\') {
        // Windows: read without echoing through PowerShell.
        $script = 'powershell -NoProfile -Command "$p = Read-Host -AsSecureString; ' .
            '$b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($p); ' .
            '[Runtime.InteropServices.Marshal]::PtrToStringAuto($b)"';
        $value = @shell_exec($script);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    if (function_exists('shell_exec') && DIRECTORY_SEPARATOR === '/') {
        $value = @shell_exec('stty -echo 2>/dev/null; read -r v; stty echo 2>/dev/null; printf %s "$v"');
        if (is_string($value)) {
            echo "\n";
            return trim($value);
        }
    }
    echo $prompt;
    $line = fgets(STDIN);
    return $line === false ? '' : trim($line);
}
