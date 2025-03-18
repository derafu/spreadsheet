<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Spreadsheet\Exception;

use Derafu\Translation\Exception\Core\TranslatableRuntimeException;

/**
 * Exception thrown when an unsupported file format is requested.
 */
final class SpreadsheetFormatNotSupportedException extends TranslatableRuntimeException
{
}
