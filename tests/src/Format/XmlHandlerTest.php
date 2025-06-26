<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet\Format;

use Derafu\Spreadsheet\Contract\SpreadsheetCasterInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Exception\SpreadsheetFileNotFoundException;
use Derafu\Spreadsheet\Exception\SpreadsheetLoadException;
use Derafu\Spreadsheet\Format\XmlHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

#[CoversClass(XmlHandler::class)]
#[CoversClass(Spreadsheet::class)]
#[CoversClass(Sheet::class)]
#[CoversClass(SpreadsheetCaster::class)]
final class XmlHandlerTest extends TestCase
{
    private string $fixturesDir;

    private string $tempDir;

    private SpreadsheetCasterInterface $caster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesDir = __DIR__ . '/../../fixtures/xml';
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

    public function testGetExtensionReturnsXml(): void
    {
        $xml = new XmlHandler();
        $this->assertSame('xml', $xml->getExtension());
    }

    public function testGetMimeTypeReturnsApplicationXml(): void
    {
        $xml = new XmlHandler();
        $this->assertSame('application/xml', $xml->getMimeType());
    }

    public function testLoadFromFileThrowsExceptionWhenFileNotFound(): void
    {
        $xml = new XmlHandler();
        $this->expectException(SpreadsheetFileNotFoundException::class);
        $xml->loadFromFile('non_existent_file.xml');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fixtureProvider(): array
    {
        return [
            'flat-array' => ['xml-flat-array'],
            'flat-array-indexed' => ['xml-flat-array-indexed'],
            'multiple-sheets-mixed' => ['xml-multiple-sheets-mixed'],
            'multiple-sheets-indexed' => ['xml-multiple-sheets-indexed'],
            'multiple-sheets-associative' => ['xml-multiple-sheets-associative'],
            'types-casting' => ['xml-types-casting'],
            'edge-cases' => ['xml-edge-cases'],
        ];
    }

    #[DataProvider('fixtureProvider')]
    public function testLoadFromFile(string $fixtureName): void
    {
        $filepath = $this->fixturesDir . '/' . $fixtureName . '.xml';
        if (!file_exists($filepath)) {
            $this->markTestSkipped("Fixture file {$filepath} does not exist.");
        }

        $xml = new XmlHandler();
        $spreadsheet = $xml->loadFromFile($filepath);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Check that we have a valid spreadsheet.
        $this->assertInstanceOf(SpreadsheetInterface::class, $spreadsheet);

        // Check sheet structure based on fixture type.
        $this->assertNotEmpty(
            $spreadsheet->getSheetNames(),
            'Spreadsheet should have at least one sheet'
        );

        // Verify specific structure based on fixture type.
        if ($fixtureName === 'xml-flat-array') {
            $this->assertFlatArrayStructure($spreadsheet);
        } elseif ($fixtureName === 'xml-flat-array-indexed') {
            $this->assertFlatArrayIndexedStructure($spreadsheet);
        } elseif ($fixtureName === 'xml-multiple-sheets-mixed') {
            $this->assertMultipleSheetsMixedStructure($spreadsheet);
        } elseif ($fixtureName === 'xml-multiple-sheets-indexed') {
            $this->assertMultipleSheetsIndexedStructure($spreadsheet);
        } elseif ($fixtureName === 'xml-multiple-sheets-associative') {
            $this->assertMultipleSheetsAssociativeStructure($spreadsheet);
        } elseif ($fixtureName === 'xml-types-casting') {
            $this->assertTypesCastingStructure($spreadsheet);
        } elseif ($fixtureName === 'xml-edge-cases') {
            $this->assertEdgeCasesStructure($spreadsheet);
        }
    }

    public function testLoadFromStringWithInvalidXml(): void
    {
        $invalidXml = '<root><unclosed>';

        $xml = new XmlHandler();
        $this->expectException(SpreadsheetLoadException::class);
        $xml->loadFromString($invalidXml);
    }

    public function testLoadFromString(): void
    {
        $xmlData = '<?xml version="1.0" encoding="UTF-8"?>
        <spreadsheet>
          <sheet name="test">
            <row>
              <name>John</name>
              <age type="integer">30</age>
            </row>
            <row>
              <name>Jane</name>
              <age type="integer">25</age>
            </row>
          </sheet>
        </spreadsheet>';

        $xml = new XmlHandler();
        $spreadsheet = $xml->loadFromString($xmlData);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        // Check sheet.
        $this->assertTrue($spreadsheet->hasSheet('test'));
        $sheet = $spreadsheet->getSheet('test');
        $rows = $sheet->getRows();

        // Check structure and values.
        $this->assertGreaterThanOrEqual(2, count($rows));
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('age', $rows[0]);
        $this->assertSame('John', $rows[0]['name']);
        $this->assertSame(30, $rows[0]['age']);
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
        $xml = new XmlHandler();
        $filepath = $xml->dumpToFile($spreadsheet, $this->tempDir . '/test.xml');

        // Verify file was created.
        $this->assertFileExists($filepath);

        // Check XML is well-formed.
        $this->assertIsObject(new SimpleXMLElement(file_get_contents($filepath)));

        // Read back and verify.
        $loadedSpreadsheet = $xml->loadFromFile($filepath);
        $loadedSpreadsheet = $this->caster->castAfterLoad($loadedSpreadsheet);

        $this->assertTrue($loadedSpreadsheet->hasSheet('test'));
        $sheet = $loadedSpreadsheet->getSheet('test');
        $rows = $sheet->getRows();

        $this->assertGreaterThanOrEqual(2, count($rows));
        $this->assertSame('John', $rows[0]['name']);
        $this->assertSame(30, $rows[0]['age']);
    }

    public function testDumpToFileWithAutoFilePath(): void
    {
        // Create a spreadsheet to write.
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('test', [
            ['col1', 'col2'],
            ['val1', 'val2'],
        ]);

        // Prepare for writing.
        $spreadsheet = $this->caster->castBeforeDump($spreadsheet);

        // Write without specifying path.
        $xml = new XmlHandler();
        $filepath = $xml->dumpToFile($spreadsheet);

        // Verify file was created with auto-generated name.
        $this->assertFileExists($filepath);
        $this->assertStringEndsWith('.xml', $filepath);

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
        ]);

