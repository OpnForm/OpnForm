<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormStatsRequest;
use App\Models\Forms\FormSubmission;
use App\Models\Workspace;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Forms\Form;
use Illuminate\Support\Facades\DB;

class FormStatsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function getFormStats(FormStatsRequest $request, Workspace $workspace, Form $form)
    {
        $this->authorize('view', $form);

        $formStats = $form->statistics()->whereBetween('date', [$request->date_from, $request->date_to])->get();
        $periodStats = ['views' => [], 'submissions' => [], 'partial_submissions' => []];
        foreach (CarbonPeriod::create($request->date_from, $request->date_to) as $dateObj) {
            $date = $dateObj->format('d-m-Y');

            $statisticData = $formStats->where('date', $dateObj->format('Y-m-d'))->first();
            $periodStats['views'][$date] = $statisticData->data['views'] ?? 0;
            $periodStats['submissions'][$date] = $form->submissions()->whereDate('created_at', $dateObj)->where('status', FormSubmission::STATUS_COMPLETED)->count();
            $periodStats['partial_submissions'][$date] = $form->submissions()->whereDate('created_at', $dateObj)->where('status', FormSubmission::STATUS_PARTIAL)->count();

            if ($dateObj->toDateString() === now()->toDateString()) {
                $periodStats['views'][$date] += $form->views()->count();
            }
        }

        return $periodStats;
    }

    public function getFormStatsDetails(Request $request, Workspace $workspace, Form $form)
    {
        $this->authorize('view', $form);

        $totalViews = $form->views_count;
        $totalSubmissions = $form->submissions_count;

        $averageDuration = Cache::remember('form_stats_average_duration_' . $form->id, 1800, function () use ($form) {
            $submissionsWithDuration = $form->submissions()->whereNotNull('completion_time')->count() ?? 0;
            $totalDuration = $form->submissions()->whereNotNull('completion_time')->sum('completion_time') ?? 0;
            return $submissionsWithDuration > 0 ? round($totalDuration / $submissionsWithDuration) : null;
        });

        // Aggregate metadata from form views using single database query
        $metaStats = Cache::remember('form_stats_metadata_' . $form->id, 3600, function () use ($form) {
            $metadataFields = [
                'source',
                'device',
                'country',
                'browser',
                'os'
            ];

            // Determine JSON extraction syntax based on database type
            $isMySQL = config('database.default') === 'mysql';
            $jsonExtractPattern = $isMySQL
                // Normalize empty string and literal 'null' to 'Unknown' in MySQL
                ? "COALESCE(NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.%s')), ''), 'null'), 'Unknown')"
                : "COALESCE(meta->>'%s', 'Unknown')";

            // Build query with safe field binding
            $query = DB::table('form_views')->where('form_id', $form->id);

            $selectFields = [];
            $groupByFields = [];

            foreach ($metadataFields as $field) {
                $extractExpression = sprintf($jsonExtractPattern, $field);
                $selectFields[] = DB::raw("$extractExpression as $field");
                $groupByFields[] = DB::raw($extractExpression);
            }

            $results = $query
                ->select(array_merge($selectFields, [DB::raw('COUNT(*) as count')]))
                ->groupBy($groupByFields)
                ->orderBy('count', 'desc')
                ->get();

            // Initialize stats arrays
            $stats = [];
            foreach ($metadataFields as $field) {
                $stats[$field] = [];
            }

            // Aggregate results into stats arrays
            foreach ($results as $row) {
                foreach ($metadataFields as $field) {
                    $value = $row->$field;
                    $stats[$field][$value] = ($stats[$field][$value] ?? 0) + $row->count;
                }
            }

            // Sort each field by count (descending)
            foreach ($metadataFields as $field) {
                arsort($stats[$field]);
            }

            return $stats;
        });

        return [
            'views' => $totalViews,
            'submissions' => $totalSubmissions,
            'completion_rate' => $totalViews > 0 ? round(($totalSubmissions / $totalViews) * 100, 2) : 0,
            'average_duration' => $averageDuration ? $this->formatDuration($averageDuration) : null,
            'meta_stats' => $metaStats
        ];
    }

    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours . 'h';
        }

        if ($minutes > 0) {
            $parts[] = $minutes . 'm';
        }

        if ($remainingSeconds > 0 || empty($parts)) {
            $parts[] = $remainingSeconds . 's';
        }

        return implode(' ', $parts);
    }
}
