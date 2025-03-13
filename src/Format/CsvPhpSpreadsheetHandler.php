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
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet as PhpSpreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;

/**
 * CSV format handler using PhpSpreadsheet.
 *
 * This is an alternative CSV handler using PhpSpreadsheet instead of League
 * CSV. Use this if you want CSV handling consistent with other PhpSpreadsheet
 * formats or you already use PhpSpreadsheet and dont want to change to League
 * CSV.
 */
class CsvPhpSpreadsheetHandler extends AbstractPhpSpreadsheetFormatHandler
{
    /**
     * Create a new CSV handler.
     *
     * @param string $delimiter Delimiter character.
     * @param string $enclosure Enclosure character.
     * @param string $escape Escape character.
     * @param string $inputEncoding Input encoding.
     * @param string $outputEncoding Output encoding.
     * @param string $sheetName Default sheet name.
     */
    public function __construct(
        private readonly string $delimiter = ',',
        private readonly string $enclosure = '"',
        private readonly string $escape = '\\',
        private readonly string $inputEncoding = 'UTF-8',
        private readonly string $outputEncoding = 'UTF-8',
        private readonly string $sheetName = 'Sheet'
    ) {
    }

    /**
     * {@inheritDoc}
     */
    protected function getReaderType(): string
    {
        return 'Csv';
    }

    /**
     * {@inheritDoc}
     */
    protected function getWriterType(): string
    {
        return 'Csv';
    }

    /**
     * {@inheritDoc}
     */
    protected function configureReader(IReader $reader): void
    {
        if ($reader instanceof CsvReader) {
            $reader->setDelimiter($this->delimiter);
            $reader->setEnclosure($this->enclosure);
            $reader->setEscapeCharacter($this->escape);
            $reader->setInputEncoding($this->inputEncoding);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function configureWriter(IWriter $writer): void
    {
        if ($writer instanceof CsvWriter) {
            $writer->setDelimiter($this->delimiter);
            $writer->setEnclosure($this->enclosure);
            $writer->setExcelCompatibility(false);
            $writer->setUseBOM(false);
            $writer->setIncludeSeparatorLine(false);
            $writer->setOutputEncoding($this->outputEncoding);
            $writer->setEnclosureRequired(false);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getExtension(): string
    {
        return 'csv';
    }

    /**
     * {@inheritDoc}
     */
    public function getMimeType(): string
    {
        return 'text/csv';
    }

    /**
     * {@inheritDoc}
     */
    protected function convertPhpSpreadsheetToSpreadsheet(
        PhpSpreadsheet $phpSpreadsheet,
        ?string $overrideSheetName = null
    ): SpreadsheetInterface {
        $spreadsheet = new Spreadsheet();

        foreach ($phpSpreadsheet->getSheetNames() as $sheetName) {
            $phpSheet = $phpSpreadsheet->getSheetByName($sheetName);
            if ($phpSheet === null) {
                continue;
            }

            $sheetName = $overrideSheetName ?? $this->sheetName ?? $sheetName;

            $rows = $phpSheet->toArray();
            $spreadsheet->createSheet($sheetName, $rows);

            // Only one sheet is supported in CSV. This is for make it explicit,
            // because PhpSpreadsheet should not return multiple sheets for CSV.
            break;
        }

        return $spreadsheet;
    }
}
