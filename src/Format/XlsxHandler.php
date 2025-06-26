<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Spreadsheet\Format;

use Derafu\Spreadsheet\Abstract\AbstractPhpSpreadsheetFormatHandler;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;

/**
 * XLSX format handler using PhpSpreadsheet.
 *
 * Handles reading and writing XLSX files (Microsoft Excel 2007+).
 */
class XlsxHandler extends AbstractPhpSpreadsheetFormatHandler
{
    /**
     * Create a new Excel handler.
     *
     * @param bool $readDataOnly Whether to read data only.
     */
    public function __construct(private bool $readDataOnly = false)
    {
    }

    /**
     * {@inheritDoc}
     */
    protected function getReaderType(): string
    {
        return 'Xlsx';
    }

    /**
     * {@inheritDoc}
     */
    protected function getWriterType(): string
    {
        return 'Xlsx';
    }

    /**
     * {@inheritDoc}
     */
    protected function configureReader(IReader $reader): void
    {
        $reader->setReadDataOnly($this->readDataOnly);
    }

    /**
     * {@inheritDoc}
     */
    protected function configureWriter(IWriter $writer): void
    {
        // No special configuration needed for Excel writer.
    }

    /**
     * {@inheritDoc}
     */
    public function getExtension(): string
    {
        return 'xlsx';
    }

    /**
     * {@inheritDoc}
     */
    public function getMimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
}
