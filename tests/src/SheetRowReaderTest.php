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
use Derafu\Array\ArrayReader;
use Derafu\Array\Exception\TypeMismatchException;
use Derafu\Spreadsheet\Sheet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Sheet::class)]
final class SheetRowReaderTest extends TestCase
{
    public function testMissingRowsAndExistingEmptyRowsAreDifferent(): void
    {
        $sheet = new Sheet('Rows', [7 => []]);

        $this->assertNull($sheet->getRowReader(0));
        $this->assertNull($sheet->getRowReader(8));
        $reader = $sheet->getRowReader(7);
        $this->assertInstanceOf(ArrayReader::class, $reader);
        $this->assertSame([], $reader->toArray());
        $this->assertSame([7 => []], $sheet->getRows());
    }

    public function testSparseColumnKeysAndFalsyValuesArePreservedWithoutCasting(): void
    {
        $row = [2 => '0012', 7 => 0, 12 => false, 20 => null];
        $sheet = new Sheet('Rows', [7 => $row]);
        $reader = $sheet->getRowReader(7);
        $this->assertInstanceOf(ArrayReader::class, $reader);

        $this->assertSame($row, $reader->toArray());
        $this->assertSame('0012', $reader->requireString(2));
        $this->assertSame(12, $reader->requireAsInt(2));
        $this->assertSame(0, $reader->requireInt(7));
        $this->assertFalse($reader->requireBool(12));
        $this->assertTrue($reader->hasKey(20));
        $this->assertFalse($reader->hasValue(20));
        $this->assertNull($reader->getString(20));
        $this->assertFalse($reader->hasKey(0));
        $this->assertSame($row, $sheet->getRow(7));
    }

    public function testNamedColumnsKeepLiteralPrecedenceAndNestedValues(): void
    {
        $row = [
            'product.name' => 'Literal name',
            'product' => ['name' => 'Nested name', 'quantity' => 12],
        ];
        $sheet = new Sheet('Products', [$row], true);
        $reader = $sheet->getRowReader(0);
        $this->assertInstanceOf(ArrayReader::class, $reader);

        $this->assertSame('Literal name', $reader->requireString('product.name'));
        $this->assertSame(12, $reader->requireInt('product.quantity'));
        $this->assertSame($row, $reader->toArray());
        $this->assertSame($row, $sheet->getRow(0));
    }

    public function testReadersKeepSnapshotsAfterCellsAndRowsChange(): void
    {
        $sheet = new Sheet('Products', [['quantity' => 12]], true);
        $before = $sheet->getRowReader(0);
        $this->assertInstanceOf(ArrayReader::class, $before);

        $sheet->setCell(0, 'quantity', 13);
        $afterCell = $sheet->getRowReader(0);
        $this->assertInstanceOf(ArrayReader::class, $afterCell);
        $this->assertSame(12, $before->requireInt('quantity'));
        $this->assertSame(13, $afterCell->requireInt('quantity'));

        $sheet->setRow(0, ['quantity' => 14]);
        $afterRow = $sheet->getRowReader(0);
        $this->assertInstanceOf(ArrayReader::class, $afterRow);
        $this->assertSame(14, $afterRow->requireInt('quantity'));
        $this->assertSame(13, $afterCell->requireInt('quantity'));

        $sheet->setRows([]);
        $this->assertNull($sheet->getRowReader(0));
        $this->assertSame(14, $afterRow->requireInt('quantity'));
    }

    public function testStrictReadsRejectNumericTextStoredInACell(): void
    {
        $sheet = new Sheet('Products', [['quantity' => '12']], true);
        $reader = $sheet->getRowReader(0);
        $this->assertInstanceOf(ArrayReader::class, $reader);
        $this->assertSame('12', $sheet->getCell(0, 'quantity'));
        $this->expectException(TypeMismatchException::class);
        $this->expectExceptionMessage('The key "quantity" must be of type int, string given.');

        $reader->requireInt('quantity');
    }

    public function testObjectsRemainTheStoredCellValues(): void
    {
        $date = new DateTimeImmutable('2026-01-15');
        $object = new stdClass();
        $row = ['date' => $date, 'object' => $object];
        $sheet = new Sheet('Values', [$row], true);
        $reader = $sheet->getRowReader(0);
        $this->assertInstanceOf(ArrayReader::class, $reader);

        $this->assertSame($date, $reader->requireDateTime('date'));
        $this->assertSame($object, $reader->toArray()['object']);
        $this->assertSame($row, $sheet->getRow(0));
    }
}