        // Prepare for writing.
        $spreadsheet = $this->caster->castBeforeDump($spreadsheet);

        // Get string.
        $xml = new XmlHandler();
        $xmlString = $xml->dumpToString($spreadsheet);

        // Should be valid XML.
        $this->assertIsObject(new SimpleXMLElement($xmlString));

        // Read back and verify.
        $loadedSpreadsheet = $xml->loadFromString($xmlString);
        $loadedSpreadsheet = $this->caster->castAfterLoad($loadedSpreadsheet);

        $sheet = $loadedSpreadsheet->getSheet('test');
        $rows = $sheet->getRows();

        $this->assertSame(['col1', 'col2', 'col3'], $rows[0]);
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
        $xml = new XmlHandler();
        $xmlString = $xml->dumpToString($spreadsheet);

        // Read back from string.
        $loadedSpreadsheet = $xml->loadFromString($xmlString);
        $loadedSpreadsheet = $this->caster->castAfterLoad($loadedSpreadsheet);

        $sheet = $loadedSpreadsheet->getSheet('test');
        $rows = $sheet->getRows();

        $this->assertSame('John', $rows[0]['name']);
        $this->assertSame(25, $rows[0]['age']);
        $this->assertTrue($rows[0]['active']);
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
        $xml = new XmlHandler();
        $xmlString = $xml->dumpToString($originalSpreadsheet);

        // Read back from string.
        $loadedSpreadsheet = $xml->loadFromString($xmlString);
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

    public function testTypesInAttributesArePreserved(): void
    {
        $xmlData = '<?xml version="1.0" encoding="UTF-8"?>
        <spreadsheet>
          <sheet name="types">
            <row>
              <string>text</string>
              <integer type="integer">123</integer>
              <float type="float">45.67</float>
              <boolean type="boolean">true</boolean>
              <null type="null"/>
              <array type="array">
                <item>1</item>
                <item>2</item>
              </array>
            </row>
          </sheet>
        </spreadsheet>';

        $xml = new XmlHandler();
        $spreadsheet = $xml->loadFromString($xmlData);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('types');
        $rows = $sheet->getRows();

        $this->assertIsString($rows[0]['string']); // string.
        $this->assertIsInt($rows[0]['integer']); // integer.
        $this->assertIsFloat($rows[0]['float']); // float.
        $this->assertIsBool($rows[0]['boolean']); // boolean.
        $this->assertNull($rows[0]['null']); // null.
        $this->assertIsArray($rows[0]['array']); // array.
    }

