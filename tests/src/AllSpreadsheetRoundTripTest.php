<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet;

use Derafu\Spreadsheet\Contract\SpreadsheetDumperInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetLoaderInterface;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Spreadsheet::class)]
#[CoversClass(Sheet::class)]
#[CoversClass(SpreadsheetCaster::class)]
#[CoversClass(SpreadsheetDumper::class)]
#[CoversClass(SpreadsheetLoader::class)]
#[CoversClass(SpreadsheetFactory::class)]
#[CoversClass(CsvLeagueHandler::class)]
#[CoversClass(XlsHandler::class)]
#[CoversClass(XlsxHandler::class)]
#[CoversClass(OdsHandler::class)]
#[CoversClass(XmlHandler::class)]
#[CoversClass(JsonHandler::class)]
#[CoversClass(YamlHandler::class)]
class AllSpreadsheetRoundTripTest extends TestCase
{
    private SpreadsheetDumperInterface $dumper;

    private SpreadsheetLoaderInterface $loader;

    protected function setUp(): void
    {
        $factory = new SpreadsheetFactory();
        $caster = new SpreadsheetCaster();

        $this->dumper = new SpreadsheetDumper($factory, $caster);
        $this->loader = new SpreadsheetLoader($factory, $caster);
    }

    public static function provideAllFixtures(): array
    {
        $fixturesDir = __DIR__ . '/../fixtures';
        $fixturesFiles = glob($fixturesDir . '/*/*');

        $fixtures = [];

        foreach ($fixturesFiles as $fixtureFile) {
            $fixtures[basename($fixtureFile)] = [
                'filepath' => $fixtureFile,
                'format' => pathinfo($fixtureFile, PATHINFO_EXTENSION),
            ];
        }

        return $fixtures;
    }

    #[DataProvider('provideAllFixtures')]
    public function testRoundTrip(string $filepath, string $format): void
    {
        $originalData = file_get_contents($filepath);
        $spreadsheet = $this->loader->loadFromString($originalData, $format);
        $this->assertInstanceOf(SpreadsheetInterface::class, $spreadsheet);

        $roundTripData = $this->dumper->dumpToString($spreadsheet, $format);
        $roundTripSpreadsheet = $this->loader->loadFromString($roundTripData, $format);
        $this->assertInstanceOf(SpreadsheetInterface::class, $roundTripSpreadsheet);

        $this->assertSame($spreadsheet->getSheetNames(), $roundTripSpreadsheet->getSheetNames());
        $this->assertSame($spreadsheet->toArray(), $roundTripSpreadsheet->toArray());
    }
}
