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

    protected function getModelClass(string $alias): \Illuminate\Http\JsonResponse|string
    {
        if (!isset($this->modelAliases[$alias])) {
            return $this->error([
                'success' => false,
                'message' => 'Invalid model alias',
            ]);
        }
        return $this->modelAliases[$alias];
    }

    public function index(Request $request, string $modelType, int $id)
    {
        $modelClass = $this->getModelClass($modelType);
        if ($modelClass instanceof \Illuminate\Http\JsonResponse) {
            return $modelClass;
        }

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

        // Check the CURRENT authenticated user's pro status, not the version creator's
        if (!$request->user()->is_pro) {
            return $this->error([
                'message' => 'You need to be a Pro user to restore this version',
            ]);
        }

        $model = $version->getModel();

        // Verify version belongs to the model (prevents cross-model version access)
        if ($version->versionable_id != $model->id || $version->versionable_type !== get_class($model)) {
            abort(404, 'Version not found');
        }

        $this->authorize('update', $model);

        $version->revert();

        return $this->success([
            'message' => 'Version restored successfully.',
        ]);
    }
}
