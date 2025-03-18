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

use Derafu\Spreadsheet\Contract\SpreadsheetCasterInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetFormatHandlerInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Exception\SpreadsheetFileNotFoundException;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Base test class for array format handlers (JSON, YAML).
 */
abstract class AbstractArrayFormatHandler extends TestCase
{
    protected string $tempDir;

    protected SpreadsheetCasterInterface $caster;

    abstract protected function getFormatHandler(): SpreadsheetFormatHandlerInterface;

    abstract protected function getFixtureDir(): string;

    abstract protected function getFileExtension(): string;

    protected function setUp(): void
    {
        parent::setUp();
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

    public function testLoadFromFileThrowsExceptionWhenFileNotFound(): void
    {
        $handler = $this->getFormatHandler();
        $this->expectException(SpreadsheetFileNotFoundException::class);
        $handler->loadFromFile('non_existent_file.' . $this->getFileExtension());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fixtureProvider(): array
    {
        return [
            'flat-array' => ['flat-array'],
            'flat-array-indexed' => ['flat-array-indexed'],
            'multiple-sheets-mixed' => ['multiple-sheets-mixed'],
            'multiple-sheets-indexed' => ['multiple-sheets-indexed'],
            'multiple-sheets-associative' => ['multiple-sheets-associative'],
            'types-casting' => ['types-casting'],
            'edge-cases' => ['edge-cases'],
        ];
    }

    #[DataProvider('fixtureProvider')]
    public function testLoadFromFile(string $fixtureName): void
    {
        $filepath = $this->getFixtureDir() . '/' . $this->getFileExtension()
            . '-' . $fixtureName . '.' . $this->getFileExtension()
        ;
        if (!file_exists($filepath)) {
            $this->markTestSkipped("Fixture file {$filepath} does not exist.");
        }

        $handler = $this->getFormatHandler();
        $spreadsheet = $handler->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Check that we have a valid spreadsheet.
        $this->assertInstanceOf(SpreadsheetInterface::class, $spreadsheet);

        // Check specific fixture types.
        if ($fixtureName === 'flat-array') {
            $this->assertFlatArrayStructure($spreadsheet);
        } elseif ($fixtureName === 'flat-array-indexed') {
            $this->assertFlatArrayIndexedStructure($spreadsheet);
        } elseif ($fixtureName === 'multiple-sheets-mixed') {
            $this->assertMultipleSheetsMixedStructure($spreadsheet);
        } elseif ($fixtureName === 'multiple-sheets-indexed') {
            $this->assertMultipleSheetsIndexedStructure($spreadsheet);
        } elseif ($fixtureName === 'multiple-sheets-associative') {
            $this->assertMultipleSheetsAssociativeStructure($spreadsheet);
        } elseif ($fixtureName === 'types-casting') {
            $this->assertTypesCastingStructure($spreadsheet);
        } elseif ($fixtureName === 'edge-cases') {
            $this->assertEdgeCasesStructure($spreadsheet);
        }
    }

    public function testLoadFromString(): void
    {
        // Simple case.
        $data = '{"sheet1":[{"name":"John","age":30},{"name":"Jane","age":25}]}';

        $handler = $this->getFormatHandler();
        $spreadsheet = $handler->loadFromString($data);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Check sheet.
        $this->assertTrue($spreadsheet->hasSheet('sheet1'));
        $sheet = $spreadsheet->getSheet('sheet1');
        $rows = $sheet->getRows();

        // Check structure and values.
        $this->assertGreaterThanOrEqual(2, count($rows));
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('age', $rows[0]);
    }

    public function testDumpToFile(): void
    {
        // Create a spreadsheet to write.
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->createSheet('test', [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ], true);

        // Prepare for writing.
        $spreadsheet = $this->caster->castBeforeDump($spreadsheet);

        // Write using handler.
        $handler = $this->getFormatHandler();
        $filepath = $handler->dumpToFile($spreadsheet, $this->tempDir . '/test.' . $this->getFileExtension());

        // Verify file was created.
        $this->assertFileExists($filepath);

        // Read back and verify.
        $loadedSpreadsheet = $handler->loadFromFile($filepath);
        $loadedSpreadsheet = $this->caster->castAfterLoad($loadedSpreadsheet);

        $this->assertTrue($loadedSpreadsheet->hasSheet('test'));
        $sheet = $loadedSpreadsheet->getSheet('test');
        $rows = $sheet->getRows();

        $this->assertGreaterThanOrEqual(2, count($rows));
        $this->assertSame('John', $rows[0]['name']);
        $this->assertSame(30, $rows[0]['age']);
    }

    public function testDumpToString(): void
    {
        // Create a spreadsheet with test data.
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('test', [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ], true);

        // Prepare for writing.
        $spreadsheet = $this->caster->castBeforeDump($spreadsheet);

        // Get string.
        $handler = $this->getFormatHandler();
        $outputString = $handler->dumpToString($spreadsheet);

        // Read back from string.
        $loadedSpreadsheet = $handler->loadFromString($outputString, 'test');
        $loadedSpreadsheet = $this->caster->castAfterLoad($loadedSpreadsheet);

        $this->assertTrue($loadedSpreadsheet->hasSheet('test'));
        $sheet = $loadedSpreadsheet->getSheet('test');
        $rows = $sheet->getRows();

        $this->assertGreaterThanOrEqual(2, count($rows));
        $this->assertSame('John', $rows[0]['name']);
        $this->assertSame(30, $rows[0]['age']);
    }

    public function testRoundTripPreservingTypes(): void
    {
        // Create a spreadsheet with various data types.
        $originalSpreadsheet = new Spreadsheet();
        $originalSheet = $originalSpreadsheet->createSheet('data', [
            ['string' => 'text', 'integer' => 123, 'float' => 45.67, 'boolean' => true, 'null' => null],
            ['string' => '', 'integer' => 0, 'float' => 0.0, 'boolean' => false, 'null' => null],
        ], true);

        // Write to string.
        $originalSpreadsheet = $this->caster->castBeforeDump($originalSpreadsheet);
        $handler = $this->getFormatHandler();
        $outputString = $handler->dumpToString($originalSpreadsheet);

        // Read back from string.
        $loadedSpreadsheet = $handler->loadFromString($outputString, 'data');
        $loadedSpreadsheet = $this->caster->castAfterLoad($loadedSpreadsheet);

        // Get the sheet.
        $loadedSheet = $loadedSpreadsheet->getSheet('data');
        $rows = $loadedSheet->getRows();

        // Check types are preserved.
        $this->assertSame('text', $rows[0]['string']); // string.
        $this->assertSame(123, $rows[0]['integer']); // integer.
        $this->assertSame(45.67, $rows[0]['float']); // float.
        $this->assertTrue($rows[0]['boolean']); // boolean.
        $this->assertNull($rows[0]['null']); // null.

        $this->assertNull($rows[1]['string']); // empty string.
        $this->assertSame(0, $rows[1]['integer']); // zero integer.
        $this->assertSame(0.0, $rows[1]['float'] + 0.0); // zero float.
        $this->assertFalse($rows[1]['boolean']); // false.
        $this->assertNull($rows[1]['null']); // null.
    }

    // Helper methods to assert specific fixture structures.

    protected function assertFlatArrayStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertNotEmpty($sheetNames);

        $sheet = $spreadsheet->getSheet($sheetNames[0]);
        $rows = $sheet->getRows();

        $this->assertNotEmpty($rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('price', $rows[0]);
        $this->assertArrayHasKey('active', $rows[0]);

        $this->assertSame('Product A', $rows[0]['name']);
        $this->assertTrue($rows[0]['active']);
    }

    protected function assertFlatArrayIndexedStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertNotEmpty($sheetNames);

        $sheet = $spreadsheet->getSheet($sheetNames[0]);
        $rows = $sheet->getRows();

        $this->assertNotEmpty($rows);
        $this->assertSame(['id', 'name', 'price', 'active', 'created_date', 'notes'], $rows[0]);
        $this->assertSame(1, $rows[1][0]); // id.
        $this->assertSame('Product A', $rows[1][1]); // name.
    }

    protected function assertMultipleSheetsMixedStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertGreaterThan(1, count($sheetNames));

        // Check for products sheet (associative).
        $this->assertTrue($spreadsheet->hasSheet('products'));
        $productsSheet = $spreadsheet->getSheet('products');
        $productsRows = $productsSheet->getRows();

        $this->assertNotEmpty($productsRows);
        $this->assertArrayHasKey('id', $productsRows[0]);
        $this->assertArrayHasKey('name', $productsRows[0]);

        // Check for customers sheet (indexed).
        $this->assertTrue($spreadsheet->hasSheet('customers'));
        $customersSheet = $spreadsheet->getSheet('customers');
        $customersRows = $customersSheet->getRows();

        $this->assertNotEmpty($customersRows);
        $this->assertContains('id', $customersRows[0]);
        $this->assertContains('name', $customersRows[0]);
    }

    protected function assertMultipleSheetsIndexedStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertGreaterThan(1, count($sheetNames));

        foreach ($sheetNames as $sheetName) {
            $sheet = $spreadsheet->getSheet($sheetName);
            $rows = $sheet->getRows();

            // All sheets should have header rows.
            $this->assertNotEmpty($rows);
            $this->assertIsArray($rows[0]);
        }
    }

    protected function assertMultipleSheetsAssociativeStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertGreaterThan(1, count($sheetNames));

        foreach ($sheetNames as $sheetName) {
            $sheet = $spreadsheet->getSheet($sheetName);
            $rows = $sheet->getRows();

            // All rows should be associative arrays.
            $this->assertNotEmpty($rows);
            $this->assertIsArray($rows[0]);

            // Get first array key to verify it's a string (associative).
            $keys = array_keys($rows[0]);
            $this->assertIsString($keys[0]);
        }
    }

    protected function assertTypesCastingStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertNotEmpty($sheetNames);

        // Check for various type sheets.
        if ($spreadsheet->hasSheet('strings')) {
            $stringsSheet = $spreadsheet->getSheet('strings');
            $stringsRows = $stringsSheet->getRows();
            $this->assertNotEmpty($stringsRows);
            $this->assertArrayHasKey('type', $stringsRows[0]);
            $this->assertArrayHasKey('value', $stringsRows[0]);
        }

        if ($spreadsheet->hasSheet('numbers')) {
            $numbersSheet = $spreadsheet->getSheet('numbers');
            $numbersRows = $numbersSheet->getRows();
            $this->assertNotEmpty($numbersRows);
            $this->assertArrayHasKey('type', $numbersRows[0]);
            $this->assertArrayHasKey('value', $numbersRows[0]);
            $this->assertIsNumeric($numbersRows[0]['value']);
        }

        if ($spreadsheet->hasSheet('booleans')) {
            $booleansSheet = $spreadsheet->getSheet('booleans');
            $booleansRows = $booleansSheet->getRows();
            $this->assertNotEmpty($booleansRows);
            $this->assertArrayHasKey('type', $booleansRows[0]);
            $this->assertArrayHasKey('value', $booleansRows[0]);
            $this->assertIsBool($booleansRows[0]['value']);
        }
    }

    protected function assertEdgeCasesStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertNotEmpty($sheetNames);

        // Check for empty sheet.
        if ($spreadsheet->hasSheet('empty_sheet')) {
            $emptySheet = $spreadsheet->getSheet('empty_sheet');
            $this->assertCount(0, $emptySheet->getRows());
        }

        // Check for special characters.
        if ($spreadsheet->hasSheet('special_characters')) {
            $specialCharsSheet = $spreadsheet->getSheet('special_characters');
            $specialCharsRows = $specialCharsSheet->getRows();
            $this->assertNotEmpty($specialCharsRows);

            foreach ($specialCharsRows as $row) {
                if (isset($row['id']) && $row['id'] == 1) {
                    $this->assertStringContainsString('quotes', $row['name']); // Contains quotes.
                }
            }
        }
    }
}
