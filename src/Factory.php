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

use Derafu\Spreadsheet\Contract\FactoryInterface;
use Derafu\Spreadsheet\Contract\FormatHandlerInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Exception\FormatNotSupportedException;
use Derafu\Spreadsheet\Format\CsvLeagueHandler;
use Derafu\Spreadsheet\Format\HtmlHandler;
use Derafu\Spreadsheet\Format\JsonHandler;
use Derafu\Spreadsheet\Format\OdsHandler;
use Derafu\Spreadsheet\Format\PdfHandler;
use Derafu\Spreadsheet\Format\XlsHandler;
use Derafu\Spreadsheet\Format\XlsxHandler;
use Derafu\Spreadsheet\Format\XmlHandler;
use Derafu\Spreadsheet\Format\YamlHandler;

/**
 * Factory for creating format-specific handlers
 *
 * This class is responsible for detecting file formats and creating the
 * appropriate handler classes.
 */
final class Factory implements FactoryInterface
{
    /**
     * Map of the default format extensions to handler classes.
     *
     * This map is used to detect the format of a file based on its extension.
     *
     * Can be overridden by passing an array of format extensions to the
     * constructor.
     *
     * @var array<string, class-string<FormatHandlerInterface>>
     */
    private array $formatHandlers = [
        'csv' => CsvLeagueHandler::class,
        'xlsx' => XlsxHandler::class,
        'xls' => XlsHandler::class,
        'ods' => OdsHandler::class,
        'xml' => XmlHandler::class,
        'json' => JsonHandler::class,
        'yaml' => YamlHandler::class,
        'html' => HtmlHandler::class,
        'pdf' => PdfHandler::class,
    ];

    /**
     * Create a new Factory instance.
     *
     * @param array<string, class-string<FormatHandlerInterface>> $formatHandlers
     */
    public function __construct(array $formatHandlers = [])
    {
        if (!empty($formatHandlers)) {
            $this->formatHandlers = $formatHandlers;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data = []): SpreadsheetInterface
    {
        return Spreadsheet::fromArray($data);
    }

    /**
     * {@inheritDoc}
     */
    public function createFormatHandler(
        string $filepath,
        ?string $formatOverride = null
    ): FormatHandlerInterface {
        $format = $formatOverride ?? $this->detectFormat($filepath);

        if (!isset($this->formatHandlers[$format])) {
            throw new FormatNotSupportedException([
                'Format "{format}" is not supported.',
                'format' => $format,
            ]);
        }

        $handlerClass = $this->formatHandlers[$format];

        return new $handlerClass();
    }

    /**
     * {@inheritDoc}
     */
    public function detectFormat(string $filepath): string
    {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        if (empty($extension)) {
            throw new FormatNotSupportedException([
                'Could not detect extension for file "{filepath}".',
                'filepath' => $filepath,
            ]);
        }

        if (!isset($this->formatHandlers[$extension])) {
            throw new FormatNotSupportedException([
                'Extension "{extension}" is not supported.',
                'extension' => $extension,
            ]);
        }

        return $extension;
    }

    /**
     * {@inheritDoc}
     */
    public function registerFormatHandler(
        string $extension,
        string $handlerClass
    ): static {
        $this->formatHandlers[strtolower($extension)] = $handlerClass;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getSupportedFormats(): array
    {
        return array_keys($this->formatHandlers);
    }
}
