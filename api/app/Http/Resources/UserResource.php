<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $personalData = Auth::id() === $this->id ? [
            'is_pro' => $this->is_pro,
            'is_subscribed' => $this->is_subscribed,
            'has_enterprise_subscription' => $this->has_enterprise_subscription,
            'admin' => $this->admin,
            'moderator' => $this->moderator,
            'template_editor' => $this->template_editor,
            'has_customer_id' => $this->has_customer_id,
            'has_forms' => $this->has_forms,
            'active_license' => $this->licenses()->active()->first(),
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
        ] : [];

        return array_merge(parent::toArray($request), $personalData);
    }
}
