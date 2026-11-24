<?php
declare(strict_types=1);

namespace Rentique\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/** Validate request fields before any coercion from JSON into PHP scalars. */
final class Fields
{
    public const MAX_MONEY = 9007199254740991; // Largest integer exactly representable by the JSON client.

    public static function integer(mixed $value, string $label, int $min = 0, int $max = 2147483647): int
    {
        if (is_string($value) && preg_match('/^-?[0-9]+$/D', $value)) {
            $value = filter_var($value, FILTER_VALIDATE_INT);
        }
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new InvalidArgumentException("$label harus bilangan bulat antara $min dan $max.");
        }
        return $value;
    }

    public static function money(mixed $value, string $label = 'Nominal'): int
    {
        return self::integer($value, $label, 0, self::MAX_MONEY);
    }

    public static function text(mixed $value, string $label, int $max = 255, bool $required = true): string
    {
        if (!is_string($value) || str_contains($value, "\0")) {
            throw new InvalidArgumentException("$label tidak valid.");
        }
        $text = trim($value);
        if (($required && $text === '') || strlen($text) > $max) {
            throw new InvalidArgumentException("$label kosong atau terlalu panjang.");
        }
        return $text;
    }

    public static function date(mixed $value): DateTimeImmutable
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (!$date || $date->format('Y-m-d') !== $value || $date->format('Y') < 1) {
            throw new InvalidArgumentException('Gunakan tanggal yang valid dengan format YYYY-MM-DD.');
        }
        return $date;
    }
}
