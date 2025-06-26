<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet\Format;

use Derafu\Spreadsheet\Format\XlsHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use Derafu\TestsSpreadsheet\Abstract\AbstractSpreadsheetFormatHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

#[CoversClass(XlsHandler::class)]
#[CoversClass(Spreadsheet::class)]
#[CoversClass(Sheet::class)]
#[CoversClass(SpreadsheetCaster::class)]
final class XlsHandlerTest extends AbstractSpreadsheetFormatHandler
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->formatHandler = new XlsHandler();
    }

    protected function tearDown(): void
    {
        // Clean global _OLE_INSTANCES if it exists.
        // PhpSpreadsheet's OLE reader creates a global variable
        // '_OLE_INSTANCES' when processing Excel 97-2003 (.xls) files. This
        // causes PHPUnit to mark tests as "risky" because they modify global
        // state. We clean up this variable here to prevent these warnings and
        // ensure tests don't affect each other through shared global state.
        if (isset($GLOBALS['_OLE_INSTANCES'])) {
            unset($GLOBALS['_OLE_INSTANCES']);
        }

        parent::tearDown();
    }

    #[RunInSeparateProcess]
    public function testLoadFromFileFlatArray(): void
    {
        parent::testLoadFromFileFlatArray();
    }

    protected function getFileExtension(): string
    {
        return 'xls';
    }

    protected function getMimeType(): string
    {
        return 'application/vnd.ms-excel';
    }
}
