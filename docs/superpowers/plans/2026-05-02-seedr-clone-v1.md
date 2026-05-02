# Seedr Clone V1 Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the approved v1 Seedr-style torrent-to-cloud MVP with multi-user quotas, qBittorrent downloads, RustFS/S3 storage, signed access, and a single-server Docker deployment path.

**Architecture:** Laravel owns all user-facing state, authorization, quota enforcement, and signed file access. qBittorrent-nox is a private Docker service controlled through a small Laravel HTTP client, while completed files are imported into private RustFS/S3 object storage and then removed from qBittorrent. Inertia Svelte pages use Wayfinder-generated routes and follow the existing starter-kit layout patterns.

**Tech Stack:** Laravel 13, Fortify, Inertia Laravel v3, Inertia Svelte v3 / Svelte 5, Wayfinder, Pest 4, Postgres for deployment, Redis queues/cache, qBittorrent-nox, RustFS S3, Docker Compose.

---

## Preconditions

- Current project root: `/Users/emmanuelmensah/Desktop/seedr`.
- Approved design spec: `docs/superpowers/specs/2026-05-02-seedr-clone-v1-design.md`.
- This directory is currently not a git repository. Commit steps are included for normal project hygiene, but they only apply after git is initialized or the project is placed in a repository.
- Before implementation code changes, use Laravel Boost `search-docs` for the exact Laravel, Inertia, Fortify, Pest, queue, filesystem, and validation APIs touched by each chunk.
- Use these skills when executing: `laravel-best-practices`, `fortify-development`, `pest-testing`, `inertia-svelte-development`, `wayfinder-development`, `tailwindcss-development`, `superpowers:test-driven-development`, and `superpowers:verification-before-completion`.
- Do not expose qBittorrent, RustFS, Postgres, or Redis publicly.
- Do not change dependencies without explicit approval. RustFS/S3 will likely require Laravel's S3 filesystem adapter package if it is not already installed.

## File Structure Map

### Backend Domain

- Create `app/Enums/TorrentStatus.php`: enum of v1 torrent states.
- Create `app/Enums/TorrentSourceType.php`: enum for `magnet` and `torrent_file`.
- Create `app/Models/Torrent.php`: submitted torrent lifecycle record.
- Create `app/Models/TorrentFile.php`: files discovered inside a torrent.
- Create `app/Models/StoredFile.php`: completed file stored in RustFS/S3.
- Create `app/Models/StorageUsageEvent.php`: append-only quota ledger.
- Modify `app/Models/User.php`: email verification contract, admin/quota casts, relationships, quota helpers.
- Create migrations for quota/admin fields and torrent/file tables.
- Create factories for torrent and file models.

### Backend Services And Jobs

- Create `config/torrents.php`: qBittorrent, quota, rate limit, and filesystem settings.
- Create `app/Services/Torrents/QBittorrentClient.php`: qBittorrent Web API wrapper.
- Create `app/Services/Torrents/TorrentMetadata.php`: value object for metadata inspection results.
- Create `app/Services/Torrents/TorrentMetadataInspector.php`: interface for metadata lookup.
- Create `app/Services/Torrents/QBittorrentMetadataInspector.php`: qBittorrent-backed metadata implementation.
- Create `app/Services/Storage/StorageQuota.php`: quota calculation and assertions.
- Create `app/Jobs/InspectTorrentMetadata.php`: inspect metadata and reject/queue.
- Create `app/Jobs/StartTorrentDownload.php`: send accepted torrent to qBittorrent.
- Create `app/Jobs/PollTorrentProgress.php`: update progress and dispatch import on completion.
- Create `app/Jobs/ImportCompletedTorrent.php`: upload completed files into RustFS/S3 and cleanup qBittorrent.

### HTTP Layer

- Create `app/Http/Controllers/TorrentController.php`: dashboard submission/cancel actions.
- Create `app/Http/Controllers/LibraryController.php`: library listing.
- Create `app/Http/Controllers/StoredFileAccessController.php`: signed download/stream responses.
- Create `app/Http/Controllers/Admin/UserController.php`: admin user/quota views.
- Create `app/Http/Controllers/Admin/TorrentController.php`: admin torrent views/cancel.
- Create `app/Http/Requests/StoreTorrentRequest.php`: magnet and `.torrent` validation.
- Create `app/Http/Requests/Admin/UpdateUserQuotaRequest.php`: quota update validation.
- Create `app/Policies/TorrentPolicy.php`: per-user torrent access.
- Create `app/Policies/StoredFilePolicy.php`: per-user file access.
- Modify `routes/web.php`: dashboard controller route, torrent routes, library routes, signed file routes, admin routes.
- Modify `app/Providers/AppServiceProvider.php`: policy registration if needed, bindings for interfaces.
- Modify `app/Providers/FortifyServiceProvider.php`: confirm rate limits as needed.
- Modify `app/Http/Middleware/HandleInertiaRequests.php`: share auth admin/quota-safe user data instead of raw user model if needed.

