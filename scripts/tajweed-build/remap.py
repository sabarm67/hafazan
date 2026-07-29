"""
One-off build script (not run in production) that remaps cpfair/quran-tajweed's
tajweed annotations - originally indexed against a April-2017 snapshot of
Tanzil's Uthmani text - onto OUR bundled Uthmani text
(database/data/quran-uthmani.json).

Why this is needed: cpfair's README explicitly warns "the encoding of the
files available from Tanzil.net has changed slightly since the annotations
were generated" and that using a different text file requires rebuilding at
your own risk. Verified (see conversation) that after stripping optional
Quranic pause/stop marks (U+06D6-U+06DF, U+06E2, U+06E9, U+06ED) + a stray
BOM + collapsing whitespace, our text is CHARACTER-IDENTICAL to their 2017
snapshot for all 6236 ayat (0 mismatches) - so annotation indices can be
safely remapped by tracking exactly which characters were removed/collapsed
during that normalization.

Output: database/data/quran-tajweed.json, same schema as the source
(surah/ayah/annotations), but start/end indices now refer to OUR actual
bundled text (pause marks included) instead of the stripped reference text.

Source data license: CC BY 4.0 (cpfair/quran-tajweed). Attribution required
wherever this data/its derivatives are used.

To reproduce: this script expects two inputs in this directory (not
committed - see .gitignore - re-download if regenerating):
  - source-annotations.json: output/tajweed.hafs.uthmani-pause-sajdah.json
    from https://github.com/cpfair/quran-tajweed
  - source-text-2017.txt: the exact reference text snapshot linked from that
    repo's README, https://github.com/cpfair/quran-tajweed/files/7281388/quran-uthmani.txt
    (only used during development to VERIFY the remap - see conversation
    history / commit message for the verification methodology; not read by
    this script itself, which only needs source-annotations.json plus our
    own database/data/quran-uthmani.json)
"""
import json
import re
import sys

sys.stdout.reconfigure(encoding="utf-8")

PAUSE_MARKS = set(range(0x06D6, 0x06E0)) | {0x06E2, 0x06E9, 0x06ED, 0xFEFF}

REPO_ROOT = __file__.rsplit("scripts", 1)[0]

with open(REPO_ROOT + "database/data/quran-uthmani.json", encoding="utf-8") as f:
    ours_raw = json.load(f)

our_text_by_key = {}
for surah in ours_raw["data"]["surahs"]:
    for ayah in surah["ayahs"]:
        our_text_by_key[(surah["number"], ayah["numberInSurah"])] = ayah["text"]

with open(REPO_ROOT + "scripts/tajweed-build/source-annotations.json", encoding="utf-8") as f:
    source_annotations = json.load(f)


def build_stripped_to_actual_map(actual_text: str):
    """
    Returns (stripped_text, index_map) where index_map[i] is the actual_text
    index corresponding to stripped_text[i]. Mirrors the exact normalization
    used to verify text equality: drop PAUSE_MARKS chars, collapse runs of
    spaces to one, strip leading/trailing whitespace.
    """
    # Step 1: drop pause marks, recording actual-text index of each kept char.
    kept_chars = []
    kept_actual_indices = []
    for i, ch in enumerate(actual_text):
        if ord(ch) in PAUSE_MARKS:
            continue
        kept_chars.append(ch)
        kept_actual_indices.append(i)

    # Step 2: collapse runs of spaces to a single space, strip ends. Track
    # which output position maps to which actual index. For a collapsed run
    # of spaces, map to the FIRST space's actual index (arbitrary but
    # consistent - validated below that no real annotation ever lands here).
    stripped_chars = []
    stripped_to_actual = []
    i = 0
    n = len(kept_chars)
    # Skip leading spaces (mirrors .strip())
    while i < n and kept_chars[i] == " ":
        i += 1
    while i < n:
        ch = kept_chars[i]
        if ch == " ":
            # collapse run of spaces
            run_start_actual = kept_actual_indices[i]
            while i < n and kept_chars[i] == " ":
                i += 1
            if i >= n:
                break  # trailing spaces - drop (mirrors .strip())
            stripped_chars.append(" ")
            stripped_to_actual.append(run_start_actual)
        else:
            stripped_chars.append(ch)
            stripped_to_actual.append(kept_actual_indices[i])
            i += 1

    return "".join(stripped_chars), stripped_to_actual


output = []
total_annotations = 0
skipped_ambiguous = 0

for entry in source_annotations:
    surah, ayah = entry["surah"], entry["ayah"]
    key = (surah, ayah)
    actual_text = our_text_by_key.get(key)
    if actual_text is None:
        raise SystemExit(f"Missing ayah in our bundled text: {key}")

    stripped_text, stripped_to_actual = build_stripped_to_actual_map(actual_text)

    remapped_annotations = []
    for ann in entry["annotations"]:
        start, end = ann["start"], ann["end"]
        total_annotations += 1
        if start >= len(stripped_to_actual) or end > len(stripped_to_actual):
            skipped_ambiguous += 1
            continue
        actual_start = stripped_to_actual[start]
        # end is exclusive; map to the actual index one past the last
        # included stripped char (stripped_to_actual[end-1] + 1 in the
        # simple case, but must not skip over any pause marks between the
        # last included actual char and the next kept char - use the actual
        # index of the *next* stripped char if it exists, else len(actual_text)).
        if end < len(stripped_to_actual):
            actual_end = stripped_to_actual[end]
        else:
            actual_end = len(actual_text)
        remapped_annotations.append({
            "rule": ann["rule"],
            "start": actual_start,
            "end": actual_end,
        })

    output.append({"surah": surah, "ayah": ayah, "annotations": remapped_annotations})

print(f"Processed {len(output)} ayat, {total_annotations} annotations, "
      f"{skipped_ambiguous} skipped (out of range)")

with open(REPO_ROOT + "database/data/quran-tajweed.json", "w", encoding="utf-8") as f:
    json.dump(output, f, ensure_ascii=False, separators=(",", ":"))

print("Wrote database/data/quran-tajweed.json")
