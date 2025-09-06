<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => $this->getFormattedPriceAttribute(),
            'currency' => $this->currency,
            'interval' => $this->interval,
            'interval_display' => $this->getIntervalDisplayAttribute(),
            'interval_count' => $this->interval_count,
            'trial_days' => $this->trial_days,
            'features' => $this->features ?? [],
            'limits' => $this->limits ?? [],
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,

            // Computed properties
            'price_per_month' => $this->getPricePerMonthAttribute(),

            // Feature checks
            'has_feature' => function ($feature) {
                return $this->hasFeature($feature);
            },

            // Limit checks
            'is_unlimited' => function ($resource) {
                return $this->isUnlimited($resource);
            },
            'get_limit' => function ($resource) {
                return $this->getLimit($resource);
            },

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
