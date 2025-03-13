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

use Derafu\Spreadsheet\Caster;
use Derafu\Spreadsheet\Contract\DumperInterface;
use Derafu\Spreadsheet\Contract\LoaderInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Dumper;
use Derafu\Spreadsheet\Factory;
use Derafu\Spreadsheet\Format\CsvLeagueHandler;
use Derafu\Spreadsheet\Format\JsonHandler;
use Derafu\Spreadsheet\Format\OdsHandler;
use Derafu\Spreadsheet\Format\XlsHandler;
use Derafu\Spreadsheet\Format\XlsxHandler;
use Derafu\Spreadsheet\Format\XmlHandler;
use Derafu\Spreadsheet\Format\YamlHandler;
use Derafu\Spreadsheet\Loader;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Spreadsheet::class)]
#[CoversClass(Sheet::class)]
#[CoversClass(Caster::class)]
#[CoversClass(Dumper::class)]
#[CoversClass(Loader::class)]
#[CoversClass(Factory::class)]
#[CoversClass(CsvLeagueHandler::class)]
#[CoversClass(XlsHandler::class)]
#[CoversClass(XlsxHandler::class)]
#[CoversClass(OdsHandler::class)]
#[CoversClass(XmlHandler::class)]
#[CoversClass(JsonHandler::class)]
#[CoversClass(YamlHandler::class)]
class AllSpreadsheetRoundTripTest extends TestCase
{
    private DumperInterface $dumper;

    private LoaderInterface $loader;

    protected function setUp(): void
    {
        $factory = new Factory();
        $caster = new Caster();

        $this->dumper = new Dumper($factory, $caster);
        $this->loader = new Loader($factory, $caster);
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