### Frontend

- Modify `resources/js/pages/Dashboard.svelte`: quota cards, active torrent, submission form, history.
- Create `resources/js/pages/Library/Index.svelte`: completed file list and media/download actions.
- Create `resources/js/pages/Admin/Users/Index.svelte`: users and quota controls.
- Create `resources/js/pages/Admin/Torrents/Index.svelte`: torrent status/admin actions.
- Create `resources/js/components/torrents/TorrentProgress.svelte`: progress display.
- Create `resources/js/components/torrents/TorrentSubmitForm.svelte`: magnet/file form.
- Create `resources/js/components/files/FileRow.svelte`: file list item.
- Modify `resources/js/components/NavMain.svelte` or the source of nav items in `AppSidebarLayout.svelte`: add Library and admin links.
- Modify `resources/js/types/auth.ts` and/or `resources/js/types/index.ts`: add typed props for quota/admin fields.
- Regenerate Wayfinder files under `resources/js/actions` and `resources/js/routes`.

### Tests

- Create feature tests for quota, torrent submission, library access, admin, and signed file access.
- Create unit tests for qBittorrent client payload/status handling and storage quota logic.
- Update existing auth/registration tests for default quota and email verification constraints.

### Deployment

- Create or modify `.env.example`: Postgres, Redis, qBittorrent, RustFS/S3, quota, and global torrent limit variables.
- Create `docker-compose.yml`: local/prod-like services.
- Create `docker/php/Dockerfile` and related entrypoint/config files only if the existing project lacks an app image.
- Create reverse proxy config after choosing Caddy, Traefik, or Nginx.

---

## Chunk 1: Foundation, Auth, Quotas

### Task 1: Enable verified multi-user quota fields

**Files:**
- Modify: `app/Models/User.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_seedr_fields_to_users_table.php`
- Modify: `app/Actions/Fortify/CreateNewUser.php`
- Test: `tests/Feature/Auth/RegistrationTest.php`
- Test: `tests/Feature/UserQuotaTest.php`

- [ ] **Step 1: Search docs**

Run Boost `search-docs` with queries: `email verification MustVerifyEmail`, `migration bigint default`, `model casts`.

- [ ] **Step 2: Create failing quota test**

Run:

```bash
php artisan make:test --pest UserQuotaTest --no-interaction
```

Add tests proving:

```php
it('assigns the default storage quota to new users', function () {
    $response = $this->post('/register', [
        'name' => 'Seed User',
        'email' => 'seed@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect();

    $user = \App\Models\User::query()->where('email', 'seed@example.com')->firstOrFail();

    expect($user->storage_quota_bytes)->toBe(734003200);
});
```

- [ ] **Step 3: Run test to verify failure**

Run:

```bash
php artisan test --compact tests/Feature/UserQuotaTest.php
```

Expected: FAIL because `storage_quota_bytes` does not exist.

- [ ] **Step 4: Create migration**

Run:

```bash
php artisan make:migration add_seedr_fields_to_users_table --table=users --no-interaction
```

Migration should add:

```php
$table->unsignedBigInteger('storage_quota_bytes')->default(734003200);
$table->boolean('is_admin')->default(false)->index();
```

- [ ] **Step 5: Update User model**

Implement:

```php
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'storage_quota_bytes' => 'integer',
            'is_admin' => 'boolean',
        ];
    }
}
```

Keep the existing attribute-based fillable/hidden style.

- [ ] **Step 6: Ensure registration writes default quota**

Check `app/Actions/Fortify/CreateNewUser.php`. If the migration default is enough, do not duplicate it. If mass assignment is already used for all user fields, only include quota explicitly if needed by tests.

- [ ] **Step 7: Run focused tests**

Run:

```bash
php artisan test --compact tests/Feature/UserQuotaTest.php tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/EmailVerificationTest.php
```

Expected: PASS.

- [ ] **Step 8: Format PHP**

