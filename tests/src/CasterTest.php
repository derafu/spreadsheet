<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet;

use DateTimeImmutable;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpreadsheetCaster::class)]
#[CoversClass(Spreadsheet::class)]
#[CoversClass(Sheet::class)]
final class CasterTest extends TestCase
{
    /**
     * Test for castAfterLoad method.
     */
    public function testcastAfterLoad(): void
    {
        // Create a spreadsheet with various data types in string format (as if
        // read from file)
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->createSheet('test', [
            [
                'string value',          // normal string.
                '',                      // empty string.
                '123',                   // numeric string (int).
                '45.67',                 // numeric string (float).
                'true',                  // boolean as string.
                'false',                 // boolean as string.
                '2025-03-12',            // date as string.
                '2025-03-12T14:30:45',   // datetime as string.
                '["a","b","c"]',         // JSON array as string.
                '{"key":"value"}',        // JSON object as string.
            ],
        ]);

        // Cast for reading.
        $caster = new SpreadsheetCaster();
        $castedSpreadsheet = $caster->castAfterLoad($spreadsheet);
        $castedSheet = $castedSpreadsheet->getSheet('test');
        $this->assertNotNull($castedSheet);
        $castedRow = $castedSheet->getRows()[0];

        // Verify types are correctly cast for reading.
        $this->assertSame('string value', $castedRow[0]); // String stays string.
        $this->assertNull($castedRow[1]); // Empty string becomes null.
        $this->assertSame(123, $castedRow[2]); // Numeric string becomes integer.
        $this->assertSame(45.67, $castedRow[3]); // Numeric string becomes float.
        $this->assertTrue($castedRow[4]); // "true" becomes true.
        $this->assertFalse($castedRow[5]); // "false" becomes false.

        // Date string should be converted to DateTime object.
        $this->assertInstanceOf(DateTimeImmutable::class, $castedRow[6]);
        $this->assertSame('2025-03-12', $castedRow[6]->format('Y-m-d'));

        // DateTime string should be converted to DateTime object.
        $this->assertInstanceOf(DateTimeImmutable::class, $castedRow[7]);
        $this->assertSame('2025-03-12 14:30:45', $castedRow[7]->format('Y-m-d H:i:s'));

        // JSON strings should be converted to their PHP equivalents.
        $this->assertIsArray($castedRow[8]);
        $this->assertSame(['a', 'b', 'c'], $castedRow[8]);

        $this->assertIsArray($castedRow[9]);
        $this->assertArrayHasKey('key', $castedRow[9]);
        $this->assertSame('value', $castedRow[9]['key']);
    }

    /**
     * A date without timezone has no timezone to convert from: it is read as it
     * is written, whatever the default timezone of PHP is. Otherwise loading
     * and dumping a file would move its dates by the offset of the server.
     */
    public function testcastAfterLoadReadsDatesWithoutTimezoneAsWritten(): void
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('America/Santiago');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('dates', [
            [
                '2025-03-12',
                '2025-03-12 23:59:59',
                '2025-03-12T23:59:59',
                '12/03/2025 23:59:59',
                '2025-03-12T23:59:59+03:00',
                // None of the known formats: it is read by the fallback.
                '2025-03-12 23:59',
                'Wed, 12 Mar 2025 23:59:59 +0300',
            ],
        ]);

        try {
            $sheet = (new SpreadsheetCaster())
                ->castAfterLoad($spreadsheet)
                ->getSheet('dates')
            ;
            $this->assertNotNull($sheet);
            $row = $sheet->getRows()[0];
        } finally {
            date_default_timezone_set($timezone);
        }

