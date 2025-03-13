<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Spreadsheet\Format;

use Derafu\Spreadsheet\Abstract\AbstractPhpSpreadsheetFormatHandler;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;

/**
 * Excel 97-2003 (XLS) format handler using PhpSpreadsheet.
 *
 * Handles reading and writing legacy Excel files in the XLS format.
 */
class XlsHandler extends AbstractPhpSpreadsheetFormatHandler
{
    /**
     * Create a new XLS handler.
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
        return 'Xls';
    }

    /**
     * {@inheritDoc}
     */
    protected function getWriterType(): string
    {
        return 'Xls';
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
        // No special configuration needed for Excel 97 writer.
    }

    /**
     * {@inheritDoc}
     */
    public function getExtension(): string
    {
        return 'xls';
    }

    /**
     * {@inheritDoc}
     */
    public function getMimeType(): string
    {
        return 'application/vnd.ms-excel';
    }
}