Run:

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 9: Commit if repository is available**

```bash
git add app/Models/User.php app/Actions/Fortify/CreateNewUser.php database/migrations tests/Feature/UserQuotaTest.php tests/Feature/Auth/RegistrationTest.php
git commit -m "feat: add verified user quotas"
```

### Task 2: Add quota ledger and quota service

**Files:**
- Create: `app/Models/StorageUsageEvent.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_storage_usage_events_table.php`
- Create: `database/factories/StorageUsageEventFactory.php`
- Create: `app/Services/Storage/StorageQuota.php`
- Test: `tests/Unit/StorageQuotaTest.php`

- [ ] **Step 1: Write failing unit tests**

Run:

```bash
php artisan make:test --pest --unit StorageQuotaTest --no-interaction
```

Test cases:

```php
it('calculates used and remaining bytes from usage events', function () {
    $user = \App\Models\User::factory()->create(['storage_quota_bytes' => 1000]);

    \App\Models\StorageUsageEvent::factory()->for($user)->create(['delta_bytes' => 250]);
    \App\Models\StorageUsageEvent::factory()->for($user)->create(['delta_bytes' => -50]);

    $quota = app(\App\Services\Storage\StorageQuota::class);

    expect($quota->usedBytes($user))->toBe(200)
        ->and($quota->remainingBytes($user))->toBe(800)
        ->and($quota->canStore($user, 801))->toBeFalse()
        ->and($quota->canStore($user, 800))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify failure**

```bash
php artisan test --compact tests/Unit/StorageQuotaTest.php
```

- [ ] **Step 3: Create model, factory, migration, service**

Run:

```bash
php artisan make:model StorageUsageEvent -mf --no-interaction
php artisan make:class Services/Storage/StorageQuota --no-interaction
```

Migration fields:

```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('stored_file_id')->nullable()->constrained()->nullOnDelete();
$table->bigInteger('delta_bytes');
$table->string('reason');
$table->json('metadata')->nullable();
$table->timestamps();
$table->index(['user_id', 'created_at']);
```

Service methods:

```php
public function usedBytes(User $user): int;
public function remainingBytes(User $user): int;
public function canStore(User $user, int $bytes): bool;
public function assertCanStore(User $user, int $bytes): void;
```

- [ ] **Step 4: Add relationship to User**

Add:

```php
public function storageUsageEvents(): HasMany
```

- [ ] **Step 5: Run tests and format**

```bash
php artisan test --compact tests/Unit/StorageQuotaTest.php
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit if repository is available**

```bash
git add app/Models app/Services database/migrations database/factories tests/Unit/StorageQuotaTest.php
git commit -m "feat: add storage quota ledger"
```

---

## Chunk 2: Torrent Domain And Submission

### Task 3: Add torrent domain tables and models

**Files:**
- Create: `app/Enums/TorrentStatus.php`
- Create: `app/Enums/TorrentSourceType.php`
- Create: `app/Models/Torrent.php`
- Create: `app/Models/TorrentFile.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_torrents_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_torrent_files_table.php`
- Create: `database/factories/TorrentFactory.php`
- Create: `database/factories/TorrentFileFactory.php`
- Test: `tests/Feature/TorrentSubmissionTest.php`

- [ ] **Step 1: Search docs**

Run Boost `search-docs` with queries: `eloquent enum casts`, `database indexes migrations`, `foreign constrained cascade`.

- [ ] **Step 2: Write failing tests for one active torrent**

Run:

```bash
php artisan make:test --pest TorrentSubmissionTest --no-interaction
```

Add tests for:

- verified user can submit a magnet;
- unverified user is redirected/forbidden;
- user with an active torrent cannot submit another.

Use statuses `pending_metadata`, `queued`, `downloading`, and `importing` as active.

- [ ] **Step 3: Run test to verify failure**

```bash
php artisan test --compact tests/Feature/TorrentSubmissionTest.php
```

- [ ] **Step 4: Create models and migrations**

Run:

```bash
php artisan make:model Torrent -mf --no-interaction
php artisan make:model TorrentFile -mf --no-interaction
php artisan make:enum Enums/TorrentStatus --no-interaction
php artisan make:enum Enums/TorrentSourceType --no-interaction
```

If `make:enum` is unavailable, create enum files manually with `apply_patch`.

Torrent migration fields:

