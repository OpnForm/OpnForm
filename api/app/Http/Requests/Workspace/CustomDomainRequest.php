<?php

namespace App\Http\Requests\Workspace;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class CustomDomainRequest extends FormRequest
{
    public const CUSTOM_DOMAINS_REGEX = '/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,20}$/';

    public Workspace $workspace;

    public array $customDomains = [];

    public function __construct(Request $request)
    {
        $this->workspace = $request->route('workspace');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'custom_domains' => [
                'present',
                'array',
                function ($attribute, $value, $fail) {
                    $errors = [];
                    $domains = collect($value)->filter(function ($domain) {
                        return ! empty(trim($domain));
                    })->each(function ($domain) use (&$errors) {
                        if (! preg_match(self::CUSTOM_DOMAINS_REGEX, $domain)) {
                            $errors[] = 'Invalid domain: ' . $domain;
                        }
                    });

                    if (count($errors)) {
                        $fail(implode(' ', $errors));
                    }

                    // Check if domains are already used by other workspaces
                    foreach ($domains as $domain) {
                        $exists = Workspace::where('id', '!=', $this->workspace->id)
                            ->whereJsonContains('custom_domains', $domain)
                            ->exists();

                        if ($exists) {
                            $fail("The domain {$domain} is already in use by another workspace.");
                        }
                    }

                    $limit = $this->workspace->custom_domain_count_limit;
                    if ($limit && $domains->count() > $limit) {
                        $fail('You can only add ' . $limit . ' domain(s).');
                    }

                    $this->customDomains = $domains->toArray();
                },
            ],
        ];
    }

    protected function passedValidation()
    {
        $this->replace(['custom_domains' => $this->customDomains]);
    }
}
