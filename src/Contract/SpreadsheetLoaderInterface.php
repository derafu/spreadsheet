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

use Derafu\Spreadsheet\Exception\SpreadsheetFileNotFoundException;
use Derafu\Spreadsheet\Exception\SpreadsheetFormatNotSupportedException;
use Derafu\Spreadsheet\Exception\SpreadsheetLoadException;

/**
 * Interface for reading spreadsheet files.
 */
interface SpreadsheetLoaderInterface
{
    /**
     * Read a spreadsheet file and return its data.
     *
     * If you don't specify the format, it will be guessed from the file
     * extension. This only works if the extension is supported by the format
     * handler. If you use custom extensions, you should specify the format.
     *
     * @param string $filepath Path to the spreadsheet file.
     * @param string|null $format Optional format override (e.g., 'xlsx', 'ods').
     * @return SpreadsheetInterface A spreadsheet object containing the data.
     *
     * @throws SpreadsheetFileNotFoundException If the file doesn't exist.
     * @throws SpreadsheetFormatNotSupportedException If the file format is not supported.
     * @throws SpreadsheetLoadException If there's an error reading the file.
     */
    public function loadFromFile(
        string $filepath,
        ?string $format = null
    ): SpreadsheetInterface;

    /**
     * Create a spreadsheet from a string.
     *
     * @param string $data The string to create the spreadsheet from.
     * @param string $format The format of the spreadsheet data.
     * @return SpreadsheetInterface A spreadsheet object containing the data.
     *
     * @throws SpreadsheetFormatNotSupportedException If the requested format is not
     * supported.
     * @throws SpreadsheetLoadException If there's an error creating the spreadsheet.
     */
    public function loadFromString(
        string $data,
        string $format
    ): SpreadsheetInterface;
}
