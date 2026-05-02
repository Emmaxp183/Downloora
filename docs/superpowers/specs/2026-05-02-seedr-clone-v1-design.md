# Seedr Clone V1 Design

Date: 2026-05-02

## Goal

Build a Seedr-style torrent-to-cloud application using the existing Laravel 13, Inertia Svelte 3, and Tailwind stack. Version 1 focuses on a safe multi-user MVP: users register, verify email, submit one torrent or magnet at a time, and access completed files from private RustFS/S3 storage.

## Non-Goals

- Direct URL downloads.
- Public file sharing.
- Paid plans or billing.
- Optional long-term seeding.
- Media transcoding.
- Subtitles, thumbnails, or resume-position media server features.
- Multi-server or Kubernetes deployment.

## Approved V1 Decisions

- Downloads are torrent and magnet only.
- The app supports multiple users with separate libraries and quotas.
- New users can self-register with email verification.
- Default user quota is 700MB.
- Each user may have 1 active torrent at a time.
- qBittorrent-nox runs in Docker and acts as the torrent engine.
- Completed torrent files are imported into RustFS/S3, then the torrent is stopped and removed from qBittorrent.
- Users can download files and play browser-compatible video/audio in the app without transcoding.
- V1 deploys as a single Docker stack on a Leaseweb 10Gbps VPS.

## Architecture

V1 runs on one Leaseweb server with Docker Compose:

- Laravel app: owns user-facing UI, auth, authorization, quota checks, torrent lifecycle state, and signed access links.
- Inertia Svelte frontend: dashboard, torrent submission, progress views, library, playback/download pages, and admin screens.
- Postgres: persistent relational data.
- Redis: queue backend and cache.
- Laravel queue workers: metadata inspection, torrent start, progress polling, completed-file import, cleanup, and retryable failure handling.
- qBittorrent-nox: private torrent engine controlled through its Web API.
- RustFS S3: private object storage for completed files.
- Reverse proxy: public HTTPS entrypoint for the Laravel app only.

Users never connect directly to qBittorrent or RustFS. Laravel is the access-control boundary.

## Product Flow

1. A visitor registers for an account.
2. Laravel requires email verification before torrent submission.
3. A verified user sees a dashboard with quota usage, active torrent status, recent completed files, and torrent history.
4. The user submits a magnet link or uploads a `.torrent` file.
5. Laravel validates that the user is verified and has no active torrent.
6. A worker inspects torrent metadata before download starts.
7. Laravel rejects the torrent if its expected size exceeds the user's remaining quota.
8. Laravel starts the accepted torrent in qBittorrent.
9. Workers poll qBittorrent and update torrent progress.
10. When the torrent completes, a worker imports files into RustFS under the user's private storage prefix.
11. Laravel records stored file metadata and quota usage.
12. Laravel stops and removes the torrent from qBittorrent.
13. The user downloads files through signed Laravel routes or plays browser-compatible media in the app.

## Core Data Model

### users

Existing auth users gain quota-related fields, including:

- `storage_quota_bytes`
- `email_verified_at`

### torrents

One submitted torrent job per user.

Important fields:

- `user_id`
- `source_type`: `magnet` or `torrent_file`
- `magnet_uri`
- `torrent_file_path`
- `info_hash`
- `name`
- `status`
- `progress`
- `total_size_bytes`
- `downloaded_bytes`
- `error_message`
- qBittorrent identifiers needed for polling and cleanup.

Primary status flow:

`pending_metadata -> rejected | queued -> downloading -> importing -> completed`

Failure statuses:

`metadata_failed`, `quota_exceeded`, `download_failed`, `import_failed`, `cancelled`

### torrent_files

Files discovered inside a torrent before and after completion.

Important fields:

- `torrent_id`
- `path`
- `size_bytes`
- `selected`
- `progress`

### stored_files

Final imported files in RustFS.

Important fields:

- `user_id`
- `torrent_id`
- `s3_disk`
- `s3_bucket`
- `s3_key`
- `original_path`
- `name`
- `mime_type`
- `size_bytes`