        // A date without time is the start of that day, not the current time.
        $this->assertInstanceOf(DateTimeImmutable::class, $row[0]);
        $this->assertSame('2025-03-12 00:00:00', $row[0]->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(DateTimeImmutable::class, $row[1]);
        $this->assertSame('2025-03-12 23:59:59', $row[1]->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(DateTimeImmutable::class, $row[2]);
        $this->assertSame('2025-03-12 23:59:59', $row[2]->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(DateTimeImmutable::class, $row[3]);
        $this->assertSame('2025-03-12 23:59:59', $row[3]->format('Y-m-d H:i:s'));
        // With an explicit offset, the instant is respected and shown in UTC.
        $this->assertInstanceOf(DateTimeImmutable::class, $row[4]);
        $this->assertSame('2025-03-12 20:59:59', $row[4]->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(DateTimeImmutable::class, $row[5]);
        $this->assertSame('2025-03-12 23:59:00', $row[5]->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(DateTimeImmutable::class, $row[6]);
        $this->assertSame('2025-03-12 20:59:59', $row[6]->format('Y-m-d H:i:s'));
        foreach ($row as $date) {
            $this->assertInstanceOf(DateTimeImmutable::class, $date);
            $this->assertSame('UTC', $date->getTimezone()->getName());
        }
    }

    public function testcastAfterLoadDetectsDates(): void
    {
        // Create a spreadsheet with various date formats.
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->createSheet('dates', [
            [
                '2025-03-12',
                '2025-03-12 23:59:59',
                '2025-03-12T23:59:59',
                '12/03/2025',
                '12/03/2025 23:59:59',
            ],
        ]);

        // Cast for reading.
        $caster = new SpreadsheetCaster();
        $castedSpreadsheet = $caster->castAfterLoad($spreadsheet);
        $castedSheet = $castedSpreadsheet->getSheet('dates');
        $this->assertNotNull($castedSheet);
        $castedRow = $castedSheet->getRows()[0];

        // Verify dates are correctly detected.
        $this->assertInstanceOf(DateTimeImmutable::class, $castedRow[0]); // ISO date.
        $this->assertInstanceOf(DateTimeImmutable::class, $castedRow[1]); // ISO datetime.
        $this->assertInstanceOf(DateTimeImmutable::class, $castedRow[2]); // ISO 8601.
        $this->assertInstanceOf(DateTimeImmutable::class, $castedRow[3]); // EU format.
        $this->assertInstanceOf(DateTimeImmutable::class, $castedRow[4]); // US format.

        // Check actual date values.
        $this->assertSame('2025-03-12', $castedRow[0]->format('Y-m-d'));
        $this->assertSame('2025-03-12 23:59:59', $castedRow[1]->format('Y-m-d H:i:s'));
        $this->assertSame('2025-03-12 23:59:59', $castedRow[2]->format('Y-m-d H:i:s'));
    }

    public function testcastAfterLoadHandlesNonDates(): void
    {
        // Create a spreadsheet with strings that look like dates but aren't.
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->createSheet('not-dates', [
            ['2023-99-99', 'Date: 2025-03-12', '123456', 'January 1st'],
        ]);

        // Cast for reading.
        $caster = new SpreadsheetCaster();
        $castedSpreadsheet = $caster->castAfterLoad($spreadsheet);
        $castedSheet = $castedSpreadsheet->getSheet('not-dates');
        $this->assertNotNull($castedSheet);
        $castedRow = $castedSheet->getRows()[0];

        // Verify non-dates remain as strings.
        $this->assertSame('2023-99-99', $castedRow[0]); // Invalid date.
        $this->assertSame('Date: 2025-03-12', $castedRow[1]); // Text with date.
        $this->assertSame(123456, $castedRow[2]); // Numeric string becomes int.
        $this->assertInstanceOf(DateTimeImmutable::class, $castedRow[3]); // Text description of date.
    }

    public function testcastBeforeDump(): void
    {
        // Create a date object.
        $date = new DateTimeImmutable('2025-03-12');

        // Create a spreadsheet with various data types.
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->createSheet('test', [
            [
                'string',
                null,
                123,
                45.67,
                true,
                false,
                $date,
                ['a', 'b', 'c'],
                (object)['key' => 'value'],
            ],
        ]);

        // Cast for writing.
        $caster = new SpreadsheetCaster();
        $castedSpreadsheet = $caster->castBeforeDump($spreadsheet);
        $castedSheet = $castedSpreadsheet->getSheet('test');
        $this->assertNotNull($castedSheet);
        $castedRow = $castedSheet->getRows()[0];

        // Verify types are correctly cast for writing.
        $this->assertSame('string', $castedRow[0]); // String stays string.
        $this->assertSame('', $castedRow[1]); // null becomes empty string.
        $this->assertSame(123, $castedRow[2]); // Integer stays integer.
        $this->assertSame(45.67, $castedRow[3]); // Float stays float.
        $this->assertSame('true', $castedRow[4]); // true becomes "true".
        $this->assertSame('false', $castedRow[5]); // false becomes "false".
        $this->assertSame('2025-03-12', $castedRow[6]); // DateTime becomes string.
        $this->assertSame('["a","b","c"]', $castedRow[7]); // Array becomes JSON.
        $this->assertSame('{"key":"value"}', $castedRow[8]); // Object becomes JSON.
    }

    public function testCastingPreservesSheetStructure(): void
    {
        // Create a complex spreadsheet with multiple sheets.
        $spreadsheet = new Spreadsheet();

        // Add an indexed sheet.
        $spreadsheet->createSheet('indexed', [
            ['Header 1', 'Header 2'],
            ['Value 1', 'Value 2'],
        ]);

        // Add an associative sheet.
        $assocSheet = new Sheet('associative', [
            ['key1' => 'Value 1', 'key2' => 'Value 2'],
            ['key1' => 'Value 3', 'key2' => 'Value 4'],
        ], true);
        $spreadsheet->addSheet($assocSheet);

        // Cast for reading.
        $caster = new SpreadsheetCaster();
        $castedSpreadsheet = $caster->castAfterLoad($spreadsheet);

        // Verify sheet structure is preserved.
        $this->assertCount(2, $castedSpreadsheet->getSheets());
        $this->assertTrue($castedSpreadsheet->hasSheet('indexed'));
        $this->assertTrue($castedSpreadsheet->hasSheet('associative'));

        // Verify indexed sheet remains indexed.
        $indexedSheet = $castedSpreadsheet->getSheet('indexed');
        $this->assertNotNull($indexedSheet);
        $this->assertFalse($indexedSheet->isAssociative());

        // Verify associative sheet remains associative.
        $assocSheet = $castedSpreadsheet->getSheet('associative');
        $this->assertNotNull($assocSheet);
        $this->assertTrue($assocSheet->isAssociative());
    }

    /**
     * Test JSON conversion during reading.
     */
    public function testcastAfterLoadConvertsJsonStrings(): void
    {
        // Prepare valid JSON strings that should be converted to arrays.
        $jsonArrayString = '["a","b","c"]';
        $jsonObjectString = '{"key":"value"}';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->createSheet('test', [
            [
                $jsonArrayString,
                $jsonObjectString,
            ],
        ]);

        // Cast for reading.
        $caster = new SpreadsheetCaster();
        $castedSpreadsheet = $caster->castAfterLoad($spreadsheet);
        $castedSheet = $castedSpreadsheet->getSheet('test');
        $this->assertNotNull($castedSheet);
        $castedRow = $castedSheet->getRows()[0];

        // JSON strings should be parsed to arrays/objects
        $this->assertIsArray($castedRow[0]);
        $this->assertSame(['a', 'b', 'c'], $castedRow[0]);

        // JSON object should be converted to an associative array.
        $this->assertIsArray($castedRow[1]);
        $this->assertArrayHasKey('key', $castedRow[1]);
        $this->assertSame('value', $castedRow[1]['key']);
    }

    /**
     * Test invalid JSON handling during reading.
     */
    public function testcastAfterLoadHandlesInvalidJson(): void
    {
        // Prepare invalid JSON strings.
        $invalidJsonString = '{"unclosed":';
        $validButNotJsonString = 'This is just text with {brackets}';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->createSheet('test', [
            [
                $invalidJsonString,
                $validButNotJsonString,
            ],
        ]);

        // Cast for reading.
        $caster = new SpreadsheetCaster();
        $castedSpreadsheet = $caster->castAfterLoad($spreadsheet);
        $castedSheet = $castedSpreadsheet->getSheet('test');
        $this->assertNotNull($castedSheet);
        $castedRow = $castedSheet->getRows()[0];

        // Invalid JSON strings should remain as strings.
        $this->assertSame($invalidJsonString, $castedRow[0]);
        $this->assertSame($validButNotJsonString, $castedRow[1]);
    }
}
