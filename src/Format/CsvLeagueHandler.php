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

use Derafu\Spreadsheet\Contract\SpreadsheetFormatHandlerInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Exception\SpreadsheetDumpException;
use Derafu\Spreadsheet\Exception\SpreadsheetFileNotFoundException;
use Derafu\Spreadsheet\Exception\SpreadsheetLoadException;
use Derafu\Spreadsheet\Spreadsheet;
use Exception;
use League\Csv\Reader;
use League\Csv\Writer;

/**
 * CSV format handler using League CSV.
 *
 * Handles reading and writing CSV files.
 */
final class CsvLeagueHandler implements SpreadsheetFormatHandlerInterface
{
    /**
     * Create a new CSV handler.
     *
     * @param string $delimiter Delimiter character.
     * @param string $enclosure Enclosure character.
     * @param string $escape Escape character.
     * @param string $sheetName Default sheet name.
     */
    public function __construct(
        private readonly string $delimiter = ',',
        private readonly string $enclosure = '"',
        private readonly string $escape = '\\',
        private readonly string $sheetName = 'Sheet'
    ) {
    }

    /**
     * Read data from a CSV file.
     *
     * In CSV files only one sheet is supported, this is not a limitation of
     * this library but of the CSV format. So the returned Spreadsheet will
     * always have only one sheet, with the name of the file without the
     * extension.
     *
     * {@inheritDoc}
     */
    public function loadFromFile(string $filepath): SpreadsheetInterface
    {
        if (!file_exists($filepath)) {
            throw new SpreadsheetFileNotFoundException([
                'File "{filepath}" not found.',
                'filepath' => $filepath,
            ]);
        }

        try {
            // Read the file content.
            $content = file_get_contents($filepath);
            if ($content === false) {
                throw new SpreadsheetLoadException([
                    'Could not read file: "{filepath}".',
                    'filepath' => $filepath,
                ]);
            }

            // Load the spreadsheet from the content.
            return $this->loadFromString(
                $content,
                pathinfo($filepath, PATHINFO_FILENAME)
            );
        } catch (Exception $e) {
            if (
                $e instanceof SpreadsheetLoadException
                || $e instanceof SpreadsheetFileNotFoundException
            ) {
                throw $e;
            }

            throw new SpreadsheetLoadException([
                'Error reading CSV file: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Create a spreadsheet from a CSV string.
     *
     * {@inheritDoc}
     */
    public function loadFromString(
        string $data,
        ?string $sheetName = null
    ): SpreadsheetInterface {
        try {
            // Create an in-memory CSV reader.
            $csv = Reader::createFromString($data);
            $csv->setDelimiter($this->delimiter);
            $csv->setEnclosure($this->enclosure);
            $csv->setEscape($this->escape);

            // Get records as array.
            $records = iterator_to_array($csv->getRecords());

            // If no records, create one empty row.
            if (empty($records)) {
                $records = [[null]]; // Compatible with PhpSpreadsheet.
            }

            // Process types in each row.
            $rows = [];
            foreach ($records as $index => $record) {
                $rows[$index] = $record;
            }

            // Create a new spreadsheet with a single sheet.
            $spreadsheet = new Spreadsheet();
            $sheetName = $sheetName ?? $this->sheetName;
            $spreadsheet->createSheet($sheetName, $rows);

            return $spreadsheet;
        } catch (Exception $e) {
            throw new SpreadsheetLoadException([
                'Error processing CSV data: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Write data to a CSV file.
     *
     * {@inheritDoc}
     */
    public function dumpToFile(
        SpreadsheetInterface $spreadsheet,
        ?string $filepath = null
    ): string {
        try {
            // If no filepath provided, create one.
            if ($filepath === null) {
                $filepath = tempnam(sys_get_temp_dir(), 'csv_') . '.csv';
            }

            // Prepare directory.
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

            // Generate CSV content.
            $csvContent = $this->dumpToString($spreadsheet);

            // Write to file.
            $result = file_put_contents($filepath, $csvContent);

            if ($result === false) {
                throw new SpreadsheetDumpException([
                    'Could not write to file: "{filepath}".',
                    'filepath' => $filepath,
                ]);
            }

            return $filepath;
        } catch (Exception $e) {
            if ($e instanceof SpreadsheetDumpException) {
                throw $e;
            }

            throw new SpreadsheetDumpException([
                'Error writing CSV file: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Create a CSV string from a spreadsheet.
     *
     * {@inheritDoc}
     */
    public function dumpToString(
        SpreadsheetInterface $spreadsheet
    ): string {
        try {
            // Create an in-memory CSV writer.
            $csv = Writer::createFromString('');
            $csv->setDelimiter($this->delimiter);
            $csv->setEnclosure($this->enclosure);
            $csv->setEscape($this->escape);

            // CSV only supports one sheet, so use the first one.
            $sheetNames = $spreadsheet->getSheetNames();
            if (empty($sheetNames)) {
                // No sheets to write, return empty string.
                return '';
            }

            $sheet = $spreadsheet->getSheet($sheetNames[0]);
            if ($sheet === null) {
                return '';
            }

            // If sheet is in associative format, we need to handle headers.
            if ($sheet->isAssociative()) {
                // Get the header row.
                $headerRow = $sheet->getHeaderRow();

                // Insert headers as the first row.
                $csv->insertOne($headerRow);

                // Now insert each data row, maintaining column order.
                foreach ($sheet->getDataRows() as $row) {
                    $orderedRow = [];
                    foreach ($headerRow as $header) {
                        $orderedRow[] = $row[$header] ?? null;
                    }
                    $csv->insertOne($orderedRow);
                }
            } else {
                // For indexed format, just insert all rows.
                $csv->insertAll($sheet->getRows());
            }

            return $csv->toString();
        } catch (Exception $e) {
            throw new SpreadsheetDumpException([
                'Error creating CSV string: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
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
}
