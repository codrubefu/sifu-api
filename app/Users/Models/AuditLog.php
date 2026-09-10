<?php

namespace App\Users\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'model_type',
    'model_id',
    'organization_id',
    'subject_user_id',
    'event_type',
    'action',
    'changed_by',
    'old_values',
    'new_values',
])]
class AuditLog extends Model
{
    use HasFactory;

    public const USER_CREATED = 'user.created';
    public const USER_UPDATED = 'user.updated';
    public const SERVICE_ASSIGNED = 'service.assigned';
    public const SERVICE_ACTIVATED = 'service.activated';
    public const SERVICE_RENEWED = 'service.renewed';
    public const SERVICE_SUSPENDED = 'service.suspended';
    public const PAYMENT_RECORDED = 'payment.recorded';
    public const APPROVAL_GRANTED = 'approval.granted';
    public const CARD_ISSUED = 'card.issued';
    public const SMS_SENT = 'sms.sent';
    public const USER_DOCUMENT_UPLOADED = 'user_document.uploaded';
    public const USER_DOCUMENT_DOWNLOADED = 'user_document.downloaded';
    public const USER_DOCUMENT_REPLACED = 'user_document.replaced';
    public const USER_DOCUMENT_DELETED = 'user_document.deleted';
    public const CHECKIN_ACCEPTED = 'checkin.accepted';
    public const CHECKIN_REFUSED = 'checkin.refused';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}
