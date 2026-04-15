<?php

declare(strict_types=1);

namespace Eml2Html\Tests;

use Eml2Html\Converter;
use PHPUnit\Framework\TestCase;

class ConverterTest extends TestCase
{
    private Converter $converter;

    protected function setUp(): void
    {
        $this->converter = new Converter();
    }

    private function requireMailparse(): void
    {
        if (!extension_loaded('mailparse')) {
            $this->markTestSkipped('ext-mailparse is not installed.');
        }
    }

    public function testConvertFileThrowsOnMissingFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->converter->convertFile('/nonexistent/file.eml');
    }

    public function testConvertStringReturnsHtml(): void
    {
        $this->requireMailparse();

        $boundary = 'boundary_test_001';
        $eml = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/related; boundary="' . $boundary . '"',
            'Subject: Test',
            '',
            '--' . $boundary,
            'Content-Type: text/html; charset="utf-8"',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            '<html><body><p>Hello</p></body></html>',
            '--' . $boundary . '--',
        ]);

        $html = $this->converter->convertString($eml);

        $this->assertStringContainsString('Hello', $html);
    }

    public function testInlineImagesAreEmbeddedAsDataUris(): void
    {
        $this->requireMailparse();

        // 1×1 transparent GIF
        $gifBinary  = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $boundary   = 'boundary_test_002';
        $contentId  = 'image001.gif@test';

        $eml = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/related; boundary="' . $boundary . '"',
            'Subject: Test with image',
            '',
            '--' . $boundary,
            'Content-Type: text/html; charset="utf-8"',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            '<html><body><img src="cid:' . $contentId . '"></body></html>',
            '',
            '--' . $boundary,
            'Content-Type: image/gif',
            'Content-Transfer-Encoding: base64',
            'Content-ID: <' . $contentId . '>',
            'Content-Disposition: inline',
            '',
            base64_encode($gifBinary),
            '--' . $boundary . '--',
        ]);

        $html = $this->converter->convertString($eml);

        $this->assertStringContainsString('data:image/gif;base64,', $html);
        $this->assertStringNotContainsString('cid:', $html);
    }

    public function testBracketedCidReferencesAreReplaced(): void
    {
        $this->requireMailparse();

        // 1×1 transparent GIF
        $gifBinary = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $boundary  = 'boundary_test_003';
        $contentId = 'image002.gif@word';

        // Word sometimes emits cid:<id> with angle brackets in the HTML src
        $eml = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/related; boundary="' . $boundary . '"',
            'Subject: Word-style bracketed cid',
            '',
            '--' . $boundary,
            'Content-Type: text/html; charset="utf-8"',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            '<html><body><img src="cid:&lt;' . $contentId . '&gt;"></body></html>',
            '',
            '--' . $boundary,
            'Content-Type: image/gif',
            'Content-Transfer-Encoding: base64',
            'Content-ID: <' . $contentId . '>',
            'Content-Disposition: inline',
            '',
            base64_encode($gifBinary),
            '--' . $boundary . '--',
        ]);

        $html = $this->converter->convertString($eml);

        // The data URI should appear somewhere in the output
        $this->assertStringContainsString('data:image/gif;base64,', $html);
    }
}
