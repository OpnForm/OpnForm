<?php

namespace App\Service\Pdf\Modern;

use setasign\Fpdi\PdfParser\Filter\Ascii85;
use setasign\Fpdi\PdfParser\Filter\AsciiHex;
use setasign\Fpdi\PdfParser\Filter\FilterException;
use setasign\Fpdi\PdfParser\Filter\Flate;
use setasign\Fpdi\PdfParser\Filter\Lzw;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObjectReference;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNull;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfType;

class ModernPdfStreamDecoder
{
    public static function decode(PdfStream $stream, PdfParser $parser, bool $resolveIndirectMetadata = true): string
    {
        $data = $stream->getStream();
        $filters = self::filters($stream, $parser, $resolveIndirectMetadata);

        if ($filters === []) {
            return $data;
        }

        $decodeParams = self::decodeParams($stream, $parser, $resolveIndirectMetadata);

        foreach ($filters as $key => $filter) {
            $decodeParam = $decodeParams[$key] ?? null;

            $data = match ($filter->value) {
                'FlateDecode', 'Fl' => (new Flate())->decode($data),
                'LZWDecode', 'LZW' => (new Lzw())->decode($data),
                'ASCII85Decode', 'A85' => (new Ascii85())->decode($data),
                'ASCIIHexDecode', 'AHx' => (new AsciiHex())->decode($data),
                'Crypt' => self::decodeCrypt($decodeParam, $data),
                default => throw new FilterException(
                    sprintf('Unsupported filter "%s".', $filter->value),
                    FilterException::UNSUPPORTED_FILTER,
                ),
            };

            if ($decodeParam instanceof PdfDictionary) {
                $data = self::decodePredictor($data, $decodeParam, $parser);
            }
        }

        return $data;
    }

    /**
     * @return array<int, PdfName>
     */
    private static function filters(PdfStream $stream, PdfParser $parser, bool $resolveIndirectMetadata): array
    {
        $filters = PdfDictionary::get($stream->value, 'Filter');
        if ($resolveIndirectMetadata) {
            $filters = PdfType::resolve($filters, $parser);
        }

        if ($filters instanceof PdfNull) {
            return [];
        }

        if ($filters instanceof PdfName) {
            return [$filters];
        }

        if (self::isIndirect($filters)) {
            throw new FilterException(
                'XRef stream filter metadata must use direct objects.',
                FilterException::UNSUPPORTED_FILTER,
            );
        }

        if (!$filters instanceof PdfArray) {
            throw new FilterException('PDF stream filter must be a name or array.', FilterException::UNSUPPORTED_FILTER);
        }

        $resolved = [];

        foreach ($filters->value as $filter) {
            if ($resolveIndirectMetadata) {
                $filter = PdfType::resolve($filter, $parser);
            }

            if ($filter instanceof PdfNull) {
                continue;
            }

            if (self::isIndirect($filter)) {
                throw new FilterException(
                    'XRef stream filter metadata must use direct objects.',
                    FilterException::UNSUPPORTED_FILTER,
                );
            }

            if (!$filter instanceof PdfName) {
                throw new FilterException(
                    'PDF stream filter array must contain only names.',
                    FilterException::UNSUPPORTED_FILTER,
                );
            }

            $resolved[] = $filter;
        }

        return $resolved;
    }

