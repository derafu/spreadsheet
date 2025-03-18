<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Spreadsheet;

use ArrayIterator;
use Derafu\Spreadsheet\Contract\SheetInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Represents a complete spreadsheet document with multiple sheets.
 */
final class Spreadsheet implements SpreadsheetInterface
{
    /**
     * Collection of sheets.
     *
     * @var array<string, SheetInterface>
     */
    private array $sheets = [];

    /**
     * Name of the active sheet.
     *
     * @var string
     */
    private string $activeSheetName;

    /**
     * Create a new Spreadsheet instance.
     *
     * @param array<string, SheetInterface> $sheets Initial sheets (optional).
     */
    public function __construct(array $sheets = [])
    {
        foreach ($sheets as $sheet) {
            $this->addSheet($sheet);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSheets(): array
    {
        return $this->sheets;
    }

    /**
     * {@inheritDoc}
     */
    public function getSheet(string $name): ?SheetInterface
    {
        return $this->sheets[$name] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function addSheet(SheetInterface $sheet): static
    {
        $this->sheets[$sheet->getName()] = $sheet;

        // If no active sheet is set, make this the active sheet.
        if (!isset($this->activeSheetName)) {
            $this->activeSheetName = $sheet->getName();
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function removeSheet(string $name): static
    {
        if (isset($this->sheets[$name])) {
            unset($this->sheets[$name]);

            // If the active sheet was removed, set a new active sheet if there
            // are still sheets.
            if ($this->activeSheetName === $name) {
                if (empty($this->sheets)) {
                    unset($this->activeSheetName);
                } else {
                    $this->activeSheetName = array_key_first($this->sheets);
                }
            }
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function hasSheet(string $name): bool
    {
        return isset($this->sheets[$name]);
    }

    /**
     * {@inheritDoc}
     */
    public function getSheetNames(): array
    {
        return array_keys($this->sheets);
    }

    /**
     * {@inheritDoc}
     */
    public function setActiveSheet(string $name): static
    {
        if (!$this->hasSheet($name)) {
            throw new InvalidArgumentException(sprintf(
                'Sheet "%s" does not exist.',
                $name
            ));
        }

        $this->activeSheetName = $name;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveSheet(): SheetInterface
    {
        if (!isset($this->activeSheetName)) {
            // This should never happen, because we always set an active sheet
            // when the sheets are added to the spreadsheet. Only happen if
            // the spreadsheet is created without sheets and this method is
            // called.
            throw new RuntimeException('No active sheet has been set.');
        }

        return $this->sheets[$this->activeSheetName];
    }

    /**
     * {@inheritDoc}
     */
    public function createSheet(
        string $name,
        array $rows = [],
        bool $isAssociative = false
    ): SheetInterface {
        $sheet = new Sheet($name, $rows, $isAssociative);
        $this->addSheet($sheet);

        return $sheet;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->sheets as $name => $sheet) {
            $result[$name] = $sheet->toArray();
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->sheets);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return count($this->sheets);
    }

    /**
     * {@inheritDoc}
     */
    public static function fromArray(array $data): static
    {
        $spreadsheet = new static();

        foreach ($data as $name => $rows) {
            $spreadsheet->createSheet($name, $rows);
        }

        return $spreadsheet;
    }
}
