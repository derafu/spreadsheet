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
use Derafu\Spreadsheet\Contract\SpreadsheetFormatHandlerInterface;
use Derafu\Spreadsheet\Exception\SpreadsheetLoadException;

/**
 * PDF format handler
 *
 * Handles writing PDF files.
 */
final class PdfHandler extends AbstractPhpSpreadsheetFormatHandler implements SpreadsheetFormatHandlerInterface
{
    /**
     * Create a new PDF handler.
     *
     * @param string $writerType Can be 'Mpdf', 'Dompdf' or 'Tcpdf'.
     */
    public function __construct(
        private readonly string $writerType = 'Mpdf'
    ) {
    }

    /**
     * {@inheritDoc}
     */
    protected function getReaderType(): string
    {
        throw new SpreadsheetLoadException('PDF files are not supported for reading, yet.');
    }

    /**
     * {@inheritDoc}
     */
    protected function getWriterType(): string
    {
        return $this->writerType;
    }

    /**
     * {@inheritDoc}
     */
    public function getExtension(): string
    {
        return 'pdf';
    }

    /**
     * {@inheritDoc}
     */
    public function getMimeType(): string
    {
        return 'application/pdf';
    }
}
