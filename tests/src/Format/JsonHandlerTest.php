<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet\Format;

use Derafu\Spreadsheet\Contract\SpreadsheetFormatHandlerInterface;
use Derafu\Spreadsheet\Exception\SpreadsheetLoadException;
use Derafu\Spreadsheet\Format\JsonHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use Derafu\TestsSpreadsheet\Abstract\AbstractArrayFormatHandler;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JsonHandler::class)]
#[CoversClass(SpreadsheetCaster::class)]
#[CoversClass(Spreadsheet::class)]
#[CoversClass(Sheet::class)]
final class JsonHandlerTest extends AbstractArrayFormatHandler
{
    protected function getFormatHandler(): SpreadsheetFormatHandlerInterface
    {
        return new JsonHandler();
    }

    protected function getFixtureDir(): string
    {
        return __DIR__ . '/../../fixtures/json';
    }

    protected function getFileExtension(): string
    {
        return 'json';
    }

    public function testGetExtensionReturnsJson(): void
    {
        $json = $this->getFormatHandler();
        $this->assertSame('json', $json->getExtension());
    }

    public function testGetMimeTypeReturnsApplicationJson(): void
    {
        $json = $this->getFormatHandler();
        $this->assertSame('application/json', $json->getMimeType());
    }

    public function testLoadFromStringWithInvalidJson(): void
    {
        $invalidJson = '{"broken": "json"';

        $json = $this->getFormatHandler();
        $this->expectException(SpreadsheetLoadException::class);
        $json->loadFromString($invalidJson);
    }

    public function testSpecialJsonFeatures(): void
    {
        // Test nested JSON parsing.
        $jsonData = '{"sheet1":[{"id":1,"name":"Product","metadata":"{\"key\":\"value\",\"nested\":{\"id\":123}}"}]}';

        $json = $this->getFormatHandler();
        $spreadsheet = $json->loadFromString($jsonData);
        $spreadsheet = $this->caster->castAfterLoad($spreadsheet);

        $sheet = $spreadsheet->getSheet('sheet1');
        $rows = $sheet->getRows();

        // Verify that the JSON string in metadata gets parsed after casting.
        $this->assertIsArray($rows[0]['metadata']); // Still a string before full processing.

        // If using a JsonField component that parses JSON strings.
        $decodedMetadata = $rows[0]['metadata'];
        $this->assertIsArray($decodedMetadata);
        $this->assertArrayHasKey('key', $decodedMetadata);
        $this->assertArrayHasKey('nested', $decodedMetadata);
    }
}
