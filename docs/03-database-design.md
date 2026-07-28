# Phase 3 — Database Design

## 1. ERD

```mermaid
erDiagram
    USERS ||--o{ ROLE_USER : has
    ROLES ||--o{ ROLE_USER : assigned_via
    USERS ||--o{ STUDENT_GUARDIAN : "is guardian"
    USERS ||--o{ STUDENT_GUARDIAN : "is student"
    USERS ||--o{ MEMORISATION_RECORDS : owns
    USERS ||--o{ REVIEW_SESSIONS : owns
    USERS ||--o{ REVIEW_SESSIONS : "teaches (nullable)"

    SURAHS ||--o{ AYAT : contains
    AYAT ||--o{ AYAH_TRANSLATIONS : has
    AYAT ||--o{ AYAH_WORDS : has
    AYAT ||--o{ MEMORISATION_RECORDS : "tracked by"
    AYAT ||--o{ REVIEW_LOGS : "attempted in"

    MEMORISATION_RECORDS ||--o{ REVIEW_LOGS : generates
    REVIEW_SESSIONS ||--o{ REVIEW_LOGS : contains

    USERS {
        bigint id PK
        string name
        string email UK
        string locale
        string timezone
    }
    ROLES {
        bigint id PK
        string name
        string slug UK
    }
    ROLE_USER {
        bigint user_id FK
        bigint role_id FK
    }
    STUDENT_GUARDIAN {
        bigint guardian_id FK
        bigint student_id FK
        string relationship
    }
    SURAHS {
        bigint id PK
        tinyint number UK
        string name_arabic
        string name_transliteration
        string name_translation_ms
        enum revelation_type
        smallint total_ayat
    }
    AYAT {
        bigint id PK
        bigint surah_id FK
        smallint number_in_surah
        smallint number_in_quran UK
        text text_arabic_uthmani
        tinyint juz_number
        tinyint hizb_number
        smallint page_number
        smallint ruku_number
        boolean is_sajda
    }
    AYAH_TRANSLATIONS {
        bigint id PK
        bigint ayah_id FK
        string locale
        text translation_text
        string source
    }
    AYAH_WORDS {
        bigint id PK
        bigint ayah_id FK
        smallint position
        string text_arabic
        string transliteration
        string translation_ms
    }
    MEMORISATION_RECORDS {
        bigint id PK
        bigint user_id FK
        bigint ayah_id FK
        tinyint memory_strength_score
        datetime last_recall_at
        int recall_count
        int mistake_count
        string current_interval_stage
        date next_review_date
        string classification
    }
    REVIEW_SESSIONS {
        bigint id PK
        bigint user_id FK
        bigint teacher_id FK
        enum session_type
        datetime started_at
        datetime ended_at
        enum status
    }
    REVIEW_LOGS {
        bigint id PK
        bigint review_session_id FK
        bigint memorisation_record_id FK
        bigint ayah_id FK
        datetime attempted_at
        boolean is_correct
        decimal correctness_score
        int time_to_recall_ms
        tinyint confidence_level
        json ai_evaluation_result
    }
```

## 2. Table Definitions

See migration source under `database/migrations/` for exact column
types/constraints. Summary:

- **`roles`** / **`role_user`** — many-to-many, so a user can hold multiple
  roles (e.g. teacher + parent) rather than one fixed role column.
- **`student_guardian`** — many-to-many between users, self-referential
  (`guardian_id`/`student_id` both FK to `users`), for parent oversight.
- **`surahs`** — one row per surah (114 total), reference data.
- **`ayat`** — one row per ayah (6,236 total), FK to `surahs`. Table name is
  `ayat` (Arabic plural), not `ayahs` — the `Ayah` Eloquent model explicitly
  sets `protected $table = 'ayat'` to override default pluralisation.
- **`ayah_translations`** — one row per (ayah, locale); `source` records
  provenance (e.g. `alquran.cloud:ms.basmeih`) for attribution.
- **`ayah_words`** — one row per word per ayah, for word-by-word display;
  intentionally unseeded in this scaffold (see `docs/02-system-architecture.md §6`).
- **`memorisation_records`** — the core per-user-per-ayah Adaptive Hifz Engine
  state: memory strength score, recall/mistake counts, current interval
  stage, next review date, Sabak/Sabqi/Manzil classification. Unique on
  `(user_id, ayah_id)`.
- **`review_sessions`** — one row per learning session (Sabak/Sabqi/
  Manzil/mixed), optionally teacher-attributed.
- **`review_logs`** — one row per recitation attempt within a session; the
  `ai_evaluation_result` JSON column stores the raw AI provider response for
  later inspection/tuning without needing a schema change per provider.

## 3. Classification & Interval Reference

`memorisation_records.classification` — `App\Enums\MemorisationClassification`:

| Value | Meaning |
|---|---|
| `sabak` | New memorisation, not yet consolidated |
| `sabqi` | Recent memorisation, active short-interval review |
| `manzil` | Long-term retained, extended-interval review |

`memorisation_records.current_interval_stage` — `App\Enums\ReviewIntervalStage`:

`immediate → 1d → 3d → 7d → 14d → 30d → 60d → 90d → 180d → 365d`

Both are stored as plain `string` columns (not native SQL `ENUM`) and cast to
PHP backed enums on the `MemorisationRecord` model, so adding a new value
later is a code change, not a migration.

The logic that transitions a record between stages/classifications based on
review outcomes lives in `App\Services\SpacedRepetitionScheduler` — memory
decay since the last recall, a gain/penalty formula per attempt, and
promotion/demotion thresholds between Sabak/Sabqi/Manzil. See its class
docblock for the exact formulas and tuning constants.

## 4. Relationships Summary

- `User` 1—* `MemorisationRecord`, `ReviewSession` (as learner), `ReviewSession` (as teacher, nullable)
- `User` *—* `Role` (via `role_user`)
- `User` *—* `User` (via `student_guardian`, self-referential guardian↔student)
- `Surah` 1—* `Ayah`
- `Ayah` 1—* `AyahTranslation`, `AyahWord`, `MemorisationRecord`, `ReviewLog`
- `ReviewSession` 1—* `ReviewLog`
- `MemorisationRecord` 1—* `ReviewLog`

## 5. Indexing Notes

- `memorisation_records`: unique `(user_id, ayah_id)`; composite index
  `(user_id, next_review_date)` for the "what's due today" query the future
  scheduler will run constantly.
- `ayat`: unique `number_in_quran` (1–6236) and unique `(surah_id, number_in_surah)`.
- `review_logs`: index `(memorisation_record_id, attempted_at)` for
  per-ayah history lookups.
- `ayah_translations`: unique `(ayah_id, locale)`.

## 6. Future Extensions

- Gamification tables (achievements, streak snapshots) — not added yet, out
  of scope for this session per the user's explicit scope decision.
- Push notification subscription table — Phase 9.
- Reporting/aggregate tables or materialized views for analytics dashboards
  — Phase 11+, once query patterns are known.
