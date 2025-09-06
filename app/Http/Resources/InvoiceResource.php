<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'subscription' => [
                'id' => $this->subscription->id,
                'plan' => [
                    'id' => $this->subscription->plan->id,
                    'name' => $this->subscription->plan->name,
                    'slug' => $this->subscription->plan->slug,
                ],
            ],
            'organization' => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
            ],
            'amount_due' => $this->amount_due,
            'amount_paid' => $this->amount_paid,
            'formatted_amount_due' => $this->getFormattedAmountDueAttribute(),
            'formatted_amount_paid' => $this->getFormattedAmountPaidAttribute(),
            'currency' => $this->currency,
            'status' => $this->status,
            'billing_reason' => $this->billing_reason,
            'billing_reason_display' => $this->getBillingReasonDisplay(),
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'due_date' => $this->due_date,
            'paid_at' => $this->paid_at,
            'invoice_pdf' => $this->invoice_pdf,
            'hosted_invoice_url' => $this->hosted_invoice_url,

            // Status flags
            'is_paid' => $this->isPaid(),
            'is_open' => $this->isOpen(),
            'is_void' => $this->isVoid(),
            'is_draft' => $this->isDraft(),
            'is_uncollectible' => $this->isUncollectible(),

            // Permissions
            'can_view' => $user ? $this->organization->isMember($user) : false,
            'can_download' => $user ? $this->organization->isMember($user) : false,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get human readable billing reason
     */
    private function getBillingReasonDisplay(): string
    {
        return match($this->billing_reason) {
            'subscription_cycle' => 'Subscription Cycle',
            'subscription_create' => 'Subscription Created',
            'subscription_update' => 'Subscription Updated',
            'subscription_threshold' => 'Subscription Threshold',
            'manual' => 'Manual Charge',
            'upcoming' => 'Upcoming Charge',
            default => ucwords(str_replace('_', ' ', $this->billing_reason ?? 'Unknown'))
        };
    }
}
