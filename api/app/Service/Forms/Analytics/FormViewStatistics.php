<?php

namespace App\Service\Forms\Analytics;

use App\Models\Forms\Form;
use Carbon\Carbon;
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

    public function dailyCounts(Form $form, string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $views = DB::table('form_views')->where('form_id', $form->id)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end->copy()->addDay())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as view_count, NULL as data')
            ->groupByRaw('DATE(created_at)');
        $history = DB::table('form_statistics')->where('form_id', $form->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('date, 0 as view_count, data');

        // Read both sources in one snapshot so cleanup cannot move views between reads.
        $counts = [];
        foreach ($views->unionAll($history)->cursor() as $row) {
            $count = $row->data === null ? $row->view_count : (json_decode($row->data, true)['views'] ?? 0);
            $counts[$row->date] = ($counts[$row->date] ?? 0) + (int) $count;
        }

        return $counts;
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
