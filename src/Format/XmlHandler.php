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
use DOMDocument;
use DOMElement;
use DOMException;
use DOMNode;
use Exception;
use SimpleXMLElement;

/**
 * XML format handler.
 *
 * Handles reading and writing spreadsheet data in XML format.
 */
final class XmlHandler implements SpreadsheetFormatHandlerInterface
{
    /**
     * Create a new XML handler.
     *
     * @param string $rootElementName Name of the root element.
     * @param string $sheetElementName Name of sheet elements.
     * @param string $rowElementName Name of row elements.
     * @param string $sheetName Default sheet name.
     */
    public function __construct(
        private readonly string $rootElementName = 'spreadsheet',
        private readonly string $sheetElementName = 'sheet',
        private readonly string $rowElementName = 'row',
        private readonly string $sheetName = 'Sheet',
    ) {
    }

    /**
     * Read data from an XML file.
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

            return $this->loadFromString($content);
        } catch (Exception $e) {
            if ($e instanceof SpreadsheetLoadException || $e instanceof SpreadsheetFileNotFoundException) {
                throw $e;
            }

            throw new SpreadsheetLoadException([
                'Error reading XML file: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Create a spreadsheet from an XML string.
     *
     * {@inheritDoc}
     */
    public function loadFromString(
        string $data,
        ?string $sheetName = null
    ): SpreadsheetInterface {
        $sheetName = $sheetName ?? $this->sheetName;
        try {
            // Enable internal errors for libxml to throw exceptions.
            $useInternalErrors = libxml_use_internal_errors(true);

            try {
                $xml = new SimpleXMLElement($data);
            } catch (Exception $e) {
                $errors = libxml_get_errors();
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = trim($error->message);
                }
                libxml_clear_errors();

                throw new SpreadsheetLoadException([
                    'Invalid XML data. Error: {error}',
                    'error' => implode('; ', $errorMessages),
                ]);
            } finally {
                // Restore previous error handling.
                libxml_use_internal_errors($useInternalErrors);
            }

            // Create spreadsheet.
            $spreadsheet = new Spreadsheet();

            // Process sheets.

            // Check for standard structure with sheet elements.
            $foundStandardStructure = false;

            // Check if the root element is our expected root element name.
            if ($xml->getName() === $this->rootElementName) {
                // Process sheets in standard format.
                foreach ($xml->{$this->sheetElementName} as $xmlSheet) {
                    $sheetName = (string)$xmlSheet['name'];
                    if (empty($sheetName)) {
                        // Use index if name attribute is missing.
                        $sheetName = $sheetName . (count($spreadsheet->getSheets()) + 1);
                    }

                    $rows = [];
                    $isAssociative = false;

                    // Check if using row element with column children
                    // (associative structure).
                    if (count($xmlSheet->{$this->rowElementName}) > 0) {
                        $firstRow = $xmlSheet->{$this->rowElementName}[0];

                        // Check if this is an indexed structure (using <col>
                        // elements).
                        if (count($firstRow->col) > 0) {
                            // This is an indexed structure using <col> elements.
                            foreach ($xmlSheet->{$this->rowElementName} as $xmlRow) {
                                $row = [];
                                foreach ($xmlRow->col as $xmlCol) {
                                    $row[] = $this->processXmlValue($xmlCol);
                                }
                                $rows[] = $row;
                            }
                        } else {
                            // This is an associative structure with named
                            // elements.
                            $isAssociative = true;

                            // Collect all unique column names to ensure
                            // consistent structure.
                            $allColumns = [];
                            foreach ($xmlSheet->{$this->rowElementName} as $xmlRow) {
                                foreach ($xmlRow->children() as $column) {
                                    $columnName = (string)$column->getName();
                                    if (!in_array($columnName, $allColumns)) {
                                        $allColumns[] = $columnName;
                                    }
                                }
                            }

                            // Process each row.
                            foreach ($xmlSheet->{$this->rowElementName} as $xmlRow) {
                                $row = [];

                                // Add columns to row.
                                foreach ($allColumns as $columnName) {
                                    $row[$columnName] = isset($xmlRow->{$columnName}) ?
                                        $this->processXmlValue($xmlRow->{$columnName}) : null;
                                }

                                $rows[] = $row;
                            }
                        }

                        // Add the sheet to the spreadsheet.
                        $spreadsheet->createSheet($sheetName, $rows, $isAssociative);
                        $foundStandardStructure = true;
                    }
                }
            }

            // If no sheets were found with standard structure, try to interpret
            // as flat structure.
            if (!$foundStandardStructure) {
                // Try to find repeating elements that might represent rows.
                $rootName = $xml->getName();
                $rootChildren = $xml->children();

                // Check if there are multiple elements with the same name
                // (potential rows).
                $childrenCount = [];
                foreach ($rootChildren as $child) {
                    $childName = $child->getName();
                    if (!isset($childrenCount[$childName])) {
                        $childrenCount[$childName] = 0;
                    }
                    $childrenCount[$childName]++;
                }

                // Find the element name that appears most frequently
                // (likely rows).
                arsort($childrenCount);
                $rowElementName = key($childrenCount);

                if ($rowElementName && $childrenCount[$rowElementName] > 0) {
                    $rows = [];
                    $isAssociative = true;

                    // Collect all unique column names from all row elements.
                    $allColumns = [];
                    foreach ($xml->{$rowElementName} as $xmlRow) {
                        foreach ($xmlRow->children() as $column) {
                            $columnName = (string)$column->getName();
                            if (!in_array($columnName, $allColumns)) {
                                $allColumns[] = $columnName;
                            }
                        }
                    }

                    // Process each row.
                    foreach ($xml->{$rowElementName} as $xmlRow) {
                        $row = [];

                        // Add columns to row.
                        foreach ($allColumns as $columnName) {
                            $row[$columnName] = isset($xmlRow->{$columnName}) ?
                                $this->processXmlValue($xmlRow->{$columnName}) : null;
                        }

                        $rows[] = $row;
                    }

                    // Use root name or row element name for sheet name.
                    $sheetName = $rootName;
                    if ($rootName === $rowElementName) {
                        $sheetName = $rootName . '_sheet';
                    }

                    // Add the sheet to the spreadsheet
                    $spreadsheet->createSheet($sheetName, $rows, $isAssociative);
                }
            }

            return $spreadsheet;
        } catch (Exception $e) {
            if ($e instanceof SpreadsheetLoadException) {
                throw $e;
            }

            throw new SpreadsheetLoadException([
                'Error processing XML data: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Extract value from XML element.
     *
     * @param SimpleXMLElement $element XML element.
     * @return mixed Processed value.
     */
    private function processXmlValue(SimpleXMLElement $element): mixed
    {
        // Check if the element has a type attribute.
        $type = (string)$element['type'];
        $value = (string)$element;

        switch ($type) {
            case 'integer':
            case 'int':
                return (int)$value;

            case 'number':
            case 'float':
            case 'double':
            case 'decimal':
                return (float)$value;

            case 'boolean':
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);

            case 'null':
                return null;

            case 'array':
                $array = [];
                foreach ($element->children() as $child) {
                    $array[] = $this->processXmlValue($child);
                }
                return $array;

            case 'object':
                $object = [];
                foreach ($element->children() as $child) {
                    $object[(string)$child['key'] ?: $child->getName()] =
                        $this->processXmlValue($child);
                }
                return $object;

            default:
                return $value;
        }
    }

    /**
     * Write data to an XML file.
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
                $filepath = tempnam(sys_get_temp_dir(), 'xml_') . '.xml';
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

            // Generate XML content.
            $xmlContent = $this->dumpToString($spreadsheet);

            // Write to file.
            $result = file_put_contents($filepath, $xmlContent);

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
                'Error writing XML file: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Create an XML string from a spreadsheet.
     *
     * {@inheritDoc}
     */
    public function dumpToString(
        SpreadsheetInterface $spreadsheet
    ): string {
        try {
            // Create XML document.
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->formatOutput = true;

            // Create root element.
            $root = $dom->createElement($this->rootElementName);
            $dom->appendChild($root);

            // Add sheets.
            foreach ($spreadsheet->getSheets() as $name => $sheet) {
                $xmlSheet = $dom->createElement($this->sheetElementName);
                $xmlSheet->setAttribute('name', $name);
                $root->appendChild($xmlSheet);

                // Write rows based on whether they're associative or indexed.
                if ($sheet->isAssociative()) {
                    foreach ($sheet->getRows() as $rowData) {
                        $xmlRow = $dom->createElement($this->rowElementName);
                        $xmlSheet->appendChild($xmlRow);

                        foreach ($rowData as $columnName => $value) {
                            $this->addXmlValue(
                                $dom,
                                $xmlRow,
                                (string)$columnName,
                                $value
                            );
                        }
                    }
                } else {
                    foreach ($sheet->getRows() as $rowData) {
                        $xmlRow = $dom->createElement($this->rowElementName);
                        $xmlSheet->appendChild($xmlRow);

                        foreach ($rowData as $index => $value) {
                            $colElement = $dom->createElement('col');
                            $xmlRow->appendChild($colElement);
                            $this->setElementValue($dom, $colElement, $value);
                        }
                    }
                }
            }

            // Convert DOM to string.
            return $dom->saveXML();
        } catch (Exception $e) {
            throw new SpreadsheetDumpException([
                'Error creating XML string: {message}',
                'message' => $e->getMessage(),
            ], $e->getCode(), $e);
        }
    }

    /**
     * Add a value to an XML node with appropriate type handling.
     *
     * @param DOMDocument $dom XML document.
     * @param DOMNode $parent Parent node.
     * @param string $name Element name.
     * @param mixed $value Value to add.
     * @return void
     * @throws DOMException If an error occurs creating elements.
     */
    private function addXmlValue(
        DOMDocument $dom,
        DOMNode $parent,
        string $name,
        mixed $value
    ): void {
        // Validate element name (must be a valid XML element name).
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        if (empty($name) || is_numeric($name[0])) {
            $name = 'col_' . $name;
        }

        // Create element.
        $element = $dom->createElement($name);
        $parent->appendChild($element);

        // Set the element value.
        $this->setElementValue($dom, $element, $value);
    }

    /**
     * Set the value of an element based on its type.
     *
     * @param DOMDocument $dom XML document.
     * @param DOMElement $element Element to set value for.
     * @param mixed $value Value to set.
     * @return void
     */
    private function setElementValue(
        DOMDocument $dom,
        DOMElement $element,
        mixed $value
    ): void {
        if ($value === null) {
            $element->setAttribute('type', 'null');
        } elseif (is_bool($value)) {
            $element->setAttribute('type', 'boolean');
            $element->appendChild(
                $dom->createTextNode($value ? 'true' : 'false')
            );
        } elseif (is_int($value)) {
            $element->setAttribute('type', 'integer');
            $element->appendChild($dom->createTextNode((string)$value));
        } elseif (is_float($value)) {
            $element->setAttribute('type', 'float');
            $element->appendChild($dom->createTextNode((string)$value));
        } elseif (is_array($value)) {
            $element->setAttribute('type', 'array');

            // Add array items.
            foreach ($value as $k => $v) {
                // If associative array.
                if (is_string($k)) {
                    $itemElement = $dom->createElement('item');
                    $itemElement->setAttribute('key', $k);
                    $element->appendChild($itemElement);

                    if (is_scalar($v) || is_null($v)) {
                        $this->setElementValue($dom, $itemElement, $v);
                    } else {
                        $this->addXmlValue($dom, $itemElement, 'value', $v);
                    }
                } else {
                    $itemElement = $dom->createElement('item');
                    $element->appendChild($itemElement);
                    $this->setElementValue($dom, $itemElement, $v);
                }
            }
        } elseif (is_object($value)) {
            $element->setAttribute('type', 'object');

            // Convert object to array and add properties.
            $objectVars = get_object_vars($value) ?: (array)$value;

            foreach ($objectVars as $k => $v) {
                $propName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $k);
                $propElement = $dom->createElement($propName);
                $element->appendChild($propElement);

                if (is_scalar($v) || is_null($v)) {
                    $this->setElementValue($dom, $propElement, $v);
                } else {
                    $this->addXmlValue($dom, $propElement, 'value', $v);
                }
            }
        } else {
            // Scalar value (string).
            // Handle potential XML special characters.
            try {
                if (
                    str_contains($value, '<')
                    || str_contains($value, '>')
                    || str_contains($value, '&')
                ) {
                    $element->appendChild($dom->createCDATASection((string)$value));
                } else {
                    $element->appendChild($dom->createTextNode((string)$value));
                }
            } catch (Exception $e) {
                // If there's an issue with the text content, use CDATA section.
                $element->appendChild($dom->createCDATASection((string)$value));
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getExtension(): string
    {
        return 'xml';
    }

    /**
     * {@inheritDoc}
     */
    public function getMimeType(): string
    {
        return 'application/xml';
    }
}
