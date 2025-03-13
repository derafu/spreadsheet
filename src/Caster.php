<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Spreadsheet;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Derafu\Spreadsheet\Contract\CasterInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Exception;
use Stringable;

/**
 * Casts values to the correct type for writing and reading from a spreadsheet.
 *
 * This class provide de minimum set of methods to cast values to the correct
 * type for writing and reading from a spreadsheet. If you need more methods,
 * you must implement your own caster and inject it in the classes that need it.
 */
final class Caster implements CasterInterface
{
    /**
     * Date formats that can be automatically detected.
     *
     * @var array<string>
     */
    private array $dateFormats = [
        // Most common formats first.
        'Y-m-d',                  // 2024-12-31
        'd/m/Y',                  // 31/12/2024
        'Y-m-d H:i:s',            // 2024-12-31 23:59:59
        'd/m/Y H:i:s',            // 31/12/2024 23:59:59

        // Less common formats last.
        'Y-m-d\TH:i:s',           // 2024-12-31T23:59:59
        'Y-m-d\TH:i:sP',          // 2024-12-31T23:59:59+00:00
    ];

    /**
     * {@inheritDoc}
     */
    public function castAfterLoad(
        SpreadsheetInterface $spreadsheet
    ): SpreadsheetInterface {
        $newSpreadsheet = clone $spreadsheet;

        foreach ($newSpreadsheet->getSheets() as $sheet) {
            $sheet->map(fn (mixed $value) => $this->castValueForReading($value));
        }

        return $newSpreadsheet;
    }

    /**
     * {@inheritDoc}
     */
    public function castBeforeDump(
        SpreadsheetInterface $spreadsheet
    ): SpreadsheetInterface {
        $newSpreadsheet = clone $spreadsheet;

        foreach ($newSpreadsheet->getSheets() as $sheet) {
            $sheet->map(fn (mixed $value) => $this->castValueForWriting($value));
        }

        return $newSpreadsheet;
    }

    /**
     * Cast a value for reading from a spreadsheet.
     *
     * @param mixed $value The value to cast.
     * @return mixed The casted value.
     */
    private function castValueForReading(mixed $value): mixed
    {
        // Null or empty string cast to null.
        if ($value === null || $value === '') {
            return null;
        }

        // If it's already a complex type (object, resource, etc.), return as is
        if (is_object($value) || is_resource($value) || is_array($value)) {
            return $value;
        }

        // Check if it's a boolean, integer or float and return as is.
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        // If it's not a string, return as is.
        if (!is_string($value)) {
            return $value;
        }

        // Check if it's a number (integer or float).
        if (is_numeric($value)) {
            if ((string)(int)$value === $value) {
                return (int)$value;
            }
            return (float)$value;
        }

        // Check if it's a boolean.
        $lower = strtolower($value);
        if ($lower === 'true') {
            return true;
        } elseif ($lower === 'false') {
            return false;
        }

        // Check if it's a DateTime.
        $dateTime = $this->tryParseDateTime($value);
        if ($dateTime !== null) {
            return $dateTime;
        }

        // Check if it's JSON.
        $jsonValue = $this->tryParseJson($value);
        if ($jsonValue !== $value) {
            return $jsonValue;
        }

        // Return as string.
        return $value;
    }

    /**
     * Cast a value for writing to a spreadsheet.
     *
     * @param mixed $value The value to cast.
     * @return mixed The casted value.
     */
    private function castValueForWriting(mixed $value): mixed
    {
        // Null cast to empty string.
        if ($value === null) {
            return '';
        }

        // Check if it's a boolean.
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // Check if it's a DateTime object.
        if ($value instanceof DateTimeInterface) {
            // If time is exactly midnight (00:00:00) and no microseconds,
            // return just the date.
            if ($value->format('H:i:s.u') === '00:00:00.000000') {
                return $value->format('Y-m-d');
            }

            // Otherwise, return ISO 8601 format without microseconds.
            // ISO 8601 format (2024-12-31T23:59:59).
            return $value->format('Y-m-d\TH:i:s');
        }

        // If it's a Stringable object, return its string representation.
        if ($value instanceof Stringable) {
            return (string)$value;
        }

        // Other objects and arrays need serialization.
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        // Numeric values should be preserved.
        if (is_numeric($value)) {
            return $value;
        }

        // Default to string.
        return (string)$value;
    }

    /**
     * Try to parse a string as a date.
     *
     * @param string $value String value to parse.
     * @return DateTimeImmutable|null DateTime object if parsing successful,
     * null otherwise.
     */
    private function tryParseDateTime(string $value): ?DateTimeImmutable
    {
        // Quick check to avoid unnecessary parsing attempts.
        if (strlen($value) < 6 || is_numeric($value)) {
            return null;
        }

        // Try each date format.
        foreach ($this->dateFormats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);

            // Check if the date is valid and the entire string was used.
            if ($date !== false) {
                $errors = $date->getLastErrors();
                if (
                    is_array($errors)
                    && (
                        $errors['warning_count'] > 0
                        || $errors['error_count'] > 0
                    )
                ) {
                    continue;
                }

                // Ensure timezone is set to UTC for consistency.
                $date->setTimezone(new DateTimeZone('UTC'));

                return $date;
            }
        }

        // Try standard date parsing as fallback.
        try {
            // strtotime() handles many common formats.
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return new DateTimeImmutable(
                    '@' . $timestamp,
                    new DateTimeZone('UTC')
                );
            }
        } catch (Exception) {
            // Ignore exceptions from date parsing attempts.
        }

        return null;
    }

    /**
     * Try to parse a string as JSON.
     *
     * @param string $value String value to parse.
     * @return array|string The parsed JSON array or the original string if
     * parsing fails.
     */
    private function tryParseJson(string $value): array|string
    {
        // Quick check to avoid unnecessary parsing attempts.
        if (empty($value) || ($value[0] !== '{' && $value[0] !== '[')) {
            return $value;
        }

        // Try to decode JSON.
        $decoded = json_decode($value, true);

        // If decoded successfully and no errors, return the decoded value.
        if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
            return $decoded;
        }

        // Return original string if parsing fails.
        return $value;
    }
}
