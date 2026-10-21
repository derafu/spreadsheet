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

use Derafu\Spreadsheet\Format\CsvLeagueHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\Translation\SpreadsheetTranslationResourceProvider;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

#[CoversClass(Sheet::class)]
#[CoversClass(CsvLeagueHandler::class)]
#[UsesClass(Spreadsheet::class)]
#[UsesClass(SpreadsheetTranslationResourceProvider::class)]
final class SheetHeadersTest extends TestCase
{
    public function testIntegerAndStringHeadersKeepTheirKeysAndCellValues(): void
    {
        $headers = ['name', 2025, 0, '01', '', -2, '2026'];
        $values = ['Laptop', 100, 0, 'Leading zero', 'Empty name', 'Negative key', 200];
        $sheet = new Sheet('Products', [$headers, $values]);
        $original = $sheet->toArray();

        $associative = $sheet->toAssociative();

        $expected = ['name' => 'Laptop', 2025 => 100, 0 => 0, '01' => 'Leading zero', '' => 'Empty name', -2 => 'Negative key', 2026 => 200];
        $this->assertNotSame($sheet, $associative);
        $this->assertTrue($associative->isAssociative());
        $this->assertSame('Products', $associative->getName());
        $this->assertSame([$expected], $associative->getRows());
        $this->assertSame(['name', 2025, 0, '01', '', -2, 2026], $associative->getColumnNames());
        $this->assertSame($original, $sheet->toArray());
        $this->assertSame([array_keys($expected), $values], $associative->toIndexed()->getRows());
    }

    public function testColumnNamesKeepTheirOriginalTypesAndOrderAcrossDifferentRows(): void
    {
        $rows = [
            ['name' => 'Laptop', 2025 => 100],
            [2025 => 200, 'stock' => 5, 0 => 'Extra'],
        ];
        $sheet = new Sheet('Products', $rows, true);

        $this->assertSame([0 => 'name', 1 => 2025, 3 => 'stock', 4 => 0], $sheet->getColumnNames());
        $this->assertSame($rows, $sheet->getRows());
        $this->assertSame($sheet, $sheet->toAssociative());
    }

    public function testEmptyOrIndexedSheetsHaveNoAssociativeColumnNames(): void
    {
        $this->assertSame([], (new Sheet('Empty', [], true))->getColumnNames());
        $this->assertSame([], (new Sheet('Indexed', [['name', 2025], ['Laptop', 100]]))->getColumnNames());
    }

    public function testEmptyAndHeaderOnlySheetsConvertToEmptyAssociativeSheets(): void
    {
        foreach ([[], [['name', 2025]]] as $rows) {
            $sheet = new Sheet('Products', $rows);

            $converted = $sheet->toAssociative();

            $this->assertTrue($converted->isAssociative());
            $this->assertSame('Products', $converted->getName());
            $this->assertSame([], $converted->getRows());
            $this->assertSame($rows, $sheet->getRows());
        }
    }

    public function testMissingCellsStillBecomeNullWhenConvertingToAssociative(): void
    {
        $sheet = new Sheet('Products', [['name', 2025], ['Laptop']]);

        $this->assertSame([['name' => 'Laptop', 2025 => null]], $sheet->toAssociative()->getRows());
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidHeaders(): iterable
    {
        yield 'null' => [null];
        yield 'true' => [true];
        yield 'false' => [false];
        yield 'fractional float' => [1.5];
        yield 'whole number float' => [1.0];
        yield 'zero float' => [0.0];
        yield 'empty array' => [[]];
        yield 'array' => [['name']];
        yield 'object' => [new stdClass()];
        yield 'stringable' => [new class () implements Stringable {
            public function __toString(): string
            {
                return 'name';
            }
        }];
    }

    private function assertInvalidHeaderIsRejectedWithoutChangingTheSheet(mixed $header): void
    {
        $translator = TranslatorFactory::create('es', resourceProviders: [new SpreadsheetTranslationResourceProvider()]);
        $type = get_debug_type($header);

        foreach ([false, true] as $hasData) {
            $rows = [['valid', $header]];
            if ($hasData) {
                $rows[] = ['First', 'Second'];
            }
            $sheet = new Sheet('Products', $rows);
            $original = $sheet->toArray();

            try {
                $sheet->toAssociative();
                $this->fail('An invalid column header must be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame("Column header at index \"1\" in sheet \"Products\" must be int|string; $type given.", $exception->getMessage());
                $this->assertSame("El encabezado de columna con índice \"1\" de la hoja \"Products\" debe ser int|string; se recibió $type.", $exception->trans($translator));
            }

            $this->assertSame($original, $sheet->toArray());
        }
    }

    #[DataProvider('invalidHeaders')]
    public function testInvalidHeadersAreTranslatableErrorsWithOrWithoutDataRows(mixed $header): void
    {
        $this->assertInvalidHeaderIsRejectedWithoutChangingTheSheet($header);
    }

    public function testResourceHeadersAreRejectedWithoutConvertingThemToIntegerKeys(): void
    {
        $resource = fopen('php://temp', 'w+');
        $this->assertIsResource($resource);
        try {
            $this->assertInvalidHeaderIsRejectedWithoutChangingTheSheet($resource);
        } finally {
            fclose($resource);
        }
    }

    public function testCsvUsesIntegerAndStringColumnNamesAndPreservesAlignment(): void
    {
        $rows = [
            ['name' => 'Laptop', 2025 => 100],
            ['stock' => 5, 2025 => 200, 'name' => 'Desktop'],
        ];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->createSheet('Products', $rows, true);

        $csv = (new CsvLeagueHandler())->dumpToString($spreadsheet);

        $this->assertSame("name,2025,stock\nLaptop,100,\nDesktop,200,5\n", $csv);
        $this->assertSame($rows, $sheet->getRows());
    }
}
