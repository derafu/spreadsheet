<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet\Abstract;

use DateTimeImmutable;
use Derafu\Spreadsheet\Contract\SheetInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetCasterInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetFormatHandlerInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Exception\SpreadsheetFileNotFoundException;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use PHPUnit\Framework\TestCase;

/**
 * Abstract class for spreadsheet formats tests.
 */
abstract class AbstractSpreadsheetFormatHandler extends TestCase
{
    private string $fixturesDir;

    private string $tempDir;

    private SpreadsheetCasterInterface $caster;

    protected SpreadsheetFormatHandlerInterface $formatHandler;

    abstract protected function getFileExtension(): string;

    abstract protected function getMimeType(): string;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesDir = __DIR__ . '/../../fixtures/' . $this->getFileExtension();
        $this->tempDir = sys_get_temp_dir() . '/derafu-spreadsheet-tests';

        // Create temp directory if it doesn't exist.
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        $this->caster = new SpreadsheetCaster();
    }

    protected function tearDown(): void
    {
        // Clean up any files in temp directory.
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testGetExtensionReturnsSpreadsheetExtension(): void
    {
        $this->assertSame($this->getFileExtension(), $this->formatHandler->getExtension());
    }

    public function testGetMimeTypeReturnsSpreadsheetMimeType(): void
    {
        $this->assertSame(
            $this->getMimeType(),
            $this->formatHandler->getMimeType()
        );
    }

    public function testLoadFromFileThrowsExceptionWhenFileNotFound(): void
    {
        $this->expectException(SpreadsheetFileNotFoundException::class);
        $this->formatHandler->loadFromFile('non_existent_file.' . $this->getFileExtension());
    }

    public function testLoadFromFileFlatArray(): void
    {
        $filepath = $this->fixturesDir . '/' . $this->getFileExtension()
            . '-flat-array.' . $this->getFileExtension()
        ;

        $spreadsheet = $this->formatHandler->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Check that we have a valid spreadsheet.
        $this->assertInstanceOf(SpreadsheetInterface::class, $spreadsheet);

        // Check sheets are available
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertNotEmpty($sheetNames);

        // Get the products sheet
        $sheet = $spreadsheet->getSheet('products');
        $this->assertInstanceOf(SheetInterface::class, $sheet);

        // Convert to associative for easier testing.
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        // Check data.
        $this->assertGreaterThan(0, count($rows));
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('price', $rows[0]);
        $this->assertArrayHasKey('active', $rows[0]);

        // Verify specific values.
        $this->assertSame(1, $rows[0]['id']);
        $this->assertSame('Product A', $rows[0]['name']);
        $this->assertSame(29.99, $rows[0]['price']);
        $this->assertTrue($rows[0]['active']);
    }

    public function testLoadFromFileFlatArrayIndexed(): void
    {
        $filepath = $this->fixturesDir . '/' . $this->getFileExtension()
            . '-flat-array-indexed.' . $this->getFileExtension()
        ;

        $spreadsheet = $this->formatHandler->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Get the products sheet
        $sheet = $spreadsheet->getSheet('products');
        $rows = $sheet->getRows();

        // Check header row.
        $this->assertSame(['id', 'name', 'price', 'active', 'created_date', 'notes'], $rows[0]);

        // Check data row.
        $this->assertSame(1, $rows[1][0]); // id as integer.
        $this->assertSame('Product A', $rows[1][1]); // name as string.
        $this->assertSame(29.99, $rows[1][2]); // price as float.
        $this->assertTrue($rows[1][3]); // active as boolean.
        $this->assertInstanceOf(DateTimeImmutable::class, $rows[1][4]); // date.
        $this->assertSame('High demand item', $rows[1][5]); // notes

        // Check another row.
        $this->assertSame(2, $rows[2][0]); // id as integer.
        $this->assertSame('Product B', $rows[2][1]); // name as string.
        $this->assertSame(49.50, $rows[2][2]); // price as float.
        $this->assertFalse($rows[2][3]); // active as boolean.
    }

    public function testLoadFromFileMultipleSheetsAssociative(): void
    {
        $filepath = $this->fixturesDir . '/' . $this->getFileExtension()
            . '-multiple-sheets-associative.' . $this->getFileExtension()
        ;

        $spreadsheet = $this->formatHandler->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Test the products sheet.
        $sheet = $spreadsheet->getSheet('products');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        // Check structure and data.
        $this->assertGreaterThan(0, count($rows));
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('price', $rows[0]);
        $this->assertArrayHasKey('active', $rows[0]);
        $this->assertArrayHasKey('stock', $rows[0]);
        $this->assertArrayHasKey('category', $rows[0]);

        // Verify specific values.
        $this->assertSame(1, $rows[0]['id']);
        $this->assertSame('Product A', $rows[0]['name']);
        $this->assertSame(29.99, $rows[0]['price']);
        $this->assertTrue($rows[0]['active']);
        $this->assertSame(150, $rows[0]['stock']);
        $this->assertSame('Electronics', $rows[0]['category']);

        // Test the customers sheet.
        $sheet = $spreadsheet->getSheet('customers');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('address', $rows[0]);

        // Test the orders sheet.
        $sheet = $spreadsheet->getSheet('orders');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        $this->assertArrayHasKey('payment_method', $rows[0]);
        $this->assertArrayHasKey('shipping_address', $rows[0]);

        // Test the settings sheet.
        $sheet = $spreadsheet->getSheet('settings');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        $this->assertArrayHasKey('key', $rows[0]);
        $this->assertArrayHasKey('value', $rows[0]);
        $this->assertArrayHasKey('type', $rows[0]);
    }

    public function testLoadFromFileMultipleSheetsIndexed(): void
    {
        $filepath = $this->fixturesDir . '/' . $this->getFileExtension()
            . '-multiple-sheets-indexed.' . $this->getFileExtension()
        ;

        $spreadsheet = $this->formatHandler->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Test the products sheet.
        $sheet = $spreadsheet->getSheet('products');
        $rows = $sheet->getRows();

        // Check header row.
        $this->assertSame(['id', 'name', 'price', 'active', 'stock', 'category'], $rows[0]);

        // Check data rows.
        $this->assertSame(1, $rows[1][0]); // id.
        $this->assertSame('Product A', $rows[1][1]); // name.
        $this->assertSame(29.99, $rows[1][2]); // price.
        $this->assertTrue($rows[1][3]); // active.
        $this->assertSame(150, $rows[1][4]); // stock.
        $this->assertSame('Electronics', $rows[1][5]); // category.

        // Test the customers sheet.
        $sheet = $spreadsheet->getSheet('customers');
        $rows = $sheet->getRows();

        $this->assertSame(['id', 'name', 'email', 'registered_date', 'orders_count', 'vip'], $rows[0]);

        // Test the order_items sheet.
        $sheet = $spreadsheet->getSheet('order_items');
        $rows = $sheet->getRows();

        $this->assertSame(['order_id', 'product_id', 'quantity', 'price', 'subtotal'], $rows[0]);

        // Test the orders sheet.
        $sheet = $spreadsheet->getSheet('orders');
        $rows = $sheet->getRows();

        $this->assertSame(['id', 'customer_id', 'date', 'total', 'status'], $rows[0]);
    }

    public function testLoadFromFileMultipleSheetsMixed(): void
    {
        $filepath = $this->fixturesDir . '/' . $this->getFileExtension()
            . '-multiple-sheets-mixed.' . $this->getFileExtension()
        ;

        $spreadsheet = $this->formatHandler->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Test the products sheet.
        $sheet = $spreadsheet->getSheet('products');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('price', $rows[0]);
        $this->assertArrayHasKey('active', $rows[0]);
        $this->assertArrayHasKey('metadata', $rows[0]);
        $this->assertArrayHasKey('stock', $rows[0]['metadata']);

        // Test the customers sheet.
        $sheet = $spreadsheet->getSheet('customers');
        $rows = $sheet->getRows();

        $this->assertSame(['id', 'name', 'email', 'registered_date', 'orders_count', 'vip'], $rows[0]);
        $this->assertSame(101, $rows[1][0]); // id.
        $this->assertSame('John Smith', $rows[1][1]); // name.

        // Test the orders sheet.
        $sheet = $spreadsheet->getSheet('orders');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('customer_id', $rows[0]);
        $this->assertArrayHasKey('items', $rows[0]);

        // Test the settings sheet.
        $sheet = $spreadsheet->getSheet('settings');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        $this->assertArrayHasKey('currency', $rows[0]);
        $this->assertArrayHasKey('tax_rate', $rows[0]);
        $this->assertArrayHasKey('shipping_methods', $rows[0]);
    }

    public function testLoadFromFileTypesCasting(): void
    {
        $filepath = $this->fixturesDir . '/' . $this->getFileExtension()
            . '-types-casting.' . $this->getFileExtension()
        ;

        $spreadsheet = $this->formatHandler->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Test string casting.
        if ($spreadsheet->hasSheet('strings')) {
            $sheet = $spreadsheet->getSheet('strings');
            $assocSheet = $sheet->toAssociative();
            $rows = $assocSheet->getRows();

            // Check specific type conversions for strings.
            foreach ($rows as $row) {
                if ($row['type'] === 'empty_string') {
                    $this->assertNull($row['value']);
                } elseif ($row['type'] === 'text') {
                    $this->assertSame('Simple text', $row['value']);
                } elseif ($row['type'] === 'looks_like_number') {
                    $this->assertSame(123, $row['value']);
                }
            }
        }

        // Test numbers casting.
        if ($spreadsheet->hasSheet('numbers')) {
            $sheet = $spreadsheet->getSheet('numbers');
            $assocSheet = $sheet->toAssociative();
            $rows = $assocSheet->getRows();

            // After casting, numbers should be properly typed.
            foreach ($rows as $row) {
                if ($row['type'] === 'integer') {
                    $this->assertIsNumeric($row['value']);
                } elseif ($row['type'] === 'float') {
                    $this->assertIsNumeric($row['value']);
                }
            }
        }

        // Test booleans casting.
        if ($spreadsheet->hasSheet('booleans')) {
            $sheet = $spreadsheet->getSheet('booleans');
            $assocSheet = $sheet->toAssociative();
            $rows = $assocSheet->getRows();

            // Check boolean conversions.
            foreach ($rows as $row) {
                if ($row['type'] === 'true' || $row['type'] === 'string_true') {
                    // After casting, "true" should become boolean true.
                    $castedRow = $this->caster->castAfterLoad($spreadsheet)
                        ->getSheet('booleans')
                        ->toAssociative()
                        ->getRows();

                    $matchingRow = array_filter($castedRow, fn ($r) => $r['type'] === $row['type']);

                    if (!empty($matchingRow)) {
                        $this->assertTrue(reset($matchingRow)['value']);
                    }
                }
            }
        }

        // Test dates casting.
        if ($spreadsheet->hasSheet('dates')) {
            $sheet = $spreadsheet->getSheet('dates');
            $assocSheet = $sheet->toAssociative();
            $rows = $assocSheet->getRows();

            foreach ($rows as $row) {
                if ($row['type'] === 'iso8601' || $row['type'] === 'date_only' || $row['type'] === 'european_format') {
                    $this->assertInstanceOf(DateTimeImmutable::class, $row['value']);
                }
            }
        }

        // Test null values casting.
        if ($spreadsheet->hasSheet('null-values')) {
            $sheet = $spreadsheet->getSheet('null-values');
            $assocSheet = $sheet->toAssociative();
            $rows = $assocSheet->getRows();

            $nullRow = array_filter($rows, fn ($row) => $row['type'] === 'null');

            if (!empty($nullRow)) {
                $this->assertNull(reset($nullRow)['value']);
            }
        }

        // Test complex types casting.
        if ($spreadsheet->hasSheet('complex-types')) {
            $sheet = $spreadsheet->getSheet('complex-types');
            $assocSheet = $sheet->toAssociative();
            $rows = $assocSheet->getRows();

            foreach ($rows as $row) {
                if ($row['type'] === 'json_string' || $row['type'] === 'array' || $row['type'] === 'nested_object') {
                    $this->assertIsArray($row['value']);
                }
            }
        }
    }

    public function testLoadFromFileEdgeCases(): void
    {
        $filepath = $this->fixturesDir . '/' . $this->getFileExtension()
            . '-edge-cases.' . $this->getFileExtension()
        ;

        $spreadsheet = $this->formatHandler->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Test empty sheet.
        if ($spreadsheet->hasSheet('empty-sheet')) {
            $sheet = $spreadsheet->getSheet('empty-sheet');
            $this->assertSame([[null]], $sheet->getRows());
        }

        // Test sheet with structure but no data.
        if ($spreadsheet->hasSheet('empty-sheet-with-structure')) {
            $sheet = $spreadsheet->getSheet('empty-sheet-with-structure');
            $rows = $sheet->getRows();

            // Should have header row only.
            $this->assertCount(1, $rows);
            $this->assertSame(['id', 'name', 'description'], $rows[0]);
        }

        // Test special characters.
        if ($spreadsheet->hasSheet('special-characters')) {
            $sheet = $spreadsheet->getSheet('special-characters');
            $assocSheet = $sheet->toAssociative();
            $rows = $assocSheet->getRows();

            // Check for special characters handling.
            foreach ($rows as $row) {
                if ($row['id'] == 1) {
                    $this->assertStringContainsString('quotes', $row['name']); // Contains quotes.
                } elseif ($row['id'] == 4) {
                    $this->assertStringContainsString('Français', $row['name']); // Contains accented characters.
                } elseif ($row['id'] == 5) {
                    $this->assertStringContainsString('продукт', $row['name']); // Contains non-Latin characters.
                } elseif ($row['id'] == 7) {
                    $this->assertStringContainsString('🚀', $row['name']); // Contains emoji.
                }
            }
        }

        // Test datetime advanced.
        if ($spreadsheet->hasSheet('datetime-advanced')) {
            $sheet = $spreadsheet->getSheet('datetime-advanced');
            $assocSheet = $sheet->toAssociative();
            $rows = $assocSheet->getRows();

            foreach ($rows as $row) {
                $this->assertArrayHasKey('timestamp', $row);
            }
        }
    }

    public function testLoadFromString(): void
    {
        // Create a spreadsheet.
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('Sheet', [
            ['col1', 'col2', 'col3'],
            ['value1', 123, true],
            ['value2', 456, false],
        ]);

        // Convert to binary string.
        $spreadsheet = $this->caster->castBeforeDump($spreadsheet);
        $data = $this->formatHandler->dumpToString($spreadsheet);

        // Load back from string.
        $loadedSpreadsheet = $this->formatHandler->loadFromString($data);
        $loadedSpreadsheet = $this->caster->castAfterLoad($loadedSpreadsheet);

        // Check sheet.
        $sheet = $loadedSpreadsheet->getSheet('Sheet');
        $rows = $sheet->getRows();

        // Check structure and values.
        $this->assertCount(3, $rows);
        $this->assertSame(['col1', 'col2', 'col3'], $rows[0]);
        $this->assertSame('value1', $rows[1][0]);
        $this->assertSame(123, $rows[1][1]);
        $this->assertTrue($rows[1][2]);
    }

    public function testDumpToFile(): void
    {
        // Create a spreadsheet to write.
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->createSheet('test', [
            ['name', 'age', 'active'],
            ['John', 25, true],
            ['Jane', 30, false],
        ]);

        // Prepare for writing.
        $spreadsheet = $this->caster->castBeforeDump($spreadsheet);

        // Write using custom handler.
        $filepath = $this->formatHandler->dumpToFile(
            $spreadsheet,
            $this->tempDir . '/write_test.' . $this->getFileExtension()
        );

        // Verify file was created.
        $this->assertFileExists($filepath);

        // Read back and verify content.
        $loadedSpreadsheet = $this->formatHandler->loadFromFile($filepath);
        $loadedSpreadsheet = $this->caster->castAfterLoad($loadedSpreadsheet);

        $sheet = $loadedSpreadsheet->getSheet('test');
        $rows = $sheet->getRows();

        $this->assertSame(['name', 'age', 'active'], $rows[0]);
        $this->assertSame('John', $rows[1][0]);
        $this->assertSame(25, $rows[1][1]);
        $this->assertTrue($rows[1][2]);
        $this->assertSame('Jane', $rows[2][0]);
        $this->assertSame(30, $rows[2][1]);
        $this->assertFalse($rows[2][2]);
    }

    public function testDumpToFileWithAutoFilePath(): void
    {
        // Create a spreadsheet to write.
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->createSheet('test', [
            ['col1', 'col2'],
            ['val1', 'val2'],
        ]);

        // Prepare for writing.
        $spreadsheet = $this->caster->castBeforeDump($spreadsheet);

        // Write without specifying path.
        $filepath = $this->formatHandler->dumpToFile($spreadsheet);

        // Verify file was created with auto-generated name.
        $this->assertFileExists($filepath);
        $this->assertStringEndsWith('.' . $this->getFileExtension(), $filepath);

        // Clean up auto-generated file.
        unlink($filepath);
    }

    public function testDumpToString(): void
    {
        // Create a spreadsheet with test data.
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('test', [
            ['col1', 'col2', 'col3'],
            ['val1', 123, true],
            ['val2', 456, false],
        ]);

        // Prepare for writing.
        $spreadsheet = $this->caster->castBeforeDump($spreadsheet);

        // Get string.
        $data = $this->formatHandler->dumpToString($spreadsheet);

        // The output is binary so we can't check the content directly.
        // Instead, we can load it back and verify.
        $loadedSpreadsheet = $this->formatHandler->loadFromString($data);
        $loadedSpreadsheet = $this->caster->castAfterLoad($loadedSpreadsheet);

        $sheet = $loadedSpreadsheet->getSheet('test');
        $rows = $sheet->getRows();

        $this->assertSame(['col1', 'col2', 'col3'], $rows[0]);
        $this->assertSame('val1', $rows[1][0]);
        $this->assertSame(123, $rows[1][1]);
        $this->assertTrue($rows[1][2]);
        $this->assertSame('val2', $rows[2][0]);
        $this->assertSame(456, $rows[2][1]);
        $this->assertFalse($rows[2][2]);
    }

    public function testDumpToStringWithAssociativeSheet(): void
    {
        // Create a spreadsheet with an associative sheet.
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->createSheet('test', [
            ['name' => 'John', 'age' => 25, 'active' => true],
            ['name' => 'Jane', 'age' => 30, 'active' => false],
        ], true);

        // Prepare for writing.
        $spreadsheet = $this->caster->castBeforeDump($spreadsheet);

        // Get string.
        $data = $this->formatHandler->dumpToString($spreadsheet);

        // Load it back and verify.
        $loadedSpreadsheet = $this->formatHandler->loadFromString($data);
        $loadedSpreadsheet = $this->caster->castAfterLoad($loadedSpreadsheet);

        $sheet = $loadedSpreadsheet->getSheet('test');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        // Check keys and values
        $this->assertCount(2, $rows);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('age', $rows[0]);
        $this->assertArrayHasKey('active', $rows[0]);

        $this->assertContains('John', [$rows[0]['name'], $rows[1]['name']]);
        $this->assertContains('Jane', [$rows[0]['name'], $rows[1]['name']]);
        $this->assertContains(25, [$rows[0]['age'], $rows[1]['age']]);
        $this->assertContains(30, [$rows[0]['age'], $rows[1]['age']]);
        $this->assertContains(true, [$rows[0]['active'], $rows[1]['active']]);
        $this->assertContains(false, [$rows[0]['active'], $rows[1]['active']]);
    }

    public function testRoundTripPreservingTypes(): void
    {
        // Create a spreadsheet with various data types.
        $originalSpreadsheet = new Spreadsheet();
        $originalSheet = $originalSpreadsheet->createSheet('data', [
            ['string', 'integer', 'float', 'boolean', 'null'],
            ['text', 123, 45.67, true, null],
            ['', 0, 0.0, false, null],
        ]);

        // Write to file.
        $originalSpreadsheet = $this->caster->castBeforeDump($originalSpreadsheet);
        $filepath = $this->formatHandler->dumpToFile(
            $originalSpreadsheet,
            $this->tempDir . '/round_trip.' . $this->getFileExtension()
        );

        // Read back from file.
        $loadedSpreadsheet = $this->formatHandler->loadFromFile($filepath);
        $loadedSpreadsheet = $this->caster->castAfterLoad($loadedSpreadsheet);

        // Get the sheet.
        $loadedSheet = $loadedSpreadsheet->getSheet('data');
        $rows = $loadedSheet->getRows();

        // Check types are preserved.
        $this->assertSame('text', $rows[1][0]); // string.
        $this->assertSame(123, $rows[1][1]); // integer.
        $this->assertSame(45.67, $rows[1][2]); // float.
        $this->assertTrue($rows[1][3]); // boolean.
        $this->assertNull($rows[1][4]); // null.

        // Check empty/zero values.
        $this->assertNull($rows[2][0]); // empty string.
        $this->assertSame(0, $rows[2][1]); // zero integer.
        $this->assertSame(0, $rows[2][2]); // zero float will be casted to integer.
        $this->assertFalse($rows[2][3]); // false.
        $this->assertNull($rows[2][4]); // null.
    }
}
