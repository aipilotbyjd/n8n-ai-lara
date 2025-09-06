<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'organization_id',
        'stripe_invoice_id',
        'number',
        'amount_due',
        'amount_paid',
        'currency',
        'status',
        'billing_reason',
        'invoice_pdf',
        'hosted_invoice_url',
        'period_start',
        'period_end',
        'due_date',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the subscription this invoice belongs to
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the organization this invoice belongs to
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Check if invoice is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if invoice is open
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if invoice is void
     */
    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /**
     * Check if invoice is draft
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if invoice is uncollectible
     */
    public function isUncollectible(): bool
    {
        return $this->status === 'uncollectible';
    }

    /**
     * Get formatted amount due
     */
    public function getFormattedAmountDueAttribute(): string
    {
        return number_format($this->amount_due, 2) . ' ' . strtoupper($this->currency);
    }

    /**
     * Get formatted amount paid
     */
    public function getFormattedAmountPaidAttribute(): string
    {
        return number_format($this->amount_paid, 2) . ' ' . strtoupper($this->currency);
    }

    /**
     * Scope for paid invoices
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope for unpaid invoices
     */
    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['open', 'draft']);
    }

    /**
     * Scope for overdue invoices
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'open')
            ->where('due_date', '<', now());
    }
}
