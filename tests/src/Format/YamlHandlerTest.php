<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet\Format;

use Derafu\Spreadsheet\Contract\SpreadsheetFormatHandlerInterface;
use Derafu\Spreadsheet\Exception\SpreadsheetLoadException;
use Derafu\Spreadsheet\Format\YamlHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use Derafu\TestsSpreadsheet\Abstract\AbstractArrayFormatHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Yaml\Yaml;

#[CoversClass(YamlHandler::class)]
#[CoversClass(SpreadsheetCaster::class)]
#[CoversClass(Spreadsheet::class)]
#[CoversClass(Sheet::class)]
final class YamlHandlerTest extends AbstractArrayFormatHandler
{
    protected function getFormatHandler(): SpreadsheetFormatHandlerInterface
    {
        return new YamlHandler();
    }

    protected function getFixtureDir(): string
    {
        return __DIR__ . '/../../fixtures/yaml';
    }

    protected function getFileExtension(): string
    {
        return 'yaml';
    }

    public function testGetExtensionReturnsYaml(): void
    {
        $yaml = $this->getFormatHandler();
        $this->assertSame('yaml', $yaml->getExtension());
    }

    public function testGetMimeTypeReturnsApplicationYaml(): void
    {
        $yaml = $this->getFormatHandler();
        $this->assertSame('application/yaml', $yaml->getMimeType());
    }

    public function testLoadFromStringWithInvalidYaml(): void
    {
        $invalidYaml = "invalid:\n  - yaml:\n  indentation";

        $yaml = $this->getFormatHandler();
        $this->expectException(SpreadsheetLoadException::class);
        $yaml->loadFromString($invalidYaml);
    }

    public function testCustomYamlOptions(): void
    {
        // Test with custom YAML options.
        $customHandler = new YamlHandler(
            Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
        );

        // Create test data with multiline string and empty array.
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet('test', [
            ['type' => 'multiline', 'content' => "This is a\nmultiline\nstring"],
            ['type' => 'empty_array', 'content' => []],
        ], true);

        // Dump to string with custom options.
        $spreadsheet = $this->caster->castBeforeDump($spreadsheet);
        $yamlString = $customHandler->dumpToString($spreadsheet);

        // Verify multiline literal block style is used (|).
        $this->assertStringContainsString('|', $yamlString);

        // Ensure it can be read back correctly.
        $loadedSpreadsheet = $customHandler->loadFromString($yamlString, 'test');
        $loadedSpreadsheet = $this->caster->castAfterLoad($loadedSpreadsheet);

        $sheet = $loadedSpreadsheet->getSheet('test');
        $rows = $sheet->getRows();

        $this->assertSame("This is a\nmultiline\nstring", $rows[0]['content']);
        $this->assertSame([], $rows[1]['content']);
    }
}
