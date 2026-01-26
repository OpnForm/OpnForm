<?php

namespace App\Enterprise\Oidc\Requests;

use App\Enterprise\Oidc\Models\IdentityConnection;
use App\Enterprise\Oidc\Rules\ValidOidcDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOidcConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled at controller level
        return true;
    }

    public function rules(): array
    {
        $connection = $this->route('connection');
        $connectionId = $connection instanceof IdentityConnection ? $connection->id : null;
        $workspaceId = $connection instanceof IdentityConnection ? $connection->workspace_id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('identity_connections', 'slug')->ignore($connectionId),
            ],
            'domain' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                new ValidOidcDomain(),
                Rule::unique('identity_connections', 'domain')->ignore($connectionId)->where(function ($query) use ($workspaceId) {
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
            'issuer' => ['sometimes', 'required', 'url'],
            'client_id' => ['sometimes', 'required', 'string'],
            'client_secret' => ['sometimes', 'required', 'string'],
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
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
