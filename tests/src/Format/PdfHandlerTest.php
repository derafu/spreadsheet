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

use Derafu\Spreadsheet\Abstract\AbstractPhpSpreadsheetFormatHandler;
use Derafu\Spreadsheet\Exception\SpreadsheetDumpException;
use Derafu\Spreadsheet\Exception\SpreadsheetLoadException;
use Derafu\Spreadsheet\Format\PdfHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception as WriterException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;

#[CoversClass(PdfHandler::class)]
#[CoversClass(AbstractPhpSpreadsheetFormatHandler::class)]
#[UsesClass(Spreadsheet::class)]
#[UsesClass(Sheet::class)]
final class PdfHandlerTest extends TestCase
{
    public function testExportsARealPdfStringContainingTheTableData(): void
    {
        $source = new Spreadsheet();
        $rows = [['name', 'quantity'], ['Laptop', 12], ['Ñandú', 3]];
        $source->createSheet('Products', $rows);

        $pdf = (new PdfHandler())->dumpToString($source);

        $this->assertPdfContainsTable($pdf);
        $this->assertSame($rows, $source->getActiveSheet()->getRows());
    }

    public function testExportsARealPdfFileContainingTheTableData(): void
    {
        $source = new Spreadsheet();
        $rows = [['name', 'quantity'], ['Laptop', 12], ['Ñandú', 3]];
        $source->createSheet('Products', $rows);
        $filepath = tempnam(sys_get_temp_dir(), 'spreadsheet_pdf_');
        $this->assertNotFalse($filepath);

        try {
            $this->assertSame($filepath, (new PdfHandler())->dumpToFile($source, $filepath));
            $this->assertFileExists($filepath);
            $pdf = file_get_contents($filepath);
            $this->assertNotFalse($pdf);
            $this->assertPdfContainsTable($pdf);
            $this->assertSame($rows, $source->getActiveSheet()->getRows());
        } finally {
            unlink($filepath);
        }
    }

    private function assertPdfContainsTable(string $pdf): void
    {
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith('%%EOF', rtrim($pdf));

        $reader = new PdfReader(new PdfParser(StreamReader::createByString($pdf)));
        $this->assertSame(1, $reader->getPageCount());
        $content = $reader->getPage(1)->getContentStream();

        foreach (['name', 'quantity', 'Laptop', '12', 'Ñandú', '3'] as $value) {
            $this->assertStringContainsString(mb_convert_encoding($value, 'UTF-16BE', 'UTF-8'), $content);
        }
    }

    public function testReportsPdfMetadataAndRejectsReading(): void
    {
        $handler = new PdfHandler();
        $this->assertSame('pdf', $handler->getExtension());
        $this->assertSame('application/pdf', $handler->getMimeType());
        $this->expectException(SpreadsheetLoadException::class);
        $this->expectExceptionMessage('PDF files are not supported for reading, yet.');
        $handler->loadFromString('%PDF-1.7');
    }

    public function testAnUnknownPdfWriterIsATranslatableDumpFailure(): void
    {
        $source = new Spreadsheet();
        $source->createSheet('Records', [['value']]);
        try {
            (new PdfHandler(writerType: 'UnknownPdfWriter'))->dumpToString($source);
            $this->fail('An unknown PDF writer must be rejected.');
        } catch (SpreadsheetDumpException $exception) {
            $this->assertStringStartsWith('Error creating spreadsheet string:', $exception->getMessage());
            $this->assertInstanceOf(WriterException::class, $exception->getPrevious());
        }
    }
}
