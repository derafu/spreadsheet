<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet;

use Derafu\Spreadsheet\Abstract\AbstractPhpSpreadsheetFormatHandler;
use Derafu\Spreadsheet\Exception\SpreadsheetDumpException;
use Derafu\Spreadsheet\Format\CsvLeagueHandler;
use Derafu\Spreadsheet\Format\JsonHandler;
use Derafu\Spreadsheet\Format\OdsHandler;
use Derafu\Spreadsheet\Format\XlsHandler;
use Derafu\Spreadsheet\Format\XlsxHandler;
use Derafu\Spreadsheet\Format\XmlHandler;
use Derafu\Spreadsheet\Format\YamlHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use Derafu\Spreadsheet\SpreadsheetDumper;
use Derafu\Spreadsheet\SpreadsheetFactory;
use Derafu\Spreadsheet\SpreadsheetLoader;
use Derafu\Spreadsheet\Translation\SpreadsheetTranslationResourceProvider;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use stdClass;

#[CoversClass(AbstractPhpSpreadsheetFormatHandler::class)]
#[CoversClass(SpreadsheetDumper::class)]
#[CoversClass(XmlHandler::class)]
#[CoversClass(JsonHandler::class)]
#[CoversClass(YamlHandler::class)]
#[UsesClass(Sheet::class)]
#[UsesClass(Spreadsheet::class)]
#[UsesClass(SpreadsheetCaster::class)]
#[UsesClass(SpreadsheetFactory::class)]
#[UsesClass(SpreadsheetLoader::class)]
#[UsesClass(CsvLeagueHandler::class)]
#[UsesClass(XlsxHandler::class)]
#[UsesClass(XlsHandler::class)]
#[UsesClass(OdsHandler::class)]
#[UsesClass(SpreadsheetTranslationResourceProvider::class)]
final class ExportCoordinatesAndPathsTest extends TestCase
{
    public function testExcelSheetsWithApostrophesInTheirNamesAreNotLostDuringImport(): void
    {
        $source = new Spreadsheet();
        foreach (['Products', "'Quoted'", "'Leading", "Trailing'"] as $index => $name) {
            $source->createSheet($name, [['name', 'value'], [$name, 'Row ' . $index]]);
        }
        $expected = $source->toArray();
        $handler = new XlsxHandler();

        $loaded = $handler->loadFromString($handler->dumpToString($source));

        $this->assertSame($source->getSheetNames(), $loaded->getSheetNames());
        $this->assertSame($expected, $loaded->toArray());
        $path = tempnam(sys_get_temp_dir(), 'spreadsheet-sheet-names-');
        $this->assertNotFalse($path);
        try {
            $handler->dumpToFile($source, $path);
            $loadedFromFile = $handler->loadFromFile($path);
            $this->assertSame($source->getSheetNames(), $loadedFromFile->getSheetNames());
            $this->assertSame($expected, $loadedFromFile->toArray());
        } finally {
            unlink($path);
        }
        $this->assertSame($expected, $source->toArray());
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_OLE_INSTANCES']);
        parent::tearDown();
    }

    /** @return iterable<string, array{int|string}> */
    public static function invalidColumnIndices(): iterable
    {
        yield 'column name on an indexed sheet' => ['name'];
        yield 'numeric string that PHP keeps as a string' => ['01'];
        yield 'negative integer' => [-1];
    }

    #[DataProvider('invalidColumnIndices')]
    public function testInvalidColumnIndicesAreTranslatableErrors(int|string $column): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('Records', [[$column => 'Value']]);

