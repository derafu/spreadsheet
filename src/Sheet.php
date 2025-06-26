<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Spreadsheet;

use ArrayIterator;
use Derafu\Spreadsheet\Contract\SheetInterface;

/**
 * Represents a spreadsheet sheet with its data.
 *
 * This class provides a structured way to work with a single sheet of data,
 * including methods for manipulating rows and cells.
 */
final class Sheet implements SheetInterface
{
    /**
     * Create a new Sheet instance.
     *
     * @param string $name Name of the sheet.
     * @param array<int, array<int|string, mixed>> $rows Rows of data.
     * @param bool $isAssociative Whether row keys are associative (column
     * headers used as keys).
     */
    public function __construct(
        private string $name,
        private array $rows = [],
        private bool $isAssociative = false
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * {@inheritDoc}
     */
    public function setRows(array $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getRow(int $index): ?array
    {
        return $this->rows[$index] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function setRow(int $index, array $row): static
    {
        $this->rows[$index] = $row;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function addRow(array $row): static
    {
        $index = count($this->rows);
        $this->setRow($index, $row);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getCell(int $rowIndex, int|string $columnIndex): mixed
    {
        if (!isset($this->rows[$rowIndex])) {
            return null;
        }

        return $this->rows[$rowIndex][$columnIndex] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function setCell(
        int $rowIndex,
        int|string $columnIndex,
        mixed $value
    ): static {
        if (!isset($this->rows[$rowIndex])) {
            $this->rows[$rowIndex] = [];
        }

        $this->rows[$rowIndex][$columnIndex] = $value;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function isAssociative(): bool
    {
        return $this->isAssociative;
    }

    /**
     * {@inheritDoc}
     */
    public function getColumnNames(): array
    {
        if (!$this->isAssociative || empty($this->rows)) {
            return [];
        }

        // Get all unique keys from all rows.
        $allKeys = [];
        foreach ($this->rows as $row) {
            $allKeys = array_unique(array_merge($allKeys, array_keys($row)));
        }

        return $allKeys;
    }

    /**
     * {@inheritDoc}
     */
    public function sort(callable $callback): static
    {
        usort($this->rows, $callback);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function map(callable $callback): static
    {
        foreach ($this->rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $this->rows[$rowIndex][$colIndex] = $callback(
                    $value,
                    $rowIndex,
                    $colIndex
                );
            }
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaderRow(): array
    {
        // For empty sheets, return an empty array.
        if (empty($this->rows)) {
            return [];
        }

        // For indexed sheets, return the first row.
        if (!$this->isAssociative) {
            return $this->rows[0] ?? [];
        }

        // For associative sheets, return the column names.
        // (Get unique keys from all rows to handle varying column sets).
        $allKeys = [];
        foreach ($this->rows as $row) {
            $allKeys = array_unique(array_merge($allKeys, array_keys($row)));
        }

        return $allKeys;
    }

    /**
     * {@inheritDoc}
     */
    public function getDataRows(): array
    {
        // For empty sheets, return an empty array.
        if (empty($this->rows)) {
            return [];
        }

        // For indexed sheets, return all rows except the first one.
        if (!$this->isAssociative) {
            return array_slice($this->rows, 1);
        }

        // For associative sheets, return all rows (headers are already
        // extracted).
        return $this->rows;
    }

    /**
     * {@inheritDoc}
     */
    public function getDataIterator(): ArrayIterator
    {
        return new ArrayIterator($this->getDataRows());
    }

    /**
     * {@inheritDoc}
     */
    public function countData(): int
    {
        return count($this->getDataRows());
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->rows);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'rows' => $this->rows,
            'isAssociative' => $this->isAssociative,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function toAssociative(): static
    {
        // If already associative, return self (no changes required).
        if ($this->isAssociative) {
            return $this;
        }

        // Create a new instance with the same name and associative data.
        $newSheet = new static(
            name: $this->name,
            rows: [],
            isAssociative: true
        );

        // If no rows, just return the empty associative sheet.
        if (empty($this->rows)) {
            return $newSheet;
        }

        // Get headers from first row.
        $headers = $this->rows[0];
        $associativeRows = [];

        // Start from index 1 (skip headers).
        for ($i = 1; $i < count($this->rows); $i++) {
            $row = $this->rows[$i];
            $associativeRow = [];

            foreach ($headers as $index => $header) {
                $associativeRow[$header] = $row[$index] ?? null;
            }

            $associativeRows[] = $associativeRow;
        }

        // Set the new rows.
        $newSheet->setRows($associativeRows);

        return $newSheet;
    }

    /**
     * {@inheritDoc}
     */
    public function toIndexed(): static
    {
        // If already indexed, return self (no changes required).
        if (!$this->isAssociative) {
            return $this;
        }

        // Create a new instance with the same name and indexed data, this is
        // the default behavior.
        $newSheet = new static($this->name);

        // If no rows, just return the empty indexed sheet.
        if (empty($this->rows)) {
            return $newSheet;
        }

        // Get all unique keys from all rows.
        $allKeys = [];
        foreach ($this->rows as $row) {
            $allKeys = array_unique(array_merge($allKeys, array_keys($row)));
        }

        // Create header row.
        $indexedRows = [$allKeys];

        // Convert each row.
        foreach ($this->rows as $row) {
            $indexedRow = [];
            foreach ($allKeys as $index => $key) {
                $indexedRow[$index] = $row[$key] ?? null;
            }
            $indexedRows[] = $indexedRow;
        }

        // Set the new rows.
        $newSheet->setRows($indexedRows);

        return $newSheet;
    }

    /**
     * {@inheritDoc}
     */
    public function filter(callable $callback): static
    {
        // Create a new instance with the same name and associative state.
        $newSheet = new static($this->name, [], $this->isAssociative);

        // Apply filter to rows and reindex.
        $filteredRows = array_filter($this->rows, $callback);
        $newSheet->setRows(array_values($filteredRows));

        return $newSheet;
    }

    /**
     * {@inheritDoc}
     */
    public function selectColumns(array $columns): static
    {
        $newRows = [];

        foreach ($this->rows as $rowIndex => $row) {
            $newRow = [];

            foreach ($columns as $columnIndex) {
                if (isset($row[$columnIndex])) {
                    $newRow[$columnIndex] = $row[$columnIndex];
                }
            }

            $newRows[$rowIndex] = $newRow;
        }

        return new static($this->name, $newRows, $this->isAssociative);
    }
}