    /**
     * @return array<int, PdfDictionary|null>
     */
    private static function decodeParams(PdfStream $stream, PdfParser $parser, bool $resolveIndirectMetadata): array
    {
        $decodeParams = PdfDictionary::get($stream->value, 'DecodeParms');
        if ($resolveIndirectMetadata) {
            $decodeParams = PdfType::resolve($decodeParams, $parser);
        }

        if ($decodeParams instanceof PdfNull) {
            return [];
        }

        if ($decodeParams instanceof PdfDictionary) {
            return [$decodeParams];
        }

        if (self::isIndirect($decodeParams)) {
            throw new FilterException(
                'XRef stream DecodeParms metadata must use direct objects.',
                FilterException::UNSUPPORTED_FILTER,
            );
        }

        if (!$decodeParams instanceof PdfArray) {
            throw new FilterException(
                'PDF stream DecodeParms must be a dictionary, array, or null.',
                FilterException::UNSUPPORTED_FILTER,
            );
        }

        $resolved = [];

        foreach ($decodeParams->value as $decodeParam) {
            if ($resolveIndirectMetadata) {
                $decodeParam = PdfType::resolve($decodeParam, $parser);
            }

            if ($decodeParam instanceof PdfNull) {
                $resolved[] = null;
                continue;
            }

            if (self::isIndirect($decodeParam)) {
                throw new FilterException(
                    'XRef stream DecodeParms metadata must use direct objects.',
                    FilterException::UNSUPPORTED_FILTER,
                );
            }

            if (!$decodeParam instanceof PdfDictionary) {
                throw new FilterException(
                    'PDF stream DecodeParms array must contain only dictionaries or null values.',
                    FilterException::UNSUPPORTED_FILTER,
                );
            }

            $resolved[] = $decodeParam;
        }

        return $resolved;
    }

    private static function isIndirect(PdfType $value): bool
    {
        return $value instanceof PdfIndirectObjectReference || $value instanceof PdfIndirectObject;
    }

    private static function decodeCrypt(?PdfDictionary $decodeParam, string $data): string
    {
        if (!$decodeParam instanceof PdfDictionary) {
            return $data;
        }

        $name = PdfDictionary::get($decodeParam, 'Name');
        if ($name instanceof PdfName && $name->value === 'Identity') {
            return $data;
        }

        throw new FilterException(
            'Support for Crypt filters other than "Identity" is not implemented.',
            FilterException::UNSUPPORTED_FILTER,
        );
    }

    private static function decodePredictor(string $data, PdfDictionary $decodeParam, PdfParser $parser): string
    {
        $predictor = self::numericDecodeParam($decodeParam, 'Predictor', 1, $parser);

        if ($predictor === 1 || $data === '') {
            return $data;
        }

        $colors = self::numericDecodeParam($decodeParam, 'Colors', 1, $parser);
        $bitsPerComponent = self::numericDecodeParam($decodeParam, 'BitsPerComponent', 8, $parser);
        $columns = self::numericDecodeParam($decodeParam, 'Columns', 1, $parser);

        if ($columns <= 0 || $colors <= 0 || $bitsPerComponent <= 0) {
            throw new FilterException('Invalid predictor decode parameters.', FilterException::UNSUPPORTED_FILTER);
        }

        if ($predictor === 2) {
            return self::decodeTiffPredictor($data, $colors, $bitsPerComponent, $columns);
        }

        if ($predictor >= 10 && $predictor <= 15) {
            return self::decodePngPredictor($data, $colors, $bitsPerComponent, $columns);
        }

        throw new FilterException(
            sprintf('Unsupported predictor "%s".', $predictor),
            FilterException::UNSUPPORTED_FILTER,
        );
    }

    private static function decodeTiffPredictor(
        string $data,
        int $colors,
        int $bitsPerComponent,
        int $columns,
    ): string {
        if ($bitsPerComponent !== 8) {
            throw new FilterException(
                'TIFF predictors with bits per component other than 8 are not implemented.',
                FilterException::NOT_IMPLEMENTED,
            );
        }

        $rowLength = self::rowLength($colors, $bitsPerComponent, $columns);
        $bytesPerPixel = self::bytesPerPixel($colors, $bitsPerComponent);
        $decoded = '';

        for ($offset = 0, $length = strlen($data); $offset < $length; $offset += $rowLength) {
            $row = substr($data, $offset, $rowLength);
            $rowBytes = strlen($row);

            for ($i = $bytesPerPixel; $i < $rowBytes; $i++) {
                $row[$i] = chr((ord($row[$i]) + ord($row[$i - $bytesPerPixel])) & 0xff);
            }

            $decoded .= $row;
        }

        return $decoded;
    }

