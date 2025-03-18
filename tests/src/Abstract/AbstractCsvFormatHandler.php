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
 * Abstract class for CSV format tests.
 */
abstract class AbstractCsvFormatHandler extends TestCase
{
    private string $fixturesDir;

    private string $tempDir;

    private SpreadsheetCasterInterface $caster;

    protected SpreadsheetFormatHandlerInterface $formatHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesDir = __DIR__ . '/../../fixtures/csv';
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

    public function testGetExtensionReturnsCsv(): void
    {
        $csv = $this->formatHandler;
        $this->assertSame('csv', $csv->getExtension());
    }

    public function testGetMimeTypeReturnsTextCsv(): void
    {
        $csv = $this->formatHandler;
        $this->assertSame('text/csv', $csv->getMimeType());
    }

    public function testLoadFromFileThrowsExceptionWhenFileNotFound(): void
    {
        $csv = $this->formatHandler;
        $this->expectException(SpreadsheetFileNotFoundException::class);
        $csv->loadFromFile('non_existent_file.csv');
    }

    public function testLoadFromFileFlatArray(): void
    {
        $filepath = $this->fixturesDir . '/csv-flat-array-products.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Check that we have a valid spreadsheet.
        $this->assertInstanceOf(SpreadsheetInterface::class, $spreadsheet);

        // Check sheet name is based on the file name.
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertCount(1, $sheetNames);

        $this->assertContains('csv-flat-array-products', $sheetNames);

        // Get the sheet and check content.
        $sheet = $spreadsheet->getSheet('csv-flat-array-products');
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
        $filepath = $this->fixturesDir . '/csv-flat-array-indexed-products.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Get the sheet.
        $sheet = $spreadsheet->getSheet('csv-flat-array-indexed-products');
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
        // We'll test with one of the multiple sheets associative files.
        $filepath = $this->fixturesDir . '/csv-multiple-sheets-associative-products.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Get the sheet.
        $sheet = $spreadsheet->getSheet('csv-multiple-sheets-associative-products');

        // Convert to associative for easier testing.
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
    }

    public function testLoadFromFileMultipleSheetsIndexed(): void
    {
        // We'll test with one of the multiple sheets indexed files.
        $filepath = $this->fixturesDir . '/csv-multiple-sheets-indexed-products.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Get the sheet.
        $sheet = $spreadsheet->getSheet('csv-multiple-sheets-indexed-products');
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
    }

