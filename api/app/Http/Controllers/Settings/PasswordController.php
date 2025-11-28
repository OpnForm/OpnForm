<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $this->validate($request, [
            'current_password' => 'required',
            'password' => [
                'required',
                'string',
                'min:8', // Minimum password length
                'regex:/[A-Za-z]/', // Include letters
                'regex:/[0-9]/', // Include numbers
                'regex:/[@$!%*#?&\-_+=.,:;<>^()[\]{}|~]/', // Include special characters (expanded set)
                'confirmed',
            ],
        ]);

        // Verify current password matches
        if (!Hash::check($request->current_password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $request->user()->update([
            'password' => bcrypt($request->password),
        ]);

        return response()->json(null, 204);
    }
}
