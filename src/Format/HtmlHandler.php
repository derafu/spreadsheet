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
use Derafu\Spreadsheet\Contract\FormatHandlerInterface;

/**
 * HTML format handler
 *
 * Handles reading and writing HTML files.
 */
final class HtmlHandler extends AbstractPhpSpreadsheetFormatHandler implements FormatHandlerInterface
{
    /**
     * Create a new HTML handler.
     */
    public function __construct()
    {
    }

    /**
     * {@inheritDoc}
     */
    protected function getReaderType(): string
    {
        return 'Html';
    }

    /**
     * {@inheritDoc}
     */
    protected function getWriterType(): string
    {
        return 'Html';
    }

    /**
     * {@inheritDoc}
     */
    public function getExtension(): string
    {
        return 'html';
    }

    /**
     * {@inheritDoc}
     */
    public function getMimeType(): string
    {
        return 'text/html';
    }
}
