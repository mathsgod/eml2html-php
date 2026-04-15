# eml2html-php

A PHP library to convert EML files into self-contained HTML, with all inline images embedded as base64 data URIs.

## Requirements

- PHP 8.0+
- [`ext-mailparse`](https://www.php.net/manual/en/book.mailparse.php) (`pecl install mailparse`)

## Installation

```bash
composer require mathsgod/eml2html
```

## Usage

### Convert from a file

```php
use Eml2Html\Converter;

$converter = new Converter();
$html = $converter->convertFile('/path/to/email.eml');

file_put_contents('output.html', $html);
```

### Convert from a string

```php
use Eml2Html\Converter;

$converter = new Converter();
$eml = file_get_contents('/path/to/email.eml');
$html = $converter->convertString($eml);
```

## How it works

1. Parses the EML file using [`php-mime-mail-parser`](https://github.com/php-mime-mail-parser/php-mime-mail-parser).
2. Extracts the HTML body (Quoted-Printable and charset decoding handled automatically).
3. Collects all inline attachments and maps each `Content-ID` to a base64 data URI.
4. Replaces every `cid:` reference in the HTML (including the `cid:<id>` bracketed variant emitted by Microsoft Word/Outlook) with the corresponding data URI, producing a fully self-contained HTML file.

## API

### `Converter::convertFile(string $path): string`

Reads the EML file at `$path` and returns self-contained HTML.  
Throws `\InvalidArgumentException` if the file cannot be read.

### `Converter::convertString(string $eml): string`

Accepts raw EML content as a string and returns self-contained HTML.

## License

MIT
