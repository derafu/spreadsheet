# Architecture of Derafu Spreadsheet

This document provides an overview of the architecture and design principles behind Derafu Spreadsheet.

[TOC]

## Core Components

Derafu Spreadsheet is built around several key components that work together to provide a unified approach to spreadsheet processing:

![Architecture Diagram](/img/derafu-spreadsheet-architecture-diagram.svg)

### Key Components

1. **Loader**
   - Handles loading spreadsheet data from files or strings.
   - Uses Factory to create appropriate Format Handlers.
   - Uses Caster to convert raw data to appropriate PHP types.

2. **Dumper**
   - Handles saving spreadsheet data to files or strings.
   - Uses Factory to create appropriate Format Handlers.
   - Uses Caster to convert PHP types to format-appropriate representations.

3. **Factory**
   - Creates and manages Format Handlers based on file extension or specified format.
   - Allows registering custom Format Handlers.
   - Provides format detection capabilities.

4. **Caster**
   - Converts raw data values to appropriate PHP types when reading.
   - Converts PHP types to appropriate string representations when writing.
   - Handles intelligent date, boolean, numeric, and JSON detection.

5. **Format Handlers**
   - Format-specific implementations that know how to read/write a particular format.
   - All implement a common FormatHandlerInterface.
   - Includes handlers for XLSX, XLS, CSV, ODS, JSON, XML, YAML, HTML, PDF.

6. **Data Model**
   - **Spreadsheet**: Top-level container for all data.
   - **Sheet**: Container for rows and cells with a name.
   - Both implement interfaces for consistent interaction.

## Design Principles

Derafu Spreadsheet was designed with several key principles in mind:

1. **Unified API**
   - Consistent interface across all supported formats.
   - Same code works regardless of source or target format.

2. **Type Intelligence**
   - Automatic conversion between string values and appropriate PHP types.
   - No manual type casting required in application code.

3. **Separation of Concerns**
   - Format handling is separated from data model.
   - Type conversion is separated from data loading/saving.
   - Each component has a single, clear responsibility.

4. **Interface-Based Design**
   - All components define and implement interfaces.
   - Allows for custom implementations and extensions.

5. **Minimal Dependencies**
   - Core functionality has minimal dependencies.
   - Format-specific dependencies only required for formats you use.

## Data Flow

When working with Derafu Spreadsheet, data flows through the components as follows:

### Loading Process:
1. **Loader** receives a file or string.
2. **Factory** creates appropriate Format Handler based on format.
3. **Format Handler** reads raw data into internal Spreadsheet structure.
4. **Caster** converts raw values to appropriate PHP types.
5. Typed **Spreadsheet** object is returned to application.

### Saving Process:
1. **Dumper** receives a Spreadsheet object and target format.
2. **Caster** converts PHP values to appropriate string representations.
3. **Factory** creates appropriate Format Handler for target format.
4. **Format Handler** writes structured data to file or string.
5. File path or string content is returned to application.

## Extensibility

Derafu Spreadsheet is designed to be extensible:

- **Custom Format Handlers**: Create handlers for proprietary or custom formats.
- **Custom Casters**: Implement specialized type conversion logic.
- **HTTP Integration**: Generate responses with downloadable spreadsheets.
- **Framework Integration**: Easy to integrate with popular PHP frameworks.

## Directory Structure

```
src/
├── Abstract/
│   └── AbstractPhpSpreadsheetFormatHandler.php
├── Contract/
│   ├── SpreadsheetCasterInterface.php
│   ├── SpreadsheetDumperInterface.php
│   ├── FactoryInterface.php
│   ├── FormatHandlerInterface.php
│   ├── SpreadsheetLoaderInterface.php
│   ├── SheetInterface.php
│   ├── SpreadsheetInterface.php
│   └── Http/
│       └── SpreadsheetHttpResponseGeneratorInterface.php
├── Exception/
│   ├── SpreadsheetDumpException.php
│   ├── SpreadsheetFileNotFoundException.php
│   ├── SpreadsheetFormatNotSupportedException.php
│   └── SpreadsheetLoadException.php
├── Format/
│   ├── CsvLeagueHandler.php
│   ├── CsvPhpSpreadsheetHandler.php
│   ├── HtmlHandler.php
│   ├── JsonHandler.php
│   ├── OdsHandler.php
│   ├── PdfHandler.php
│   ├── XlsHandler.php
│   ├── XlsxHandler.php
│   ├── XmlHandler.php
│   └── YamlHandler.php
├── Http/
│   └── NyholmSpreadsheetHttpResponseGenerator.php
├── Caster.php
├── Dumper.php
├── Factory.php
├── Loader.php
├── Sheet.php
└── Spreadsheet.php
```
