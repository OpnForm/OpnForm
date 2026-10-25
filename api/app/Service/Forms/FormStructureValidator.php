<?php

namespace App\Service\Forms;

use App\Models\Workspace;
use App\Rules\ComputedVariablesRule;
use App\Rules\FormPropertiesRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class FormStructureValidator
{
    public const MAX_PROPERTY_COUNT = 500;

    public function __construct(private readonly FormValidationIssueMapper $issueMapper)
    {
    }

    public function validator(array $definition, ?Workspace $workspace = null): ValidatorContract
    {
        return Validator::make($definition, [
            'properties' => ['required', 'array', 'max:'.self::MAX_PROPERTY_COUNT, new FormPropertiesRule($workspace)],
            'computed_variables' => ['nullable', 'array', new ComputedVariablesRule()],
        ]);
    }

    public function validate(array $definition, ?Workspace $workspace = null): void
    {
        $validator = $this->validator($definition, $workspace);
        if ($validator->passes()) {
            return;
        }

        $errors = $validator->errors()->toArray();
        $issues = $this->issueMapper->fromErrors($errors);

        throw new ValidationException($validator, response()->json([
            'message' => $this->issueMapper->summary($this->issueMapper->count($errors)),
            'errors' => $errors,
            'issues' => $issues,
        ], 422));
    }

    /**
     * @return array<int, array{code: string, path: string, message: string, meta: array<string, mixed>}>
     */
    public function issues(array $definition, ?Workspace $workspace = null): array
    {
        $validator = $this->validator($definition, $workspace);
        $validator->passes();

        return $this->issueMapper->fromErrors($validator->errors()->toArray());
    }
}
