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
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * YAML format handler.
 *
 * Handles reading and writing spreadsheet data in YAML format using Symfony
 * YAML component.
 */
final class YamlHandler implements SpreadsheetFormatHandlerInterface
{
    /**
     * Create a new YAML handler.
     *
     * @param int-mask-of<SymfonyYaml::PARSE_*> $parseFlags Symfony YAML parser flags.
     * @param int-mask-of<SymfonyYaml::DUMP_*> $dumpFlags Symfony YAML dumper flags.
     * @param string $sheetName Default sheet name.
     */
    public function __construct(
        private readonly int $parseFlags = SymfonyYaml::PARSE_EXCEPTION_ON_INVALID_TYPE,
        private readonly int $dumpFlags = SymfonyYaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
        private readonly string $sheetName = 'Sheet'
    ) {
    }

    /**
     * Read data from a YAML file.
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

            return $this->loadFromString(
                $content,
                pathinfo($filepath, PATHINFO_FILENAME)
            );
        } catch (Exception $e) {
            if ($e instanceof SpreadsheetLoadException || $e instanceof SpreadsheetFileNotFoundException) {
                throw $e;
            }

            throw new SpreadsheetLoadException([
                'Error reading YAML file: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Create a spreadsheet from a YAML string.
     *
     * {@inheritDoc}
     */
    public function loadFromString(
        string $data,
        ?string $sheetName = null
    ): SpreadsheetInterface {
        $sheetName = $sheetName ?? $this->sheetName;
        try {
            // Parse YAML data.
            $parsedData = SymfonyYaml::parse($data, $this->parseFlags);

            // Create a spreadsheet.
            $spreadsheet = new Spreadsheet();

            // Handle different YAML structures.

            // If YAML is not an array, wrap it in an array with default sheet
            // name.
            if (!is_array($parsedData)) {
                $spreadsheet->createSheet($sheetName, [[$parsedData]]);
                return $spreadsheet;
            }

            // Case 1: Flat array of objects (e.g., [{key:value}, {key:value}]).
            if (
                is_array($parsedData)
                && array_keys($parsedData) === range(0, count($parsedData) - 1)
                && !empty($parsedData)
                && is_array($parsedData[0])
                && !$this->isIndexedArray($parsedData[0])
            ) {
                $spreadsheet->createSheet($sheetName, $parsedData, true);
                return $spreadsheet;
            }

            // Case 2: Sequential array of arrays (e.g.,
            // [[value, value], [value, value]]).
            if (
                is_array($parsedData)
                && array_keys($parsedData) === range(0, count($parsedData) - 1)
                && !empty($parsedData)
                && is_array($parsedData[0])
                && $this->isIndexedArray($parsedData[0])
            ) {
                $spreadsheet->createSheet($sheetName, $parsedData, false);
                return $spreadsheet;
            }

            // Case 3: Object with sheet names as keys.
            foreach ($parsedData as $sheetName => $rows) {
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
        } catch (ParseException $e) {
            throw new SpreadsheetLoadException([
                'Invalid YAML data. Error: {error}',
                'error' => $e->getMessage(),
            ], $e->getCode(), $e);
        } catch (Exception $e) {
            if ($e instanceof SpreadsheetLoadException) {
                throw $e;
            }

            throw new SpreadsheetLoadException([
                'Error processing YAML data: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Write data to a YAML file.
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
                $filepath = tempnam(sys_get_temp_dir(), 'yaml_') . '.yaml';
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

            // Generate YAML content.
            $yamlContent = $this->dumpToString($spreadsheet);

            // Write to file.
            $result = file_put_contents($filepath, $yamlContent);

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
                'Error writing YAML file: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Create a YAML string from a spreadsheet.
     *
     * {@inheritDoc}
     */
    public function dumpToString(
        SpreadsheetInterface $spreadsheet
    ): string {
        try {
            $sheets = $spreadsheet->getSheets();

            // Special case: If there's only one sheet and it's associative, we
            // can output a flat array of objects.
            if (count($sheets) === 1) {
                $sheet = reset($sheets);
                if ($sheet->isAssociative()) {
                    return SymfonyYaml::dump($sheet->getRows(), 4, 2, $this->dumpFlags);
                }
            }

            // Default case: Use structure with sheet names as keys.
            $data = [];
            foreach ($spreadsheet->getSheets() as $name => $sheet) {
                $data[$name] = $sheet->getRows();
            }

            // Dump data as YAML.
            return SymfonyYaml::dump($data, 4, 2, $this->dumpFlags);
        } catch (Exception $e) {
            throw new SpreadsheetDumpException([
                'Error creating YAML string: {message}',
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
        return 'yaml';
    }

    /**
     * {@inheritDoc}
     */
    public function getMimeType(): string
    {
        return 'application/yaml';
    }
}
