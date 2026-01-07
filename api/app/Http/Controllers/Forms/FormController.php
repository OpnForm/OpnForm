<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormRequest;
use App\Http\Requests\UpdateFormRequest;
use App\Http\Requests\UploadAssetRequest;
use App\Http\Resources\FormListResource;
use App\Http\Resources\FormResource;
use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\Workspace;
use App\Notifications\Forms\MobileEditorEmail;
use App\Service\Forms\FormCleaner;
use App\Service\Storage\StorageFileNameParser;
use App\Service\Storage\FileUploadPathService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FormController extends Controller
{
    public const ASSETS_UPLOAD_PATH = 'assets/forms';

    private FormCleaner $formCleaner;

    public function __construct()
    {
        $this->middleware('auth', ['except' => ['uploadAsset']]);
        $this->formCleaner = new FormCleaner();
    }

    public function index(Workspace $workspace)
    {
        $this->authorize('ownsWorkspace', $workspace);
        $this->authorize('viewAny', Form::class);

        // Select only columns needed for FormListResource (excludes heavy 'properties' and 'removed_properties')
        $forms = $workspace->forms()
            ->select([
                'id',
                'slug',
                'title',
                'visibility',
                'tags',
                'closes_at',
                'max_submissions_count',
                'workspace_id',
                'custom_domain',
                'created_at',
                'updated_at',
            ])
            ->with(['workspace'])
            ->withCount(['submissions as submissions_count' => fn ($q) => $q->where('status', FormSubmission::STATUS_COMPLETED)])
            ->withTotalViews()
            ->orderByDesc('updated_at')
            ->paginate(10);

        return FormListResource::collection($forms);
    }

    public function show(Form $form)
    {
        $this->authorize('view', $form);

        return (new FormResource($form))->setCleanings(
            $this->formCleaner->processForm(request(), $form)->simulateCleaning($form->workspace)->getPerformedCleanings()
        );
    }

    /**
     * Return all user forms, used for zapier
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function indexAll()
    {
        $forms = collect();
        foreach (Auth::user()->workspaces as $workspace) {
            $this->authorize('ownsWorkspace', $workspace);
            $this->authorize('viewAny', Form::class);

            $workspaceIsPro = $workspace->is_pro;
            $newForms = $workspace->forms()->get()->map(function (Form $form) use ($workspace, $workspaceIsPro) {
                // Add attributes for faster loading
                $form->extra = (object) [
                    'loadedWorkspace' => $workspace,
                    'workspaceIsPro' => $workspaceIsPro,
                    'userIsOwner' => true,
                ];

                return $form;
            });

            $forms = $forms->merge($newForms);
        }

        return FormResource::collection($forms);
    }

    public function store(StoreFormRequest $request)
    {
        $workspace = Workspace::findOrFail($request->get('workspace_id'));
        $this->authorize('ownsWorkspace', $workspace);
        $this->authorize('create', [Form::class, $workspace]);

        $formData = $this->formCleaner
            ->processRequest($request)
            ->simulateCleaning($workspace)
            ->getData();

        $form = Form::create(array_merge($formData, [
            'creator_id' => $request->user()->id,
        ]));

        if (config('app.self_hosted') && !empty($formData['slug'])) {
            $form->slug = $formData['slug'];
            $form->save();
        }

        if ($this->formCleaner->hasCleaned()) {
            $formStatus = $form->workspace->is_trialing ? 'Non-trial' : 'Pro';
            $message =  'Form successfully created, but the ' . $formStatus . ' features you used will be disabled when sharing your form:';
        } else {
            $message =  'Form created.';
        }

        return $this->success([
            'message' => $message . ($form->visibility == 'draft' ? ' But other people won\'t be able to see the form since it\'s currently in draft mode' : ''),
            'form' => (new FormResource($form))->setCleanings($this->formCleaner->getPerformedCleanings()),
            'users_first_form' => $request->user()->forms()->count() == 1,
        ]);
    }

    public function update(UpdateFormRequest $request, Form $form)
    {
        $this->authorize('update', $form);

        $formData = $this->formCleaner
            ->processRequest($request)
            ->simulateCleaning($form->workspace)
            ->getData();

        // Set Removed Properties (pre-compute lookup set to avoid O(n²) complexity)
        $newPropertyIds = collect($formData['properties'])->pluck('id')->flip()->all();
        $formData['removed_properties'] = array_merge(
            $form->removed_properties,
            collect($form->properties)->filter(function ($field) use ($newPropertyIds) {
                return !Str::of($field['type'])->startsWith('nf-') && !isset($newPropertyIds[$field['id']]);
            })->toArray()
        );

        $form->slug = (config('app.self_hosted') && !empty($formData['slug'])) ? $formData['slug'] : $form->slug;

        $form->update($formData);

        if ($this->formCleaner->hasCleaned()) {
            $formSubscription = $form->is_pro ? 'Enterprise' : 'Pro';
            $formStatus = $form->workspace->is_trialing ? 'Non-trial' : $formSubscription;
            $message = 'Form successfully updated, but the ' . $formStatus . ' features you used will be disabled when sharing your form.';
        } else {
            $message = 'Form updated.';
        }

        return $this->success([
            'message' => $message . ($form->visibility == 'draft' ? ' But other people won\'t be able to see the form since it\'s currently in draft mode' : ''),
            'form' => (new FormResource($form))->setCleanings($this->formCleaner->getPerformedCleanings()),
        ]);
    }

    public function destroy(Form $form)
    {
        $this->authorize('delete', $form);

        $form->delete();

        return $this->success([
            'message' => 'Form was deleted.',
        ]);
    }

    public function duplicate(Form $form)
    {
        $this->authorize('update', $form);

        // Create copy
        $formCopy = $form->replicate();
        // generate new slug before changing title
        if (Str::isUuid($formCopy->slug)) {
            $formCopy->slug = Str::uuid();
        } else { // it will generate a new slug
            $formCopy->slug = null;
            $formCopy->save();
        }
        $formCopy->title = 'Copy of ' . $formCopy->title;
        $formCopy->removed_properties = [];
        $formCopy->save();

        return $this->success([
            'message' => 'Form successfully duplicated. You are now editing the duplicated version of the form.',
            'new_form' => new FormResource($formCopy),
        ]);
    }

    public function regenerateLink(Form $form, $option)
    {
        $this->authorize('update', $form);

        if ($option == 'slug') {
            $form->generateSlug();
        } elseif ($option == 'uuid') {
            $form->slug = Str::uuid();
        }
        $form->save();

        return $this->success([
            'message' => 'Form url successfully updated. Your new form url now is: ' . $form->share_url . '.',
            'form' => new FormResource($form),
        ]);
    }

    /**
     * Upload a form asset
     */
    public function uploadAsset(UploadAssetRequest $request)
    {
        $fileNameParser = StorageFileNameParser::parse($request->url);

        // Make sure we retrieve the file in tmp storage, move it to persistent
        $fileName = FileUploadPathService::getTmpFileUploadPath($fileNameParser->uuid);
        if (!Storage::exists($fileName)) {
            // File not found, we skip
            return null;
        }
        $newPath = self::ASSETS_UPLOAD_PATH . '/' . $fileNameParser->getMovedFileName();
        Storage::move($fileName, $newPath);

        return $this->success([
            'message' => 'File uploaded.',
            'url' => route('forms.assets.show', [$fileNameParser->getMovedFileName()]),
        ]);
    }

    /**
     * File uploads retrieval
     */
    public function viewFile(Form $form, $fileName)
    {
        $this->authorize('view', $form);

        $path = FileUploadPathService::getFileUploadPath($form->id, $fileName);
        if (!Storage::exists($path)) {
            return $this->error([
                'message' => 'File not found.',
            ]);
        }

        return redirect()->to(Storage::temporaryUrl($path, now()->addMinutes(5)));
    }

    /**
     * Updates a form's workspace
     */
    public function updateWorkspace(Form $form, Workspace $workspace)
    {
        $this->authorize('update', $form);
        $this->authorize('ownsWorkspace', $workspace);

        $form->workspace_id = $workspace->id;
        $form->creator_id = auth()->user()->id;
        $form->save();

        return $this->success([
            'message' => 'Form workspace updated successfully.',
        ]);
    }

    public function mobileEditorEmail(Form $form)
    {
        $this->authorize('update', $form);

        $form->creator->notify(new MobileEditorEmail($form->slug));

        return $this->success([
            'message' => 'Email sent.',
        ]);
    }
}