```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('source_type');
$table->longText('magnet_uri')->nullable();
$table->string('torrent_file_path')->nullable();
$table->string('info_hash')->nullable()->index();
$table->string('name')->nullable();
$table->string('status')->index();
$table->unsignedTinyInteger('progress')->default(0);
$table->unsignedBigInteger('total_size_bytes')->nullable();
$table->unsignedBigInteger('downloaded_bytes')->default(0);
$table->string('qbittorrent_hash')->nullable()->index();
$table->text('error_message')->nullable();
$table->timestamp('started_at')->nullable();
$table->timestamp('completed_at')->nullable();
$table->timestamps();
$table->index(['user_id', 'status']);
```

Torrent file migration fields:

```php
$table->id();
$table->foreignId('torrent_id')->constrained()->cascadeOnDelete();
$table->string('path');
$table->unsignedBigInteger('size_bytes')->default(0);
$table->boolean('selected')->default(true);
$table->unsignedTinyInteger('progress')->default(0);
$table->timestamps();
$table->index(['torrent_id', 'path']);
```

- [ ] **Step 5: Add enum values**

`TorrentStatus` values:

```php
case PendingMetadata = 'pending_metadata';
case Rejected = 'rejected';
case Queued = 'queued';
case Downloading = 'downloading';
case Importing = 'importing';
case Completed = 'completed';
case MetadataFailed = 'metadata_failed';
case QuotaExceeded = 'quota_exceeded';
case DownloadFailed = 'download_failed';
case ImportFailed = 'import_failed';
case Cancelled = 'cancelled';
```

`TorrentSourceType` values:

```php
case Magnet = 'magnet';
case TorrentFile = 'torrent_file';
```

- [ ] **Step 6: Add relationships and scopes**

`User`:

```php
public function torrents(): HasMany;
public function storedFiles(): HasMany;
```

`Torrent`:

```php
public function user(): BelongsTo;
public function files(): HasMany;
public function scopeActive(Builder $query): Builder;
public function isActive(): bool;
```

- [ ] **Step 7: Run tests and format**

```bash
php artisan test --compact tests/Feature/TorrentSubmissionTest.php
vendor/bin/pint --dirty --format agent
```

### Task 4: Add torrent submission routes and request validation

**Files:**
- Create: `app/Http/Controllers/TorrentController.php`
- Create: `app/Http/Requests/StoreTorrentRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TorrentSubmissionTest.php`

- [ ] **Step 1: Search docs**

Run Boost `search-docs` with queries: `form request validation file upload`, `rate limiting routes`, `dispatch job after response`.

- [ ] **Step 2: Extend failing tests**

Add assertions that:

- invalid magnet is rejected;
- `.torrent` upload accepts only torrent files within a small upload limit;
- successful submit creates a `Torrent` with `pending_metadata`;
- `InspectTorrentMetadata` is dispatched.

- [ ] **Step 3: Create request/controller**

Run:

```bash
php artisan make:request StoreTorrentRequest --no-interaction
php artisan make:controller TorrentController --no-interaction
```

Request validation:

```php
'magnet_uri' => ['nullable', 'required_without:torrent_file', 'string', 'starts_with:magnet:?'],
'torrent_file' => ['nullable', 'required_without:magnet_uri', 'file', 'max:5120'],
```

Controller methods:

```php
public function store(StoreTorrentRequest $request): RedirectResponse;
public function destroy(Torrent $torrent): RedirectResponse;
```

Route group:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
    Route::post('torrents', [TorrentController::class, 'store'])->middleware('throttle:6,1')->name('torrents.store');
    Route::delete('torrents/{torrent}', [TorrentController::class, 'destroy'])->name('torrents.destroy');
});
```

Use policies before allowing cancel/delete.

- [ ] **Step 4: Run focused tests**

```bash
php artisan test --compact tests/Feature/TorrentSubmissionTest.php
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Regenerate Wayfinder routes**

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

- [ ] **Step 6: Commit if repository is available**

```bash
git add app/Enums app/Models app/Http/Controllers/TorrentController.php app/Http/Requests/StoreTorrentRequest.php database routes/web.php resources/js/actions resources/js/routes tests/Feature/TorrentSubmissionTest.php
git commit -m "feat: add torrent submission domain"
```

---

## Chunk 3: qBittorrent Integration And Workers

### Task 5: Add qBittorrent client service

**Files:**
- Create: `config/torrents.php`
- Create: `app/Services/Torrents/QBittorrentClient.php`
- Test: `tests/Unit/QBittorrentClientTest.php`
- Modify: `.env.example`

- [ ] **Step 1: Search docs**

