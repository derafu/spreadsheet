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

use Derafu\Spreadsheet\Exception\SpreadsheetFormatNotSupportedException;

/**
 * Interface for the factory class that creates format-specific handlers.
 */
interface SpreadsheetFactoryInterface
{
    /**
     * Create a spreadsheet from an array.
     *
     * @param array<string,array<int,array<int|string,mixed>>> $data
     * @return SpreadsheetInterface A new spreadsheet with the data.
     */
    public function create(array $data = []): SpreadsheetInterface;

    /**
     * Create a format handler for the given file.
     *
     * @param string $filepath Path to the file.
     * @param string|null $formatOverride Optional format override.
     * @return SpreadsheetFormatHandlerInterface The appropriate format handler.
     *
     * @throws SpreadsheetFormatNotSupportedException If the format is not supported.
     */
    public function createFormatHandler(
        string $filepath,
        ?string $formatOverride = null
    ): SpreadsheetFormatHandlerInterface;

    /**
     * Detect the format of a file based on its extension.
     *
     * @param string $filepath Path to the file.
     * @return string The detected format (lowercase extension without dot).
     *
     * @throws SpreadsheetFormatNotSupportedException If the format could not be detected
     * or is not supported.
     */
    public function detectFormat(string $filepath): string;

    /**
     * Register a custom format handler.
     *
     * @param string $extension File extension (without dot).
     * @param class-string<SpreadsheetFormatHandlerInterface> $handlerClass Format handler class.
     * @return static
     */
    public function registerFormatHandler(
        string $extension,
        string $handlerClass
    ): static;

    /**
     * Get all supported formats.
     *
     * @return array<string> List of supported format extensions.
     */
    public function getSupportedFormats(): array;
}
