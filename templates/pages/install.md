# Install the library

This guide will walk you through installing Derafu Spreadsheet and its dependencies.

[TOC]

## Basic Installation

The simplest way to install Derafu Spreadsheet is through Composer:

```bash
composer require derafu/spreadsheet
```

This installs the core package. Out of the box, only works with JSON and XML. Depending on which file formats you want to work with, you may need to install additional dependencies.

## Format-Specific Dependencies

Derafu Spreadsheet supports multiple formats through different handlers. Each format may require additional dependencies:

### CSV Support

For CSV support, you can choose between two handlers:

```bash
# Recommended: League CSV (faster, more memory efficient).
composer require league/csv

# Alternative: PhpSpreadsheet CSV handling.
composer require phpoffice/phpspreadsheet
```

### Excel and OpenDocument Support

For XLSX, XLS, and ODS support:

```bash
composer require phpoffice/phpspreadsheet
```

### YAML Support

For YAML support:

```bash
composer require symfony/yaml
```

### PDF Support

For PDF export:

```bash
# Base requirement.
composer require phpoffice/phpspreadsheet

# Choose one PDF library:
composer require mpdf/mpdf        # For MpdfWriter (recommended).
# OR
composer require dompdf/dompdf    # For DompdfWriter.
# OR
composer require tecnickcom/tcpdf # For TcpdfWriter.
```

### HTTP Response Support

For PSR-7 HTTP response integration:

```bash
composer require nyholm/psr7
```

If you don't want to use nyholm/psr7, you can use any PSR-7 compatible library implementing by your own `ResponseGeneratorInterface`.

## Complete Installation (All Formats)

If you want to support all formats, with the default handlers, you can install all dependencies at once:

```bash
composer require derafu/spreadsheet league/csv phpoffice/phpspreadsheet \
    symfony/yaml mpdf/mpdf nyholm/psr7
```

## Installation in Frameworks

### Symfony

To use Derafu Spreadsheet in Symfony, install the package:

```bash
composer require derafu/spreadsheet
```

Then define services in your `services.yaml`:

```yaml
services:
    Derafu\Spreadsheet\Contract\FactoryInterface:
        class: Derafu\Spreadsheet\Factory

    Derafu\Spreadsheet\Contract\CasterInterface:
        class: Derafu\Spreadsheet\Caster

    Derafu\Spreadsheet\Contract\LoaderInterface:
        class: Derafu\Spreadsheet\Loader
        arguments:
            - '@Derafu\Spreadsheet\Factory'
            - '@Derafu\Spreadsheet\Caster'

    Derafu\Spreadsheet\Contract\DumperInterface:
        class: Derafu\Spreadsheet\Dumper
        arguments:
            - '@Derafu\Spreadsheet\Factory'
            - '@Derafu\Spreadsheet\Caster'
```

Check the constructors of the classes to see what arguments they expect. You can configure them as you want, for example change the delimiter for CSV.
