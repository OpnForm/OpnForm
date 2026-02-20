<?php

namespace App\Http\Controllers;

use App\Http\Resources\VersionResource;
use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\Version;
use Illuminate\Http\Request;

class VersionController extends Controller
{
    protected $modelAliases = [
        'form' => Form::class,
        'submission' => FormSubmission::class,
    ];

    protected function getModelClass(string $alias): string
    {
        if (!isset($this->modelAliases[$alias])) {
            abort(400, 'Invalid model alias');
        }
        return $this->modelAliases[$alias];
    }

    public function index(Request $request, string $modelType, int $id)
    {
        $modelClass = $this->getModelClass($modelType);

        $model = $modelClass::findOrFail($id);

        $this->authorize('view', $model);

        abort_unless(method_exists($model, 'versions'), 400, 'Model is not versionable');

        // Limit versions to prevent N+1 issues when calling diff() in the filter loop
        $versions = $model->versions()
            ->with('user')
            ->latest('created_at')
            ->take(50)
            ->get()
            ->filter(function ($version) {
                $diff = $version->diff();
                return !empty($diff) && count($diff) > 0;
            })
            ->values();

        return VersionResource::collection($versions);
    }

    public function restore(Request $request, int $versionId)
    {
        $version = Version::findOrFail($versionId);

        // Check the current authenticated user's business status
        if (!$request->user()->is_business) {
            return $this->error([
                'message' => 'You need to be a Business user to restore this version',
            ]);
        }

        // Get the actual model from the database to verify ownership (prevents IDOR)
        $modelClass = $version->versionable_type;
        if (!class_exists($modelClass)) {
            abort(404, 'Version not found');
        }
        $model = $modelClass::findOrFail($version->versionable_id);

        $this->authorize('update', $model);

        $version->revert();

        return $this->success([
            'message' => 'Version restored successfully.',
        ]);
    }
}
