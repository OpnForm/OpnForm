<?php

namespace App\Enterprise\Oidc\Requests;

use App\Enterprise\Oidc\Rules\ValidOidcDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOidcConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled at controller level
        return true;
    }

    public function rules(): array
    {
        $workspaceId = $this->route('workspace');
        $workspaceId = $workspaceId instanceof \App\Models\Workspace ? $workspaceId->id : $workspaceId;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('identity_connections', 'slug'),
            ],
            'domain' => [
                'required',
                'string',
                'max:255',
                new ValidOidcDomain(),
                Rule::unique('identity_connections', 'domain')->where(function ($query) use ($workspaceId) {
                    // Domain must be unique per workspace (or globally if workspace_id is null)
                    if ($workspaceId) {
                        return $query->where(function ($q) use ($workspaceId) {
                            $q->where('workspace_id', $workspaceId)
                                ->orWhereNull('workspace_id');
                        });
                    }
                    return $query->whereNull('workspace_id');
                }),
            ],
            'issuer' => ['required', 'url'],
            'client_id' => ['required', 'string'],
            'client_secret' => ['required', 'string'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string'],
            'options' => ['nullable', 'array'],
            'options.require_state' => ['nullable', 'boolean'],
            'options.field_mappings' => ['nullable', 'array'],
            'options.field_mappings.email' => ['nullable', 'string', 'max:255'],
            'options.field_mappings.name' => ['nullable', 'string', 'max:255'],
            'options.group_role_mappings' => ['nullable', 'array'],
            'options.group_role_mappings.*.idp_group' => ['required_with:options.group_role_mappings', 'string', 'max:255'],
            'options.group_role_mappings.*.role' => ['required_with:options.group_role_mappings', 'string', Rule::in(['owner', 'admin', 'editor', 'member'])],
            'redirect_path' => ['nullable', 'string', 'max:255'],
            'enabled' => ['boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        // Set default scopes if not provided
        if (!$this->has('scopes')) {
            $this->merge([
                'scopes' => config('oidc.default_scopes'),
            ]);
        }

        // Set default enabled to true
        if (!$this->has('enabled')) {
            $this->merge(['enabled' => true]);
        }

        if (!$this->has('options.require_state')) {
            $options = $this->input('options', []);
            if (!is_array($options)) {
                $options = [];
            }
            $options['require_state'] = true;
            $this->merge(['options' => $options]);
        }
    }
}