    public function testLoadFromFileTypesCasting(): void
    {
        // Test with various type casting fixtures.

        // Test strings casting.
        $filepath = $this->fixturesDir . '/csv-types-casting-strings.csv';
        if (file_exists($filepath)) {
            $csv = $this->formatHandler;
            $spreadsheet = $csv->loadFromFile($filepath);
            $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

            $sheet = $spreadsheet->getSheet('csv-types-casting-strings');
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
        $filepath = $this->fixturesDir . '/csv-types-casting-numbers.csv';
        if (file_exists($filepath)) {
            $csv = $this->formatHandler;
            $spreadsheet = $csv->loadFromFile($filepath);
            $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

            $sheet = $spreadsheet->getSheet('csv-types-casting-numbers');
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
        $filepath = $this->fixturesDir . '/csv-types-casting-booleans.csv';
        if (file_exists($filepath)) {
            $csv = $this->formatHandler;
            $spreadsheet = $csv->loadFromFile($filepath);
            $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

            $sheet = $spreadsheet->getSheet('csv-types-casting-booleans');
            $assocSheet = $sheet->toAssociative();
            $rows = $assocSheet->getRows();

            // Check boolean conversions.
            foreach ($rows as $row) {
                if ($row['type'] === 'true' || $row['type'] === 'string_true') {
                    // After casting, "true" should become boolean true
                    $castedRow = $this->caster->castAfterLoad($spreadsheet)
                        ->getSheet('csv-types-casting-booleans')
                        ->toAssociative()
                        ->getRows();

                    $matchingRow = array_filter($castedRow, fn ($r) => $r['type'] === $row['type']);

                    if (!empty($matchingRow)) {
                        $this->assertTrue(reset($matchingRow)['value']);
                    }
                }
            }
        }
    }

    public function testLoadFromFileEdgeCases(): void
    {
        // Test empty sheet.
        $filepath = $this->fixturesDir . '/csv-edge-cases-empty-sheet.csv';
        if (file_exists($filepath)) {
            $csv = $this->formatHandler;
            $spreadsheet = $csv->loadFromFile($filepath);

            $sheet = $spreadsheet->getSheet('csv-edge-cases-empty-sheet');
            $this->assertSame([[null]], $sheet->getRows());
        }

        // Test sheet with structure but no data.
        $filepath = $this->fixturesDir . '/csv-edge-cases-empty-sheet-with-structure.csv';
        if (file_exists($filepath)) {
            $csv = $this->formatHandler;
            $spreadsheet = $csv->loadFromFile($filepath);

            $sheet = $spreadsheet->getSheet('csv-edge-cases-empty-sheet-with-structure');
            $rows = $sheet->getRows();

            // Should have header row only.
            $this->assertCount(1, $rows);
            $this->assertSame(['id', 'name', 'description'], $rows[0]);
        }

        // Test special characters.
        $filepath = $this->fixturesDir . '/csv-edge-cases-special-characters.csv';
        if (file_exists($filepath)) {
            $csv = $this->formatHandler;
            $spreadsheet = $csv->loadFromFile($filepath);

            $sheet = $spreadsheet->getSheet('csv-edge-cases-special-characters');
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
    }

    public function testLoadFromString(): void
    {
        $csvData = "col1,col2,col3\nvalue1,123,true\nvalue2,456,false";

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromString($csvData);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Check sheet.
        $sheet = $spreadsheet->getSheet('Sheet'); // Default name when loading from string.
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
        $csv = $this->formatHandler;
        $filepath = $csv->dumpToFile($spreadsheet, $this->tempDir . '/write_test.csv');

        // Verify file was created.
        $this->assertFileExists($filepath);

        // Read back the content.
        $content = file_get_contents($filepath);
        $this->assertStringContainsString('name,age,active', $content);
        $this->assertStringContainsString('John,25,true', $content);
        $this->assertStringContainsString('Jane,30,false', $content);
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
        $csv = $this->formatHandler;
        $filepath = $csv->dumpToFile($spreadsheet);

        // Verify file was created with auto-generated name.
        $this->assertFileExists($filepath);
        $this->assertStringEndsWith('.csv', $filepath);

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
        $csv = $this->formatHandler;
        $csvString = $csv->dumpToString($spreadsheet);

        // Verify content.
        $this->assertStringContainsString('col1,col2,col3', $csvString);
        $this->assertStringContainsString('val1,123,true', $csvString);
        $this->assertStringContainsString('val2,456,false', $csvString);
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
        $csv = $this->formatHandler;
        $csvString = $csv->dumpToString($spreadsheet);

        // Split into lines for easier testing.
        $lines = explode("\n", trim($csvString));

        // The first line should be headers.
        $this->assertCount(3, $lines);

        // Check for headers (order might vary).
        $headers = str_getcsv($lines[0]);
        $this->assertCount(3, $headers);
        $this->assertContains('name', $headers);
        $this->assertContains('age', $headers);
        $this->assertContains('active', $headers);

        // Check data rows.
        $this->assertStringContainsString('John', $lines[1]);
        $this->assertStringContainsString('25', $lines[1]);
        $this->assertStringContainsString('true', $lines[1]);
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

        // Write to string.
        $originalSpreadsheet = $this->caster->castBeforeDump($originalSpreadsheet);
        $csv = $this->formatHandler;
        $csvString = $csv->dumpToString($originalSpreadsheet);

        // Read back from string.
        $loadedSpreadsheet = $csv->loadFromString($csvString, 'data');
        $loadedSpreadsheet = $this->caster->castAfterLoad($loadedSpreadsheet);

        // Get the sheet.
        $loadedSheet = $loadedSpreadsheet->getSheet('data');
        $rows = $loadedSheet->getRows();

        // Check types are preserved.
        $this->assertSame('text', $rows[1][0]); // string
        $this->assertSame(123, $rows[1][1]); // integer.
        $this->assertSame(45.67, $rows[1][2]); // float.
        $this->assertTrue($rows[1][3]); // boolean.

        // Note: null values are preserved but represented as empty strings in CSV.
        $this->assertNull($rows[2][0]); // empty string.
        $this->assertSame(0, $rows[2][1]); // zero integer.
        $this->assertSame(0, $rows[2][2]); // zero float will be casted to integer.
        $this->assertFalse($rows[2][3]); // false.
    }

    public function testLoadFromFileMultipleSheetsMixedCustomers(): void
    {
        $filepath = $this->fixturesDir . '/csv-multiple-sheets-mixed-customers.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-multiple-sheets-mixed-customers');
        $rows = $sheet->getRows();

        $this->assertSame(['id', 'name', 'email', 'registered_date', 'orders_count', 'vip'], $rows[0]);
        $this->assertSame(101, $rows[1][0]); // id
        $this->assertSame('John Smith', $rows[1][1]); // name
    }

    public function testLoadFromFileMultipleSheetsMixedOrders(): void
    {
        $filepath = $this->fixturesDir . '/csv-multiple-sheets-mixed-orders.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-multiple-sheets-mixed-orders');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('customer_id', $rows[0]);
        $this->assertArrayHasKey('items', $rows[0]);
    }

    public function testLoadFromFileMultipleSheetsMixedProducts(): void
    {
        $filepath = $this->fixturesDir . '/csv-multiple-sheets-mixed-products.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-multiple-sheets-mixed-products');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('price', $rows[0]);
        $this->assertArrayHasKey('active', $rows[0]);
        $this->assertArrayHasKey('metadata', $rows[0]);
        $this->assertArrayHasKey('stock', $rows[0]['metadata']);
    }

