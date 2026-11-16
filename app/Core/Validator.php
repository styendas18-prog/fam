<?php
declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules
     * @return array<string,string>
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $string = is_string($value) ? trim($value) : $value;
            foreach (explode('|', $ruleString) as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name === 'nullable' && ($string === null || $string === '')) {
                    break;
                }
                if ($name === 'required' && ($string === null || $string === '')) {
                    $errors[$field] = 'This field is required.';
                    continue;
                }
                if ($string === null || $string === '') {
                    continue;
                }
                switch ($name) {
                    case 'email':
                        if (!filter_var((string) $string, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = 'Enter a valid email address.';
                        }
                        break;
                    case 'min':
                        if (mb_strlen((string) $string) < (int) $arg) {
                            $errors[$field] = 'Must be at least ' . (int) $arg . ' characters.';
                        }
                        break;
                    case 'max':
                        if (mb_strlen((string) $string) > (int) $arg) {
                            $errors[$field] = 'Must be no more than ' . (int) $arg . ' characters.';
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($string)) {
                            $errors[$field] = 'Must be a number.';
                        }
                        break;
                    case 'decimal':
                        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', (string) $string)) {
                            $errors[$field] = 'Enter a valid amount with up to 2 decimals.';
                        }
                        break;
                    case 'url':
                        if (!filter_var((string) $string, FILTER_VALIDATE_URL)) {
                            $errors[$field] = 'Enter a valid URL.';
                        }
                        break;
                    case 'https':
                        $parts = parse_url((string) $string);
                        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
                            $errors[$field] = 'URL must use HTTPS.';
                        }
                        break;
                    case 'in':
                        if (!in_array((string) $string, explode(',', (string) $arg), true)) {
                            $errors[$field] = 'Select a valid option.';
                        }
                        break;
                    case 'matches':
                        if ((string) $string !== (string) ($data[$arg] ?? '')) {
                            $errors[$field] = 'Values do not match.';
                        }
                        break;
                    case 'regex':
                        if (!preg_match((string) $arg, (string) $string)) {
                            $errors[$field] = 'The format is invalid.';
                        }
                        break;
                }
            }
        }
        return $errors;
    }

    public static function passwordStrong(string $password): ?string
    {
        if (strlen($password) < 10) {
            return 'Password must be at least 10 characters.';
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Password must include upper case, lower case, and a number.';
        }
        return null;
    }

    public static function amount(string|float|int|null $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $amount = round((float) $value, 2);
        if ($amount <= 0 || $amount > 10_000_000) {
            return null;
        }
        return $amount;
    }
}
