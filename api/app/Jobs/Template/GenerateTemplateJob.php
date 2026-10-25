<?php

namespace App\Jobs\Template;

use App\Models\Template;
use App\Service\AI\Prompts\Form\GenerateFormPrompt;
use App\Service\AI\Prompts\Template\GenerateTemplateMetadataPrompt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GenerateTemplateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;
    public bool $failOnTimeout = true;
    public ?Template $generatedTemplate = null;
    public const MAX_RELATED_TEMPLATES = 8;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $prompt, public ?string $adminReceiptId = null)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $receipt = null;
        if ($this->adminReceiptId) {
            $receipt = \App\Models\AdminApiAction::findOrFail($this->adminReceiptId);
            if ($receipt->status !== 'running') {
                return;
            }
            $token = \Laravel\Sanctum\PersonalAccessToken::find($receipt->token_id);
            $actor = $token?->tokenable;
            if (config('app.self_hosted') || !$actor?->moderator || $actor->is_blocked || !$token->expires_at?->isFuture()
                || !in_array('admin:templates:create', $token->abilities, true)
                || (config('sanctum.expiration') && $token->created_at->addMinutes(config('sanctum.expiration'))->isPast())) {
                $receipt->update(['status' => 'uncertain']);
                return;
            }
            if (!\App\Models\AdminApiAction::whereKey($receipt->id)->where('status', 'running')->update(['status' => 'processing'])) {
                return;
            }
        }
        // Get form structure using the form prompt class
        $formData = GenerateFormPrompt::run($this->prompt);

        // Generate all template metadata using the consolidated prompt
        $metadata = GenerateTemplateMetadataPrompt::run($this->prompt);

        // Extract metadata components
        $formShortDescription = $metadata['short_description'];
        $formDescription = $metadata['detailed_description'];
        $formTitle = $metadata['title'];
        $industry = $metadata['industries'];
        $types = $metadata['types'];
        $formQAs = $metadata['qa_content'];

        // Get Related Templates
        $relatedTemplates = $this->getRelatedTemplates($industry, $types);

        // Get image cover URL
        $imageUrl = $this->getImageCoverUrl($metadata['image_search_query']);

        $template = $this->createFormTemplate(
            $formData,
            $formTitle,
            $formDescription,
            $formShortDescription,
            $formQAs,
            $imageUrl,
            $industry,
            $types,
            $relatedTemplates
        );
        $this->generatedTemplate = $template;

        // Set reverse related Templates
        $this->setReverseRelatedTemplates($template);
        $receipt?->update(['status' => 'completed', 'result' => ['template_slug' => $template->slug]]);
    }

    public function failed(?\Throwable $exception): void
    {
        if ($this->adminReceiptId) {
            \App\Models\AdminApiAction::whereKey($this->adminReceiptId)->where('status', '!=', 'completed')->update(['status' => 'uncertain']);
        }
    }

    /**
     * Get an image cover URL for the template using unsplash API
     */
    private function getImageCoverUrl($searchQuery): ?string
    {
        $searchQuery = trim((string) $searchQuery);
        if ($searchQuery === '') {
            return null;
        }

        $accessKey = config('services.unsplash.access_key');
        if (!$accessKey) {
            return null;
        }

        $url = 'https://api.unsplash.com/search/photos?query=' . urlencode($searchQuery) . '&client_id=' . $accessKey;
        $response = Http::get($url)->json();

        if (!is_array($response)) {
            return null;
        }

        $results = $response['results'] ?? null;
        if (!is_array($results) || count($results) === 0) {
            return null;
        }

        $photoIndex = random_int(0, count($results) - 1);
        $photoUrl = $results[$photoIndex]['urls']['regular'] ?? null;

        if (!$photoUrl) {
            return null;
        }

        return Str::of($photoUrl)->replace('w=1080', 'w=600')->toString();
    }

    private function getRelatedTemplates(array $industries, array $types): array
    {
        $templateScore = [];
        Template::chunk(100, function ($otherTemplates) use ($industries, $types, &$templateScore) {
            foreach ($otherTemplates as $otherTemplate) {
                $industryOverlap = count(array_intersect($industries ?? [], $otherTemplate->industry ?? []));
                $typeOverlap = count(array_intersect($types ?? [], $otherTemplate->types ?? []));
                $score = $industryOverlap + $typeOverlap;
                if ($score > 1) {
                    $templateScore[$otherTemplate->slug] = $score;
                }
            }
        });
        arsort($templateScore); // Sort by Score

        return array_slice(array_keys($templateScore), 0, self::MAX_RELATED_TEMPLATES);
    }

    private function createFormTemplate(
        array $formData,
        string $formTitle,
        string $formDescription,
        string $formShortDescription,
        array $formQAs,
        ?string $imageUrl,
        array $industry,
        array $types,
        array $relatedTemplates
    ) {
        return Template::create([
            'name' => $formTitle,
            'description' => $formDescription,
            'short_description' => $formShortDescription,
            'questions' => $formQAs,
            'structure' => $formData,
            'image_url' => $imageUrl,
            'publicly_listed' => true,
            'industries' => $industry,
            'types' => $types,
            'related_templates' => $relatedTemplates,
        ]);
    }

    private function setReverseRelatedTemplates(Template $newTemplate)
    {
        if (!$newTemplate || count($newTemplate->related_templates) === 0) {
            return;
        }

        $templates = Template::whereIn('slug', $newTemplate->related_templates)->get();
        foreach ($templates as $template) {
            if (count($template->related_templates) < self::MAX_RELATED_TEMPLATES) {
                $template->update(['related_templates' => array_merge($template->related_templates, [$newTemplate->slug])]);
            }
        }
    }
}
