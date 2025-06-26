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

/**
 * Interface for type casting operations in spreadsheets.
 *
 * This interface defines methods for casting values when reading from or
 * writing to spreadsheets, ensuring proper type conversions and data integrity.
 */
interface SpreadsheetCasterInterface
{
    /**
     * Cast values in a spreadsheet after loading operations.
     *
     * This method transforms raw values from a spreadsheet into appropriate PHP
     * types, such as converting string numerics to integers or floats,
     * recognizing dates, and handling boolean values.
     *
     * @param SpreadsheetInterface $spreadsheet The spreadsheet with values to cast.
     * @return SpreadsheetInterface A new spreadsheet with cast values.
     */
    public function castAfterLoad(
        SpreadsheetInterface $spreadsheet
    ): SpreadsheetInterface;

    /**
     * Cast values in a spreadsheet before dumping operations.
     *
     * This method prepares PHP values for storage in a spreadsheet format, such
     * as converting booleans to strings, formatting dates, and ensuring other
     * values are in a format suitable for the target spreadsheet.
     *
     * @param SpreadsheetInterface $spreadsheet The spreadsheet with values to cast.
     * @return SpreadsheetInterface A new spreadsheet with cast values.
     */
    public function castBeforeDump(
        SpreadsheetInterface $spreadsheet
    ): SpreadsheetInterface;
}