### storage_usage_events

Append-only usage ledger for quota accounting.

Important fields:

- `user_id`
- `stored_file_id`
- `delta_bytes`
- `reason`
- `metadata`

Quota calculations should be auditable from usage events instead of depending only on a mutable counter.

## Safety And Limits

- New verified users receive a 700MB quota.
- Each user is limited to 1 active torrent.
- The server has a configurable global active torrent limit.
- Torrent size is checked before download starts.
- User files are stored under private per-user S3 prefixes.
- qBittorrent and RustFS are not exposed publicly.
- Downloads and media playback use signed Laravel routes.
- Registration, login, torrent submission, and download link generation are rate-limited.
- V1 includes a basic admin screen for users, quotas, torrent states, and failed jobs.
- Opening registration to strangers requires terms, abuse handling, and provider-safe operating limits.

## Error Handling

- Metadata failures store a clear user-visible failure reason and do not start qBittorrent downloads.
- Quota failures mark the torrent as `quota_exceeded` and preserve enough metadata for the user to understand why it was rejected.
- Download failures mark the torrent as `download_failed` and attempt qBittorrent cleanup.
- Import failures mark the torrent as `import_failed`; cleanup should not delete local completed files until the import is verified.
- qBittorrent cleanup runs only after RustFS upload and database records are successfully committed.
- Retryable jobs must be idempotent so duplicate worker attempts do not double-count quota or create duplicate stored files.

## Admin Capabilities

V1 admin screens should allow an admin to:

- View users.
- View and update per-user quota.
- View torrent status and failure messages.
- View stored files per user.
- Cancel stuck or active torrents.
- See basic server-wide active torrent counts.

Admin-created downloads, billing, refunds, and detailed analytics are out of scope for v1.

## Deployment Shape

Docker Compose should run these services on the Leaseweb server:

- `app`
- `queue`
- `scheduler`
- `postgres`
- `redis`
- `qbittorrent`
- `rustfs`
- `reverse-proxy`

Persistent volumes are required for Postgres, Redis if configured for persistence, qBittorrent temporary downloads, RustFS objects, and Laravel storage.

Only the reverse proxy should be public. qBittorrent, Postgres, Redis, and RustFS should stay on the private Docker network.

## Testing Strategy

Every implementation slice should include programmatic tests.

Required v1 coverage:

- Registration and email verification.
- Default 700MB quota assignment.
- Admin quota updates.
- Verified users can submit torrent jobs.
- Unverified users cannot submit torrent jobs.
- Users with an active torrent cannot submit another.
- Oversized torrents are rejected after metadata inspection.
- qBittorrent service handles expected Web API responses.
- Worker state transitions are tested.
- Completed files upload to the S3 disk.
- Stored file records are created.
- Storage usage events are written.
- qBittorrent torrents are removed only after successful import.
- Users can list only their own files.
- Signed download and stream routes prevent cross-user access.

Use focused Pest feature tests and mocked qBittorrent/RustFS boundaries where possible. Use real S3-compatible integration testing only when the local Docker services are available.

## Implementation Order

1. Quota fields, policies, and admin quota controls.
2. Torrent submission model, validation, and dashboard states.
3. qBittorrent client service and mocked tests.
4. Metadata inspection and quota rejection flow.
5. Download start and progress polling workers.
6. RustFS/S3 import and stored file library.
7. Signed download and basic media playback routes.
8. Docker Compose services for Postgres, Redis, qBittorrent, RustFS, and reverse proxy.
9. Production deployment checklist for Leaseweb.

## Open Questions For The Implementation Plan

- Which reverse proxy should be used: Caddy, Traefik, or Nginx?
- Should admins be represented by a boolean field on `users` or by a roles/permissions package later?
- Should `.torrent` uploads be enabled in the first implementation slice or after magnet links work?
- Which RustFS bucket naming convention should be used for production?
- What global active torrent limit should be set for the first Leaseweb deployment?
