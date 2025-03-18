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

/**
 * JSON format handler.
 *
 * Handles reading and writing spreadsheet data in JSON format.
 */
final class JsonHandler implements SpreadsheetFormatHandlerInterface
{
    /**
     * Create a new JSON handler.
     *
     * @param int $encodeOptions JSON encoding options.
     * @param int $decodeOptions JSON decoding options.
     * @param int $depth Maximum depth for JSON encoding/decoding.
     * @param string $sheetName Default sheet name.
     */
    public function __construct(
        private readonly int $encodeOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
        private readonly int $decodeOptions = JSON_OBJECT_AS_ARRAY,
        private readonly int $depth = 512,
        private readonly string $sheetName = 'Sheet',
    ) {
    }

    /**
     * Read data from a JSON file.
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
            // Read file content.
            $content = file_get_contents($filepath);
            if ($content === false) {
                throw new SpreadsheetLoadException([
                    'Could not read file: "{filepath}".',
                    'filepath' => $filepath,
                ]);
            }

            // Load the spreadsheet from the content.
            $spreadsheet = $this->loadFromString(
                $content,
                pathinfo($filepath, PATHINFO_FILENAME)
            );

            // If no sheets were created (the format was not recognized),
            // try wrapping the data to create a single sheet with the filename.
            if (count($spreadsheet->getSheets()) === 0) {
                $sheetName = pathinfo($filepath, PATHINFO_FILENAME);
                $spreadsheet = new Spreadsheet();
                $data = json_decode($content, true, $this->depth, $this->decodeOptions);
                $spreadsheet->createSheet($sheetName, [$data]);
            }

            return $spreadsheet;
        } catch (Exception $e) {
            if ($e instanceof SpreadsheetLoadException || $e instanceof SpreadsheetFileNotFoundException) {
                throw $e;
            }

            throw new SpreadsheetLoadException([
                'Error reading JSON file: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Create a spreadsheet from a JSON string.
     *
     * {@inheritDoc}
     */
    public function loadFromString(
        string $data,
        ?string $sheetName = null
    ): SpreadsheetInterface {
        $sheetName = $sheetName ?? $this->sheetName;

        try {
            // Decode JSON data
            $decodedData = json_decode(
                $data,
                true,
                $this->depth,
                $this->decodeOptions
            );

            // If the JSON is invalid, throw an exception.
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new SpreadsheetLoadException([
                    'Invalid JSON data. Error: {error}',
                    'error' => json_last_error_msg(),
                ]);
            }

            // Create spreadsheet.
            $spreadsheet = new Spreadsheet();

            // Handle different JSON structures.

            // If JSON is not an array, wrap it in an array with default sheet
            // name.
            if (!is_array($decodedData)) {
                $spreadsheet->createSheet($sheetName, [[$decodedData]]);
                return $spreadsheet;
            }

            // Case 1: Flat array of objects (most common case, e.g.,
            // [{key:value}, {key:value}])
            if (
                is_array($decodedData)
                && array_keys($decodedData) === range(0, count($decodedData) - 1)
                && !empty($decodedData)
                && is_array($decodedData[0])
                && !$this->isIndexedArray($decodedData[0])
            ) {
                $spreadsheet->createSheet($sheetName, $decodedData, true);
                return $spreadsheet;
            }

            // Case 2: Sequential array of arrays (e.g.,
            // [[value, value], [value, value]])
            if (
                is_array($decodedData)
                && array_keys($decodedData) === range(0, count($decodedData) - 1)
                && !empty($decodedData)
                && is_array($decodedData[0])
                && $this->isIndexedArray($decodedData[0])
            ) {
                $spreadsheet->createSheet($sheetName, $decodedData, false);
                return $spreadsheet;
            }

            // Case 3: Object with sheet names as keys.
            foreach ($decodedData as $sheetName => $rows) {
                // Skip if not an array or is empty.
                if (!is_array($rows) || empty($rows)) {
                    continue;
                }

                // If the first row is not an array, wrap it in an array.
                if (!isset($rows[0])) {
                    $rows = [$rows];
                }

                // Determine if data is associative (first row is associative
                // array/object).
                $isAssociative = false;
                if (is_array($rows[0])) {
                    $isAssociative = !$this->isIndexedArray($rows[0]);
                }

                // Add the sheet to the spreadsheet.
                $spreadsheet->createSheet(
                    (string)$sheetName,
                    $rows,
                    $isAssociative
                );
            }

            return $spreadsheet;
        } catch (Exception $e) {
            if ($e instanceof SpreadsheetLoadException) {
                throw $e;
            }

            throw new SpreadsheetLoadException([
                'Error processing JSON data: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Write data to a JSON file.
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
                $filepath = tempnam(sys_get_temp_dir(), 'json_') . '.json';
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

            // Generate JSON content.
            $jsonContent = $this->dumpToString($spreadsheet);

            // Write to file.
            $result = file_put_contents($filepath, $jsonContent);

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
                'Error writing JSON file: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Create a JSON string from a spreadsheet.
     *
     * {@inheritDoc}
     */
    public function dumpToString(
        SpreadsheetInterface $spreadsheet
    ): string {
        try {
            $sheets = $spreadsheet->getSheets();

            // Special case: If there's only one sheet and it's associative, we
            // can output a flat array of objects (more compatible with API
            // formats).
            if (count($sheets) === 1) {
                $sheet = reset($sheets);
                if ($sheet->isAssociative()) {
                    $jsonContent = json_encode(
                        $sheet->getRows(),
                        $this->encodeOptions,
                        $this->depth
                    );

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new SpreadsheetDumpException([
                            'Failed to encode data as JSON: {error}',
                            'error' => json_last_error_msg(),
                        ]);
                    }

                    return $jsonContent;
                }
            }

            // Default case: Use structure with sheet names as keys.
            $data = [];
            foreach ($spreadsheet->getSheets() as $name => $sheet) {
                $data[$name] = $sheet->getRows();
            }

            // Encode data as JSON.
            $jsonContent = json_encode(
                $data,
                $this->encodeOptions,
                $this->depth
            );

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new SpreadsheetDumpException([
                    'Failed to encode data as JSON: {error}',
                    'error' => json_last_error_msg(),
                ]);
            }

            return $jsonContent;
        } catch (Exception $e) {
            if ($e instanceof SpreadsheetDumpException) {
                throw $e;
            }

            throw new SpreadsheetDumpException([
                'Error creating JSON string: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Determine if an array is indexed (sequential numeric keys) vs associative.
     *
     * @param array $array The array to check.
     * @return bool True if the array has sequential numeric keys (0, 1, 2...).
     */
    private function isIndexedArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }

        $keys = array_keys($array);

        return $keys === range(0, count($keys) - 1);
    }

    /**
     * {@inheritDoc}
     */
    public function getExtension(): string
    {
        return 'json';
    }

    /**
     * {@inheritDoc}
     */
    public function getMimeType(): string
    {
        return 'application/json';
    }
}
