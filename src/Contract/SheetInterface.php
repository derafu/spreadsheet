<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Spreadsheet\Contract;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Interface for a spreadsheet sheet.
 */
interface SheetInterface extends IteratorAggregate, Countable
{
    /**
     * Get the sheet name.
     *
     * @return string The sheet name.
     */
    public function getName(): string;

    /**
     * Set the sheet name.
     *
     * @param string $name The new sheet name.
     * @return static
     */
    public function setName(string $name): static;

    /**
     * Get all rows.
     *
     * @return array<int, array<int|string, mixed>> The rows of data.
     */
    public function getRows(): array;

    /**
     * Set all rows.
     *
     * @param array<int, array<int|string, mixed>> $rows The rows to set.
     * @return static
     */
    public function setRows(array $rows): static;

    /**
     * Get a specific row.
     *
     * @param int $index Row index (0-based).
     * @return array<int|string, mixed>|null The row or null if it doesn't exist.
     */
    public function getRow(int $index): ?array;

    /**
     * Set a specific row.
     *
     * @param int $index Row index (0-based).
     * @param array<int|string, mixed> $row The row data.
     * @return static
     */
    public function setRow(int $index, array $row): static;

    /**
     * Add a row to the end of the sheet.
     *
     * @param array<int|string, mixed> $row The row data.
     * @return static
     */
    public function addRow(array $row): static;

    /**
     * Get a specific cell.
     *
     * @param int $rowIndex Row index (0-based).
     * @param int|string $columnIndex Column index or name.
     * @return mixed The cell value or null if it doesn't exist.
     */
    public function getCell(int $rowIndex, int|string $columnIndex): mixed;

    /**
     * Set a specific cell.
     *
     * @param int $rowIndex Row index (0-based).
     * @param int|string $columnIndex Column index or name.
     * @param mixed $value The cell value.
     * @return static
     */
    public function setCell(
        int $rowIndex,
        int|string $columnIndex,
        mixed $value
    ): static;

    /**
     * Check if the data is in associative format.
     *
     * @return bool True if associative, false if indexed.
     */
    public function isAssociative(): bool;

    /**
     * Get column names if the data is associative.
     *
     * @return array<int, string> Array of column names.
     */
    public function getColumnNames(): array;

    /**
     * Sort rows based on a callback function.
     *
     * This method is mutable, so it will modify the current sheet.
     *
     * @param callable $callback Comparison function for sorting.
     * @return static Same sheet with sorted rows.
     */
    public function sort(callable $callback): static;

    /**
     * Apply a function to each cell.
     *
     * This method is mutable, so it will modify the current sheet.
     *
     * @param callable $callback Function to apply to each cell.
     * @return static Same sheet with modified cells.
     */
    public function map(callable $callback): static;

    /**
     * Get the header row of the sheet.
     *
     * For indexed sheets, returns the first row.
     * For associative sheets, returns the column names.
     *
     * @return array<int|string, mixed> The header row.
     */
    public function getHeaderRow(): array;

    /**
     * Get the data rows of the sheet (excluding header row).
     *
     * For indexed sheets, returns all rows except the first one.
     * For associative sheets, returns all rows.
     *
     * @return array<int, array<int|string, mixed>> The data rows.
     */
    public function getDataRows(): array;

    /**
     * Get iterator for data rows only (excluding header row).
     *
     * @return ArrayIterator<int, array<int|string, mixed>>
     */
    public function getDataIterator(): ArrayIterator;

    /**
     * Count only data rows (excluding header row).
     *
     * @return int Number of data rows.
     */
    public function countData(): int;

    /**
     * Get an iterator for the rows.
     *
     * @return ArrayIterator<int, array<int|string, mixed>>
     */
    public function getIterator(): ArrayIterator;

    /**
     * Get the number of rows.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Convert the sheet to an array.
     *
     * @return array{name: string, rows: array<int, array<int|string, mixed>>, isAssociative: bool}
     */
    public function toArray(): array;

    /**
     * Convert indexed data to associative (using first row as keys).
     *
     * This method is immutable, so it will return a new sheet with the
     * associative data.
     *
     * @return static New sheet with associative data.
     */
    public function toAssociative(): static;

    /**
     * Convert associative data to indexed format.
     *
     * This method is immutable, so it will return a new sheet with the
     * indexed data.
     *
     * @return static New sheet with indexed data.
     */
    public function toIndexed(): static;

    /**
     * Filter rows based on a callback function.
     *
     * This method is immutable, so it will return a new sheet with the
     * filtered rows.
     *
     * @param callable $callback Function that returns true for rows to keep.
     * @return static New sheet with filtered rows.
     */
    public function filter(callable $callback): static;

    /**
     * Create a new Sheet with a subset of columns.
     *
     * This method is immutable, so it will return a new sheet with the
     * specified columns.
     *
     * @param array<int|string> $columns Column indices or names to include.
     * @return static New sheet with only the specified columns.
     */
    public function selectColumns(array $columns): static;
}
