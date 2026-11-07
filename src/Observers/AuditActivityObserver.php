<?php

declare(strict_types=1);

namespace Lgu\GovSuite\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
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
    public function creating(Model $model): void
    {
        $officeId = $model->office_id ?? null;

        // Prevent race condition across concurrent audit insertions within the same office context
        if ($officeId && DB::transactionLevel() > 0) {
            DB::table('offices')->where('id', $officeId)->lockForUpdate()->value('id');
        }

        // 1. Fetch the previous hash in sequence
        $query = $model->newQuery()->withoutGlobalScopes()->orderBy('id', 'desc');
        if ($officeId) {
            $query->where('office_id', $officeId);
        }

        $previousActivity = $query->first();
        $genesisHash = (string) Config::get('lgu.coa.genesis_hash', str_repeat('0', 64));
        $previousHash = $previousActivity ? ($previousActivity->row_hash ?? $genesisHash) : $genesisHash;
        $model->previous_hash = $previousHash;

        // 2. Extract and sort attributes consistently
        $attributes = [
            'office_id'      => $model->office_id ?? null,
            'causer_id'      => $model->causer_id ?? null,
            'causer_type'    => $model->causer_type ?? null,
            'subject_id'     => $model->subject_id ?? null,
            'subject_type'   => $model->subject_type ?? null,
            'event'          => $model->event ?? null,
            'description'    => $model->description ?? null,
            'properties'     => $model->properties ?? null,
            'created_at'     => $model->created_at ? $model->created_at->toIso8601String() : now()->toIso8601String(),
        ];
        ksort($attributes);

        // 3. Generate SHA-256 hash chain link
        $jsonPayload = json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $model->row_hash = hash('sha256', $previousHash . $jsonPayload);
    }
}
