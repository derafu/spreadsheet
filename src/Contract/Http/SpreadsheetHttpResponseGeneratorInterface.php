<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Spreadsheet\Contract\Http;

use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Exception\SpreadsheetDumpException;
use Derafu\Spreadsheet\Exception\SpreadsheetFormatNotSupportedException;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface for generating HTTP responses from spreadsheet data.
 */
interface SpreadsheetHttpResponseGeneratorInterface
{
    /**
     * Create a PSR-7 response with spreadsheet data for download.
     *
     * @param SpreadsheetInterface $spreadsheet The spreadsheet to include in
     * the response.
     * @param string|null $filename Filename for the download (without path).
     * @param string|null $format Format to output (e.g., 'xlsx', 'ods', 'csv').
     * @return ResponseInterface Response with spreadsheet data.
     *
     * @throws SpreadsheetFormatNotSupportedException If the requested format is not
     * supported.
     * @throws SpreadsheetDumpException If there's an error generating the output.
     */
    public function createResponse(
        SpreadsheetInterface $spreadsheet,
        ?string $filename = null,
        ?string $format = null
    ): ResponseInterface;
}
