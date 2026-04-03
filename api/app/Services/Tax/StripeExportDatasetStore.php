<?php

namespace App\Services\Tax;

class StripeExportDatasetStore
{
    public function basePath(): string
    {
        return storage_path('app/stripe-export-datasets');
    }

    public function datasetPath(string $datasetId): string
    {
        return $this->basePath() . '/' . $datasetId;
    }

    public function metadataPath(string $datasetId): string
    {
        return $this->datasetPath($datasetId) . '/metadata.json';
    }

    public function datasetRowsPath(string $datasetId): string
    {
        return $this->datasetPath($datasetId) . '/dataset.json';
    }

    public function chunksPath(string $datasetId): string
    {
        return $this->datasetPath($datasetId) . '/chunks';
    }

    public function chunkPath(string $datasetId, string $chunkKey): string
    {
        return $this->chunksPath($datasetId) . '/' . $chunkKey . '.json';
    }

    public function initialize(string $datasetId, array $metadata = []): void
    {
        if (!is_dir($this->chunksPath($datasetId))) {
            mkdir($this->chunksPath($datasetId), 0777, true);
        }

        $this->writeJson($this->metadataPath($datasetId), array_merge([
            'dataset_id' => $datasetId,
            'status' => 'initialized',
            'created_at' => now()->toIso8601String(),
        ], $metadata));
    }

    public function writeChunk(string $datasetId, string $chunkKey, array $rows, array $stats = []): void
    {
        $this->writeJson($this->chunkPath($datasetId, $chunkKey), [
            'chunk_key' => $chunkKey,
            'row_count' => count($rows),
            'stats' => $stats,
            'rows' => $rows,
        ]);
    }

    public function mergeChunks(string $datasetId): array
    {
        $files = glob($this->chunksPath($datasetId) . '/*.json') ?: [];
        sort($files);

        $rows = [];
        $chunkSummaries = [];

        foreach ($files as $file) {
            $payload = $this->readJson($file);
            $rows = array_merge($rows, $payload['rows'] ?? []);
            $chunkSummaries[] = [
                'chunk_key' => $payload['chunk_key'] ?? basename($file, '.json'),
                'row_count' => $payload['row_count'] ?? count($payload['rows'] ?? []),
                'stats' => $payload['stats'] ?? [],
            ];
        }

        usort($rows, function (array $left, array $right) {
            return [$left['created_ts'] ?? 0, $left['invoice_id'] ?? ''] <=> [$right['created_ts'] ?? 0, $right['invoice_id'] ?? ''];
        });

        $this->writeJson($this->datasetRowsPath($datasetId), $rows);

        $metadata = $this->readMetadata($datasetId);
        $metadata['status'] = 'completed';
        $metadata['completed_at'] = now()->toIso8601String();
        $metadata['row_count'] = count($rows);
        $metadata['chunks'] = $chunkSummaries;
        $this->writeJson($this->metadataPath($datasetId), $metadata);

        return $rows;
    }

    public function loadRows(string $datasetId): array
    {
        $path = $this->datasetRowsPath($datasetId);
        if (!file_exists($path)) {
            return $this->mergeChunks($datasetId);
        }

        return $this->readJson($path);
    }

    public function readMetadata(string $datasetId): array
    {
        $path = $this->metadataPath($datasetId);

        return file_exists($path) ? $this->readJson($path) : [];
    }

    private function writeJson(string $path, array $payload): void
    {
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function readJson(string $path): array
    {
        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
