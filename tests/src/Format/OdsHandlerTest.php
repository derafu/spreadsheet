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

use Derafu\Spreadsheet\Format\OdsHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use Derafu\TestsSpreadsheet\Abstract\AbstractSpreadsheetFormatHandler;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OdsHandler::class)]
#[CoversClass(Spreadsheet::class)]
#[CoversClass(Sheet::class)]
#[CoversClass(SpreadsheetCaster::class)]
final class OdsHandlerTest extends AbstractSpreadsheetFormatHandler
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->formatHandler = new OdsHandler();
    }

    protected function getFileExtension(): string
    {
        return 'ods';
    }

    protected function getMimeType(): string
    {
        return 'application/vnd.oasis.opendocument.spreadsheet';
    }
}
