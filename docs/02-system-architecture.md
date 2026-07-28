# Phase 2 — System Architecture

## 1. High-Level Architecture

```mermaid
flowchart LR
    subgraph Client
        PWA["Vue 3 PWA\n(TypeScript, Vite, Tailwind)"]
        IDB[(IndexedDB\noffline cache/queue)]
        SW[Service Worker]
        PWA --- IDB
        PWA --- SW
    end

    subgraph Backend["Laravel 12 API (backend/)"]
        API[REST API v1]
        Auth[Sanctum\ncookie-based SPA auth]
        AIIface["AiProviderInterface"]
        QuranIface["QuranContentRepositoryInterface"]
        Queue[Queue / Horizon]
        API --- Auth
        API --> AIIface
        API --> QuranIface
        API --> Queue
    end

    subgraph AIProviders["AI Providers (pluggable)"]
        Claude[Claude — real]
        OpenAI[OpenAI — stub]
        Gemini[Gemini — stub]
        Azure[Azure OpenAI — stub]
        Ollama[Ollama — stub]
    end

    subgraph QuranSources["Quran Content Sources"]
        Tanzil["Tanzil corpus\n(local DB, imported offline)"]
        AlQuranCloud["Al Quran Cloud API\n(translation / word-by-word / audio)"]
    end

    PWA <-- "HTTPS / JSON, cookie session" --> API
    AIIface --> Claude
    AIIface -.-> OpenAI
    AIIface -.-> Gemini
    AIIface -.-> Azure
    AIIface -.-> Ollama
    QuranIface --> Tanzil
    QuranIface --> AlQuranCloud

    API --- DB[(MariaDB)]
    API --- Redis[(Redis\ncache / sessions / queue)]
```

## 2. Component Breakdown

- **`backend/`** — Laravel 12 API-only application. Owns all business logic,
  the AI/Quran abstraction layers, and persistence.
- **`frontend/`** — Vue 3 + TypeScript SPA/PWA. Talks to the backend only via
  the versioned REST API; never accesses the database or AI providers
  directly.
- **`docker/`** — nginx config for the dockerised backend.

## 3. Tech Stack & Rationale

| Layer | Choice | Why |
|---|---|---|
| Backend framework | Laravel 12 / PHP 8.4+ | Mature ecosystem (Sanctum, Horizon, queues, scheduler) for a content- and job-heavy domain |
| DB | MariaDB (SQLite for dependency-free local dev) | Relational integrity for the memorisation/review graph; wide hosting support |
| Cache/queue/sessions | Redis | Required by Horizon; also backs cookie-session storage and content caching |
| Frontend framework | Vue 3 + TypeScript + Vite | Fast dev loop, strong typing, first-class PWA plugin ecosystem |
| Styling | Tailwind CSS v4 | Utility-first, easy dark/light and accessibility theming |
| Offline storage | IndexedDB (`idb`) | Structured offline queueing beyond what a Cache Storage-only SW can do |
| PWA tooling | `vite-plugin-pwa` (Workbox) | Standard, well-supported service worker + manifest generation |

## 4. Repository Layout Decision

Separate top-level `backend/` (Laravel 12 API) and `frontend/` (Vue 3 PWA)
rather than a Laravel-served monolith. The offline/installable PWA
requirement (service worker scope, asset precaching, independent deploy/CDN
hosting) fits a decoupled SPA better, and it keeps a future native/Capacitor
build and the AI/Quran abstraction layers cleanly backend-only.

**Trade-off accepted**: decoupled origins require explicit CORS +
Sanctum stateful-domain configuration, instead of "free" same-origin cookie
auth in a monolith. See §6.

## 5. AI Provider Abstraction

`App\Contracts\AI\AiProviderInterface` defines `evaluateRecitation()`,
`generateFeedback()`, `getProviderName()`, `isAvailable()`.
`App\Services\AI\AiProviderManager` (Laravel's driver-manager pattern — the
same one powering cache/queue/mail) resolves the configured driver from
`config('ai.default')` / `AI_PROVIDER` env var. Adding a new provider means
adding one `create*Driver()` method and a config block — zero changes to
consuming code.

