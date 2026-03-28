<?php

namespace App\Http\Requests;

use App\Models\Forms\Form;
use App\Rules\CustomFieldValidationRule;
use App\Rules\InputMaskRule;
use App\Rules\MatrixValidationRule;
use App\Rules\StorageFile;
use App\Rules\ValidHCaptcha;
use App\Rules\ValidPhoneInputRule;
use App\Rules\ValidReCaptcha;
use App\Rules\ValidUrl;
use App\Service\Forms\FormLogicPropertyResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Stevebauman\Purify\Facades\Purify;

class AnswerFormRequest extends FormRequest
{
    public Form $form;

    protected array $requestRules = [];

    protected int $maxFileSize;

    public function __construct(Request $request)
    {
        // Get form from route model binding instead of middleware
        $this->form = $request->route('form') ?? $request->form;
        $this->maxFileSize = $this->form->workspace->max_file_size;
    }

    private function getFieldMaxFileSize($fieldProps)
    {
        return array_key_exists('max_file_size', $fieldProps) ?
            min($fieldProps['max_file_size'] * 1000000, $this->maxFileSize) : $this->maxFileSize;
    }


    /**
     * Get the validation rules that apply to the form.
     *
     * @return array
     */
    public function rules()
    {
        // Skip validation if this is a partial submission
        if ($this->has('is_partial')) {
            return [];
        }

        $selectionFields = collect($this->form->properties)->filter(function ($pro) {
            return in_array($pro['type'], ['select', 'multi_select']);
        });
        foreach ($this->form->properties as $property) {
            $rules = [];
            /*if (!$this->form->is_pro) {  // If not pro then not check logic
                $property['logic'] = false;
            }*/

            $data = $this->toArray();

            // User custom validation
            if (!(Str::of($property['type'])->startsWith('nf-')) && isset($property['validation'])) {
                $rules[] = (new CustomFieldValidationRule($property['validation'], $data, $this->form));
            }

            // For get values instead of Id for select/multi select options
            foreach ($selectionFields as $field) {
                if (isset($data[$field['id']]) && is_array($data[$field['id']])) {
                    $data[$field['id']] = array_map(function ($val) use ($field) {
                        $tmpop = collect($field[$field['type']]['options'])->first(function ($op) use ($val) {
                            return isset($op['id'], $op['name']) && ($op['id'] === $val || $op['name'] === $val);
                        });
                        return isset($tmpop['name']) ? $tmpop['name'] : $val;
                    }, $data[$field['id']]);
                } elseif (isset($data[$field['id']])) {
                    // Handle single select values
                    $tmpop = collect($field[$field['type']]['options'])->first(function ($op) use ($field, $data) {
                        return isset($op['id'], $op['name']) && ($op['id'] === $data[$field['id']] || $op['name'] === $data[$field['id']]);
                    });
                    $data[$field['id']] = isset($tmpop['name']) ? $tmpop['name'] : $data[$field['id']];
                }
            }
            if (
                FormLogicPropertyResolver::isRequired($property, $data) &&
                !FormLogicPropertyResolver::isHidden($property, $data)
            ) {
                $rules[] = 'required';

                if ($property['type'] == 'checkbox') {
                    // Required for checkboxes means true
                    $rules[] = 'accepted';
                } elseif ($property['type'] == 'rating') {
                    // For star rating, needs a minimum of 1 star
                    $rules[] = 'min:1';
                } elseif ($property['type'] == 'matrix') {
                    $rules[] = new MatrixValidationRule($property, true);
                }
            } else {
                $rules[] = 'nullable';
                if ($property['type'] == 'matrix') {
                    $rules[] = new MatrixValidationRule($property, false);
                }
            }

            // Clean id to escape "."
            $propertyId = $property['id'];
            if (in_array($property['type'], ['multi_select'])) {
                $rules[] = 'array';

                // Add min/max selection constraints for multi_select
                if (isset($property['min_selection']) && $property['min_selection'] > 0) {
                    $rules[] = 'min:' . $property['min_selection'];
                }
                if (isset($property['max_selection']) && $property['max_selection'] > 0) {
                    $rules[] = 'max:' . $property['max_selection'];
                }

                $this->requestRules[$propertyId . '.*'] = $this->getPropertyRules($property);
            } else {
                $rules = array_merge($rules, $this->getPropertyRules($property));
            }

            $this->requestRules[$propertyId] = $rules;
        }

        // Validate Captcha
        if ($this->form->use_captcha) {
            if ($this->form->captcha_provider === 'recaptcha') {
                $this->requestRules['g-recaptcha-response'] = [new ValidReCaptcha()];
            } elseif ($this->form->captcha_provider === 'hcaptcha') {
                $this->requestRules['h-captcha-response'] = [new ValidHCaptcha()];
            }
        }

        // Validate submission_id for edit mode
        if ($this->form->is_pro && $this->form->editable_submissions) {
            $this->requestRules['submission_id'] = 'string';
        }

        // Add rules for metadata fields
        $this->requestRules['completion_time'] = 'nullable|integer';
        $this->requestRules['submission_id'] = 'nullable|string';

        return $this->requestRules;
    }

