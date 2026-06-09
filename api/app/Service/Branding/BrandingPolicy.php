<?php

namespace App\Service\Branding;

use App\Models\Forms\Form;
use App\Service\Billing\Feature;

class BrandingPolicy
{
    public function canRemoveBranding(Form $form, bool $requested): bool
    {
        return $requested && ($form->workspace?->hasFeature(Feature::BRANDING_REMOVAL) ?? false);
    }

    public function canRemoveFormBranding(Form $form): bool
    {
        return $this->canRemoveBranding($form, (bool) $form->no_branding);
    }
}
