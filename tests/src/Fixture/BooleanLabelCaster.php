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

use Closure;
use Derafu\Spreadsheet\Contract\SpreadsheetCasterInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;

/**
 * A custom caster for CSV integrations that represent booleans as yes/no.
 */
final class BooleanLabelCaster implements SpreadsheetCasterInterface
{
    public function castAfterLoad(SpreadsheetInterface $spreadsheet): SpreadsheetInterface
    {
        return $this->convert($spreadsheet, static fn (mixed $value): mixed => match ($value) {
            'yes' => true,
            'no' => false,
            default => $value,
        });
    }

    public function castBeforeDump(SpreadsheetInterface $spreadsheet): SpreadsheetInterface
    {
        return $this->convert($spreadsheet, static fn (mixed $value): mixed => match ($value) {
            true => 'yes',
            false => 'no',
            default => $value,
        });
    }

    /** @param Closure(mixed): mixed $convert */
    private function convert(SpreadsheetInterface $spreadsheet, Closure $convert): SpreadsheetInterface
    {
        $result = clone $spreadsheet;
        foreach ($spreadsheet->getSheets() as $sheet) {
            $converted = clone $sheet;
            $converted->map($convert);
            $result->addSheet($converted);
        }

        return $result;
    }
}
