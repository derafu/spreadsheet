<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet\Format;

use DateTimeImmutable;
use Derafu\Spreadsheet\Abstract\AbstractPhpSpreadsheetFormatHandler;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Format\HtmlHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use Derafu\Spreadsheet\SpreadsheetDumper;
use Derafu\Spreadsheet\SpreadsheetFactory;
use Derafu\Spreadsheet\SpreadsheetLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlHandler::class)]
#[CoversClass(AbstractPhpSpreadsheetFormatHandler::class)]
#[UsesClass(Spreadsheet::class)]
#[UsesClass(Sheet::class)]
#[UsesClass(SpreadsheetCaster::class)]
#[UsesClass(SpreadsheetLoader::class)]
#[UsesClass(SpreadsheetDumper::class)]
#[UsesClass(SpreadsheetFactory::class)]
final class HtmlHandlerTest extends TestCase
{
    private function assertTableTypes(SpreadsheetInterface $spreadsheet): void
    {
        $this->assertCount(1, $spreadsheet);
        $rows = $spreadsheet->getActiveSheet()->getRows();
        $this->assertCount(3, $rows);
        $this->assertSame('Ñandú & <text>', $rows[1][0]);
        $this->assertTrue($rows[1][1]);
        $this->assertSame(12, $rows[1][2]);
        $this->assertSame(1.5, $rows[1][3]);
        $this->assertInstanceOf(DateTimeImmutable::class, $rows[1][4]);
        $this->assertSame('2025-03-12T12:30:00.000000+00:00', $rows[1][4]->format('Y-m-d\TH:i:s.uP'));
        $this->assertSame(['stock' => 3], $rows[1][5]);
        $this->assertNull($rows[1][6]);
        $this->assertFalse($rows[2][1]);
        $this->assertSame(0, $rows[2][2]);
        $this->assertSame([1, 2], $rows[2][5]);
        $this->assertSame('Present', $rows[2][6]);
    }

    public function testSingleTableRoundTripsThroughFileAndStringWithCasting(): void
    {
        $fixture = dirname(__DIR__, 2) . '/fixtures/html/html-table-types.html';
        $html = file_get_contents($fixture);
        $this->assertIsString($html);
        $loader = new SpreadsheetLoader();
        $dumper = new SpreadsheetDumper();
        $loaded = $loader->loadFromFile($fixture, 'html');
        $this->assertTableTypes($loaded);
        $this->assertTableTypes($loader->loadFromString($html, 'html'));
        $output = $dumper->dumpToString($loaded, 'html');
        $this->assertTableTypes($loader->loadFromString($output, 'html'));
        $this->assertTableTypes($loaded);

        $file = tempnam(sys_get_temp_dir(), 'html-round-trip-');
        $this->assertIsString($file);
        try {
            $dumper->dumpToFile($loaded, $file, 'html');
            $this->assertTableTypes($loader->loadFromFile($file, 'html'));
        } finally {
            unlink($file);
        }
    }

    public function testRawHtmlHandlerKeepsTextForTheCasterAndReportsItsFormat(): void
    {
        $handler = new HtmlHandler();
        $this->assertSame('html', $handler->getExtension());
        $this->assertSame('text/html', $handler->getMimeType());
        $html = file_get_contents(dirname(__DIR__, 2) . '/fixtures/html/html-table-types.html');
        $this->assertIsString($html);
        $rows = $handler->loadFromString($html)->getActiveSheet()->getRows();
        $this->assertSame('true', $rows[1][1]);
        $this->assertSame('false', $rows[2][1]);
        $this->assertSame('{"stock":3}', $rows[1][5]);
    }
}
