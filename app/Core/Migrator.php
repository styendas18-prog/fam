<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Migrator
{
    public static function run(PDO $pdo, string $directory): int
    {
        $files = glob(rtrim($directory, '/\\') . '/*.sql') ?: [];
        sort($files);
        $count = 0;
        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Unable to read migration ' . basename($file));
            }
            foreach (self::splitStatements($sql) as $statement) {
                $pdo->exec($statement);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Split SQL on statement delimiters while respecting quoted strings.
     * Comments are stripped before splitting.
     */
    public static function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = [];
        $current = '';
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $prev = $i > 0 ? $sql[$i - 1] : '';
            if ($char === "'" && !$inDouble && !$inBacktick && $prev !== '\\') {
                $inSingle = !$inSingle;
            } elseif ($char === '"' && !$inSingle && !$inBacktick && $prev !== '\\') {
                $inDouble = !$inDouble;
            } elseif ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }
            if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $trimmed = trim($current);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }
        return $statements;
    }
}
