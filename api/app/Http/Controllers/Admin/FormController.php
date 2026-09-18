<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Service\Admin\AdminForms;

class FormController extends Controller
{
    public function getDeletedForms(User $user)
    {
        return app(AdminForms::class)->getDeletedForms($user);
    }

    public function restoreDeletedForm(string $slug)
    {
        return app(AdminForms::class)->restoreDeletedForm($slug);
    }

}