    public function testCDataHandling(): void
    {
        $xmlData = '<?xml version="1.0" encoding="UTF-8"?>
        <spreadsheet>
          <sheet name="cdata">
            <row>
              <html><![CDATA[<p>This is <strong>HTML</strong> content</p>]]></html>
              <script><![CDATA[function test() { return "Some <script>"; }]]></script>
            </row>
          </sheet>
        </spreadsheet>';

        $xml = new XmlHandler();
        $spreadsheet = $xml->loadFromString($xmlData);

        $sheet = $spreadsheet->getSheet('cdata');
        $rows = $sheet->getRows();

        $this->assertStringContainsString('<strong>', $rows[0]['html']);
        $this->assertStringContainsString('function test()', $rows[0]['script']);

        // Round trip test.
        $outputXml = $xml->dumpToString($spreadsheet);
        $this->assertStringContainsString('CDATA', $outputXml);
    }

    // Helper methods to assert specific fixture structures.

    private function assertFlatArrayStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertNotEmpty($sheetNames);

        $sheet = $spreadsheet->getSheet($sheetNames[0]);
        $rows = $sheet->getRows();

        $this->assertNotEmpty($rows);
        $this->assertTrue($sheet->isAssociative(), "Sheet should be associative");

        // Check for typical columns in flat array fixtures.
        $firstRow = $rows[0];
        $this->assertArrayHasKey('id', $firstRow);
        $this->assertArrayHasKey('name', $firstRow);

        // Verify types.
        $this->assertIsInt($firstRow['id']);
    }

    private function assertFlatArrayIndexedStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertNotEmpty($sheetNames);

        $sheet = $spreadsheet->getSheet($sheetNames[0]);
        $rows = $sheet->getRows();

        $this->assertNotEmpty($rows);
        $this->assertFalse($sheet->isAssociative(), "Sheet should be indexed");

        // First row should be headers.
        $this->assertContains('id', $rows[0]);
        $this->assertContains('name', $rows[0]);
    }

    private function assertMultipleSheetsMixedStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertGreaterThan(1, count($sheetNames));

        // At least one sheet should be associative and one indexed.
        $foundAssociative = false;
        $foundIndexed = false;

        foreach ($sheetNames as $sheetName) {
            $sheet = $spreadsheet->getSheet($sheetName);
            if ($sheet->isAssociative()) {
                $foundAssociative = true;
            } else {
                $foundIndexed = true;
            }
        }

        $this->assertTrue($foundAssociative, "Should have at least one associative sheet");
        $this->assertTrue($foundIndexed, "Should have at least one indexed sheet");
    }

    private function assertMultipleSheetsIndexedStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertGreaterThan(1, count($sheetNames));

        foreach ($sheetNames as $sheetName) {
            $sheet = $spreadsheet->getSheet($sheetName);
            $this->assertFalse($sheet->isAssociative(), "All sheets should be indexed");

            $rows = $sheet->getRows();
            $this->assertNotEmpty($rows);
        }
    }

    private function assertMultipleSheetsAssociativeStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertGreaterThan(1, count($sheetNames));

        foreach ($sheetNames as $sheetName) {
            $sheet = $spreadsheet->getSheet($sheetName);
            $this->assertTrue($sheet->isAssociative(), "All sheets should be associative");

            $rows = $sheet->getRows();
            $this->assertNotEmpty($rows);
        }
    }

    private function assertTypesCastingStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertNotEmpty($sheetNames);

        $sheet = $spreadsheet->getSheet($sheetNames[0]);
        $rows = $sheet->getRows();
        $this->assertNotEmpty($rows);

        // Check for presence of different types
        $firstRow = $rows[0];

        if (isset($firstRow['string'])) {
            $this->assertIsString($firstRow['string']);
        }

        if (isset($firstRow['integer'])) {
            $this->assertIsInt($firstRow['integer']);
        }

        if (isset($firstRow['float'])) {
            $this->assertIsFloat($firstRow['float']);
        }

        if (isset($firstRow['boolean'])) {
            $this->assertIsBool($firstRow['boolean']);
        }

        if (isset($firstRow['null'])) {
            $this->assertNull($firstRow['null']);
        }

        if (isset($firstRow['array'])) {
            $this->assertIsArray($firstRow['array']);
        }
    }

    private function assertEdgeCasesStructure(SpreadsheetInterface $spreadsheet): void
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertNotEmpty($sheetNames);

        // Edge cases might include various special situations.
        // Check for presence of sheets with special content.
        foreach ($sheetNames as $sheetName) {
            $sheet = $spreadsheet->getSheet($sheetName);
            $rows = $sheet->getRows();

            // Empty sheets are valid.
            if (empty($rows)) {
                continue;
            }

            // For non-empty sheets, check first row.
            $firstRow = $rows[0];

            // Special characters might be present in keys or values.
            foreach ($firstRow as $key => $value) {
                // Just verify we can access the values without errors.
                $this->assertNotNull($key);
            }
        }
    }
}