Run Boost `search-docs` with queries: `http client timeout retry fake`, `config env`.

- [ ] **Step 2: Write failing HTTP client tests**

Run:

```bash
php artisan make:test --pest --unit QBittorrentClientTest --no-interaction
```

Test with `Http::fake()` and `Http::preventStrayRequests()`:

- login posts credentials;
- add magnet posts to `/api/v2/torrents/add`;
- torrent properties/list calls include hash;
- delete removes torrent and files when requested.

- [ ] **Step 3: Create config**

`config/torrents.php`:

```php
return [
    'default_user_quota_bytes' => (int) env('TORRENTS_DEFAULT_USER_QUOTA_BYTES', 734003200),
    'global_active_limit' => (int) env('TORRENTS_GLOBAL_ACTIVE_LIMIT', 5),
    'qbittorrent' => [
        'base_url' => env('QBITTORRENT_BASE_URL', 'http://qbittorrent:8080'),
        'username' => env('QBITTORRENT_USERNAME'),
        'password' => env('QBITTORRENT_PASSWORD'),
        'timeout' => (int) env('QBITTORRENT_TIMEOUT', 10),
    ],
];
```

- [ ] **Step 4: Implement client**

Methods:

```php
public function addMagnet(Torrent $torrent): void;
public function addTorrentFile(Torrent $torrent): void;
public function getTorrent(string $hash): array;
public function files(string $hash): array;
public function delete(string $hash, bool $deleteFiles = true): void;
```

Every HTTP call must use explicit `timeout()`, `connectTimeout()`, and `throw()`.

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact tests/Unit/QBittorrentClientTest.php
vendor/bin/pint --dirty --format agent
```

### Task 6: Add metadata inspection and quota rejection flow

**Files:**
- Create: `app/Services/Torrents/TorrentMetadata.php`
- Create: `app/Services/Torrents/TorrentMetadataInspector.php`
- Create: `app/Services/Torrents/QBittorrentMetadataInspector.php`
- Create: `app/Jobs/InspectTorrentMetadata.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/TorrentMetadataInspectionTest.php`

- [ ] **Step 1: Write failing tests**

Run:

```bash
php artisan make:test --pest TorrentMetadataInspectionTest --no-interaction
```

Use a fake inspector bound into the container. Test:

- metadata updates torrent name, hash, total size, and files;
- oversized torrent becomes `quota_exceeded`;
- inspector exception becomes `metadata_failed`.

- [ ] **Step 2: Implement value object and interface**

`TorrentMetadata` should contain:

```php
public function __construct(
    public string $name,
    public string $infoHash,
    public int $totalSizeBytes,
    public array $files,
) {}
```

Document file array shape:

```php
/** @var array<int, array{path: string, size_bytes: int}> */
```

- [ ] **Step 3: Implement job**

`InspectTorrentMetadata`:

- loads the torrent;
- calls `TorrentMetadataInspector`;
- writes `torrent_files`;
- checks `StorageQuota::canStore`;
- marks `quota_exceeded` or `queued`;
- dispatches `StartTorrentDownload` only after queueing.

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact tests/Feature/TorrentMetadataInspectionTest.php
vendor/bin/pint --dirty --format agent
```

### Task 7: Add download start, polling, and import job skeletons

**Files:**
- Create: `app/Jobs/StartTorrentDownload.php`
- Create: `app/Jobs/PollTorrentProgress.php`
- Create: `app/Jobs/ImportCompletedTorrent.php`
- Test: `tests/Feature/TorrentWorkerStateTest.php`

- [ ] **Step 1: Write failing worker state tests**

Run:

```bash
php artisan make:test --pest TorrentWorkerStateTest --no-interaction
```

Test:

- queued torrent becomes downloading when qBittorrent accepts it;
- qBittorrent failure becomes `download_failed`;
- completed qBittorrent torrent becomes `importing` and dispatches import;
- import failure becomes `import_failed`.

- [ ] **Step 2: Implement jobs with idempotency checks**

Each job should:

- re-read the current torrent from the database;
- no-op when status is no longer applicable;
- set clear failure statuses in `failed()` or catch blocks;
- use `afterCommit()` dispatching where database transactions are involved.

- [ ] **Step 3: Run tests and format**

