<?php

namespace setasign\FpdiPdfParser\PdfParser;

use App\Service\Pdf\Modern\ModernPdfParser;
use setasign\Fpdi\PdfParser\StreamReader;

class PdfParser extends ModernPdfParser
{
    /**
     * @param array<string, mixed> $parserParams
     */
    public function __construct(StreamReader $streamReader, array $parserParams = [])
    {
        if ($parserParams !== []) {
            // FPDI forwards params for commercial parser compatibility; OpnForm does not need extra parser options.
            $parserParams = [];
        }

        parent::__construct($streamReader);
    }
}
