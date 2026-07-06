<?php

namespace App\Service\Pdf\Modern;

use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfParser\Type\PdfTypeException;

class ObjectStreamResolver
{
    /**
     * @var array<int, array<int, PdfIndirectObject>>
     */
    private array $objectsByStream = [];

    public function __construct(private readonly PdfParser $parser)
    {
    }

    /**
     * @throws CrossReferenceException
     * @throws PdfTypeException
     */
    public function resolve(int $objectNumber, int $objectStreamNumber, int $objectIndex): PdfIndirectObject
    {
        $objects = $this->objectsByStream[$objectStreamNumber] ??= $this->readObjectStream($objectStreamNumber);

        if (isset($objects[$objectNumber])) {
            return $objects[$objectNumber];
        }

        throw new CrossReferenceException(
            sprintf(
                'Object (id:%s) not found in object stream (id:%s, index:%s).',
                $objectNumber,
                $objectStreamNumber,
                $objectIndex,
            ),
            CrossReferenceException::OBJECT_NOT_FOUND,
        );
    }

    /**
     * @return array<int, PdfIndirectObject>
     *
     * @throws CrossReferenceException
     * @throws PdfTypeException
     */
    private function readObjectStream(int $objectStreamNumber): array
    {
        $object = $this->parser->getIndirectObject($objectStreamNumber, true);
        $stream = PdfStream::ensure($object->value);
        $type = PdfDictionary::get($stream->value, 'Type');

        if (!$type instanceof PdfName || $type->value !== 'ObjStm') {
            throw new CrossReferenceException(
                sprintf('Object (id:%s) is not an object stream.', $objectStreamNumber),
                CrossReferenceException::INVALID_DATA,
            );
        }

        $objectCount = $this->numericValue(PdfDictionary::get($stream->value, 'N'));
        $firstObjectOffset = $this->numericValue(PdfDictionary::get($stream->value, 'First'));
        $data = ModernPdfStreamDecoder::decode($stream, $this->parser);

        if ($firstObjectOffset < 0 || $firstObjectOffset > strlen($data)) {
            throw new CrossReferenceException(
                sprintf('Object stream (id:%s) has an invalid first object offset.', $objectStreamNumber),
                CrossReferenceException::INVALID_DATA,
            );
        }

        $pairs = $this->readObjectTable(substr($data, 0, $firstObjectOffset), $objectCount, $objectStreamNumber);
        $objects = [];

        foreach ($pairs as $index => ['number' => $number, 'offset' => $relativeOffset]) {
            $nextOffset = $pairs[$index + 1]['offset'] ?? (strlen($data) - $firstObjectOffset);
            $length = $nextOffset - $relativeOffset;

            if ($relativeOffset < 0 || $length < 0) {
                throw new CrossReferenceException(
                    sprintf('Object stream (id:%s) contains invalid object offsets.', $objectStreamNumber),
                    CrossReferenceException::INVALID_DATA,
                );
            }

            $slice = substr($data, $firstObjectOffset + $relativeOffset, $length);
            $valueParser = new ModernPdfParser(StreamReader::createByString($slice));
            $value = $valueParser->readValue();

            if (!$value instanceof PdfType) {
                throw new CrossReferenceException(
                    sprintf('Unable to parse object (id:%s) from object stream (id:%s).', $number, $objectStreamNumber),
                    CrossReferenceException::INVALID_DATA,
                );
            }

            $objects[$number] = PdfIndirectObject::create($number, 0, $value);
        }

        return $objects;
    }

    /**
     * @return array<int, array{number:int,offset:int}>
     *
     * @throws CrossReferenceException
     * @throws PdfTypeException
     */
    private function readObjectTable(string $header, int $objectCount, int $objectStreamNumber): array
    {
        $parser = new ModernPdfParser(StreamReader::createByString($header));
        $pairs = [];

        for ($i = 0; $i < $objectCount; $i++) {
            $number = $parser->readValue(null, PdfNumeric::class);
            $offset = $parser->readValue(null, PdfNumeric::class);

            if (!$number instanceof PdfNumeric || !$offset instanceof PdfNumeric) {
                throw new CrossReferenceException(
                    sprintf('Object stream (id:%s) has an invalid object table.', $objectStreamNumber),
                    CrossReferenceException::INVALID_DATA,
                );
            }

            $pairs[] = [
                'number' => (int) $number->value,
                'offset' => (int) $offset->value,
            ];
        }

        if ($pairs === [] && $objectCount > 0) {
            throw new CrossReferenceException(
                sprintf('Object stream (id:%s) has an invalid object table.', $objectStreamNumber),
                CrossReferenceException::INVALID_DATA,
            );
        }

        return $pairs;
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
                'Numeric value expected in object stream.',
                CrossReferenceException::INVALID_DATA,
            );
        }

        return (int) $resolved->value;
    }
}
