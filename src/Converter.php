<?php

declare(strict_types=1);

namespace Eml2Html;

use PhpMimeMailParser\Parser;

class Converter
{
    /**
     * Convert a raw EML string to self-contained HTML.
     *
     * All inline images referenced via cid: are replaced with base64 data URIs
     * so the returned HTML string has no external dependencies.
     *
     * @param string $eml Raw EML content.
     * @return string Self-contained HTML.
     */
    public function convertString(string $eml): string
    {
        $parser = new Parser();
        $parser->setText($eml);

        return $this->process($parser);
    }

    /**
     * Convert an EML file to self-contained HTML.
     *
     * @param string $path Path to the .eml file.
     * @return string Self-contained HTML.
     * @throws \InvalidArgumentException When the file cannot be read.
     */
    public function convertFile(string $path): string
    {
        if (!is_readable($path)) {
            throw new \InvalidArgumentException("Cannot read file: $path");
        }

        $parser = new Parser();
        $parser->setPath($path);

        return $this->process($parser);
    }

    /**
     * Run the conversion pipeline on an already-configured Parser instance.
     */
    private function process(Parser $parser): string
    {
        $html = (string) $parser->getMessageBody('html');

        $cidMap = $this->buildCidMap($parser);

        return $this->embedImages($html, $cidMap);
    }

    /**
     * Build a map of Content-ID → base64 data URI for every inline attachment.
     *
     * EML files reference inline images via cid:<content-id> in the HTML body.
     * The Content-ID header value may or may not be wrapped in angle brackets.
     *
     * @return array<string, string>  Keys are bare Content-IDs (no angle brackets).
     */
    private function buildCidMap(Parser $parser): array
    {
        $cidMap = [];

        foreach ($parser->getAttachments(true) as $attachment) {
            $contentId = $attachment->getContentId();
            if ($contentId === '' || $contentId === null) {
                continue;
            }

            // Strip surrounding angle brackets if present (e.g. <image001.png@...>)
            $contentId = trim($contentId, '<>');

            $mimeType = $attachment->getContentType();
            $dataUri  = "data:$mimeType;base64," . base64_encode($attachment->getContent());

            $cidMap[$contentId] = $dataUri;
        }

        return $cidMap;
    }

    /**
     * Replace every cid: reference in $html with the matching base64 data URI.
     *
     * Handles both plain `cid:id` and bracketed `cid:<id>` variants that
     * Microsoft Word and Outlook may emit.
     *
     * @param array<string, string> $cidMap
     */
    private function embedImages(string $html, array $cidMap): string
    {
        if (empty($cidMap)) {
            return $html;
        }

        foreach ($cidMap as $contentId => $dataUri) {
            $html = str_replace(
                ["cid:$contentId", "cid:<$contentId>", "cid:&lt;$contentId&gt;"],
                $dataUri,
                $html
            );
        }

        return $html;
    }
}
