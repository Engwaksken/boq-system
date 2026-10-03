<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SubscriptionResource extends ProxySubscriptionResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
