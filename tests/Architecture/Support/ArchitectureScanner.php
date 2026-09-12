<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

final class ArchitectureScanner
{
    /**
     * @return list<array{file: string, line: int, referenced: string, package: string}>
     */
    public static function references(string $code, string $file = 'inline.php'): array
    {
        $tokens = token_get_all($code);
        $references = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token) && $token[0] === T_USE) {
                $next = self::nextSignificantToken($tokens, $index + 1);
                if ($next === '(') {
                    continue;
                }

                $statement = [];
                for ($index++; $index < $count && $tokens[$index] !== ';'; $index++) {
                    $statement[] = $tokens[$index];
                }

                foreach (self::useNames($statement) as $name) {
                    self::appendReference($references, $name, $file, $token[2]);
                }

                continue;
            }

            if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                self::appendReference($references, $token[1], $file, $token[2]);
            }
        }

        return $references;
    }

    /**
     * @param  list<array{file: string, line: int, referenced: string, package: string}>  $references
     * @param  list<string>  $allowedDependencies
     * @return list<string>
     */
    public static function dependencyViolations(string $consumer, array $references, array $allowedDependencies): array
    {
        $violations = [];

        foreach ($references as $reference) {
            if ($reference['package'] === $consumer) {
                continue;
            }

            if (! in_array($reference['package'], $allowedDependencies, true)) {
                $violations[] = sprintf(
                    '%s:%d package %s cannot reference %s',
                    $reference['file'],
                    $reference['line'],
                    $consumer,
                    $reference['referenced'],
                );
            }
        }

        return $violations;
    }

    /**
     * @param  list<array{file: string, line: int, referenced: string, package: string}>  $references
     * @return list<string>
     */
    public static function modelViolations(string $consumer, array $references): array
    {
        $violations = [];

        foreach ($references as $reference) {
            if ($reference['package'] === $consumer || ! str_contains($reference['referenced'], '\\Models\\')) {
                continue;
            }

            $violations[] = sprintf(
                '%s:%d package %s cannot reference mutable model %s',
                $reference['file'],
                $reference['line'],
                $consumer,
                $reference['referenced'],
            );
        }

        return $violations;
    }

    /** @return list<string> */
    public static function createdTables(string $code): array
    {
        $tokens = token_get_all($code);
        $tables = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if (! is_array($token) || ! in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $name = ltrim($token[1], '\\');
            if ($name !== 'Schema' && ! str_ends_with($name, '\\Schema')) {
                continue;
            }

            $doubleColonIndex = self::nextSignificantIndex($tokens, $index + 1);
            $methodIndex = self::nextSignificantIndex($tokens, $doubleColonIndex + 1);
            $openIndex = self::nextSignificantIndex($tokens, $methodIndex + 1);
            $tableIndex = self::nextSignificantIndex($tokens, $openIndex + 1);

            if (($tokens[$doubleColonIndex] ?? null) !== T_DOUBLE_COLON
                && (! is_array($tokens[$doubleColonIndex] ?? null) || $tokens[$doubleColonIndex][0] !== T_DOUBLE_COLON)) {
                continue;
            }

            if (! is_array($tokens[$methodIndex] ?? null) || $tokens[$methodIndex][0] !== T_STRING || $tokens[$methodIndex][1] !== 'create') {
                continue;
            }

            if (($tokens[$openIndex] ?? null) !== '(' || ! is_array($tokens[$tableIndex] ?? null) || $tokens[$tableIndex][0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $tables[] = self::decodeStringLiteral($tokens[$tableIndex][1]);
        }

        return $tables;
    }

    /**
     * @param  array<string, list<array{file: string, code: string}>>  $migrationsByPackage
     * @param  array<string, array{owner: string, writers: list<string>, reporting_readers: list<string>}>  $tables
     * @return list<string>
     */
    public static function migrationViolations(array $migrationsByPackage, array $tables): array
    {
        $violations = [];
        $creators = [];

        foreach ($tables as $table => $record) {
            $owner = $record['owner'] ?? null;
            $writers = $record['writers'] ?? null;
            $readers = $record['reporting_readers'] ?? null;

            if (! is_string($owner) || $owner === '') {
                $violations[] = "{$table} must declare one owner";
            }
            if ($writers !== [$owner]) {
                $violations[] = sprintf('%s writers must equal [%s]', $table, (string) $owner);
            }
            if (! is_array($readers)) {
                $violations[] = "{$table} reporting_readers must be a list";
            }
        }

        foreach ($migrationsByPackage as $package => $migrations) {
            foreach ($migrations as $migration) {
                foreach (self::createdTables($migration['code']) as $table) {
                    $creators[$table][] = "{$package}:{$migration['file']}";

                    if (! isset($tables[$table])) {
                        $violations[] = "{$migration['file']}: table {$table} is not registered";

                        continue;
                    }

                    if ($tables[$table]['owner'] !== $package) {
                        $violations[] = sprintf(
                            '%s: table %s is owned by %s, not %s',
                            $migration['file'],
                            $table,
                            $tables[$table]['owner'],
                            $package,
                        );
                    }
                }
            }
        }

        foreach ($creators as $table => $files) {
            if (count($files) > 1) {
                $violations[] = sprintf('%s is created more than once: %s', $table, implode(', ', $files));
            }
        }

        return $violations;
    }

    /** @param array<int, array|string> $tokens */
    private static function nextSignificantIndex(array $tokens, int $start): int
    {
        $count = count($tokens);
        for ($index = $start; $index < $count; $index++) {
            $token = $tokens[$index];
            if (! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $index;
            }
        }

        return $count;
    }

    /** @param array<int, array|string> $tokens */
    private static function nextSignificantToken(array $tokens, int $start): array|string|null
    {
        return $tokens[self::nextSignificantIndex($tokens, $start)] ?? null;
    }

    /**
     * @param  array<int, array|string>  $tokens
     * @return list<string>
     */
    private static function useNames(array $tokens): array
    {
        $statement = self::tokensToString($tokens);
        $open = strpos($statement, '{');

        if ($open === false) {
            return array_values(array_filter(array_map(
                self::stripAlias(...),
                explode(',', $statement),
            )));
        }

        $prefix = rtrim(trim(substr($statement, 0, $open)), '\\');
        $close = strrpos($statement, '}');
        $members = $close === false ? '' : substr($statement, $open + 1, $close - $open - 1);

        return array_values(array_filter(array_map(
            fn (string $member): string => $prefix.'\\'.ltrim(self::stripAlias($member), '\\'),
            explode(',', $members),
        )));
    }

    /** @param array<int, array|string> $tokens */
    private static function tokensToString(array $tokens): string
    {
        $value = '';
        foreach ($tokens as $token) {
            $value .= is_array($token) ? $token[1] : $token;
        }

        return $value;
    }

    private static function stripAlias(string $name): string
    {
        $parts = preg_split('/\\s+as\\s+/i', trim($name), 2);

        return trim((string) ($parts[0] ?? ''));
    }

    /**
     * @param  list<array{file: string, line: int, referenced: string, package: string}>  $references
     */
    private static function appendReference(array &$references, string $name, string $file, int $line): void
    {
        $normalized = ltrim(trim($name), '\\');
        if (! str_starts_with($normalized, 'Rehla\\')) {
            return;
        }

        $parts = explode('\\', $normalized);
        if (($parts[1] ?? '') === '') {
            return;
        }

        $references[] = [
            'file' => $file,
            'line' => $line,
            'referenced' => $normalized,
            'package' => $parts[1],
        ];
    }

    private static function decodeStringLiteral(string $literal): string
    {
        $quote = $literal[0] ?? '';
        $body = substr($literal, 1, -1);

        if ($quote === "'") {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $body);
        }

        return stripcslashes($body);
    }
}
