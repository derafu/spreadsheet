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

use Derafu\Spreadsheet\Sheet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sheet::class)]
final class SheetOperationsTest extends TestCase
{
    public function testAddingRowsPreservesExistingSparseIndices(): void
    {
        $sheet = new Sheet('Products', [0 => ['first'], 2 => ['existing']]);

        $this->assertSame($sheet, $sheet->addRow(['added']));
        $this->assertSame([
            0 => ['first'],
            2 => ['existing'],
            3 => ['added'],
        ], $sheet->getRows());

        $empty = new Sheet('Empty');
        $this->assertSame($empty, $empty->addRow(['first']));
        $this->assertSame([['first']], $empty->getRows());
    }

    public function testSelectingColumnsPreservesNullCellsAndSparseRowIndices(): void
    {
        $rows = [2 => ['id' => 1, 'optional' => null, 'active' => false, 'quantity' => 0, 'note' => '']];
        $sheet = new Sheet('Products', $rows, true);

        $selected = $sheet->selectColumns(['optional', 'missing', 'active', 'quantity', 'note']);

        $this->assertSame([
            2 => ['optional' => null, 'active' => false, 'quantity' => 0, 'note' => ''],
        ], $selected->getRows());
        $this->assertNotSame($sheet, $selected);
        $this->assertSame('Products', $selected->getName());
        $this->assertTrue($selected->isAssociative());
        $this->assertSame($rows, $sheet->getRows());
    }

    public function testRowsAndCellsCanBeEditedAndMissingValuesAreNull(): void
    {
        $sheet = new Sheet('Draft');
        $this->assertSame($sheet, $sheet->setName('Products'));
        $this->assertSame('Products', $sheet->getName());
        $this->assertNull($sheet->getRow(0));
        $this->assertNull($sheet->getCell(0, 'name'));
        $this->assertSame($sheet, $sheet->setRows([['id' => 1]]));
        $this->assertSame(['id' => 1], $sheet->getRow(0));
        $this->assertNull($sheet->getCell(0, 'missing'));
        $this->assertSame($sheet, $sheet->setRow(0, ['id' => 2]));
        $this->assertSame($sheet, $sheet->addRow(['id' => 3]));
        $this->assertSame([['id' => 2], ['id' => 3]], $sheet->getRows());
        $this->assertSame($sheet, $sheet->setCell(4, 'name', 'Laptop'));
        $this->assertSame('Laptop', $sheet->getCell(4, 'name'));
        $this->assertSame(['name' => 'Laptop'], $sheet->getRow(4));
        $this->assertSame($sheet, $sheet->setCell(4, 'name', 'Desktop'));
        $this->assertSame('Desktop', $sheet->getCell(4, 'name'));
    }

    public function testSortingIsMutableAndFilteringCreatesAnIndependentSheet(): void
    {
        $rows = [['name' => 'Desktop', 'price' => 200], ['name' => 'Laptop', 'price' => 100]];
        $sheet = new Sheet('Products', $rows, true);
        $this->assertSame($sheet, $sheet->sort(static fn (array $a, array $b): int => $a['price'] <=> $b['price']));
        $this->assertSame(array_reverse($rows), $sheet->getRows());
        $filtered = $sheet->filter(static fn (array $row): bool => $row['name'] === 'Desktop');
        $this->assertNotSame($sheet, $filtered);
        $this->assertSame('Products', $filtered->getName());
        $this->assertTrue($filtered->isAssociative());
        $this->assertSame([['name' => 'Desktop', 'price' => 200]], $filtered->getRows());
        $filtered->setCell(0, 'price', 300);
        $this->assertSame(array_reverse($rows), $sheet->getRows());
        $this->assertSame([], $sheet->filter(static fn (array $row): bool => false)->getRows());
    }

    public function testSelectingColumnsKeepsTheirRequestedOrderAndFalsyValues(): void
    {
        $rows = [['id' => 1, 'note' => '', 'quantity' => 0, 'active' => false]];
        $sheet = new Sheet('Products', $rows, true);
        $selected = $sheet->selectColumns(['active', 'quantity', 'note', 'missing']);
        $this->assertNotSame($sheet, $selected);
        $this->assertSame('Products', $selected->getName());
        $this->assertTrue($selected->isAssociative());
        $this->assertSame([['active' => false, 'quantity' => 0, 'note' => '']], $selected->getRows());
        $this->assertSame($rows, $sheet->getRows());
        $this->assertSame([[]], $sheet->selectColumns([])->getRows());
    }

    public function testHeadersDataIteratorsAndCountsDistinguishBothSheetLayouts(): void
    {
        $indexed = new Sheet('Products', [['id', 'name'], [1, 'Laptop'], [2, 'Desktop']]);
        $this->assertSame(['id', 'name'], $indexed->getHeaderRow());
        $this->assertSame([[1, 'Laptop'], [2, 'Desktop']], $indexed->getDataRows());
        $this->assertSame($indexed->getDataRows(), iterator_to_array($indexed->getDataIterator()));
        $this->assertSame($indexed->getRows(), iterator_to_array($indexed));
        $this->assertCount(3, $indexed);
        $this->assertSame(2, $indexed->countData());
        $associative = new Sheet('Products', [['id' => 1, 'name' => 'Laptop']], true);
        $this->assertSame(['id', 'name'], $associative->getHeaderRow());
        $this->assertSame($associative->getRows(), $associative->getDataRows());
        $this->assertSame(1, $associative->countData());
        $empty = new Sheet('Empty');
        $this->assertSame([], $empty->getHeaderRow());
        $this->assertSame([], $empty->getDataRows());
        $this->assertSame([], iterator_to_array($empty));
        $this->assertSame(0, $empty->countData());
        $this->assertCount(0, $empty);
        $this->assertSame($indexed, $indexed->toIndexed());
        $this->assertSame([], (new Sheet('Empty', [], true))->toIndexed()->getRows());
    }

    public function testMappingReceivesTheActualRowAndColumnKeys(): void
    {
        $sheet = new Sheet('Products', [2 => ['name' => 'Laptop', 'count' => 0]], true);
        $calls = [];
        $this->assertSame($sheet, $sheet->map(static function (mixed $value, int $row, int|string $column) use (&$calls): mixed {
            $calls[] = [$value, $row, $column];

            return is_int($value) ? $value + 1 : $value;
        }));
        $this->assertSame([['Laptop', 2, 'name'], [0, 2, 'count']], $calls);
        $this->assertSame([2 => ['name' => 'Laptop', 'count' => 1]], $sheet->getRows());
    }
}