`evaluateRecitation()` is invoked end-to-end from the PWA: the browser's Web
Speech API transcribes a spoken recitation to text client-side, the text is
posted to `POST /api/v1/surahs/{s}/ayat/{a}/evaluate-recitation`
(`App\Http\Controllers\Api\V1\AiEvaluationController`, throttled at
20/min since each call is a billed request), and the controller returns the
parsed `RecitationEvaluationResult` for the frontend to display before the
learner confirms the attempt. If the provider is unavailable or the call
fails, the endpoint returns `503` and the UI falls back to manual
self-assessment rather than blocking the learner.

Shipped in this scaffold:

| Provider | Status |
|---|---|
| Claude | Real — calls the Anthropic Messages API |
| OpenAI | Stub — interface satisfied, throws `AiProviderNotImplementedException` |
| Gemini | Stub |
| Azure OpenAI | Stub |
| Ollama | Stub — intended for offline/local-LLM dev use |

## 6. Quran Content Abstraction

`App\Contracts\Quran\QuranContentRepositoryInterface` isolates all Quran
content access (surah/ayah metadata, Arabic text, translation, word-by-word,
audio URL) behind a contract, so the underlying source can be replaced
without touching consuming code if licensing/attribution requirements
change.

Shipped implementation — `TanzilAlQuranCloudRepository`:

- **Arabic Uthmani text** + structural metadata (surah/juz/hizb-quarter/page/
  ruku/sajda) — read from the local `surahs`/`ayat` tables, seeded via
  `php artisan quran:import-tanzil`, which fetches the Uthmani edition Al
  Quran Cloud mirrors from Tanzil (`GET /v1/quran/quran-uthmani`, one bulk
  request for all 6,236 ayat) and upserts it. An offline path from a
  manually downloaded Tanzil corpus is a documented but unimplemented
  alternative (`TANZIL_CORPUS_PATH`).
- **Translation, audio** — real HTTP calls to the Al Quran Cloud API
  (`api.alquran.cloud`), cached in Redis (~7 days TTL).
- **Word-by-word** — Al Quran Cloud has no official word-by-word endpoint;
  this reads from the local `ayah_words` table instead, which this scaffold
  leaves unseeded. Populate it from a licensed word-by-word corpus before
  relying on it.

## 7. Auth Strategy

Cookie-based Sanctum SPA authentication (`SANCTUM_STATEFUL_DOMAINS`,
`SESSION_DOMAIN`, CORS `supports_credentials: true`) rather than bearer
tokens in `localStorage`. Chosen because frontend and backend are first-party
here, and cookie auth avoids XSS token-theft exposure that `localStorage`
tokens carry.

**Trade-off accepted**: this couples auth to the configured stateful
domain(s). A future separate-domain production split or a native mobile
client may be better served by Sanctum's bearer-token mode instead — that's
a config change (`config/sanctum.php`), not an architectural one.

