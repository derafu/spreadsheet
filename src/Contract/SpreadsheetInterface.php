<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Spreadsheet\Contract;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;

/**
 * Interface for spreadsheet objects.
 *
 * Represents a complete spreadsheet document with multiple sheets.
 */
interface SpreadsheetInterface extends IteratorAggregate, Countable
{
    /**
     * Get all sheets in the spreadsheet.
     *
     * @return array<string, SheetInterface> An array of sheets, with sheet
     * names as keys.
     */
    public function getSheets(): array;

    /**
     * Get a specific sheet by name.
     *
     * @param string $name Sheet name.
     * @return SheetInterface|null The sheet with the given name, or null if
     * not found.
     */
    public function getSheet(string $name): ?SheetInterface;

    /**
     * Add or replace a sheet.
     *
     * @param SheetInterface $sheet The sheet to add.
     * @return $this For method chaining.
     */
    public function addSheet(SheetInterface $sheet): static;

    /**
     * Remove a sheet by name.
     *
     * @param string $name Sheet name.
     * @return $this For method chaining.
     */
    public function removeSheet(string $name): static;

    /**
     * Check if a sheet with the given name exists.
     *
     * @param string $name Sheet name.
     * @return bool True if the sheet exists, false otherwise.
     */
    public function hasSheet(string $name): bool;

    /**
     * Get the sheet names.
     *
     * @return array<int, string> An array of sheet names.
     */
    public function getSheetNames(): array;

    /**
     * Set the active sheet.
     *
     * @param string $name Sheet name.
     * @return $this For method chaining.
     * @throws InvalidArgumentException If the sheet doesn't exist.
     */
    public function setActiveSheet(string $name): static;

    /**
     * Get the active sheet.
     *
     * @return SheetInterface The active sheet.
     */
    public function getActiveSheet(): SheetInterface;

    /**
     * Create a new sheet.
     *
     * @param string $name Sheet name.
     * @param array<int, array<int|string, mixed>> $rows Sheet data (optional).
     * @param bool $isAssociative Whether the data is associative (optional).
     * @return SheetInterface The newly created sheet.
     */
    public function createSheet(
        string $name,
        array $rows = [],
        bool $isAssociative = false
    ): SheetInterface;

    /**
     * Convert the spreadsheet to an array structure.
     *
     * @return array Array representation.
     */
    public function toArray(): array;

    /**
     * Get an iterator for the sheets.
     *
     * @return ArrayIterator<string, SheetInterface>
     */
    public function getIterator(): ArrayIterator;

    /**
     * Get the number of sheets.
     *
     * @return int Number of sheets.
     */
    public function count(): int;

    /**
     * Create a spreadsheet from an array structure.
     *
     * @param array<string,array<int,array<int|string,mixed>>> $data
     * @return static New spreadsheet instance.
     */
    public static function fromArray(array $data): static;
}
