<?php
namespace App\Observers;

use App\Models\AuditActivity;
use Illuminate\Support\Facades\DB;

/**
 * Observer AuditActivityObserver
 *
 * Implements Cryptographic Hash Chaining for Commission on Audit (COA) compliance.
 *
 * Cryptographic Formula:
 * H_n = SHA256( H_{n-1} || NormalizedJSON(Record_n) )
 *
 * Guarantees:
 * - Tamper-evident ledger: Any out-of-band SQL UPDATE or DELETE invalidates subsequent hashes.
 * - Concurrency protection: Locks the scope/office record to prevent race conditions during parallel writes.
 */
class AuditActivityObserver
{
    /**
     * Handle the AuditActivity "creating" event.
     */
    public function creating(AuditActivity $model): void
    {
        $officeId = $model->office_id ?? null;

        // Prevent race condition across concurrent audit insertions within the same office context
        if ($officeId && DB::transactionLevel() > 0) {
            DB::table('offices')->where('id', $officeId)->lockForUpdate()->value('id');
        }

        // 1. Fetch the previous hash in sequence
        $query = AuditActivity::withoutGlobalScopes()->orderBy('id', 'desc');
        if ($officeId) {
            $query->where('office_id', $officeId);
        }

        $previousActivity = $query->first();
        $previousHash = $previousActivity ? ($previousActivity->row_hash ?? '') : '';
        $model->previous_hash = $previousHash;

        // 2. Extract and sort attributes consistently
        $attributes = [
            'office_id' => $model->office_id,
            'causer_id' => $model->causer_id,
            'causer_type' => $model->causer_type,
            'subject_id' => $model->subject_id,
            'subject_type' => $model->subject_type,
            'event' => $model->event,
            'description' => $model->description,
            'properties' => $model->properties,
            'created_at' => $model->created_at ? $model->created_at->toIso8601String() : now()->toIso8601String(),
        ];
        ksort($attributes);

        // 3. Generate SHA-256 hash chain link
        $jsonPayload = json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $model->row_hash = hash('sha256', $previousHash . $jsonPayload);
    }
}
