<?php

namespace App\Service\Pdf\Modern;

use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\PdfParserException;

class ModernPdfParser extends PdfParser
{
    /**
     * @throws CrossReferenceException
     * @throws PdfParserException
     */
    public function getCrossReference()
    {
        if ($this->xref === null) {
            $this->xref = new ModernCrossReference($this, $this->resolveFileHeader());
        }

        return $this->xref;
    }
}
