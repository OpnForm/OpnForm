<?php

namespace App\Http\Controllers\Forms;

use App\Http\Requests\Templates\FormTemplateRequest;
use App\Http\Resources\FormTemplateResource;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;

class TemplateController extends Controller
{
    private function getProdTemplates($slug = false)
    {
        if (!config('app.self_hosted') || !config('opnform.show_official_templates')) {
            return [];
        }

        $prodTemplates = \Cache::remember('prod_templates', 3600, function () {
            $response = Http::get('https://api.opnform.com/templates');
            if ($response->successful()) {
                return collect($response->json())->map(function ($item) {
                    $item['from_prod'] = true;
                    return $item;
                })->toArray();
            }
        });
        if ($slug) {
            return collect($prodTemplates)->filter(function ($item) use ($slug) {
                return $item['slug'] === $slug;
            })->toArray();
        }
        return $prodTemplates;
    }

    public function index(Request $request)
    {
        $limit = (int) $request->get('limit', 0);
        $onlyMy = (bool) $request->get('onlymy', false);

        $query = Template::query();

        if (Auth::check()) {
            if ($onlyMy) {
                $query->where('creator_id', Auth::id());
            } else {
                $query->where(function ($q) {
                    $q->where('publicly_listed', true)
                        ->orWhere('creator_id', Auth::id());
                });
            }
        } else {
            $query->where('publicly_listed', true);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $templates = $query->orderByDesc('created_at')->get();

        return response()->json(array_merge(
            FormTemplateResource::collection($templates)->toArray($request),
            $this->getProdTemplates()
        ));
    }

    public function create(FormTemplateRequest $request)
    {
        $this->authorize('create', Template::class);

        // Create template
        $template = $request->getTemplate();
        $template->save();

        return $this->success([
            'message' => 'Template was created.',
            'template_id' => $template->id,
            'data' => new FormTemplateResource($template),
        ]);
    }

    public function update(FormTemplateRequest $request, string $id)
    {
        $template = Template::findOrFail($id);
        $this->authorize('update', $template);

        $template->update($request->all());

        return $this->success([
            'message' => 'Template was updated.',
            'template_id' => $template->id,
            'data' => new FormTemplateResource($template),
        ]);
    }

    public function destroy($id)
    {
        $template = Template::findOrFail($id);
        $this->authorize('delete', $template);

        $template->delete();

        return $this->success([
            'message' => 'Template was deleted.',
        ]);
    }

    public function show(string $slug)
    {
        $template = Template::whereSlug($slug)->first();
        if ($template) {
            return new FormTemplateResource($template);
        }

        // Get prod template by slug and return first match (or null if not found)
        $prodTemplates = $this->getProdTemplates($slug);
        // Use array_values to reindex since filter() preserves original keys
        return !empty($prodTemplates) ? array_values($prodTemplates)[0] : null;
    }
}
