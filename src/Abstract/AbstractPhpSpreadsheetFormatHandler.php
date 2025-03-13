<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Spreadsheet\Abstract;

use Derafu\Spreadsheet\Contract\FormatHandlerInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Exception\DumpException;
use Derafu\Spreadsheet\Exception\FileNotFoundException;
use Derafu\Spreadsheet\Exception\LoadException;
use Derafu\Spreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet as PhpSpreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception as WriterException;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;

/**
 * Base class for spreadsheet format handlers using PhpSpreadsheet.
 *
 * This class supports almost all formats supported by PhpSpreadsheet. But not
 * all options for each format in PhpSpreadsheet are supported. The reason is
 * that this library wants to be a simple and easy to use library for
 * spreadsheet processing, not a full featured one. If you need more features,
 * you can use the original PhpSpreadsheet library directly or implement your
 * own FormatHandlerInterface that uses this class as base.
 */
abstract class AbstractPhpSpreadsheetFormatHandler implements FormatHandlerInterface
{
    /**
     * Get the PhpSpreadsheet reader type for this format.
     *
     * @return string The reader type (e.g., 'Xlsx', 'Csv', 'Ods', etc.).
     */
    abstract protected function getReaderType(): string;

    /**
     * Get the PhpSpreadsheet writer type for this format.
     *
     * @return string The writer type (e.g., 'Xlsx', 'Csv', 'Ods', etc.).
     */
    abstract protected function getWriterType(): string;

    /**
     * Configure the reader with format-specific options.
     *
     * @param IReader $reader The reader to configure.
     * @return void
     */
    protected function configureReader(IReader $reader): void
    {
        // Default implementation does nothing. Override in subclasses if needed.
    }

    /**
     * Configure the writer with format-specific options.
     *
     * @param IWriter $writer The writer to configure.
     * @return void
     */
    protected function configureWriter(IWriter $writer): void
    {
        // Default implementation does nothing. Override in subclasses if needed.
    }

    /**
     * {@inheritDoc}
     */
    public function loadFromFile(string $filepath): SpreadsheetInterface
    {
        if (!file_exists($filepath)) {
            throw new FileNotFoundException([
                'File "{filepath}" not found.',
                'filepath' => $filepath,
            ]);
        }

        try {
            $reader = IOFactory::createReader($this->getReaderType());
            $this->configureReader($reader);

            $phpSpreadsheet = $reader->load($filepath);

            return $this->convertPhpSpreadsheetToSpreadsheet(
                $phpSpreadsheet,
                pathinfo($filepath, PATHINFO_FILENAME)
            );
        } catch (ReaderException $e) {
            throw new LoadException([
                'Error reading spreadsheet: "{message}".',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function loadFromString(
        string $data,
        ?string $sheetName = null
    ): SpreadsheetInterface {
        try {
            // Create temporary file with the data.
            $tempFile = tempnam(sys_get_temp_dir(), 'spreadsheet_');
            if ($tempFile === false) {
                throw new LoadException([
                    'Could not create temporary file for loading from string.',
                ]);
            }

            // Write data to the temporary file.
            file_put_contents($tempFile, $data);

            try {
                // Load from the temporary file.
                $reader = IOFactory::createReader($this->getReaderType());
                $this->configureReader($reader);

                $phpSpreadsheet = $reader->load($tempFile);

                return $this->convertPhpSpreadsheetToSpreadsheet(
                    $phpSpreadsheet,
                    $sheetName
                );
            } finally {
                // Clean up temporary file.
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        } catch (ReaderException $e) {
            throw new LoadException([
                'Error reading spreadsheet from string: "{message}".',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function dumpToFile(
        SpreadsheetInterface $spreadsheet,
        ?string $filepath = null
    ): string {
        try {
            // Generate a file path if none provided.
            if ($filepath === null) {
                $filepath = tempnam(sys_get_temp_dir(), 'spreadsheet_')
                    . '.' . $this->getExtension()
                ;
            }

            // Prepare directory.
            $directory = dirname($filepath);
            if (
                !is_dir($directory)
                && !mkdir($directory, 0755, true)
                && !is_dir($directory)
            ) {
                throw new DumpException([
                    'Directory "{directory}" could not be created.',
                    'directory' => $directory,
                ]);
            }

            $phpSpreadsheet = $this->convertSpreadsheetToPhpSpreadsheet(
                $spreadsheet
            );
            $writer = IOFactory::createWriter(
                $phpSpreadsheet,
                $this->getWriterType()
            );
            $this->configureWriter($writer);

            $writer->save($filepath);

            return $filepath;
        } catch (WriterException $e) {
            throw new DumpException([
                'Error writing spreadsheet: "{message}".',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function dumpToString(SpreadsheetInterface $spreadsheet): string
    {
        try {
            $phpSpreadsheet = $this->convertSpreadsheetToPhpSpreadsheet(
                $spreadsheet
            );
            $writer = IOFactory::createWriter(
                $phpSpreadsheet,
                $this->getWriterType()
            );
            $this->configureWriter($writer);

            // Save to a temporary file and read back the content.
            $tempFile = tempnam(sys_get_temp_dir(), 'spreadsheet_');
            if ($tempFile === false) {
                throw new DumpException([
                    'Could not create temporary file for dumping to string.',
                ]);
            }

            try {
                $writer->save($tempFile);
                $content = file_get_contents($tempFile);

                if ($content === false) {
                    throw new DumpException([
                        'Could not read temporary file after writing.',
                    ]);
                }

                return $content;
            } finally {
                // Clean up temporary file.
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        } catch (WriterException $e) {
            throw new DumpException([
                'Error creating spreadsheet string: "{message}".',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Convert PhpSpreadsheet object to SpreadsheetInterface.
     *
     * @param PhpSpreadsheet $phpSpreadsheet
     * @param string|null $overrideSheetName Default sheet name to use.
     * @return SpreadsheetInterface
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

            $rows = $phpSheet->toArray();
            $spreadsheet->createSheet($sheetName, $rows);
        }

        return $spreadsheet;
    }

    /**
     * Convert SpreadsheetInterface object to PhpSpreadsheet.
     *
     * @param SpreadsheetInterface $spreadsheet
     * @return PhpSpreadsheet
     */
    protected function convertSpreadsheetToPhpSpreadsheet(
        SpreadsheetInterface $spreadsheet
    ): PhpSpreadsheet {
        $phpSpreadsheet = new PhpSpreadsheet();
        $phpSpreadsheet->removeSheetByIndex(0); // Remove default sheet.

        $sheetIndex = 0;
        foreach ($spreadsheet->getSheets() as $sheetName => $sheet) {
            $phpSheet = $phpSpreadsheet->createSheet($sheetIndex++);
            $phpSheet->setTitle($sheetName);

            if ($sheet->isAssociative()) {
                $sheet = $sheet->toIndexed();
            }

            foreach ($sheet->getRows() as $rowIndex => $row) {
                foreach ($row as $colIndex => $value) {
                    $coordinate =
                        Coordinate::stringFromColumnIndex($colIndex + 1)
                            . ($rowIndex + 1)
                    ;
                    $phpSheet->setCellValue($coordinate, $value);
                }
            }
        }

        return $phpSpreadsheet;
    }
}
