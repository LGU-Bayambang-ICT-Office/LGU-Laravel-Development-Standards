---
name: lgu-code-author
description: >-
  Scaffolds and implements Laravel code compliant with Philippine LGU Engineering Standards
  (LGU-ICTO-STD-2026-V1). Enforces Dual-Identifier (BIGINT id + UUID), Data Privacy SPI
  encryption (AES-256-CBC) with blind index, immutable COA audit trails, Action/Service
  boundaries, afterCommit event listeners, Maker-Checker policies, and dedicated MVC routes.
---

# LGU Laravel Code Authoring Skill (`lgu-code-author`)

This skill provides step-by-step architectural recipes, code templates, and validation checklists for generating and refactoring Laravel applications to comply with **LGU-ICTO-STD-2026-V1** (Municipality of Bayambang).

---

## 1. Operating Environment & Tooling

When executing this skill:
- **With Laravel Boost / MCP Active:** Inspect current models, database schemas (`get_database_schema`), and routes (`get_routes`) before writing code.
- **Progressive Fallback:** Inspect files directly (`read_file`) in `app/Models`, `database/migrations`, `app/Actions`, and `routes/web.php`.

---

## 2. Architectural Recipes

### Recipe 1: Database Migration & Schema Design (Section 7)

Every persistent domain table must follow the **Dual-Identifier Standard** and strict foreign key integrity:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_declarations', function (Blueprint $table) {
            // Invariant 1: Dual-Identifier Standard
            $table->id(); // BIGINT UNSIGNED auto-increment for internal PK & foreign joins
            $table->uuid('uuid')->unique(); // UUIDv4 for all URL exposure & external API refs

            // Multi-Office Scoping (Tenancy)
            $table->foreignId('office_id')
                ->constrained('offices')
                ->restrictOnDelete(); // RESTRICT prevents accidental orphan cascades

            // Sensitive Personal Information (SPI) - Encrypted + Blind Index
            $table->text('owner_tin')->nullable(); // Stores AES-256-CBC ciphertext
            $table->string('owner_tin_blind_index', 64)->nullable()->index(); // HMAC-SHA256 search index

            // Domain Attributes
            $table->string('declaration_number', 50)->unique();
            $table->decimal('market_value', 15, 2);
            $table->decimal('assessed_value', 15, 2);
            $table->string('status', 30)->default('draft');

            // Audit & Timestamps
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes(); // Optional for non-financial master records; NEVER delete financial ledgers

            // Indexes for composite queries
            $table->index(['office_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_declarations');
    }
};
```

**Schema Invariants Checklist:**
- [x] Primary key is `$table->id()` (`BIGINT UNSIGNED`).
- [x] `$table->uuid('uuid')->unique()` exists.
- [x] Office tenancy key `$table->foreignId('office_id')` present with `restrictOnDelete()`.
- [x] SPI columns have pair: `text('{field}')` + `string('{field}_blind_index', 64)->nullable()->index()`.
- [x] Financial/disbursement tables use `restrictOnDelete()`. Never `cascadeOnDelete()` on sovereign ledgers.

---

### Recipe 2: Eloquent Model & Privacy Casts (Section 2.1 & 7.3)

Models must implement `HasRouteUuid`, `OfficeScope`, and SPI cryptography:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lgu\GovSuite\Casts\EncryptedPii;
use Lgu\GovSuite\Scopes\OfficeScope;
use Lgu\GovSuite\Traits\HasPiiHashing;
use Lgu\GovSuite\Traits\HasRouteUuid;

class TaxDeclaration extends Model
{
    use HasFactory, HasRouteUuid, HasPiiHashing, SoftDeletes;

    /**
     * Mass assignment protection: explicitly define fillable.
     * NEVER use protected $guarded = [];
     */
    protected $fillable = [
        'office_id',
        'declaration_number',
        'owner_tin',
        'owner_tin_blind_index',
        'market_value',
        'assessed_value',
        'status',
        'created_by',
        'approved_by',
    ];

    /**
     * Cryptographic and type casts.
     */
    protected function casts(): array
    {
        return [
            'owner_tin' => EncryptedPii::class, // Transparent AES-256-CBC encryption
            'market_value' => 'decimal:2',
            'assessed_value' => 'decimal:2',
        ];
    }

    /**
     * Automatic blind index computation on save.
     */
    protected static function booted(): void
    {
        // Enforce Multi-Office Tenancy Isolation
        static::addGlobalScope(new OfficeScope());

        // Automatically hash PII before saving
        static::saving(function (TaxDeclaration $model) {
            if ($model->isDirty('owner_tin')) {
                $model->owner_tin_blind_index = self::hashPii($model->owner_tin);
            }
        });
    }
}
```