```bash
php artisan test --compact tests/Feature/TorrentWorkerStateTest.php
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Commit if repository is available**

```bash
git add app/Jobs app/Services config/torrents.php .env.example tests/Unit/QBittorrentClientTest.php tests/Feature/TorrentMetadataInspectionTest.php tests/Feature/TorrentWorkerStateTest.php
git commit -m "feat: add torrent worker pipeline"
```

---

## Chunk 4: RustFS/S3 Import And Library Access

### Task 8: Add stored files and import behavior

**Files:**
- Create: `app/Models/StoredFile.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_stored_files_table.php`
- Create: `database/factories/StoredFileFactory.php`
- Modify: `app/Jobs/ImportCompletedTorrent.php`
- Modify: `config/filesystems.php`
- Modify: `.env.example`
- Test: `tests/Feature/ImportCompletedTorrentTest.php`

- [ ] **Step 1: Confirm S3 adapter dependency**

Check whether `league/flysystem-aws-s3-v3` is installed:

```bash
composer show league/flysystem-aws-s3-v3
```

If missing, ask for explicit approval before:

```bash
composer require league/flysystem-aws-s3-v3
```

- [ ] **Step 2: Search docs**

Run Boost `search-docs` with queries: `filesystem s3 temporary urls`, `storage fake`, `streamed response storage`.

- [ ] **Step 3: Write failing import test**

Run:

```bash
php artisan make:test --pest ImportCompletedTorrentTest --no-interaction
```

Use `Storage::fake('s3')` or a configured fake disk. Test:

- completed files are written under `users/{user_id}/torrents/{torrent_id}/...`;
- `stored_files` rows are created;
- positive `storage_usage_events` rows are created;
- qBittorrent delete is called after import success only.

- [ ] **Step 4: Create model/migration/factory**

Run:

```bash
php artisan make:model StoredFile -mf --no-interaction
```

Migration fields:

```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('torrent_id')->nullable()->constrained()->nullOnDelete();
$table->string('s3_disk')->default('s3');
$table->string('s3_bucket');
$table->string('s3_key')->unique();
$table->string('original_path');
$table->string('name');
$table->string('mime_type')->nullable();
$table->unsignedBigInteger('size_bytes');
$table->timestamps();
$table->index(['user_id', 'created_at']);
```

- [ ] **Step 5: Configure RustFS disk**

Use Laravel S3-compatible config in `config/filesystems.php`. `.env.example` should include:

```dotenv
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=seedr
AWS_ENDPOINT=http://rustfs:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
```

- [ ] **Step 6: Implement import transaction**

The import must:

- upload files first to S3 keys;
- create `stored_files`;
- create usage events;
- mark torrent `completed`;
- delete qBittorrent torrent only after successful upload and DB commit.

- [ ] **Step 7: Run tests and format**

```bash
php artisan test --compact tests/Feature/ImportCompletedTorrentTest.php
vendor/bin/pint --dirty --format agent
```

### Task 9: Add library, signed downloads, and basic playback

**Files:**
- Create: `app/Http/Controllers/LibraryController.php`
- Create: `app/Http/Controllers/StoredFileAccessController.php`
- Create: `app/Policies/StoredFilePolicy.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/Library/Index.svelte`
- Create: `resources/js/components/files/FileRow.svelte`
- Test: `tests/Feature/LibraryAccessTest.php`

- [ ] **Step 1: Write failing access tests**

Run:

```bash
php artisan make:test --pest LibraryAccessTest --no-interaction
```

Test:

- user sees only their stored files;
- user cannot download another user's file;
- signed route is required;
- stream route returns successful response for owned file.

- [ ] **Step 2: Create controllers and policy**

Run:

```bash
php artisan make:controller LibraryController --no-interaction
php artisan make:controller StoredFileAccessController --no-interaction
php artisan make:policy StoredFilePolicy --model=StoredFile --no-interaction
```

Routes:

```php
Route::get('library', [LibraryController::class, 'index'])->name('library.index');
Route::get('files/{storedFile}/download', [StoredFileAccessController::class, 'download'])->middleware('signed')->name('files.download');
Route::get('files/{storedFile}/stream', [StoredFileAccessController::class, 'stream'])->middleware('signed')->name('files.stream');
```

- [ ] **Step 3: Implement Svelte page**

Use Svelte 5 runes and Wayfinder route imports. Include:

- file name;
- size;
- original torrent/path;
- download button;
- play button only for browser-compatible media MIME types.

- [ ] **Step 4: Regenerate Wayfinder and run frontend checks**

```bash
php artisan wayfinder:generate --with-form --no-interaction
npm run types:check
```

- [ ] **Step 5: Run tests and format**

```bash
php artisan test --compact tests/Feature/LibraryAccessTest.php
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit if repository is available**

