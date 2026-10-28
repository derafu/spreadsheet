<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet\Fixture;

use Derafu\Spreadsheet\Contract\SpreadsheetCasterInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;

/**
 * Encodes text with a reversible prefix to expose consumer caster replacement.
 */
final class PrefixingCaster implements SpreadsheetCasterInterface
{
    public int $readCalls = 0;

    public int $writeCalls = 0;

    public function __construct(private readonly SpreadsheetCasterInterface $inner)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function castAfterLoad(SpreadsheetInterface $spreadsheet): SpreadsheetInterface
    {
        ++$this->readCalls;
        $decoded = clone $spreadsheet;
        foreach ($spreadsheet->getSheets() as $sheet) {
            $copy = clone $sheet;
            $copy->map(static fn (mixed $value): mixed => is_string($value) && str_starts_with($value, 'stored:')
                ? substr($value, 7)
                : $value);
            $decoded->addSheet($copy);
        }

        return $this->inner->castAfterLoad($decoded);
    }

    /**
     * {@inheritDoc}
     */
    public function castBeforeDump(SpreadsheetInterface $spreadsheet): SpreadsheetInterface
    {
        ++$this->writeCalls;
        $encoded = $this->inner->castBeforeDump($spreadsheet);
        foreach ($encoded->getSheets() as $sheet) {
            $sheet->map(static fn (mixed $value): mixed => is_string($value) ? 'stored:' . $value : $value);
        }

        return $encoded;
    }
}