        try {
            (new XlsxHandler())->dumpToString($spreadsheet);
            $this->fail('An invalid indexed column must be rejected.');
        } catch (SpreadsheetDumpException $exception) {
            $this->assertSame("Column index \"$column\" at row \"0\" in sheet \"Records\" must be a non-negative integer.", $exception->getMessage());
            $translator = TranslatorFactory::create('es', resourceProviders: [new SpreadsheetTranslationResourceProvider()]);
            $this->assertSame("El índice de columna \"$column\" en la fila \"0\" de la hoja \"Records\" debe ser un entero no negativo.", $exception->trans($translator));
        }
    }

    public function testSparseIntegerColumnIndicesKeepTheirExcelPositions(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->createSheet('Records', [[0 => 'Column A', 2 => 'Column C']]);
        $handler = new XlsxHandler();

        $loaded = $handler->loadFromString($handler->dumpToString($spreadsheet));

        $this->assertSame([['Column A', null, 'Column C']], $loaded->getActiveSheet()->getRows());
        $this->assertSame([[0 => 'Column A', 2 => 'Column C']], $sheet->getRows());
    }

    public function testAssociativeColumnNamesAreConvertedToPositionsBeforeExporting(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('Records', [['name' => 'Laptop', 2025 => 100]], true);
        $handler = new XlsxHandler();

        $loaded = $handler->loadFromString($handler->dumpToString($spreadsheet));

        $this->assertSame([['name', '2025'], ['Laptop', '100']], $loaded->getActiveSheet()->getRows());
        $this->assertTrue($spreadsheet->getActiveSheet()->isAssociative());
    }

    /** @return iterable<string, array{string}> */
    public static function automaticPathFormats(): iterable
    {
        foreach (['csv', 'json', 'yaml', 'xml', 'xlsx', 'xls', 'ods'] as $format) {
            yield $format => [$format];
        }
    }

    private function removeGeneratedFile(string $path): void
    {
        unlink($path);
        // Existing handlers append an extension to the file created by tempnam.
        $base = substr($path, 0, -strlen(pathinfo($path, PATHINFO_EXTENSION)) - 1);
        if (is_file($base) && filesize($base) === 0) {
            unlink($base);
        }
    }

    #[DataProvider('automaticPathFormats')]
    public function testAnOmittedPathIsGeneratedByTheRequestedFormatHandler(string $format): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('Records', [[true, false, 12]]);
        $original = $spreadsheet->toArray();

        $path = (new SpreadsheetDumper())->dumpToFile($spreadsheet, format: $format);

        try {
            $this->assertFileExists($path);
            $this->assertSame($format, pathinfo($path, PATHINFO_EXTENSION));
            $loaded = (new SpreadsheetLoader())->loadFromFile($path, $format);
            $this->assertSame([[true, false, 12]], $loaded->getActiveSheet()->getRows());
            $this->assertSame($original, $spreadsheet->toArray());
        } finally {
            $this->removeGeneratedFile($path);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function defaultFormats(): iterable
    {
        yield 'library default' => ['xlsx'];
        yield 'configured default' => ['json'];
    }

    #[DataProvider('defaultFormats')]
    public function testAnOmittedPathAndFormatUseTheConfiguredDefault(string $format): void
    {
        $dumper = $format === 'xlsx' ? new SpreadsheetDumper() : new SpreadsheetDumper(format: $format);
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('Records', [['value']]);

        $path = $dumper->dumpToFile($spreadsheet);

        try {
            $this->assertFileExists($path);
            $this->assertSame($format, pathinfo($path, PATHINFO_EXTENSION));
            $loaded = (new SpreadsheetLoader())->loadFromFile($path, $format);
            $this->assertSame([['value']], $loaded->getActiveSheet()->getRows());
        } finally {
            $this->removeGeneratedFile($path);
        }
    }

    public function testAnExplicitPathStillCreatesItsParentDirectories(): void
    {
        $directory = sys_get_temp_dir() . '/derafu-export-' . bin2hex(random_bytes(8));
        $path = $directory . '/nested/export.csv';
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('Records', [[true, false]]);

        try {
            $this->assertSame($path, (new SpreadsheetDumper())->dumpToFile($spreadsheet, $path, 'csv'));
            $this->assertSame("true,false\n", file_get_contents($path));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            if (is_dir($directory . '/nested')) {
                rmdir($directory . '/nested');
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testXmlPropertyNamesKeepTheirExistingSanitizationAndValues(): void
    {
        $object = new stdClass();
        $object->{'with space'} = 'Text';
        $object->{'with/slash'} = 12;
        $object->{'dash-name'} = false;
        $object->underscore_name = null;
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('Records', [[$object]]);

        $xml = new SimpleXMLElement((new XmlHandler())->dumpToString($spreadsheet));
        $cell = $xml->sheet->row->col;

        $this->assertSame('object', (string)$cell['type']);
        $this->assertSame('Text', (string)$cell->with_space);
        $this->assertSame('12', (string)$cell->with_slash);
        $this->assertSame('false', (string)$cell->{'dash-name'});
        $this->assertSame('null', (string)$cell->underscore_name['type']);
        $this->assertSame('Text', $object->{'with space'});
    }
}
