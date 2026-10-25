<?php

namespace App\Service\Forms\Analytics;

use App\Models\Forms\Form;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FormViewStatistics
{
    private const FIELDS = ['source', 'device', 'country', 'browser', 'os'];

    public function emptyMetadata(): array
    {
        return array_fill_keys(self::FIELDS, []);
    }

    public function mergeMetadata(array $stats, array $counts): array
    {
        foreach (self::FIELDS as $field) {
            foreach ($counts[$field] ?? [] as $value => $count) {
                $stats[$field][$value] = ($stats[$field][$value] ?? 0) + $count;
            }
        }

        return $stats;
    }

    public function rowMetadata(object $row): array
    {
        $stats = $this->emptyMetadata();
        foreach (self::FIELDS as $field) {
            $stats[$field][$row->$field] = (int) $row->count;
        }

        return $stats;
    }

    public function groupedViews(Builder $query): Builder
    {
        $pattern = $query->getConnection()->getDriverName() === 'mysql'
            ? "COALESCE(NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.%s')), ''), 'null'), 'Unknown')"
            : "COALESCE(NULLIF(NULLIF(meta->>'%s', ''), 'null'), 'Unknown')";

        foreach (self::FIELDS as $field) {
            $expression = sprintf($pattern, $field);
            $query->selectRaw("$expression as $field")->groupByRaw($expression);
        }

        return $query->selectRaw('COUNT(*) as count');
    }

    public function metadata(Form $form): array
    {
        $views = $this->groupedViews(DB::table('form_views')->where('form_id', $form->id))
            ->selectRaw('NULL as data');
        $history = DB::table('form_statistics')->where('form_id', $form->id);
        foreach (self::FIELDS as $field) {
            $history->selectRaw("NULL as $field");
        }
        $history->selectRaw('0 as count')->addSelect('data');

        // One statement keeps both sources on the same snapshot during cleanup.
        $stats = $this->emptyMetadata();
        foreach ($views->unionAll($history)->cursor() as $row) {
            $counts = $row->data === null
                ? $this->rowMetadata($row)
                : (json_decode($row->data, true)['meta_stats'] ?? []);
            $stats = $this->mergeMetadata($stats, $counts);
        }

        foreach (self::FIELDS as $field) {
            arsort($stats[$field]);
        }

        return $stats;
    }
}