    private static function decodePngPredictor(
        string $data,
        int $colors,
        int $bitsPerComponent,
        int $columns,
    ): string {
        if (!in_array($bitsPerComponent, [1, 2, 4, 8, 16], true)) {
            throw new FilterException(
                sprintf('Unsupported PNG predictor bits per component "%s".', $bitsPerComponent),
                FilterException::UNSUPPORTED_FILTER,
            );
        }

        $rowLength = self::rowLength($colors, $bitsPerComponent, $columns);
        $incomingRowLength = $rowLength + 1;
        $remainder = strlen($data) % $incomingRowLength;
        if ($remainder !== 0) {
            $data .= str_repeat("\0", $incomingRowLength - $remainder);
        }

        $bytesPerPixel = self::bytesPerPixel($colors, $bitsPerComponent);
        $decoded = '';
        $previousRow = str_repeat("\0", $rowLength);

        for ($offset = 0, $length = strlen($data); $offset < $length; $offset += $incomingRowLength) {
            $filterType = ord($data[$offset]);
            $row = substr($data, $offset + 1, $rowLength);
            $decodedRow = '';

            for ($i = 0; $i < $rowLength; $i++) {
                $raw = ord($row[$i]);
                $left = $i >= $bytesPerPixel ? ord($decodedRow[$i - $bytesPerPixel]) : 0;
                $up = ord($previousRow[$i]);
                $upperLeft = $i >= $bytesPerPixel ? ord($previousRow[$i - $bytesPerPixel]) : 0;

                $decodedRow .= chr(($raw + self::pngPredictorValue($filterType, $left, $up, $upperLeft)) & 0xff);
            }

            $decoded .= $decodedRow;
            $previousRow = $decodedRow;
        }

        return $decoded;
    }

    private static function pngPredictorValue(int $filterType, int $left, int $up, int $upperLeft): int
    {
        return match ($filterType) {
            0 => 0,
            1 => $left,
            2 => $up,
            3 => intdiv($left + $up, 2),
            4 => self::paeth($left, $up, $upperLeft),
            default => throw new FilterException(
                sprintf('Unsupported PNG predictor row filter "%s".', $filterType),
                FilterException::UNSUPPORTED_FILTER,
            ),
        };
    }

    private static function paeth(int $left, int $up, int $upperLeft): int
    {
        $p = $left + $up - $upperLeft;
        $pa = abs($p - $left);
        $pb = abs($p - $up);
        $pc = abs($p - $upperLeft);

        if ($pa <= $pb && $pa <= $pc) {
            return $left;
        }

        if ($pb <= $pc) {
            return $up;
        }

        return $upperLeft;
    }

    private static function numericDecodeParam(
        PdfDictionary $dictionary,
        string $key,
        int $default,
        PdfParser $parser,
    ): int {
        $value = PdfDictionary::get($dictionary, $key, PdfNumeric::create($default));

        if ($value instanceof PdfNull) {
            return $default;
        }

        $resolved = PdfType::resolve($value, $parser);

        if (!$resolved instanceof PdfNumeric) {
            throw new FilterException(
                sprintf('Numeric predictor decode parameter "%s" expected.', $key),
                FilterException::UNSUPPORTED_FILTER,
            );
        }

        return (int) $resolved->value;
    }

    private static function rowLength(int $colors, int $bitsPerComponent, int $columns): int
    {
        return max(1, (int) ceil($colors * $columns * $bitsPerComponent / 8));
    }

    private static function bytesPerPixel(int $colors, int $bitsPerComponent): int
    {
        return max(1, (int) ceil($colors * $bitsPerComponent / 8));
    }
}
