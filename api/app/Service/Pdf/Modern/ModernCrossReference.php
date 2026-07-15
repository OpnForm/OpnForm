<?php

namespace App\Service\Pdf\Modern;

use setasign\Fpdi\PdfParser\CrossReference\CrossReference;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\CrossReference\FixedReader;
use setasign\Fpdi\PdfParser\CrossReference\ReaderInterface;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfIndirectObject;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfParser\Type\PdfTypeException;

class ModernCrossReference extends CrossReference
{
    private ObjectStreamResolver $objectStreamResolver;

    private ?PdfDictionary $primaryTrailer = null;

    /**
     * @var array<int, true>
     */
    private array $hybridXrefStreamOffsets = [];

    public function __construct(PdfParser $parser, $fileHeaderOffset = 0)
    {
        $parser->getTokenizer()->clearStack();

        $this->parser = $parser;
        $this->fileHeaderOffset = $fileHeaderOffset;
        $this->objectStreamResolver = new ObjectStreamResolver($parser);

        $offset = $this->findStartXref();
        $reader = null;

        while ($offset != false) {
            try {
                $reader = $this->readXref($offset + $this->fileHeaderOffset);
            } catch (CrossReferenceException $e) {
                if ($e->getCode() === CrossReferenceException::INVALID_DATA && $this->fileHeaderOffset !== 0) {
                    $this->fileHeaderOffset = 0;
                    $reader = $this->readXref($offset);
                } else {
                    throw $e;
                }
            }

            $trailer = $reader->getTrailer();
            $this->checkForEncryption($trailer);
            $this->primaryTrailer ??= $trailer;

            $hybridReader = $this->readHybridXrefStream($trailer);
            if ($hybridReader !== null) {
                $this->readers[] = $hybridReader;
            }

            $this->readers[] = $reader;

            if (isset($trailer->value['Prev'])) {
                $offset = $trailer->value['Prev']->value;
            } else {
                $offset = false;
            }
        }

        if ($reader instanceof FixedReader) {
            $reader->fixFaultySubSectionShift();
        }

        if ($reader === null) {
            throw new CrossReferenceException('No cross-reference found.', CrossReferenceException::NO_XREF_FOUND);
        }
    }

    public function getTrailer()
    {
        return $this->primaryTrailer ?? parent::getTrailer();
    }

    public function getIndirectObject($objectNumber)
    {
        $objectNumber = (int) $objectNumber;
        $location = $this->findLocationFor($objectNumber);

        if ($location === null) {
            throw new CrossReferenceException(
                sprintf('Object (id:%s) not found.', $objectNumber),
                CrossReferenceException::OBJECT_NOT_FOUND,
            );
        }

        if ($location['type'] === 'compressed') {
            return $this->objectStreamResolver->resolve(
                $objectNumber,
                $location['object_stream_number'],
                $location['index'],
            );
        }

        return $this->readIndirectObjectAtOffset($objectNumber, $location['offset']);
    }

    protected function initReaderInstance($initValue)
    {
        if ($initValue instanceof PdfIndirectObject) {
            try {
                $stream = PdfStream::ensure($initValue->value);
            } catch (PdfTypeException $e) {
                throw new CrossReferenceException(
                    'Invalid object type at xref reference offset.',
                    CrossReferenceException::INVALID_DATA,
                    $e,
                );
            }

            $type = PdfDictionary::get($stream->value, 'Type');
            if (!$type instanceof PdfName || $type->value !== 'XRef') {
                throw new CrossReferenceException(
                    'The xref position points to an incorrect object type.',
                    CrossReferenceException::INVALID_DATA,
                );
            }

            $this->checkForEncryption($stream->value);

            return new XrefStreamReader($this->parser, $stream);
        }

        return parent::initReaderInstance($initValue);
    }

    private function readHybridXrefStream(PdfDictionary $trailer): ?ReaderInterface
    {
        if (!isset($trailer->value['XRefStm'])) {
            return null;
        }

        $xrefStreamOffset = $this->numericValue($trailer->value['XRefStm']);
        if (isset($this->hybridXrefStreamOffsets[$xrefStreamOffset])) {
            return null;
        }

        $this->hybridXrefStreamOffsets[$xrefStreamOffset] = true;

        try {
            return $this->readXref($xrefStreamOffset + $this->fileHeaderOffset);
        } catch (CrossReferenceException $e) {
            if ($e->getCode() === CrossReferenceException::INVALID_DATA && $this->fileHeaderOffset !== 0) {
                return $this->readXref($xrefStreamOffset);
            }

            throw $e;
        }
    }

    /**
     * @return array{type:string,offset:int}|array{type:string,object_stream_number:int,index:int}|null
     */
    private function findLocationFor(int $objectNumber): ?array
    {
        foreach ($this->getReaders() as $reader) {
            if ($reader instanceof XrefStreamReader) {
                if ($reader->hasOffsetFor($objectNumber)) {
                    return [
                        'type' => 'offset',
                        'offset' => $reader->getOffsetFor($objectNumber),
                    ];
                }

                $compressedEntry = $reader->getCompressedEntryFor($objectNumber);
                if ($compressedEntry !== null) {
                    return [
                        'type' => 'compressed',
                        'object_stream_number' => $compressedEntry['object_stream_number'],
                        'index' => $compressedEntry['index'],
                    ];
                }

                if ($reader->hasFreeEntryFor($objectNumber)) {
                    return null;
                }

                continue;
            }

            $offset = $reader->getOffsetFor($objectNumber);
            if ($offset !== false) {
                return [
                    'type' => 'offset',
                    'offset' => $offset,
                ];
            }
        }

        return null;
    }

    private function readIndirectObjectAtOffset(int $objectNumber, int $offset): PdfIndirectObject
    {
        $parser = $this->parser;
        $parser->getTokenizer()->clearStack();
        $parser->getStreamReader()->reset($offset + $this->fileHeaderOffset);

        try {
            $object = $parser->readValue(null, PdfIndirectObject::class);
        } catch (PdfTypeException $e) {
            throw new CrossReferenceException(
                sprintf('Object (id:%s) not found at location (%s).', $objectNumber, $offset),
                CrossReferenceException::OBJECT_NOT_FOUND,
                $e,
            );
        }

        if (!$object instanceof PdfIndirectObject || $object->objectNumber !== $objectNumber) {
            $actualObjectNumber = $object instanceof PdfIndirectObject ? $object->objectNumber : 'unknown';

            throw new CrossReferenceException(
                sprintf('Wrong object found, got %s while %s was expected.', $actualObjectNumber, $objectNumber),
                CrossReferenceException::OBJECT_NOT_FOUND,
            );
        }

        return $object;
    }

    private function numericValue($value): int
    {
        $resolved = PdfType::resolve($value, $this->parser);

        if (!$resolved instanceof PdfNumeric) {
            throw new CrossReferenceException(
                'Numeric value expected in cross-reference trailer.',
                CrossReferenceException::INVALID_DATA,
            );
        }

        return (int) $resolved->value;
    }
}
