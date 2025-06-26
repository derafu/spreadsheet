<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Spreadsheet;

use Derafu\Spreadsheet\Contract\SpreadsheetCasterInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetDumperInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetFactoryInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Exception\SpreadsheetDumpException;

/**
 * Main dumper class for spreadsheet files
 *
 * Handles dumping data to spreadsheet files in various formats.
 */
final class SpreadsheetDumper implements SpreadsheetDumperInterface
{
    /**
     * Create a new Dumper instance.
     *
     * @param SpreadsheetFactoryInterface $factory Factory for creating format-specific handlers.
     * @param SpreadsheetCasterInterface $caster Caster for casting values to the correct type.
     * @param string $format Default format to use.
     */
    public function __construct(
        private readonly SpreadsheetFactoryInterface $factory = new SpreadsheetFactory(),
        private readonly SpreadsheetCasterInterface $caster = new SpreadsheetCaster(),
        private readonly string $format = 'xlsx'
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function dumpToFile(
        SpreadsheetInterface $spreadsheet,
        ?string $filepath = null,
        ?string $format = null
    ): string {
        $handler = $this->factory->createFormatHandler(
            $filepath,
            $format ?? $this->format
        );

        // Ensure the target directory exists.
        $directory = dirname($filepath);
        if (
            !is_dir($directory)
            && !mkdir($directory, 0755, true)
            && !is_dir($directory)
        ) {
            throw new SpreadsheetDumpException([
                'Directory "{directory}" could not be created.',
                'directory' => $directory,
            ]);
        }

        $writeSpreadsheet = $this->caster->castBeforeDump($spreadsheet);

        return $handler->dumpToFile($writeSpreadsheet, $filepath);
    }

    /**
     * {@inheritDoc}
     */
    public function dumpToString(
        SpreadsheetInterface $spreadsheet,
        ?string $format = null
    ): string {
        $handler = $this->factory->createFormatHandler(
            'dummy.' . ($format ?? $this->format),
            $format ?? $this->format
        );

        return $handler->dumpToString($spreadsheet);
    }
}
