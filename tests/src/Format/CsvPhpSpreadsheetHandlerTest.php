<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet\Format;

use Derafu\Spreadsheet\Format\CsvPhpSpreadsheetHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use Derafu\TestsSpreadsheet\Abstract\AbstractCsvFormatHandler;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CsvPhpSpreadsheetHandler::class)]
#[CoversClass(Spreadsheet::class)]
#[CoversClass(Sheet::class)]
#[CoversClass(SpreadsheetCaster::class)]
final class CsvPhpSpreadsheetHandlerTest extends AbstractCsvFormatHandler
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->formatHandler = new CsvPhpSpreadsheetHandler();
    }
}
