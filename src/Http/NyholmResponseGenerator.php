<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Spreadsheet\Http;

use Derafu\Spreadsheet\Caster;
use Derafu\Spreadsheet\Contract\CasterInterface;
use Derafu\Spreadsheet\Contract\FactoryInterface;
use Derafu\Spreadsheet\Contract\Http\ResponseGeneratorInterface;
use Derafu\Spreadsheet\Contract\SpreadsheetInterface;
use Derafu\Spreadsheet\Exception\DumpException;
use Derafu\Spreadsheet\Factory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

/**
 * Generates HTTP responses using Nyholm's PSR-7 implementation.
 */
final class NyholmResponseGenerator implements ResponseGeneratorInterface
{
    /**
     * Nyholm's PSR-17 factory.
     *
     * @var Psr17Factory
     */
    private Psr17Factory $psr17Factory;

    /**
     * Create a new NyholmResponseGenerator.
     *
     * @param FactoryInterface|null $factory Factory for creating format-specific handlers.
     * @param CasterInterface|null $caster Caster for casting values to the correct type.
     * @throws DumpException If Nyholm PSR-7 is not available.
     */
    public function __construct(
        private ?FactoryInterface $factory = null,
        private ?CasterInterface $caster = null
    ) {
        if (!class_exists(Psr17Factory::class)) {
            throw new DumpException(
                'Nyholm PSR-7 implementation is required. Install with "composer require nyholm/psr7".'
            );
        }

        $this->factory = $factory ?? new Factory();
        $this->caster = $caster ?? new Caster();
        $this->psr17Factory = new Psr17Factory();
    }

    /**
     * {@inheritDoc}
     */
    public function createResponse(
        SpreadsheetInterface $spreadsheet,
        ?string $filename = null,
        ?string $format = null
    ): ResponseInterface {
        $filename = $filename ?? $spreadsheet->getActiveSheet()->getName() . '.xlsx';
        $format = $format ?? $this->factory->detectFormat($filename);
        $handler = $this->factory->createFormatHandler($filename, $format);

        // Get MIME type for the format.
        $mimeType = $handler->getMimeType();

        // Create a temporary file.
        $tempFile = tempnam(sys_get_temp_dir(), 'derafu_');
        if ($tempFile === false) {
            throw new DumpException('Could not create temporary file.');
        }

        try {
            // Cast the spreadsheet before dumping and dump it to a string.
            $writeSpreadsheet = $this->caster->castBeforeDump($spreadsheet);
            $data = $handler->dumpToString($writeSpreadsheet);

            // Create response.
            $response = $this->psr17Factory->createResponse(200);

            // Create stream with content.
            $stream = $this->psr17Factory->createStream($data);

            // Set response headers and body.
            return $response
                ->withHeader('Content-Type', $mimeType)
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Cache-Control', 'max-age=0')
                ->withBody($stream)
            ;
        } finally {
            // Clean up.
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