    public function testLoadFromFileMultipleSheetsMixedSettings(): void
    {
        $filepath = $this->fixturesDir . '/csv-multiple-sheets-mixed-settings.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-multiple-sheets-mixed-settings');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        $this->assertArrayHasKey('currency', $rows[0]);
        $this->assertArrayHasKey('tax_rate', $rows[0]);
        $this->assertArrayHasKey('shipping_methods', $rows[0]);
    }

    public function testLoadFromFileMultipleSheetsAssociativeCustomers(): void
    {
        $filepath = $this->fixturesDir . '/csv-multiple-sheets-associative-customers.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-multiple-sheets-associative-customers');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('address', $rows[0]);
    }

    public function testLoadFromFileMultipleSheetsAssociativeOrders(): void
    {
        $filepath = $this->fixturesDir . '/csv-multiple-sheets-associative-orders.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-multiple-sheets-associative-orders');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        $this->assertArrayHasKey('payment_method', $rows[0]);
        $this->assertArrayHasKey('shipping_address', $rows[0]);
    }

    public function testLoadFromFileMultipleSheetsAssociativeSettings(): void
    {
        $filepath = $this->fixturesDir . '/csv-multiple-sheets-associative-settings.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-multiple-sheets-associative-settings');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        $this->assertArrayHasKey('key', $rows[0]);
        $this->assertArrayHasKey('value', $rows[0]);
        $this->assertArrayHasKey('type', $rows[0]);
    }

    public function testLoadFromFileMultipleSheetsIndexedCustomers(): void
    {
        $filepath = $this->fixturesDir . '/csv-multiple-sheets-indexed-customers.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-multiple-sheets-indexed-customers');
        $rows = $sheet->getRows();

        $this->assertSame(['id', 'name', 'email', 'registered_date', 'orders_count', 'vip'], $rows[0]);
    }

    public function testLoadFromFileMultipleSheetsIndexedOrderItems(): void
    {
        $filepath = $this->fixturesDir . '/csv-multiple-sheets-indexed-order-items.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-multiple-sheets-indexed-order-items');
        $rows = $sheet->getRows();

        $this->assertSame(['order_id', 'product_id', 'quantity', 'price', 'subtotal'], $rows[0]);
    }

    public function testLoadFromFileMultipleSheetsIndexedOrders(): void
    {
        $filepath = $this->fixturesDir . '/csv-multiple-sheets-indexed-orders.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-multiple-sheets-indexed-orders');
        $rows = $sheet->getRows();

        $this->assertSame(['id', 'customer_id', 'date', 'total', 'status'], $rows[0]);
    }

    public function testLoadFromFileTypesCastingDates(): void
    {
        $filepath = $this->fixturesDir . '/csv-types-casting-dates.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-types-casting-dates');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        foreach ($rows as $row) {
            if ($row['type'] === 'iso8601' || $row['type'] === 'date_only' || $row['type'] === 'european_format') {
                $this->assertInstanceOf(DateTimeImmutable::class, $row['value']);
            }
        }
    }

    public function testLoadFromFileTypesCastingNullValues(): void
    {
        $filepath = $this->fixturesDir . '/csv-types-casting-null-values.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-types-casting-null-values');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        $nullRow = array_filter($rows, fn ($row) => $row['type'] === 'null');

        if (!empty($nullRow)) {
            $this->assertNull(reset($nullRow)['value']);
        }
    }

    public function testLoadFromFileTypesCastingComplexTypes(): void
    {
        $filepath = $this->fixturesDir . '/csv-types-casting-complex-types.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-types-casting-complex-types');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        foreach ($rows as $row) {
            if ($row['type'] === 'json_string' || $row['type'] === 'array' || $row['type'] === 'nested_object') {
                $this->assertIsArray($row['value']);
            }
        }
    }

    public function testLoadFromFileEdgeCasesDatetimeAdvanced(): void
    {
        $filepath = $this->fixturesDir . '/csv-edge-cases-datetime-advanced.csv';

        $csv = $this->formatHandler;
        $spreadsheet = $csv->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('csv-edge-cases-datetime-advanced');
        $assocSheet = $sheet->toAssociative();
        $rows = $assocSheet->getRows();

        foreach ($rows as $row) {
            $this->assertArrayHasKey('timestamp', $row);
        }
    }
}
