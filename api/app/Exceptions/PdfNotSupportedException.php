<?php

namespace App\Exceptions;

use Exception;

class PdfNotSupportedException extends Exception
{
    public function __construct(string $message = null)
    {
        parent::__construct(
            $message ?? 'This PDF uses compression or features not supported. Please try re-saving your PDF with compatibility mode (PDF 1.4) or use a simpler PDF.'
        );
    }
}
