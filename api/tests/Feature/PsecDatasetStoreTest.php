<?php

use App\Services\Tax\StripeExportDatasetStore;

beforeEach(function () {
    $this->datasetDirectory = sys_get_temp_dir() . '/psec-dataset-' . bin2hex(random_bytes(8));
    mkdir($this->datasetDirectory);
    $this->datasetStore = new class ($this->datasetDirectory) extends StripeExportDatasetStore {
        public function __construct(private string $directory)
        {
        }
        public function basePath(): string
        {
            return $this->directory;
        }
    };
});
afterEach(function () {
    \Illuminate\Support\Facades\File::deleteDirectory($this->datasetDirectory);
});

it('rejects dataset traversal before touching the filesystem', function (string $id) {
    expect(fn () => $this->datasetStore->datasetPath($id))->toThrow(InvalidArgumentException::class);
})->with(['../outside', '/tmp/outside', 'a/b', 'a\\b', '..', '', 'a' . "\0"]);

it('rejects chunk traversal', function () {
    expect(fn () => $this->datasetStore->chunkPath('dataset-2026', '../outside'))->toThrow(InvalidArgumentException::class);
});

it('keeps the previous complete file when JSON encoding fails', function () {
    $this->datasetStore->initialize('dataset-2026', ['label' => 'valid']);
    expect(fn () => $this->datasetStore->updateMetadata('dataset-2026', ['label' => "\xB1\x31"]))->toThrow(JsonException::class);
    expect($this->datasetStore->readMetadata('dataset-2026')['label'])->toBe('valid');
});

it('writes and merges valid dataset chunks', function () {
    $this->datasetStore->initialize('dataset-2026');
    $this->datasetStore->writeChunk('dataset-2026', 'chunk_01', [['invoice_id' => 'inv_1', 'created_ts' => 1]]);
    expect($this->datasetStore->mergeChunks('dataset-2026'))->toHaveCount(1)
        ->and($this->datasetStore->readMetadata('dataset-2026')['status'])->toBe('completed');
});
