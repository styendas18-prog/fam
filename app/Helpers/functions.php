<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Settings;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $base = rtrim((string) Config::get('url'), '/');
        // Local development is commonly opened as both localhost and 127.0.0.1.
        // Browsers do not share session cookies across those host names, so keep
        // generated URLs on the same host the request arrived on.
        if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
            $configuredHost = strtolower((string) parse_url($base, PHP_URL_HOST));
            $requestHost = strtolower((string) parse_url('http://' . $_SERVER['HTTP_HOST'], PHP_URL_HOST));
            $localHosts = ['localhost', '127.0.0.1', '::1'];
            if (in_array($configuredHost, $localHosts, true) && in_array($requestHost, $localHosts, true)) {
                $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
                $base = $scheme . '://' . $_SERVER['HTTP_HOST'];
            }
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $relative = ltrim($path, '/');
        $file = BASE_PATH . '/public/' . $relative;
        $version = is_file($file) ? (string) filemtime($file) : '1';
        return url($relative) . '?v=' . $version;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): never
    {
        header('Location: ' . (preg_match('#^https?://#i', $path) ? $path : url($path)), true, $status);
        exit;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Flash::add($type, $message);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): string
    {
        $data = $_SESSION['_old'] ?? [];
        return e($data[$key] ?? $default);
    }
}

if (!function_exists('remember_old')) {
    function remember_old(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_token'], $input['api_key']);
        $_SESSION['_old'] = $input;
    }
}

if (!function_exists('clear_old')) {
    function clear_old(): void
    {
        unset($_SESSION['_old']);
    }
}

if (!function_exists('money')) {
    function money(float|int|string|null $amount, string $currency = 'INR'): string
    {
        $symbol = $currency === 'INR' ? '₹' : $currency . ' ';
        return $symbol . number_format((float) $amount, 2);
    }
}

if (!function_exists('ist')) {
    function ist(?string $utc, string $format = 'd M Y, h:i A'): string
    {
        if (!$utc) {
            return '—';
        }
        try {
            $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
            return $date->setTimezone(new DateTimeZone('Asia/Kolkata'))->format($format);
        } catch (Throwable) {
            return '—';
        }
    }
}

if (!function_exists('ist_iso')) {
    function ist_iso(?string $utc): string
    {
        if (!$utc) {
            return '';
        }
        try {
            return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('Asia/Kolkata'))
                ->format(DateTimeInterface::ATOM);
        } catch (Throwable) {
            return '';
        }
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $utc): string
    {
        if (!$utc) {
            return '—';
        }
        $ts = strtotime($utc . ' UTC');
        if ($ts === false) {
            return '—';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'just now';
        }
        foreach ([31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'] as $seconds => $label) {
            if ($diff >= $seconds) {
                $n = (int) floor($diff / $seconds);
                return $n . ' ' . $label . ($n === 1 ? '' : 's') . ' ago';
            }
        }
        return 'just now';
    }
}

if (!function_exists('status_meta')) {
    function status_meta(string $status): array
    {
        return match ($status) {
            'success', 'connected', 'active', 'delivered', 'verified', 'resolved' => ['label' => ucfirst($status), 'class' => 'badge-success', 'dot' => 'bg-emerald-500'],
            'pending', 'processing', 'testing', 'open', 'queued', 'retry' => ['label' => ucfirst($status), 'class' => 'badge-warning', 'dot' => 'bg-amber-500'],
            'failed', 'error', 'suspended', 'rejected', 'closed' => ['label' => ucfirst($status), 'class' => 'badge-danger', 'dot' => 'bg-rose-500'],
            'expired', 'cancelled', 'inactive', 'disabled', 'not_configured' => ['label' => str_replace('_', ' ', ucfirst($status)), 'class' => 'badge-muted', 'dot' => 'bg-slate-400'],
            default => ['label' => str_replace('_', ' ', ucfirst($status)), 'class' => 'badge-info', 'dot' => 'bg-sky-500'],
        };
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $out = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $out .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        return $out !== '' ? $out : 'M';
    }
}

if (!function_exists('mask_email')) {
    function mask_email(string $email): string
    {
        if (!str_contains($email, '@')) {
            return '—';
        }
        [$name, $domain] = explode('@', $email, 2);
        return mb_substr($name, 0, 2) . str_repeat('•', max(2, mb_strlen($name) - 2)) . '@' . $domain;
    }
}

if (!function_exists('query_string')) {
    function query_string(array $overrides = []): string
    {
        $params = array_merge($_GET, $overrides);
        $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);
        return $params ? '?' . http_build_query($params) : '';
    }
}

if (!function_exists('active_nav')) {
    function active_nav(string $current, string $key): string
    {
        return $current === $key ? 'is-active' : '';
    }
}

if (!function_exists('brand')) {
    function brand(string $key, string $default = ''): string
    {
        return (string) Settings::get('site_' . $key, $default);
    }
}

if (!function_exists('user')) {
    function user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('admin')) {
    function admin(): ?array
    {
        return Auth::admin();
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = ''): never
    {
        throw new App\Core\HttpException($status, $message);
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$values): never
    {
        echo '<pre style="background:#0b1220;color:#e2e8f0;padding:20px;border-radius:12px;overflow:auto">';
        foreach ($values as $value) {
            var_export($value);
            echo "\n";
        }
        echo '</pre>';
        exit;
    }
}
