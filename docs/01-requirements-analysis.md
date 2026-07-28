# Phase 1 — Requirements Analysis

## 1. Overview & Goals

Al-Quran Hafazan System is an adaptive, personalised Quran memorisation (Hifz)
platform. It goes beyond displaying Quran pages: it continuously measures
each learner's memorisation strength per ayah and generates a personalised
daily learning programme, combining traditional Hifz methodology (Sabak /
Sabqi / Manzil) with spaced repetition, active recall, and AI-assisted
feedback.

Primary goal for this scaffold session: establish the architectural
foundation (backend/frontend skeleton, core domain schema, AI and Quran
content abstraction layers) that all later phases build on. It does **not**
implement the learning algorithm itself.

## 2. Stakeholders & Roles

| Role | Description |
|---|---|
| Student | Primary learner — children, teenagers, adults, seniors, beginners through advanced huffaz |
| Teacher | Assigns memorisation, reviews recitations, approves progress, manages a class |
| Parent/Guardian | Monitors a student's (typically their child's) progress and consistency |
| Admin | Platform administration (not a learning role) |

A single account may hold multiple roles (e.g. a hafiz who also teaches).
Modelled as a many-to-many `roles` relationship, not a single column — see
`docs/03-database-design.md`.

## 3. Functional Requirements (by module)

**Learning workflow** — per-session flow: Intention (dua) → New Memorisation
(Sabak) → Listen (loop/slow/normal speed) → Repeat → Active Recall (Quran
hidden) → AI Evaluation (missing/extra/reordered/repeated words, pauses,
pronunciation confidence — assistive only, not a Tajwid ruling) → Muraja'ah
(priority-ordered revision) → Reflection (translation, notes, themes).

**Adaptive Hifz Engine** — per-ayah Memory Strength Score (0–100) driven by
recall count, mistake count, recall latency, confidence, days since last
review, revision frequency, difficulty history, confusion with similar ayat,
audio accuracy, teacher/AI assessment, and retention decay. Drives automatic
review interval scheduling (immediate → 1d → 3d → 7d → 14d → 30d → 60d → 90d
→ 180d → 365d): mistakes shorten the interval and lower the score; correct
recitation extends the interval and raises the score.

**Three-tier review system** — Sabak (new), Sabqi (recent, actively
reviewed), Manzil (long-term retained) — auto-managed, teacher-configurable,
AI-optimised.

**Learning modes** — Quick, Daily Hifz, Weekend, Ramadan, Revision Only,
Intensive Hifz, Teacher, Classroom, Family, Kids, Senior.

**AI Coach** — encouragement, weak-pattern detection, pacing suggestions,
burnout-risk estimation, milestone celebration. Never shames the learner.

**Content & reflection** — Arabic (Uthmani), Bahasa Malaysia translation,
word-by-word (where licensing permits), root words, themes, cross-references,
personal notes, bookmarks.

**Analytics** — ayat/juz memorised, streaks, weekly/monthly progress,
retention %, memory strength, mistake averages, weakest/strongest pages,
completion forecast, learning velocity, revision backlog, page heat maps
(green/yellow/orange/red).

**Teacher portal** — assign memorisation, monitor students, review
recordings, approve memorisation, homework, reports, optional rankings.

**Parent portal** — progress, streaks, reminders, recordings, consistency.

**Gamification** — streaks, achievements, milestones; deliberately modest,
not competitive-heavy.

**Notifications** — adaptive/motivational/revision reminders, optional
prayer-time-aware scheduling.

**Offline support** — offline memorisation, recordings, Quran text/audio,
deferred sync with conflict resolution.

**Auth** — email, mobile, Google/Apple/Microsoft OAuth, biometric, passkeys
(optional), 2FA.

**Reports** — daily/weekly/monthly/yearly, per role, PDF/Excel export.

## 4. Non-Functional Requirements

- **Offline-first**: core reading/memorisation/recording must work without a
  network connection; sync resolves conflicts when connectivity returns.
- **Cross-platform**: one responsive PWA codebase — installable on Android,
  iOS, Windows, macOS, Linux, plus desktop/mobile browsers.
- **Accessibility**: large-font mode, senior-friendly mode, colour-blind
  friendly palettes, readable Arabic typography, dark/light mode.
- **Performance**: consistent 15-line Madinah Mushaf page layout with no text
  reflow; audio/Quran text cached for fast, low-bandwidth access.
- **Security & privacy**: encrypted sensitive data, RBAC, audit logs, rate
  limiting, OWASP-aligned practices — see `docs/02-system-architecture.md`.
- **Localisation**: Bahasa Malaysia as the primary UI/translation language,
  English as fallback.
- **Content integrity**: Quran text/translation/audio must come from
  authoritative, appropriately licensed sources with correct attribution
  (Tanzil, Al Quran Cloud API, or equivalent) — see §6.

## 5. Out of Scope (so far)

Real and working: the Adaptive Hifz Engine's scoring/scheduling algorithm
(`App\Services\SpacedRepetitionScheduler`), the full memorisation-record/
review-session/review-log API, an end-to-end Sabak learning session in the
PWA, and AI-assisted recitation evaluation (browser speech-to-text →
`POST /api/v1/surahs/{s}/ayat/{a}/evaluate-recitation` → Claude compares
against the expected text). Arabic speech recognition accuracy is
browser-dependent and best-effort — a manual self-assessment fallback is
always available and used automatically where recognition isn't supported
or fails. **Not implemented**: teacher/parent portal features, gamification,
push notifications, offline sync/conflict resolution, and
analytics/reporting — see the scaffold's inline TODOs and `README.md` for
what's stubbed vs real.

## 6. Assumptions & Constraints

- Quran text: Tanzil Uthmani corpus (downloaded separately per its licence —
  not bundled in this repo).
- Translations/word-by-word/audio: Al Quran Cloud API (`api.alquran.cloud`),
  subject to its terms; word-by-word specifically has no official endpoint
  there and needs a separately sourced corpus (see
  `docs/03-database-design.md`, `ayah_words`).
- AI evaluation is an assistive signal only; qualified teachers remain the
  authority on Tajwid correctness.
- Target primary market/language: Malaysia / Bahasa Malaysia, English as
  secondary.

## 7. Glossary

- **Hifz** — Quran memorisation.
- **Sabak** — today's new memorisation portion.
- **Sabqi** — recently memorised material under active short-interval review.
- **Manzil** — long-retained material under long-interval maintenance review.
- **Muraja'ah** — revision.
- **Tadabbur** — reflective/deep understanding of meaning.
- **Tajwid** — rules of correct Quranic recitation/pronunciation.
- **Juz'** — one of 30 parts the Quran is divided into.
