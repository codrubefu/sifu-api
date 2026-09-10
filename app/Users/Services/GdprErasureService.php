<?php

namespace App\Users\Services;

use App\Users\Models\GdprRequest;
use App\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GdprErasureService
{
    public function execute(GdprRequest $request, User $subject, User $actor): void
    {
        DB::transaction(function () use ($request, $subject, $actor): void {
            $fingerprint = hash('sha256', $subject->organization_id.'|'.$subject->id.'|'.config('app.key'));

            // Documents with no statutory retention obligation.
            DB::table('article_user_receipts')->where('user_id', $subject->id)->delete();
            DB::table('custom_field_values')->where('entity_type', User::class)->where('entity_id', $subject->id)->delete();
            DB::table('notification_deliveries')->where('user_id', $subject->id)->delete();
            DB::table('user_documents')->where('organization_id', $subject->organization_id)->where('user_id', $subject->id)
                ->orderBy('id')
                ->get(['id', 'disk', 'path'])
                ->each(function ($document): void {
                    Storage::disk($document->disk)->delete($document->path);
                    DB::table('user_documents')->where('id', $document->id)->update([
                        'status' => 'deleted',
                        'path' => 'gdpr-erased',
                        'original_name' => 'gdpr-erased',
                        'checksum' => hash('sha256', 'gdpr-erased|'.$document->id),
                        'updated_at' => now(),
                    ]);
                });

            // Operational activity is retained only as anonymous statistics.
            DB::table('audit_logs')->where('subject_user_id', $subject->id)->update([
                'subject_user_id' => null, 'model_id' => null, 'old_values' => null, 'new_values' => null,
            ]);
            DB::table('audit_logs')->where('changed_by', $subject->id)->update(['changed_by' => null]);

            // Financial records stay intact, but direct identifiers and provider data do not.
            DB::table('payments')->where('organization_id', $subject->organization_id)
                ->where(function ($query) use ($subject): void {
                    $query->where('admin_id', $subject->id)->orWhereIn('model_id', $subject->serviceAssignments()->pluck('id'));
                })->update([
                    'first_name' => 'REDACTED', 'last_name' => 'REDACTED', 'provider_payload' => null,
                    'external_reference' => null, 'bank_reference' => null,
                ]);

            $subject->accessTokens()->delete();
            $subject->groups()->detach();
            $subject->locations()->detach();
            $subject->forceFill([
                'first_name' => 'Deleted', 'last_name' => 'User', 'phone' => null, 'push_token' => null,
                'email' => "deleted+{$fingerprint}@invalid.local", 'password' => null, 'user_code' => null, 'active' => false,
            ])->save();

            $request->update([
                'status' => 'completed', 'processed_by' => $actor->id, 'processed_at' => now(),
                'user_id' => null, 'requested_by' => null, 'subject_fingerprint' => $fingerprint,
                'details' => null,
                'execution_proof' => [
                    'policy' => 'gdpr-retention-v1', 'executed_at' => now()->toIso8601String(),
                    'actions' => ['documents_deleted', 'activity_anonymized', 'financial_records_minimized', 'account_anonymized'],
                ],
            ]);
        });
    }
}
