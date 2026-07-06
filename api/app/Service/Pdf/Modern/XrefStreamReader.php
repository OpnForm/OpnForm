<?php

namespace App\Service\Pdf\Modern;

use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\CrossReference\ReaderInterface;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfParser\Type\PdfTypeException;

class XrefStreamReader implements ReaderInterface
{
    private PdfDictionary $trailer;

    /**
     * @var array<int, int>
     */
    private array $offsets = [];

    /**
     * @var array<int, array{object_stream_number:int,index:int}>
     */
    private array $compressedEntries = [];

    /**
     * @var array<int, true>
     */
    private array $freeEntries = [];

    /**
     * @throws CrossReferenceException
     * @throws PdfTypeException
     */
    public function __construct(
        private readonly PdfParser $parser,
        private readonly PdfStream $stream,
    ) {
        $this->trailer = $stream->value;
        $this->read();
    }

    public function getOffsetFor($objectNumber)
    {
        return $this->offsets[(int) $objectNumber] ?? false;
    }

    public function getTrailer()
    {
        return $this->trailer;
    }

    public function hasOffsetFor(int $objectNumber): bool
    {
        return array_key_exists($objectNumber, $this->offsets);
    }

    /**
     * @return array{object_stream_number:int,index:int}|null
     */
    public function getCompressedEntryFor(int $objectNumber): ?array
    {
        return $this->compressedEntries[$objectNumber] ?? null;
    }

    public function hasFreeEntryFor(int $objectNumber): bool
    {
        return isset($this->freeEntries[$objectNumber]);
    }

    /**
     * @throws CrossReferenceException
     * @throws PdfTypeException
     */
    private function read(): void
    {
        $widths = $this->readWidths();
        $entryLength = array_sum($widths);

        if ($entryLength <= 0) {
            throw new CrossReferenceException(
                'Invalid xref stream entry width.',
                CrossReferenceException::INVALID_DATA,
            );
        }

        $index = $this->readIndex();
        $data = ModernPdfStreamDecoder::decode($this->stream, $this->parser, resolveIndirectMetadata: false);
        $position = 0;

        for ($i = 0, $count = count($index); $i < $count; $i += 2) {
            $objectNumber = $index[$i];
            $objectCount = $index[$i + 1];

            for ($entry = 0; $entry < $objectCount; $entry++, $objectNumber++) {
                if ($position + $entryLength > strlen($data)) {
                    throw new CrossReferenceException(
                        'Unexpected end of xref stream.',
                        CrossReferenceException::UNEXPECTED_END,
                    );
                }

                $type = $widths[0] === 0 ? 1 : $this->readInteger($data, $position, $widths[0]);
                $field2 = $this->readInteger($data, $position, $widths[1]);
                $field3 = $this->readInteger($data, $position, $widths[2]);

                match ($type) {
                    0 => $this->freeEntries[$objectNumber] = true,
                    1 => $this->offsets[$objectNumber] = $field2,
                    2 => $this->compressedEntries[$objectNumber] = [
                        'object_stream_number' => $field2,
                        'index' => $field3,
                    ],
                    default => null,
                };
            }
        }
    }

    /**
     * @return array{0:int,1:int,2:int}
     *
     * @throws CrossReferenceException
     * @throws PdfTypeException
     */
    private function readWidths(): array
    {
        $widths = PdfArray::ensure(PdfDictionary::get($this->trailer, 'W'), 3)->value;

        return [
            $this->numericValue($widths[0]),
            $this->numericValue($widths[1]),
            $this->numericValue($widths[2]),
        ];
    }

    /**
     * @return array<int, int>
     *
     * @throws CrossReferenceException
     * @throws PdfTypeException
     */
    private function readIndex(): array
    {
        $index = PdfDictionary::get($this->trailer, 'Index');

        if ($index instanceof PdfArray) {
            $values = [];

            foreach ($index->value as $value) {
                $values[] = $this->numericValue($value);
            }

            if (count($values) % 2 !== 0) {
                throw new CrossReferenceException(
                    'Invalid xref stream index.',
                    CrossReferenceException::INVALID_DATA,
                );
            }

            return $values;
        }

        return [0, $this->numericValue(PdfDictionary::get($this->trailer, 'Size'))];
    }

    /**
     * @throws CrossReferenceException
     * @throws PdfTypeException
     */
    private function numericValue(PdfType $value): int
    {
        $resolved = PdfType::resolve($value, $this->parser);

        if (!$resolved instanceof PdfNumeric) {
            throw new CrossReferenceException(
                'Numeric value expected in xref stream.',
                CrossReferenceException::INVALID_DATA,
            );
        }

        return (int) $resolved->value;
    }

    private function readInteger(string $data, int &$position, int $width): int
    {
        $value = 0;

        for ($i = 0; $i < $width; $i++, $position++) {
            $value = ($value << 8) + ord($data[$position]);
        }

        return $value;
    }
}
