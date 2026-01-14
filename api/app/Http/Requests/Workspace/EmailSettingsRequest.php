<?php

namespace App\Http\Requests\Workspace;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class EmailSettingsRequest extends FormRequest
{
    public Workspace $workspace;

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
        $allFieldsPresent = $this->filled(['host', 'port', 'username', 'password', 'sender_address']);

        return [
            'host' => [
                $allFieldsPresent ? 'required' : 'nullable',
                'required_with:port,username,password,sender_address',
                'string',
            ],
            'port' => [
                $allFieldsPresent ? 'required' : 'nullable',
                'required_with:host,username,password,sender_address',
                'integer',
            ],
            'encryption' => [
                'nullable',
                'in:tls,ssl',
            ],
            'username' => [
                $allFieldsPresent ? 'required' : 'nullable',
                'required_with:host,port,password,sender_address',
                'string',
            ],
            'password' => [
                $allFieldsPresent ? 'required' : 'nullable',
                'required_with:host,port,username,sender_address',
                'string',
            ],
            'sender_address' => [
                'nullable',
                'email',
            ],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'host.required_with' => 'The host field is required.',
            'port.required_with' => 'The port field is required.',
            'username.required_with' => 'The username field is required.',
            'password.required_with' => 'The password field is required.',
        ];
    }
}
