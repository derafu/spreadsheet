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

use Derafu\Spreadsheet\Exception\DumpException;
use Derafu\Spreadsheet\Exception\FileNotFoundException;
use Derafu\Spreadsheet\Exception\LoadException;

/**
 * Interface for spreadsheet format handlers.
 *
 * This interface defines the standard methods that all spreadsheet format
 * implementations must provide.
 */
interface FormatHandlerInterface
{
    /**
     * Read data from a spreadsheet file.
     *
     * When loading a file, the default name for the sheets in the spreadsheet
     * will be the name of the file without the extension if the sheet name
     * cannot be determined from data. If are multiple sheets in the data,
     * the default name will suffix with an index.
     *
     * @param string $filepath Path to the file to read.
     * @return SpreadsheetInterface A spreadsheet object containing the data.
     * @throws LoadException If the file cannot be read.
     * @throws FileNotFoundException If the file does not exist.
     */
    public function loadFromFile(string $filepath): SpreadsheetInterface;

    /**
     * Create a spreadsheet from a string.
     *
     * @param string $data The string to create the spreadsheet from.
     * @param string|null $sheetName The default name for the sheets in the
     * spreadsheet if no name can be determined from data. If null, the
     * default global name will be used. If are multiple sheets in the data,
     * the default name will suffix with an index.
     * @return SpreadsheetInterface A spreadsheet object containing the data.
     */
    public function loadFromString(
        string $data,
        ?string $sheetName = null
    ): SpreadsheetInterface;

    /**
     * Write data to a spreadsheet file.
     *
     * @param SpreadsheetInterface $spreadsheet The spreadsheet to write.
     * @param string|null $filepath Path to the file to write.
     * @return string The path to the file that was written.
     * @throws DumpException If the file cannot be written.
     */
    public function dumpToFile(
        SpreadsheetInterface $spreadsheet,
        ?string $filepath = null
    ): string;

    /**
     * Create a string from a spreadsheet.
     *
     * @param SpreadsheetInterface $spreadsheet The spreadsheet to create the
     * string from.
     * @return string The string containing the data.
     */
    public function dumpToString(
        SpreadsheetInterface $spreadsheet
    ): string;

    /**
     * Get the format's file extension.
     *
     * @return string The file extension without the leading dot.
     */
    public function getExtension(): string;

    /**
     * Get the format's MIME type.
     *
     * @return string The MIME type.
     */
    public function getMimeType(): string;
}
