<?php

declare(strict_types=1);

/**
 * Derafu: Spreadsheet - Unified Spreadsheet Processing for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsSpreadsheet;

use Derafu\Spreadsheet\Abstract\AbstractPhpSpreadsheetFormatHandler;
use Derafu\Spreadsheet\Contract\SpreadsheetFormatHandlerInterface;
use Derafu\Spreadsheet\Exception\SpreadsheetDumpException;
use Derafu\Spreadsheet\Exception\SpreadsheetFileNotFoundException;
use Derafu\Spreadsheet\Exception\SpreadsheetLoadException;
use Derafu\Spreadsheet\Format\CsvLeagueHandler;
use Derafu\Spreadsheet\Format\JsonHandler;
use Derafu\Spreadsheet\Format\XlsxHandler;
use Derafu\Spreadsheet\Format\XmlHandler;
use Derafu\Spreadsheet\Format\YamlHandler;
use Derafu\Spreadsheet\Sheet;
use Derafu\Spreadsheet\Spreadsheet;
use Derafu\Spreadsheet\SpreadsheetCaster;
use Derafu\Spreadsheet\SpreadsheetDumper;
use Derafu\Spreadsheet\SpreadsheetFactory;
use Derafu\Spreadsheet\SpreadsheetLoader;
use Derafu\Spreadsheet\Translation\SpreadsheetTranslationResourceProvider;
use Derafu\Translation\Contract\TranslatableInterface;
use ErrorException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractPhpSpreadsheetFormatHandler::class)]
#[CoversClass(CsvLeagueHandler::class)]
#[CoversClass(JsonHandler::class)]
#[CoversClass(YamlHandler::class)]
#[CoversClass(XmlHandler::class)]
#[CoversClass(SpreadsheetLoader::class)]
#[CoversClass(SpreadsheetDumper::class)]
#[UsesClass(SpreadsheetFactory::class)]
#[UsesClass(SpreadsheetCaster::class)]
#[UsesClass(Spreadsheet::class)]
#[UsesClass(Sheet::class)]
#[UsesClass(XlsxHandler::class)]
#[UsesClass(SpreadsheetTranslationResourceProvider::class)]
final class FormatFailurePathsTest extends TestCase
{
    #[DataProvider('textFormats')]
    public function testReadWarningsConvertedToExceptionsRemainWrappedAsLoadFailures(string $format): void
    {
        $path = $this->temporaryFile('content');
        try {
            $this->assertTrue(chmod($path, 0000));
            clearstatcache(true, $path);
            if (is_readable($path)) {
                $this->markTestSkipped('The current user can read files regardless of their permission bits.');
            }

            set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
                if ($severity !== E_WARNING) {
                    return false;
                }
                throw new ErrorException($message, 0, $severity, $file, $line);
            });
            try {
                $this->handler($format)->loadFromFile($path);
                $this->fail('The unreadable file must produce a translated load exception.');
            } catch (SpreadsheetLoadException $exception) {
                $this->assertStringStartsWith('Error reading ' . strtoupper($format) . ' file:', $exception->getMessage());
                $previous = $exception->getPrevious();
                $this->assertInstanceOf(ErrorException::class, $previous);
                $this->assertSame(E_WARNING, $previous->getSeverity());
                $this->assertStringContainsString($path, $previous->getMessage());
            } finally {
                restore_error_handler();
            }
        } finally {
            chmod($path, 0600);
            unlink($path);
        }
    }

    #[DataProvider('textFormats')]
    public function testWriteWarningsConvertedToExceptionsRemainWrappedAsDumpFailures(string $format): void
    {
        $directory = sys_get_temp_dir() . '/derafu-warning-' . bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($directory));
        $source = new Spreadsheet();
        $source->createSheet('Records', [['value']]);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ($severity !== E_WARNING) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $this->handler($format)->dumpToFile($source, $directory);
            $this->fail('Writing over a directory must produce a translated dump exception.');
        } catch (SpreadsheetDumpException $exception) {
            $this->assertStringStartsWith('Error writing ' . strtoupper($format) . ' file:', $exception->getMessage());
            $previous = $exception->getPrevious();
            $this->assertInstanceOf(ErrorException::class, $previous);
            $this->assertSame(E_WARNING, $previous->getSeverity());
            $this->assertStringContainsString($directory, $previous->getMessage());
        } finally {
            restore_error_handler();
            rmdir($directory);
        }
    }

    private function handler(string $format): SpreadsheetFormatHandlerInterface
    {
        return (new SpreadsheetFactory())->createFormatHandler('data.' . $format);
    }

    private function temporaryFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'derafu-io-test-');
        $this->assertIsString($path);
        $this->assertSame(strlen($content), file_put_contents($path, $content));

        return $path;
    }

    /** @param callable(): mixed $action */
    private function withFilesystemWarnings(callable $action): mixed
    {
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            if ($severity !== E_WARNING || preg_match('/^(fopen|file_get_contents|file_put_contents|mkdir)\(/', $message) !== 1) {
                return false;
            }
            $warnings[] = $message;

            return true;
        });
        try {
            return $action();
        } finally {
            restore_error_handler();
            $this->assertNotEmpty($warnings, 'The operation must have encountered a real filesystem error.');
        }
    }

    /** @return iterable<string, array{string}> */
    public static function textFormats(): iterable
    {
        foreach (['csv', 'json', 'yaml', 'xml'] as $format) {
            yield $format => [$format];
        }
    }

    #[DataProvider('textFormats')]
    public function testUnreadableFilesRaiseLoadExceptions(string $format): void
    {
        $path = $this->temporaryFile('content');
        try {
            $this->assertTrue(chmod($path, 0000));
            clearstatcache(true, $path);
            if (is_readable($path)) {
                $this->markTestSkipped('The current user can read files regardless of their permission bits.');
            }
            $this->withFilesystemWarnings(function () use ($path, $format): void {
                try {
                    $this->handler($format)->loadFromFile($path);
                    $this->fail('An unreadable file must be rejected.');
                } catch (SpreadsheetLoadException $exception) {
                    $this->assertStringContainsString($path, $exception->getMessage());
                }
            });
        } finally {
            chmod($path, 0600);
            unlink($path);
        }
    }

    public function testLoaderRejectsMissingAndUnreadableFilesBeforeSelectingAHandler(): void
    {
        $missing = sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(8));
        try {
            (new SpreadsheetLoader())->loadFromFile($missing);
            $this->fail('A missing file must be rejected.');
        } catch (SpreadsheetFileNotFoundException $exception) {
            $this->assertSame("File not found: \"$missing\".", $exception->getMessage());
        }
        $path = $this->temporaryFile('content');
        try {
            chmod($path, 0000);
            clearstatcache(true, $path);
            if (is_readable($path)) {
                $this->markTestSkipped('The current user can read files regardless of their permission bits.');
            }
            try {
                (new SpreadsheetLoader())->loadFromFile($path);
                $this->fail('An unreadable file must be rejected.');
            } catch (SpreadsheetFileNotFoundException $exception) {
                $this->assertSame("File not readable: \"$path\".", $exception->getMessage());
            }
        } finally {
            chmod($path, 0600);
            unlink($path);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function writeFormats(): iterable
    {
        yield from self::textFormats();
        yield 'xlsx' => ['xlsx'];
    }

    #[DataProvider('writeFormats')]
    public function testWritingOverADirectoryIsARealDumpFailure(string $format): void
    {
        $directory = sys_get_temp_dir() . '/derafu-directory-' . bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($directory));
        $source = new Spreadsheet();
        $source->createSheet('Records', [['value']]);
        try {
            $this->withFilesystemWarnings(function () use ($source, $directory, $format): void {
                try {
                    $this->handler($format)->dumpToFile($source, $directory);
                    $this->fail('A directory cannot be overwritten with file contents.');
                } catch (SpreadsheetDumpException $exception) {
                    $this->assertStringContainsString($directory, $exception->getMessage());
                }
            });
        } finally {
            rmdir($directory);
        }
    }

    #[DataProvider('writeFormats')]
    public function testDirectoriesCannotBeCreatedBeneathARegularFile(string $format): void
    {
        $blocker = $this->temporaryFile('regular file');
        $source = new Spreadsheet();
        $source->createSheet('Records', [['value']]);
        try {
            foreach ([false, true] as $useFacade) {
                $this->withFilesystemWarnings(function () use ($blocker, $source, $format, $useFacade): void {
                    $path = $blocker . '/nested/data.' . $format;
                    try {
                        if ($useFacade) {
                            (new SpreadsheetDumper())->dumpToFile($source, $path, $format);
                        } else {
                            $this->handler($format)->dumpToFile($source, $path);
                        }
                        $this->fail('A parent path component is a regular file.');
                    } catch (SpreadsheetDumpException $exception) {
                        $this->assertStringContainsString($blocker . '/nested', $exception->getMessage());
                    }
                });
            }
        } finally {
            unlink($blocker);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function malformedContents(): iterable
    {
        yield 'JSON' => ['json', '{broken'];
        yield 'YAML' => ['yaml', "rows: [invalid\n"];
        yield 'XML' => ['xml', '<broken>'];
        yield 'XLSX' => ['xlsx', 'Not a ZIP archive'];
    }

    #[DataProvider('malformedContents')]
    public function testMalformedFilesAndStringsProduceTranslatableLoadExceptions(string $format, string $content): void
    {
        $path = $this->temporaryFile($content);
        try {
            foreach ([false, true] as $fromFile) {
                try {
                    $handler = $this->handler($format);
                    if ($fromFile) {
                        $handler->loadFromFile($path);
                    } else {
                        $handler->loadFromString($content);
                    }
                    $this->fail('Malformed content must be rejected.');
                } catch (SpreadsheetLoadException $exception) {
                    $this->assertInstanceOf(TranslatableInterface::class, $exception);
                    $this->assertNotSame('', $exception->getMessage());
                }
            }
        } finally {
            unlink($path);
        }
    }

    public function testInvalidCsvConfigurationIsWrappedOnBothReadRoutes(): void
    {
        $handler = new CsvLeagueHandler(delimiter: 'invalid');
        $path = $this->temporaryFile("a,b\n");
        try {
            foreach ([false, true] as $fromFile) {
                try {
                    if ($fromFile) {
                        $handler->loadFromFile($path);
                    } else {
                        $handler->loadFromString("a,b\n");
                    }
                    $this->fail('An invalid delimiter must be rejected.');
                } catch (SpreadsheetLoadException $exception) {
                    $this->assertStringStartsWith('Error processing CSV data:', $exception->getMessage());
                }
            }
        } finally {
            unlink($path);
        }
    }

    public function testJsonExceptionsAreWrappedWhenThrowOnErrorIsConfigured(): void
    {
        $this->expectException(SpreadsheetLoadException::class);
        $this->expectExceptionMessage('Error processing JSON data:');
        (new JsonHandler(decodeOptions: JSON_THROW_ON_ERROR))->loadFromString('{broken');
    }
}
