<?php

namespace App\Console\Commands;

use App\Models\Forms\FormStatistic;
use App\Models\Forms\FormView;
use App\Service\Forms\Analytics\FormViewStatistics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDatabase extends Command
{
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
        $formIds = DB::transaction(function () use ($statistics) {
            $now = now();
            $maxId = FormView::max('id') ?? 0;
            $views = DB::table('form_views')->where('created_at', '<', $now)->where('id', '<=', $maxId);
            $finalData = [];

            $statistics->groupedViews((clone $views)->select('form_id')->selectRaw('DATE(created_at) as date'))
                ->groupBy('form_id')->groupByRaw('DATE(created_at)')->orderBy('date')
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

            // Delete only rows included in this aggregation, after every write succeeds.
            $removed = $views->delete();
            $this->line($removed . ' form views records aggregated and deleted.');

            return array_unique(array_column($finalData, 'form_id'));
        });

        foreach ($formIds as $formId) {
            Cache::forget('form_stats_metadata_v2_' . $formId);
        }
    }
}
