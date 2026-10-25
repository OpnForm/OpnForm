<?php

namespace App\Service\Forms;

use App\Models\Forms\Form;
use App\Models\Forms\FormSubmission;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class McpFormManagementService
{
    public function __construct(
        private readonly AgentFormDefinition $definitions,
        private readonly AgentFormQualityAnalyzer $qualityAnalyzer,
        private readonly FormCreationService $formCreation,
        private readonly FormUpdateService $formUpdate,
    ) {
    }

    public function workspace(User $user, int $workspaceId): Workspace
    {
        $workspace = $user->workspaces()
            ->withPivot('role')
            ->where('workspaces.id', $workspaceId)
            ->first();

        if (! $workspace) {
            throw ValidationException::withMessages([
                'workspace_id' => ['Workspace not found or not accessible.'],
            ]);
        }

        return $workspace;
    }

    /** @return array<int, array<string, mixed>> */
    public function listWorkspaces(User $user): array
    {
        return $user->workspaces()
            ->withPivot('role')
            ->orderBy('workspaces.name')
            ->get()
            ->map(fn (Workspace $workspace) => $this->serializeWorkspace($workspace))
            ->all();
    }

    /** @return array<string, mixed> */
    public function serializeWorkspace(Workspace $workspace): array
    {
        $role = $workspace->pivot?->role;

        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'icon' => $workspace->icon,
            'role' => $role,
            'can_write_forms' => $role !== User::ROLE_READONLY,
            'plan_tier' => $workspace->plan_tier,
        ];
    }

    /** @return array{forms: array<int, array<string, mixed>>, pagination: array<string, int>} */
    public function listForms(
        User $user,
        ?int $workspaceId,
        ?string $search,
        ?string $visibility,
        int $page,
        int $perPage,
    ): array {
        $workspaceIds = $user->workspaces()->pluck('workspaces.id');

        if ($workspaceId !== null && ! $workspaceIds->contains($workspaceId)) {
            throw ValidationException::withMessages([
                'workspace_id' => ['Workspace not found or not accessible.'],
            ]);
        }

        $query = Form::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->with('workspace')
            ->withCount(['submissions as submissions_count' => fn (Builder $query) => $query->where('status', FormSubmission::STATUS_COMPLETED)])
            ->withTotalViews()
            ->when($workspaceId !== null, fn (Builder $query) => $query->where('workspace_id', $workspaceId))
            ->when($search, fn (Builder $query) => $query->whereRaw('LOWER(title) LIKE ?', ['%'.Str::lower($search).'%']))
            ->when($visibility, fn (Builder $query) => $query->where('visibility', $visibility))
            ->orderByDesc('updated_at');

        $forms = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'forms' => collect($forms->items())->map(fn (Form $form) => $this->serializeFormSummary($form))->all(),
            'pagination' => [
                'page' => $forms->currentPage(),
                'per_page' => $forms->perPage(),
                'total' => $forms->total(),
                'last_page' => $forms->lastPage(),
            ],
        ];
    }

    public function form(User $user, int $formId, bool $lockForUpdate = false): Form
    {
        $form = Form::query()
            ->with('workspace')
            ->when($lockForUpdate, fn (Builder $query) => $query->lockForUpdate())
            ->find($formId);

        if (! $form || ! $user->ownsForm($form)) {
            throw ValidationException::withMessages([
                'form_id' => ['Form not found or not accessible.'],
            ]);
        }

        return $form;
    }

    /** @return array<string, mixed> */
    public function create(User $user, ?int $workspaceId, array $definition): array
    {
        $workspace = $this->resolveCreationWorkspace($user, $workspaceId);
        $this->assertWritable($workspace, $user);

        $definition = $this->definitions->normalizeAndValidate($definition, $workspace);
        $this->qualityAnalyzer->assertReadyForAgentPersistence($definition);
        $definition['visibility'] = 'draft';
        $created = $this->formCreation->create($definition, $user, $workspace);

        return [
            'message' => 'Form created as an unpublished draft.',
            'form' => $this->serializeForm($created['form']->load('workspace')),
            'disabled_features' => $created['cleanings'],
            'next_step' => 'Tell the user the form is saved as an unpublished draft, then ask whether they want to publish it. Call publish_form only after explicit confirmation.',
        ];
    }

    /** @return array<string, mixed> */
    public function update(User $user, int $formId, string $expectedRevision, array $definition): array
    {
        return DB::transaction(function () use ($user, $formId, $expectedRevision, $definition) {
            $form = $this->form($user, $formId, lockForUpdate: true);
            $this->assertWritable($form->workspace, $user);
            $this->assertFresh($form, $expectedRevision);

            $definition = $this->definitions->normalizeAndValidate($definition, $form->workspace);
            $definition['visibility'] = $form->visibility;

            $updated = $this->formUpdate->update($form, $definition);

            return [
                'message' => 'Form updated.',
                'form' => $this->serializeForm($updated['form']->refresh()->load('workspace')),
                'disabled_features' => $updated['cleanings'],
            ];
        });
    }

    /** @return array<string, mixed> */
    public function publish(User $user, int $formId, string $expectedRevision, bool $confirmed): array
    {
        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm_publish' => ['Ask the user for explicit confirmation before publishing.'],
            ]);
        }

        return DB::transaction(function () use ($user, $formId, $expectedRevision) {
            $form = $this->form($user, $formId, lockForUpdate: true);
            $this->assertWritable($form->workspace, $user);
            $this->assertFresh($form, $expectedRevision);
            $form->update(['visibility' => 'public']);

            return [
                'message' => 'Form published.',
                'form' => $this->serializeForm($form->refresh()->load('workspace')),
            ];
        });
    }

    /** @return array<string, mixed> */
    public function trash(User $user, int $formId, string $expectedRevision, bool $confirmed): array
    {
        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm_trash' => ['Ask the user for explicit confirmation before moving the form to trash.'],
            ]);
        }

        return DB::transaction(function () use ($user, $formId, $expectedRevision) {
            $form = $this->form($user, $formId, lockForUpdate: true);
            $this->assertWritable($form->workspace, $user);
            $this->assertFresh($form, $expectedRevision);
            $summary = $this->serializeFormSummary($form);
            $form->delete();

            return [
                'message' => 'Form moved to trash. This MCP integration does not expose restore or permanent deletion.',
                'form' => $summary,
            ];
        });
    }

    /** @return array<string, mixed> */
    public function serializeForm(Form $form): array
    {
        return array_merge($this->serializeFormSummary($form), [
            'definition' => $this->definitions->fromForm($form),
            'revision' => $this->revision($form),
            'edit_url' => $form->edit_url,
        ]);
    }

    /** @return array<string, mixed> */
    private function serializeFormSummary(Form $form): array
    {
        return [
            'id' => $form->id,
            'workspace_id' => $form->workspace_id,
            'title' => $form->title,
            'visibility' => $form->visibility,
            'share_url' => $form->share_url,
            'submissions_count' => $form->submissions_count,
            'views_count' => $form->views_count,
            'created_at' => $form->created_at?->toISOString(),
            'updated_at' => $form->updated_at?->toISOString(),
        ];
    }

    private function resolveCreationWorkspace(User $user, ?int $workspaceId): Workspace
    {
        if ($workspaceId !== null) {
            return $this->workspace($user, $workspaceId);
        }

        $workspaces = $user->workspaces()->withPivot('role')->limit(2)->get();

        if ($workspaces->count() === 1) {
            return $workspaces->first();
        }

        throw ValidationException::withMessages([
            'workspace_id' => [$workspaces->isEmpty()
                ? 'No workspace is available for this account.'
                : 'This account has multiple workspaces. Call list_workspaces and provide workspace_id.'],
        ]);
    }

    private function assertWritable(Workspace $workspace, User $user): void
    {
        if ($workspace->isReadonlyUser($user)) {
            throw ValidationException::withMessages([
                'workspace_id' => ['This workspace membership is read-only.'],
            ]);
        }
    }

    private function assertFresh(Form $form, string $expectedRevision): void
    {
        if (! hash_equals($this->revision($form), $expectedRevision)) {
            throw ValidationException::withMessages([
                'expected_revision' => ['The form changed since it was fetched. Call get_form and retry with its revision value.'],
            ]);
        }
    }

    private function revision(Form $form): string
    {
        return hash('sha256', json_encode([
            'id' => $form->id,
            'updated_at' => $form->updated_at?->toISOString(),
            'definition' => $this->definitions->fromForm($form),
        ], JSON_THROW_ON_ERROR));
    }
}
