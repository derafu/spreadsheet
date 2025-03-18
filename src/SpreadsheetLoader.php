<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Spreadsheet;

use Derafu\Spreadsheet\Contract\SpreadsheetCasterInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetFactoryInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetLoaderInterface;
use Derafu\Spreadsheet\Exception\SpreadsheetFileNotFoundException;

/**
 * Main loader class for spreadsheet files.
 *
 * Handles loading spreadsheet files in various formats.
 */
final class SpreadsheetLoader implements SpreadsheetLoaderInterface
{
    /**
     * Create a new Loader instance.
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
    public function loadFromFile(
        string $filepath,
        ?string $format = null
    ): SpreadsheetInterface {
        $this->validateFile($filepath);

        $handler = $this->factory->createFormatHandler($filepath, $format);

        $spreadsheet = $handler->loadFromFile($filepath);

        return $this->caster->castAfterLoad($spreadsheet);
    }

    /**
     * {@inheritDoc}
     */
    public function loadFromString(
        string $data,
        ?string $format = null
    ): SpreadsheetInterface {
        $handler = $this->factory->createFormatHandler(
            'dummy.' . ($format ?? $this->format),
            $format ?? $this->format
        );

        return $handler->loadFromString($data);
    }

    /**
     * Validate that a file exists and is readable.
     *
     * @param string $filepath Path to the file to validate.
     * @throws SpreadsheetFileNotFoundException If the file doesn't exist or isn't readable.
     */
    private function validateFile(string $filepath): void
    {
        if (!file_exists($filepath)) {
            throw new SpreadsheetFileNotFoundException([
                'File not found: "{filepath}".',
                'filepath' => $filepath,
            ]);
        }

        if (!is_readable($filepath)) {
            throw new SpreadsheetFileNotFoundException([
                'File not readable: "{filepath}".',
                'filepath' => $filepath,
            ]);
        }
    }
}
