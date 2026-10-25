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

use DateTimeImmutable;
use Derafu\Spreadsheet\Abstract\AbstractPhpSpreadsheetFormatHandler;
use Derafu\Spreadsheet\Contract\SpreadsheetCasterInterface;
use Derafu\Spreadsheet\Format\CsvLeagueHandler;
use Derafu\Spreadsheet\Format\JsonHandler;
use Derafu\Spreadsheet\Format\OdsHandler;
use Derafu\Spreadsheet\Format\XlsHandler;
use Derafu\Spreadsheet\Format\XlsxHandler;
use Derafu\Spreadsheet\Format\XmlHandler;
use Derafu\Spreadsheet\Format\YamlHandler;
use Derafu\Spreadsheet\Http\NyholmSpreadsheetHttpResponseGenerator;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use Derafu\Spreadsheet\SpreadsheetDumper;
use Derafu\Spreadsheet\SpreadsheetFactory;
use Derafu\Spreadsheet\SpreadsheetLoader;
use Derafu\TestsSpreadsheet\Fixture\BooleanLabelCaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Stringable;

#[CoversClass(SpreadsheetCaster::class)]
#[CoversClass(SpreadsheetLoader::class)]
#[CoversClass(SpreadsheetDumper::class)]
#[CoversClass(NyholmSpreadsheetHttpResponseGenerator::class)]
#[UsesClass(Sheet::class)]
#[UsesClass(Spreadsheet::class)]
#[UsesClass(SpreadsheetFactory::class)]
#[UsesClass(CsvLeagueHandler::class)]
#[UsesClass(JsonHandler::class)]
#[UsesClass(YamlHandler::class)]
#[UsesClass(XmlHandler::class)]
#[UsesClass(AbstractPhpSpreadsheetFormatHandler::class)]
#[UsesClass(XlsxHandler::class)]
#[UsesClass(XlsHandler::class)]
#[UsesClass(OdsHandler::class)]
final class CastingPipelineTest extends TestCase
{
    public function testRowReadersExposeTheTypesProducedByTheExistingLoader(): void
    {
        $spreadsheet = (new SpreadsheetLoader())->loadFromString(
            '[{"quantity":"12","amount":"19.90","active":"false","date":"2026-01-15"}]',
            'json'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $row = $sheet->getRow(0);
        $reader = $sheet->getRowReader(0);
        $this->assertNotNull($reader);

        $this->assertSame(12, $reader->requireInt('quantity'));
        $this->assertSame(19.9, $reader->requireFloat('amount'));
        $this->assertFalse($reader->requireBool('active'));
        $this->assertSame('2026-01-15', $reader->requireDateTime('date')->format('Y-m-d'));
        $this->assertSame($row, $sheet->getRow(0));
    }

    public function testHttpJsonResponseContainsTheExportedDataAndDownloadHeaders(): void
    {
        $source = new Spreadsheet();
        $rows = [['name' => 'Ñandú', 'quantity' => 12, 'active' => true]];
        $source->createSheet('Products', $rows, true);

        $response = (new NyholmSpreadsheetHttpResponseGenerator())->createResponse($source, 'products.json');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('attachment; filename="products.json"', $response->getHeaderLine('Content-Disposition'));
        $this->assertSame('max-age=0', $response->getHeaderLine('Cache-Control'));
        $this->assertSame(
            [['name' => 'Ñandú', 'quantity' => 12, 'active' => 'true']],
            json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR)
        );
        $this->assertSame($rows, $source->getActiveSheet()->getRows());
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_OLE_INSTANCES']);
        parent::tearDown();
    }

    /** @return iterable<string, array{string}> */
    public static function formats(): iterable
    {
        foreach (['csv', 'json', 'yaml', 'xml', 'xlsx', 'xls', 'ods'] as $format) {
            yield $format => [$format];
        }
    }

    #[DataProvider('formats')]
    public function testFileAndStringRoutesPreserveTheSameTypesWithoutChangingTheSource(string $format): void
    {
        $source = new Spreadsheet();
        $date = new DateTimeImmutable('2025-03-12T12:30:00+00:00');
        $sheet = $source->createSheet('Sheet', [[true, false, null, 12, 1.5, $date, ['nested' => true]]]);
        $original = $source->toArray();
        $dumper = new SpreadsheetDumper();
        $loader = new SpreadsheetLoader();
        $directory = sys_get_temp_dir() . '/derafu-casting-' . bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($directory));
        $path = $directory . '/Sheet.' . $format;