```bash
git add app/Models/StoredFile.php app/Http/Controllers/LibraryController.php app/Http/Controllers/StoredFileAccessController.php app/Policies/StoredFilePolicy.php config/filesystems.php database resources/js/pages/Library resources/js/components/files routes/web.php tests/Feature/ImportCompletedTorrentTest.php tests/Feature/LibraryAccessTest.php
git commit -m "feat: import and serve stored files"
```

---

## Chunk 5: Dashboard And Admin UI

### Task 10: Replace placeholder dashboard with torrent dashboard

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/pages/Dashboard.svelte`
- Create: `resources/js/components/torrents/TorrentProgress.svelte`
- Create: `resources/js/components/torrents/TorrentSubmitForm.svelte`
- Test: `tests/Feature/DashboardTest.php`

- [ ] **Step 1: Search docs**

Run Boost `search-docs` with queries: `inertia render props`, `inertia svelte form component`, `deferred props`.

- [ ] **Step 2: Write/adjust feature tests**

Update `DashboardTest.php` to assert dashboard props include:

- quota used;
- quota limit;
- remaining bytes;
- active torrent;
- recent torrents;
- recent stored files.

- [ ] **Step 3: Create controller**

Run:

```bash
php artisan make:controller DashboardController --no-interaction
```

Return `Inertia::render('Dashboard', [...])` and avoid N+1 queries with scoped eager loading/counts.

- [ ] **Step 4: Update Svelte page**

Follow existing `Dashboard.svelte` layout module pattern. Use:

- quota summary cards;
- active torrent progress component;
- torrent submit form;
- recent files and recent history tables.

Use Wayfinder form helpers instead of hardcoded action URLs.

- [ ] **Step 5: Run checks**

```bash
php artisan test --compact tests/Feature/DashboardTest.php
npm run types:check
vendor/bin/pint --dirty --format agent
```

### Task 11: Add basic admin controls

**Files:**
- Create: `app/Http/Middleware/EnsureUserIsAdmin.php`
- Create: `app/Http/Controllers/Admin/UserController.php`
- Create: `app/Http/Controllers/Admin/TorrentController.php`
- Create: `app/Http/Requests/Admin/UpdateUserQuotaRequest.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/Admin/Users/Index.svelte`
- Create: `resources/js/pages/Admin/Torrents/Index.svelte`
- Test: `tests/Feature/Admin/AdminAccessTest.php`
- Test: `tests/Feature/Admin/UserQuotaManagementTest.php`

- [ ] **Step 1: Write failing admin tests**

Run:

```bash
php artisan make:test --pest Admin/AdminAccessTest --no-interaction
php artisan make:test --pest Admin/UserQuotaManagementTest --no-interaction
```

Test:

- non-admin users are forbidden from admin routes;
- admins can view users;
- admins can update a user quota;
- admins can view torrent states;
- admins can cancel a stuck/active torrent.

- [ ] **Step 2: Create middleware/controllers/request**

Run:

```bash
php artisan make:middleware EnsureUserIsAdmin --no-interaction
php artisan make:controller Admin/UserController --no-interaction
php artisan make:controller Admin/TorrentController --no-interaction
php artisan make:request Admin/UpdateUserQuotaRequest --no-interaction
```

Quota request should accept MB input and convert to bytes in the controller, or accept bytes and validate min/max explicitly.

- [ ] **Step 3: Add admin route group**

Use `auth`, `verified`, and admin middleware. Example:

```php
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/quota', [AdminUserController::class, 'updateQuota'])->name('users.quota.update');
    Route::get('torrents', [AdminTorrentController::class, 'index'])->name('torrents.index');
    Route::delete('torrents/{torrent}', [AdminTorrentController::class, 'destroy'])->name('torrents.destroy');
});
```

- [ ] **Step 4: Add admin pages**

Use simple operational tables, not marketing layouts:

- users table: name, email, verified, quota, used, actions;
- torrents table: user, name, status, progress, size, failure, actions.

- [ ] **Step 5: Update nav**

Find the nav item source in `resources/js/layouts/app/AppSidebarLayout.svelte` or related component and add:

- `Dashboard`;
- `Library`;
- `Admin Users` and `Admin Torrents` only when `auth.user.is_admin` is true.

- [ ] **Step 6: Regenerate Wayfinder and run checks**

```bash
php artisan wayfinder:generate --with-form --no-interaction
php artisan test --compact tests/Feature/Admin/AdminAccessTest.php tests/Feature/Admin/UserQuotaManagementTest.php
npm run types:check
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit if repository is available**