    /**
     * Renames validated fields (because field names are ids)
     *
     * @return array
     */
    public function attributes()
    {
        $fields = [];
        foreach ($this->form->properties as $property) {
            $fields[$property['id']] = $property['name'];
        }

        return $fields;
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        $messages = [];
        foreach ($this->form->properties as $property) {
            if ($property['type'] == 'date' && isset($property['date_range']) && $property['date_range']) {
                $messages[$property['id'] . '.0.required_with'] = __('validation.from_date_required');
                $messages[$property['id'] . '.1.required_with'] = __('validation.to_date_required');
                $messages[$property['id'] . '.0.before_or_equal'] = __('validation.from_date_before_or_equal');
            }
            if ($property['type'] == 'rating') {
                $messages[$property['id'] . '.min'] = __('validation.rating_min');
            }
            if ($property['type'] == 'multi_select') {
                if (isset($property['min_selection']) && $property['min_selection'] > 0) {
                    $messages[$property['id'] . '.min'] = __('validation.select_min', ['min' => $property['min_selection']]);
                }
                if (isset($property['max_selection']) && $property['max_selection'] > 0) {
                    $messages[$property['id'] . '.max'] = __('validation.select_max', ['max' => $property['max_selection']]);
                }
            }
        }

        return $messages;
    }

    /**
     * Return validation rules for a given form property
     */
    private function getPropertyRules($property): array
    {
        switch ($property['type']) {
            case 'text':
                if (isset($property['input_mask']) && $property['input_mask']) {
                    return ['string', new InputMaskRule($property['input_mask'])];
                }

                return ['string'];
            case 'rich_text':
            case 'signature':
                return ['string'];
            case 'number':
            case 'rating':
            case 'scale':
            case 'slider':
                return ['numeric'];
            case 'select':
            case 'multi_select':
                if (($property['allow_creation'] ?? false)) {
                    return ['string'];
                }

                return [Rule::in($this->getSelectPropertyOptions($property))];
            case 'checkbox':
                return ['boolean'];
            case 'url':
                if (isset($property['file_upload']) && $property['file_upload']) {
                    $this->requestRules[$property['id'] . '.*'] = [new StorageFile($this->maxFileSize, [], $this->form)];

                    return ['array'];
                }

                return [new ValidUrl()];
            case 'files':
                $allowedFileTypes = [];
                if (!empty($property['allowed_file_types'])) {
                    $allowedFileTypes = explode(',', $property['allowed_file_types']);
                }
                $this->requestRules[$property['id'] . '.*'] = [new StorageFile($this->getFieldMaxFileSize($property), $allowedFileTypes, $this->form)];

                return ['array'];
            case 'email':
                return ['email:filter'];
            case 'date':
                if (isset($property['date_range']) && $property['date_range']) {
                    $this->requestRules[$property['id'] . '.*'] = $this->getRulesForDate($property);
                    $this->requestRules[$property['id'] . '.0'] = ['required_with:' . $property['id'] . '.1', 'before_or_equal:' . $property['id'] . '.1'];
                    $this->requestRules[$property['id'] . '.1'] = ['required_with:' . $property['id'] . '.0'];

                    return ['array', 'min:2'];
                }

                return $this->getRulesForDate($property);
            case 'phone_number':
                if (isset($property['use_simple_text_input']) && $property['use_simple_text_input']) {
                    return ['string'];
                }

                return ['string', 'min:6', new ValidPhoneInputRule()];
            default:
                return [];
        }
    }

    private function getRulesForDate($property)
    {
        if (isset($property['disable_past_dates']) && $property['disable_past_dates']) {
            return ['date', 'after:yesterday'];
        } elseif (isset($property['disable_future_dates']) && $property['disable_future_dates']) {
            return ['date', 'before:tomorrow'];
        }

        return ['date'];
    }

    private function getSelectPropertyOptions($property): array
    {
        $type = $property['type'];
        if (!isset($property[$type])) {
            return [];
        }

        return array_column($property[$type]['options'], 'name');
    }

    protected function prepareForValidation()
    {
        // Set locale based on form language
        if ($this->form?->language && in_array($this->form->language, Form::LANGUAGES)) {
            app()->setLocale($this->form->language);
        }


        $receivedData = $this->toArray();
        $mergeData = [];
        $countryCodeMapper = json_decode(file_get_contents(resource_path('data/country_code_mapper.json')), true);
        collect($this->form->properties)->each(function ($property) use ($countryCodeMapper, $receivedData, &$mergeData) {
            $receivedValue = $receivedData[$property['id']] ?? null;

            // Escape all '\' in select options
            if (in_array($property['type'], ['select', 'multi_select']) && !is_null($receivedValue)) {
                if (is_array($receivedValue)) {
                    $mergeData[$property['id']] = collect($receivedValue)->map(function ($value) {
                        $value = Str::of($value);

                        return $value->replace(
                            ["\e", "\f", "\n", "\r", "\t", "\v", '\\'],
                            ['\\e', '\\f', '\\n', '\\r', '\\t', '\\v', '\\\\']
                        )->toString();
                    })->toArray();
                } else {
                    $receivedValue = Str::of($receivedValue);
                    $mergeData[$property['id']] = $receivedValue->replace(
                        ["\e", "\f", "\n", "\r", "\t", "\v", '\\'],
                        ['\\e', '\\f', '\\n', '\\r', '\\t', '\\v', '\\\\']
                    )->toString();
                }
            }

            if ($property['type'] === 'phone_number' && (!isset($property['use_simple_text_input']) || !$property['use_simple_text_input']) && $receivedValue && in_array($receivedValue, $countryCodeMapper)) {
                $mergeData[$property['id']] = null;
            }

            if ($property['type'] === 'rich_text' && $receivedValue) {
                $mergeData[$property['id']] = Purify::clean($receivedValue);
            }
        });

        $this->merge($mergeData);
    }
}
