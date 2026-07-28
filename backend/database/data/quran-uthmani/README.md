# Ayah text import

`php artisan quran:import-tanzil` populates the `ayat` table for real — it
fetches the Uthmani edition Al Quran Cloud mirrors from Tanzil
(`GET /v1/quran/quran-uthmani`) in one bulk request and upserts all 6,236
ayat, keyed on `number_in_quran`. No file needs to be placed in this
directory for that path.

This directory is kept as the target for an optional **offline** import path
(`TANZIL_CORPUS_PATH` in `.env`, `config('quran.tanzil_alquran_cloud.tanzil_corpus_path')`)
that isn't implemented yet — useful if you'd rather import from a locally
downloaded Tanzil corpus (https://tanzil.net/download) instead of depending
on the Al Quran Cloud API at import time. Not needed for normal use.

Required attribution text (from Tanzil's terms) must be surfaced in the
app's UI wherever this text is displayed — see
`docs/01-requirements-analysis.md`.