Flow: client `GET /sanctum/csrf-cookie` → client reads `XSRF-TOKEN` cookie →
subsequent mutating requests send `X-XSRF-TOKEN` header → Laravel validates
CSRF + session cookie via `EnsureFrontendRequestsAreStateful` middleware
(`bootstrap/app.php`'s `$middleware->statefulApi()`).

## 8. Offline / PWA Architecture

- **Service worker** (Workbox, via `vite-plugin-pwa`) precaches the app
  shell so the PWA installs and boots offline.
- **IndexedDB** (`frontend/src/lib/db.ts`) holds structured offline state:
  cached ayat and a `pendingReviewLogs` queue for recitation attempts made
  offline. Both stores are scaffolded but unused until offline sync
  (a future phase) writes to them.
- **Sync/conflict resolution** for the pending-logs queue is explicitly not
  implemented in this scaffold.

## 9. Dev Environment (Docker Topology)

See `docker-compose.yml`. Services: `app` (PHP 8.4-FPM), `nginx` (serves
`backend/public`, port 8000), `mariadb`, `redis`, `horizon` (queue worker),
`frontend` (Vite dev server, port 5173). All on one bridge network; the
frontend's Vite dev proxy forwards `/api` and `/sanctum` to `nginx:80` in
dev, keeping the browser same-origin against `:5173` and sidestepping CORS
during local development (CORS config is still required for any non-proxied
deployment).

## 10. Production Deployment (Forge)

Live at **https://hafazan.rcaquacycle.com**, auto-deployed from GitHub
(`sabarm67/hafazan`, `main` branch) via [Laravel Forge](https://forge.laravel.com).

Unlike local dev (decoupled origins, §9), production serves the frontend and
API from **one Forge site, one origin** — the simplest topology given a
single domain, and it eliminates CORS/cross-domain Sanctum concerns
entirely in production.

**How it works**: `frontend/vite.config.ts` sets `build.outDir` to
`../backend/public` (with `emptyOutDir: false` so it doesn't wipe Laravel's
own `index.php`/`.htaccess`/`favicon.ico`/`robots.txt`). The Forge deploy
script (`scripts/forge-deploy.sh`) runs `composer install`, Laravel's
migrate/cache commands, then `npm ci && npm run build` for the frontend, in
that order. `routes/web.php` registers a `Route::fallback()` that serves
the built `public/index.html` for any request `/api/*` and `/sanctum/*`
don't already claim, so Vue Router's client-side routing works on refresh
and deep links.

**Forge site configuration** (one-time setup, since the repo nests Laravel
under `backend/` rather than at the root — this trips up several of Forge's
defaults, which assume `composer.json` and Laravel live at the repository
root). This site uses **Zero-Downtime Deployment**:

- **Repository**: `sabarm67/hafazan`, branch `main`.
- **Web Directory**: `/backend/public` (not the default `/public`).
- **Deployment Script**: paste `scripts/forge-deploy.sh`'s contents into
  the Zero-Downtime Deployment script field. ZDD already clones a fresh
  copy into `releases/<id>` and `cd`s there before the script runs, so the
  script does *not* `cd` to the site root or `git pull` itself.
- **Install Composer Dependencies / Install NPM Dependencies & Build
  Assets** — **uncheck both** in the ZDD settings. These are Forge's own
  automatic steps and they always look for `composer.json`/`package.json`
  at the release root; ours are in `backend/` and `frontend/`. Left
  checked, they fail before the custom script even runs (that's the
  `composer.json file in .../releases/000000` error this section exists to
  head off). `scripts/forge-deploy.sh` installs both itself with the
  correct paths.
- **Shared/persistent files** — set these to `backend/.env` and
  `backend/storage` (not the defaults of `.env` / `storage`), so the env
  file and storage directory persist across releases instead of each fresh
  release getting an empty one.
- **Scheduler** (if used): Forge's default cron runs
  `php artisan schedule:run` from the site root — edit it to
  `cd /home/forge/hafazan.rcaquacycle.com/current/backend && php artisan schedule:run`
  (`current` is ZDD's symlink to the active release).
- **Queue worker / Horizon**: point the daemon/queue command at the nested
  artisan under the same `current` symlink, e.g.
  `php /home/forge/hafazan.rcaquacycle.com/current/backend/artisan queue:work`
  (Horizon requires Redis installed on the server — Forge can provision it).
- **Node**: Forge's server needs Node available (NVM) for the frontend
  build step in the deploy script.

**Environment** (`backend/.env`, managed in Forge's environment editor, not
committed):

| Key | Production value |
|---|---|
| `APP_URL` | `https://hafazan.rcaquacycle.com` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `FRONTEND_URL` | `https://hafazan.rcaquacycle.com` |
| `SANCTUM_STATEFUL_DOMAINS` | `hafazan.rcaquacycle.com` |
| `SESSION_DOMAIN` | `hafazan.rcaquacycle.com` |
| `DB_*` | From Forge's provisioned database |
| `ANTHROPIC_API_KEY` | Real key — without it, AI evaluation 503s and the PWA falls back to manual self-assessment (see §5) |

SSL is Forge's standard Let's Encrypt integration — not project-specific,
no repo changes needed.

## 11. Security Considerations

- RBAC via the `roles` many-to-many relationship; route middleware
  authorization is a future-phase addition once role-gated endpoints exist.
- Rate limiting: Laravel's default throttle middleware is available but not
  yet tuned per-endpoint — a Phase 11+ concern.
- Audit logging, encryption-at-rest for sensitive fields, and a full OWASP
  pass are future-phase work, not present in this scaffold.
