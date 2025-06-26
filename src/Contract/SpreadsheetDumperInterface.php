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

use Derafu\Spreadsheet\Exception\SpreadsheetDumpException;
use Derafu\Spreadsheet\Exception\SpreadsheetFormatNotSupportedException;

/**
 * Interface for spreadsheet writing functionality.
 *
 * This interface defines the standard methods that all spreadsheet writing
 * implementations must provide.
 */
interface SpreadsheetDumperInterface
{
    /**
     * Write data to a spreadsheet file.
     *
     * You should specify the filepath or format. If you don't, a default format
     * will be used to generate a filename. If you specify the filepath, the
     * format will be guessed from the file extension, ignoring the format
     * parameter unless the extension is not supported. In this last case, the
     * format parameter will be used. This can cause inconsistency between the
     * format and the file extension.
     *
     * @param SpreadsheetInterface $spreadsheet The spreadsheet to write.
     * @param string|null $filepath Path to the output file.
     * @param string|null $format Optional format (e.g., 'xlsx', 'ods').
     * @return string The path to the file that was written.
     *
     * @throws SpreadsheetFormatNotSupportedException If the requested format is not
     * supported.
     * @throws SpreadsheetDumpException If there's an error writing the file.
     */
    public function dumpToFile(
        SpreadsheetInterface $spreadsheet,
        ?string $filepath = null,
        ?string $format = null
    ): string;

    /**
     * Create a string from a spreadsheet.
     *
     * You should specify the format. If you don't, a default format will be
     * used.
     *
     * @param SpreadsheetInterface $spreadsheet The spreadsheet to create the
     * string from.
     * @param string|null $format Optional format (e.g., 'xlsx', 'ods').
     * @return string The string containing the data.
     *
     * @throws SpreadsheetFormatNotSupportedException If the requested format is not
     * supported.
     * @throws SpreadsheetDumpException If there's an error creating the string.
     */
    public function dumpToString(
        SpreadsheetInterface $spreadsheet,
        ?string $format = null
    ): string;
}
