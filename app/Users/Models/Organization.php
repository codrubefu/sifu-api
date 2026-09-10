<?php

namespace App\Users\Models;

use App\CustomFields\Models\CustomField;
use App\CustomFields\Models\CustomFieldValue;
use App\Users\Models\Concerns\LogsModelChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Database\Factories\OrganizationFactory;

#[Fillable(['name', 'slug', 'url', 'description', 'address', 'email', 'phone', 'web', 'cui', 'nr_reg_com', 'capital', 'cont', 'bank', 'receipt_code', 'receipt_number', 'invoice_code', 'invoice_number', 'bill_code', 'bill_number'])]
#[UseFactory(OrganizationFactory::class)]
class Organization extends Model
{
    use LogsModelChanges;
    use HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function locationGroups(): HasMany
    {
        return $this->hasMany(LocationGroup::class);
    }

    public function smtpSetting(): HasOne
    {
        return $this->hasOne(SmtpSetting::class);
    }
}