```bash
git add app/Http/Middleware/EnsureUserIsAdmin.php app/Http/Controllers/Admin app/Http/Requests/Admin bootstrap/app.php routes/web.php resources/js/pages/Admin resources/js/components/torrents resources/js/pages/Dashboard.svelte tests/Feature/Admin tests/Feature/DashboardTest.php
git commit -m "feat: add torrent dashboard and admin controls"
```

---

## Chunk 6: Docker And Deployment

### Task 12: Add local/prod-like Docker stack

**Files:**
- Create: `docker-compose.yml`
- Create: `docker/php/Dockerfile`
- Create: `docker/php/entrypoint.sh`
- Create: `docker/caddy/Caddyfile` or chosen proxy config
- Modify: `.env.example`

- [ ] **Step 1: Choose reverse proxy**

Recommended: Caddy for v1 because it gives simple HTTPS automation and concise config.

Confirm the choice before writing files. If user chooses Nginx or Traefik, adjust this task.

- [ ] **Step 2: Write Docker Compose**

Services:

- `app`;
- `queue`;
- `scheduler`;
- `postgres`;
- `redis`;
- `qbittorrent`;
- `rustfs`;
- `reverse-proxy`.

Do not publish qBittorrent, RustFS, Postgres, or Redis to the public interface in production.

- [ ] **Step 3: Add health checks and volumes**

Required named volumes:

- `postgres-data`;
- `redis-data` if Redis persistence is enabled;
- `qbittorrent-config`;
- `qbittorrent-downloads`;
- `rustfs-data`;
- `laravel-storage`.

- [ ] **Step 4: Add environment examples**

`.env.example` should include:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=seedr
DB_USERNAME=seedr
DB_PASSWORD=

QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=redis

TORRENTS_DEFAULT_USER_QUOTA_BYTES=734003200
TORRENTS_GLOBAL_ACTIVE_LIMIT=5
QBITTORRENT_BASE_URL=http://qbittorrent:8080
QBITTORRENT_USERNAME=
QBITTORRENT_PASSWORD=

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=seedr
AWS_ENDPOINT=http://rustfs:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
```

- [ ] **Step 5: Verify compose config**

Run:

```bash
docker compose config
```

Expected: valid merged config.

- [ ] **Step 6: Start stack locally**

Run:

```bash
docker compose up -d --build
docker compose ps
```

Expected: all services healthy/running.

- [ ] **Step 7: Run migrations/tests in container**

Run:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan test --compact
```

Expected: tests pass.

### Task 13: Final verification pass

**Files:**
- All modified application, test, and Docker files.

- [ ] **Step 1: Run backend tests**

```bash
php artisan test --compact
```

Expected: PASS.

- [ ] **Step 2: Run PHP formatter**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: no remaining dirty formatting changes.

- [ ] **Step 3: Run frontend checks**

```bash
npm run types:check
npm run format:check
npm run lint:check
```

Expected: PASS.

- [ ] **Step 4: Build frontend**

```bash
npm run build
```

Expected: Vite build completes.

- [ ] **Step 5: Run app locally**

```bash
composer run dev
```

Open the Boost-resolved local URL and verify:

- registration;
- email verification notice;
- dashboard;
- magnet submission validation;
- quota display;
- library page;
- admin routes.

- [ ] **Step 6: Check browser logs**

Use Laravel Boost `browser-logs` and fix recent JavaScript or HTTP errors.

- [ ] **Step 7: Commit if repository is available**

```bash
git add .
git commit -m "feat: build seedr clone v1"
```

---

## Execution Notes

- Start with tests and keep each task small.
- Prefer `php artisan make:* --no-interaction` for Laravel files.
- Use named routes and Wayfinder-generated helpers in Svelte.
- Keep controller methods thin; move external behavior into services/jobs.
- Do not hardcode public URLs to qBittorrent or RustFS.
- Keep RustFS/S3 object keys private and user-scoped.
- Do not delete qBittorrent local files until S3 upload and database records are confirmed.
- Do not expose media/files directly from RustFS in v1; Laravel signed routes stay in front.
- Run `vendor/bin/pint --dirty --format agent` after PHP edits.
- Run the minimum focused tests during each task, then full checks at the end.
