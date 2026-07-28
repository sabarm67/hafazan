# Phase 4 — API Design

## 1. Design Principles

- REST over JSON, versioned under `/api/v1`.
- Laravel API Resources (`App\Http\Resources\Api\V1\*`) shape every response
  — never serialize Eloquent models directly.
- Read endpoints are public where the data is non-sensitive reference data
  (surahs/ayat); everything user-scoped requires `auth:sanctum`.

## 2. Versioning Strategy

URI-based versioning (`/api/v1/...`) rather than header-based. Chosen for
discoverability (a URL alone tells you the contract version — useful for a
multi-client future: web PWA, native wrappers, teacher/parent dashboards) and
because it's simpler to route/cache/document than content-negotiation
versioning. A `v2` prefix can be introduced alongside `v1` without breaking
existing clients when a breaking change is eventually needed.

## 3. Auth Flow

Cookie-based Sanctum SPA auth (see `docs/02-system-architecture.md §7`):

1. `GET /sanctum/csrf-cookie` — sets the `XSRF-TOKEN` cookie.
2. Client reads that cookie and sends it back as the `X-XSRF-TOKEN` header on
   every mutating request (`frontend/src/lib/api.ts` does this
   automatically).
3. `POST /api/v1/auth/register` or `POST /api/v1/auth/login` — establishes a
   session (`Auth::login()` + `$request->session()->regenerate()`).
4. Subsequent requests are authenticated via the session cookie;
   `auth:sanctum` middleware resolves `$request->user()`.
5. `POST /api/v1/auth/logout` — invalidates the session.

## 4. Endpoint Catalogue

### Auth

| Method | Path | Auth | Status |
|---|---|---|---|
| POST | `/api/v1/auth/register` | — | ✅ Real |
| POST | `/api/v1/auth/login` | — | ✅ Real |
| POST | `/api/v1/auth/logout` | Sanctum | ✅ Real |
| GET | `/api/v1/auth/me` | Sanctum | ✅ Real |

### Quran reference data (read-only)

| Method | Path | Auth | Status |
|---|---|---|---|
| GET | `/api/v1/surahs` | — | ✅ Real |
| GET | `/api/v1/surahs/{number}` | — | ✅ Real |
| GET | `/api/v1/surahs/{surahNumber}/ayat` | — | ✅ Real |
| GET | `/api/v1/surahs/{surahNumber}/ayat/{ayahNumber}` | — | ✅ Real |
| GET | `/api/v1/surahs/{surahNumber}/ayat/{ayahNumber}/translation` | — | ✅ Real (live Al Quran Cloud call, cached) |

### Adaptive Hifz Engine surface

| Method | Path | Auth | Status |
|---|---|---|---|
| GET | `/api/v1/memorisation-records` | Sanctum | ✅ Real — `?due=1`, `?classification=` filters |
| POST | `/api/v1/memorisation-records` | Sanctum | ✅ Real — idempotent per (user, ayah) |
| GET | `/api/v1/memorisation-records/{id}` | Sanctum | ✅ Real |
| PUT/PATCH | `/api/v1/memorisation-records/{id}` | Sanctum | ✅ Real — `{"reset_for_review": true}` only |
| GET | `/api/v1/review-sessions` | Sanctum | ✅ Real |
| POST | `/api/v1/review-sessions` | Sanctum | ✅ Real |
| GET | `/api/v1/review-sessions/{id}` | Sanctum | ✅ Real |
| PUT/PATCH | `/api/v1/review-sessions/{id}` | Sanctum | ✅ Real — ends the session |
| GET | `/api/v1/review-sessions/{id}/logs` | Sanctum | ✅ Real |
| POST | `/api/v1/review-sessions/{id}/logs` | Sanctum | ✅ Real — runs `SpacedRepetitionScheduler::processAttempt()` |
| POST | `/api/v1/surahs/{s}/ayat/{a}/evaluate-recitation` | Sanctum | ✅ Real — calls Claude via `AiProviderInterface`; throttled 20/min; `503` if the provider is unavailable/fails |

### Not yet routed (future phases)

Teacher portal (assign/approve/monitor) — Phase 10. Parent portal
(progress/streaks/notifications) — Phase 10. Analytics/reports (dashboards,
heat maps, PDF/Excel export) — Phase 11+. Push notification subscriptions —
Phase 9.

## 5. Request/Response Conventions

- All responses are wrapped in a `data` key by Laravel API Resources
  (`{"data": {...}}` or `{"data": [...]}`for collections).
- Validation errors: `422` with Laravel's standard
  `{"message": "...", "errors": {"field": ["..."]}}` shape (`FormRequest`
  classes under `App\Http\Requests\Api\V1\`).
- Auth failures: `401` (unauthenticated) or `422` with a `email` field error
  for invalid login credentials (matches Laravel's default `Auth::attempt`
  failure convention).
- Not-found: `404` for missing resources (via `firstOrFail()` /
  `findOrFail()`).
- Unimplemented: `501` with `{"message": "Not implemented yet — see Phase N."}`
  for stub controller actions.
- Pagination: not yet applied (surah/ayah lists are small, fixed-size); will
  follow Laravel's default cursor/length-aware paginator shape when
  introduced for larger result sets (e.g. review logs).

## 6. Rate Limiting

Not yet configured per-endpoint. Laravel's default `api` throttle middleware
is available (`throttle:api`) but tuning limits per route (e.g. stricter on
`/auth/login` to slow brute-force attempts) is a Phase 11+ security-hardening
task.

## 7. Future Endpoints

Teacher assignment/approval endpoints, parent oversight endpoints,
analytics/report endpoints (with PDF/Excel export), push notification
subscription endpoints, and a GraphQL surface (optional, per the master
spec) are all out of scope for this scaffold session.
