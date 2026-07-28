# Al-Quran Hafazan System

Adaptive Quran memorisation (Hifz) platform — Laravel 12 API backend + Vue 3
PWA frontend. See [docs/](docs) for the full specification: requirements,
architecture, database design, and API design.

Real and working: the two core abstraction layers (AI provider, Quran
content), the full Quran text/reference data, the Adaptive Hifz Engine
(`App\Services\SpacedRepetitionScheduler` — memory-strength scoring,
Sabak/Sabqi/Manzil transitions, interval scheduling) and its API, a working
end-to-end learning session in the PWA (Intention → Select → Listen →
Repeat → Recall → Reflect → Muraja'ah), and AI-assisted recitation
evaluation (browser speech-to-text → Claude comparison, with automatic
fallback to manual self-assessment). Teacher/parent portals, gamification,
push notifications, and analytics dashboards are still TODOs — see
`docs/01-requirements-analysis.md` for the phase breakdown and each stub
file's inline comments for exactly what's pending.

Live at **https://hafazan.rcaquacycle.com**, auto-deployed from `main` via
Laravel Forge — see `docs/02-system-architecture.md` §10 for the deploy
topology and `scripts/forge-deploy.sh` for the deploy script.

## Structure

```
backend/    Laravel 12 API (PHP 8.4+, Sanctum, Redis, Horizon)
frontend/   Vue 3 + TypeScript + Vite PWA (Tailwind, Pinia, IndexedDB)
docs/       Requirements, architecture, database, and API specs
docker/     nginx config for the dockerised backend
scripts/    Deployment scripts (Forge)
```

## Running locally

### With Docker (recommended)

```bash
cp backend/.env.example backend/.env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan quran:import-tanzil
```

- Backend: http://localhost:8000
- Frontend: http://localhost:5173

### Without Docker

Backend (PHP 8.2+, Composer):

```bash
cd backend
cp .env.example .env   # then switch DB_CONNECTION=sqlite if you don't have MariaDB running
php artisan key:generate
php artisan migrate --seed
php artisan quran:import-tanzil
php artisan serve
```

Frontend (Node 20+):

```bash
cd frontend
npm install
npm run dev
```

## Quran data

Surah metadata (names, ayah counts) is seeded via `SurahSeeder`. Ayah text is
imported by running:

```bash
php artisan quran:import-tanzil
```

This fetches the Uthmani edition Al Quran Cloud mirrors from Tanzil in one
bulk request and populates all 6,236 `ayat` rows. See
`docs/01-requirements-analysis.md` for the full data-source policy (Tanzil +
Al Quran Cloud API, licensing/attribution requirements).

## AI provider

The AI layer (`App\Contracts\AI\AiProviderInterface`) defaults to Claude and
requires `ANTHROPIC_API_KEY` in `backend/.env` to make real calls. Without a
key, `POST /api/v1/surahs/{s}/ayat/{a}/evaluate-recitation` returns `503`
and the PWA's Recall step falls back to manual self-assessment automatically
— no key is required to use the app, only to get AI-scored recitation
feedback. OpenAI, Gemini, Azure OpenAI, and Ollama adapters exist behind the
same interface but are stubs — see `docs/02-system-architecture.md`.

Recitation capture uses the browser's Web Speech API (client-side,
Chrome-family browsers have the best Arabic support) — no audio is uploaded
or stored; only the transcribed text is sent for evaluation.
