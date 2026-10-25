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
use App\Service\Forms\Analytics\FormViewStatistics;

class FormStatsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function getFormStats(FormStatsRequest $request, Workspace $workspace, Form $form)
    {
        $this->ensureFormBelongsToWorkspace($form, $workspace);
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
        $this->ensureFormBelongsToWorkspace($form, $workspace);
        $this->authorize('view', $form);

        $totalViews = $form->views_count;
        $totalSubmissions = $form->submissions_count;

        $averageDuration = Cache::remember('form_stats_average_duration_' . $form->id, 1800, function () use ($form) {
            $submissionsWithDuration = $form->submissions()->whereNotNull('completion_time')->count() ?? 0;
            $totalDuration = $form->submissions()->whereNotNull('completion_time')->sum('completion_time') ?? 0;
            return $submissionsWithDuration > 0 ? round($totalDuration / $submissionsWithDuration) : null;
        });

        $metaStats = Cache::remember('form_stats_metadata_v2_' . $form->id, 300, function () use ($form) {
            return app(FormViewStatistics::class)->metadata($form);
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

    private function ensureFormBelongsToWorkspace(Form $form, Workspace $workspace): void
    {
        if ($form->workspace_id !== $workspace->id) {
            abort(404);
        }
    }
}