**Exact-Match Search Query via Blind Index:**
```php
// Finding a record securely without decrypting the entire table:
$hashedInput = TaxDeclaration::hashPii($searchTin);
$record = TaxDeclaration::where('owner_tin_blind_index', $hashedInput)->first();
```

---

### Recipe 3: Domain Action & Service Segregation (Section 5 & 10)

Business state mutations belong in **Action** classes, not Controllers or Livewire components.

```php
namespace App\Actions\Taxation;

use App\Events\TaxDeclarationApproved;
use App\Models\TaxDeclaration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveTaxDeclarationAction
{
    /**
     * Execute the tax declaration approval mutation.
     * Enforces Segregation of Duties (maker cannot be checker) & DB transactions.
     */
    public function execute(TaxDeclaration $declaration, User $approver): TaxDeclaration
    {
        // Invariant: Segregation of Duties
        if ($declaration->created_by === $approver->id) {
            throw ValidationException::withMessages([
                'approver' => 'Segregation of Duties violation: Initiator cannot approve this tax declaration.',
            ]);
        }

        if ($declaration->status !== 'pending_review') {
            throw new \DomainException("Cannot approve tax declaration in status [{$declaration->status}].");
        }

        return DB::transaction(function () use ($declaration, $approver) {
            $declaration->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
            ]);

            // Event dispatched with afterCommit safety
            TaxDeclarationApproved::dispatch($declaration);

            return $declaration;
        });
    }
}
```

**Queueable Event Listener with `afterCommit` Safety:**
```php
namespace App\Listeners;

use App\Events\TaxDeclarationApproved;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyTaxpayerOnApproval implements ShouldQueue
{
    /**
     * Ensures event only runs if parent DB transaction committed successfully.
     */
    public bool $afterCommit = true;

    public function handle(TaxDeclarationApproved $event): void
    {
        // Safe dispatch of notifications/SMS/ARTA receipt
    }
}
```

---

### Recipe 4: Dedicated MVC Show Route & Printable Streaming (Section 4 & 12)

**Web Route Definitions (`routes/web.php`):**
```php
use App\Http\Controllers\TaxDeclarationController;
use App\Http\Controllers\StreamTaxDeclarationPdfController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Dedicated Traditional Show Route (Explicit UUID binding via HasRouteUuid)
    Route::get('/tax-declarations/{tax_declaration}', [TaxDeclarationController::class, 'show'])
        ->name('tax-declarations.show');

    // Dedicated Single-Action Printable Form Stream
    Route::get('/tax-declarations/{tax_declaration}/print', StreamTaxDeclarationPdfController::class)
        ->name('tax-declarations.print');
});
```

**Single-Action PDF Streaming Controller:**
```php
namespace App\Http\Controllers;

use App\Models\TaxDeclaration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class StreamTaxDeclarationPdfController extends Controller
{
    public function __invoke(TaxDeclaration $taxDeclaration): Response
    {
        $this->authorize('view', $taxDeclaration);

        $pdf = Pdf::loadView('prints.tax-declaration', [
            'declaration' => $taxDeclaration,
        ])->setPaper('a4', 'portrait');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="TD-' . $taxDeclaration->declaration_number . '.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
```

---

### Recipe 5: Authorization Policy & Segregation of Duties (Section 15)

```php
namespace App\Policies;

use App\Models\TaxDeclaration;
use App\Models\User;
use Lgu\GovSuite\Policies\BaseGovernmentPolicy;

class TaxDeclarationPolicy extends BaseGovernmentPolicy
{
    public function view(User $user, TaxDeclaration $declaration): bool
    {
        return $this->isInSameOffice($user, $declaration)
            && $user->hasPermissionTo('tax-declarations.view');
    }

    public function approve(User $user, TaxDeclaration $declaration): bool
    {
        // Maker-Checker: Initiator cannot approve
        if ($declaration->created_by === $user->id) {
            return false;
        }

        return $this->isInSameOffice($user, $declaration)
            && $user->hasPermissionTo('tax-declarations.approve');
    }
}
```

---

## 3. Strict Prohibitions Checklist

When authoring or modifying code, verify none of the following anti-patterns are introduced:
- ❌ **No exposing integer IDs in URLs:** Always bind via `uuid`.
- ❌ **No unprotected mass assignment:** Never use `protected $guarded = []`.
- ❌ **No raw SQL string concatenation:** Direct variable interpolation into `whereRaw` or `DB::raw` is prohibited.
- ❌ **No unescaped Blade output for user data:** Never use `{!! $input !!}` without HTMLPurifier.
- ❌ **No monolithic Livewire modals for record inspection:** Large forms or records belong in dedicated `show` routes. Livewire modals are strictly for $\le 3$ inputs.
- ❌ **No financial record deletion:** Disallow `DELETE` and `TRUNCATE` on vouchers, ledgers, and tax assessments.