        try {
            $string = $dumper->dumpToString($source, $format);
            $this->assertSame($path, $dumper->dumpToFile($source, $path, $format));
            $fromFile = $loader->loadFromFile($path, $format);
            $fromString = $loader->loadFromString($string, $format);

            foreach ([$fromFile, $fromString] as $loaded) {
                $loadedSheet = $loaded->getSheet('Sheet');
                $this->assertNotNull($loadedSheet);
                $rows = $loadedSheet->getRows();
                $this->assertTrue($rows[0][0]);
                $this->assertFalse($rows[0][1]);
                $this->assertNull($rows[0][2]);
                $this->assertSame(12, $rows[0][3]);
                $this->assertSame(1.5, $rows[0][4]);
                $this->assertInstanceOf(DateTimeImmutable::class, $rows[0][5]);
                $this->assertSame('2025-03-12T12:30:00.000000+00:00', $rows[0][5]->format('Y-m-d\TH:i:s.uP'));
                $this->assertSame(['nested' => true], $rows[0][6]);
            }
            $this->assertSame($original, $source->toArray());
            $this->assertSame($sheet, $source->getSheet('Sheet'));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            rmdir($directory);
        }
    }

    public function testReadingCreatesIndependentSheetsAndKeepsTheActiveSheetAndKeys(): void
    {
        $source = new Spreadsheet();
        $first = $source->createSheet('First', [4 => ['false', '12', '2025-03-12', '{"nested":true}']]);
        $second = $source->createSheet('Second', [['enabled' => 'true']], true);
        $source->setActiveSheet('Second');
        $original = $source->toArray();

        $converted = (new SpreadsheetCaster())->castAfterLoad($source);

        $this->assertNotSame($source, $converted);
        $this->assertSame(['First', 'Second'], $converted->getSheetNames());
        $this->assertSame('Second', $converted->getActiveSheet()->getName());
        $convertedFirst = $converted->getSheet('First');
        $convertedSecond = $converted->getSheet('Second');
        $this->assertNotNull($convertedFirst);
        $this->assertNotNull($convertedSecond);
        $this->assertNotSame($first, $convertedFirst);
        $this->assertNotSame($second, $convertedSecond);
        $this->assertTrue($convertedSecond->isAssociative());
        $this->assertSame([['enabled' => true]], $convertedSecond->getRows());
        $this->assertSame([4], array_keys($convertedFirst->getRows()));
        $this->assertFalse($convertedFirst->getCell(4, 0));
        $this->assertSame(12, $convertedFirst->getCell(4, 1));
        $this->assertInstanceOf(DateTimeImmutable::class, $convertedFirst->getCell(4, 2));
        $this->assertSame(['nested' => true], $convertedFirst->getCell(4, 3));
        $this->assertSame($original, $source->toArray());

        $convertedFirst->setCell(4, 0, 'Changed copy');
        $this->assertSame('false', $first->getCell(4, 0));
        $first->setCell(4, 1, 'Changed original');
        $this->assertSame(12, $convertedFirst->getCell(4, 1));
    }

    public function testWritingCreatesIndependentSheetsAndUsesTheExistingConversions(): void
    {
        $text = new class () implements Stringable {
            public function __toString(): string
            {
                return 'Rendered text';
            }
        };
        $date = new DateTimeImmutable('2025-03-12T12:30:00+00:00');
        $source = new Spreadsheet();
        $first = $source->createSheet('First', [[null, true, false, $date, ['nested' => true], $text]]);
        $second = $source->createSheet('Second', [['value' => 'unchanged']], true);
        $source->setActiveSheet('Second');
        $original = $source->toArray();

        $converted = (new SpreadsheetCaster())->castBeforeDump($source);

        $convertedFirst = $converted->getSheet('First');
        $convertedSecond = $converted->getSheet('Second');
        $this->assertNotNull($convertedFirst);
        $this->assertNotNull($convertedSecond);
        $this->assertNotSame($first, $convertedFirst);
        $this->assertNotSame($second, $convertedSecond);
        $this->assertSame('Second', $converted->getActiveSheet()->getName());
        $this->assertSame([['', 'true', 'false', '2025-03-12T12:30:00', '{"nested":true}', 'Rendered text']], $convertedFirst->getRows());
        $this->assertSame($original, $source->toArray());
        $convertedFirst->setCell(0, 0, 'Changed copy');
        $this->assertNull($first->getCell(0, 0));
    }

    public function testInjectedCasterControlsBothFileAndStringRoutesAndHttpOutput(): void
    {
        $caster = new BooleanLabelCaster();
        $this->assertInstanceOf(SpreadsheetCasterInterface::class, $caster);
        $dumper = new SpreadsheetDumper(caster: $caster);
        $loader = new SpreadsheetLoader(caster: $caster);
        $source = new Spreadsheet();
        $source->createSheet('Sheet', [[true, false, '12']]);
        $path = tempnam(sys_get_temp_dir(), 'derafu-custom-caster-');
        $this->assertIsString($path);
        try {
            $string = $dumper->dumpToString($source, 'csv');
            $this->assertSame("yes,no,12\n", $string);
            $dumper->dumpToFile($source, $path, 'csv');
            $this->assertSame($string, file_get_contents($path));
            foreach ([$loader->loadFromFile($path, 'csv'), $loader->loadFromString($string, 'csv')] as $loaded) {
                $sheet = $loaded->getActiveSheet();
                $this->assertSame([[true, false, '12']], $sheet->getRows());
            }
            $response = (new NyholmSpreadsheetHttpResponseGenerator(caster: $caster))->createResponse($source, 'export.csv');
            $this->assertSame($string, (string)$response->getBody());
            $this->assertSame([[true, false, '12']], $source->getActiveSheet()->getRows());
        } finally {
            unlink($path);
        }
    }
}
