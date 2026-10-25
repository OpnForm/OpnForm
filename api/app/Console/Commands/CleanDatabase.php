<?php

namespace App\Console\Commands;

use App\Models\Forms\FormStatistic;
use App\Models\Forms\FormView;
use App\Service\Forms\Analytics\FormViewStatistics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CleanDatabase extends Command
{
    private const VIEW_BATCH_SIZE = 1000;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'forms:database-cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Database Cleanup';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->cleanFormStatistics();

        $this->line('Database Cleanup Success.');

        return Command::SUCCESS;
    }

    /**
     * Manage FormViews & FormSubmissions records
     */
    private function cleanFormStatistics()
    {
        $this->line('Aggregating form views...');
        $statistics = app(FormViewStatistics::class);
        $now = now();
        $maxId = FormView::max('id') ?? 0;

        do {
            $batch = DB::transaction(function () use ($statistics, $now, $maxId) {
                $ids = DB::table('form_views')->where('created_at', '<', $now)->where('id', '<=', $maxId)
                    ->orderBy('id')->limit(self::VIEW_BATCH_SIZE)->lockForUpdate()->pluck('id');
                if ($ids->isEmpty()) {
                    return null;
                }

                $views = DB::table('form_views')->whereIn('id', $ids);
                $finalData = [];

                $statistics->groupedViews((clone $views)->select('form_id')->selectRaw('DATE(created_at) as date'))
                    ->groupBy('form_id')->groupByRaw('DATE(created_at)')->orderBy('form_id')->orderBy('date')
                    ->get()->each(function ($row) use (&$finalData, $statistics) {
                        $key = $row->form_id . '-' . $row->date;
                        $finalData[$key] ??= [
                            'form_id' => $row->form_id,
                            'date' => $row->date,
                            'data' => ['views' => 0, 'submissions' => 0, 'meta_stats' => $statistics->emptyMetadata()],
                        ];
                        $finalData[$key]['data']['views'] += (int) $row->count;
                        $finalData[$key]['data']['meta_stats'] = $statistics->mergeMetadata(
                            $finalData[$key]['data']['meta_stats'],
                            $statistics->rowMetadata($row)
                        );
                    });

                // Serialize daily-row creation even when concurrent batches have disjoint views.
                $formIds = array_unique(array_column($finalData, 'form_id'));
                DB::table('forms')->whereIn('id', $formIds)->orderBy('id')->lockForUpdate()->get(['id']);

                foreach ($finalData as $row) {
                    $found = FormStatistic::where('form_id', $row['form_id'])->where('date', $row['date'])->lockForUpdate()->first();
                    if ($found !== null) {
                        $data = $found->data;
                        $data['views'] = ($data['views'] ?? 0) + $row['data']['views'];
                        $data['meta_stats'] = $statistics->mergeMetadata($data['meta_stats'] ?? [], $row['data']['meta_stats']);
                        $found->update(['data' => $data]);
                    } else {
                        FormStatistic::create($row);
                    }
                }

                // Delete only the exact locked rows whose counts have been stored.
                return ['removed' => $views->delete(), 'form_ids' => $formIds];
            }, 3);

            if ($batch !== null) {
                foreach ($batch['form_ids'] as $formId) {
                    Cache::forget('form_stats_metadata_v2_' . $formId);
                }
                $this->line($batch['removed'] . ' form views records aggregated and deleted.');
            }
        } while ($batch !== null);
    }
}
