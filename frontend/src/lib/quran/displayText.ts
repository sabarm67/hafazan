/**
 * Characters present in the bundled Uthmani text that are hidden from
 * on-screen rendering (not stripped from the stored data — tajweed rule
 * indices are computed against the full text, so removing characters here
 * would misalign them; see stripHiddenMarks()'s callers for how they stay
 * aligned).
 *
 * U+06ED ARABIC SMALL LOW MEEM: renders as an oversized, disconnected dot
 * with the KFGQPC Hafs font (reported by the user, positioned exactly
 * where this mark sits within a word, e.g. inside "هُدًۭى" in
 * Al-Baqarah 2) rather than the small counted-recitation mark it's meant
 * to be. Hidden rather than deleted from storage in case a future font/
 * feature can render it properly.
 */
const HIDDEN_MARKS = new Set([0x06ed])

export function stripHiddenMarks(text: string): string {
  let out = ''
  for (const ch of text) {
    if (!HIDDEN_MARKS.has(ch.codePointAt(0) ?? -1)) out += ch
  }
  return out
}

const BISMILLAH = 'بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ'
/**
 * NFC-normalized form, used only for the startsWith() comparison below.
 * The hand-typed BISMILLAH constant above and the bundled Uthmani data
 * are visually identical but weren't byte-identical — Tanzil's data
 * orders the fatha/shadda combining marks in "لَّ"/"رَّ" as
 * shadda-then-fatha, this literal had fatha-then-shadda. Both render the
 * same glyph, but as raw codepoint sequences a plain startsWith() only
 * matched Al-Fatihah's ayah 1 (which round-trips through the exact same
 * literal representation) and silently failed for every other surah -
 * NFC's canonical ordering (by Unicode combining class: fatha is class
 * 30, shadda is class 33) reorders either input the same way, so this
 * comparison is robust regardless of which order source data happens to
 * use. Doesn't affect the .length-based slicing below - normalizing only
 * reorders these combining marks, it doesn't change the character count.
 */
const BISMILLAH_NFC = BISMILLAH.normalize('NFC')

export interface BismillahSplit {
  /** The Bismillah substring (with any leading BOM), or null if ayah 1 doesn't start with it, or IS just the Bismillah (Al-Fatihah). */
  bismillah: string | null
  /** Character offset in the original text where content after the Bismillah begins. */
  contentStart: number
}

/**
 * Detects a Bismillah prefixed onto a surah's first ayah — Tanzil's text
 * embeds it as literal text within ayah 1 for every surah except Al-Fatihah
 * (where ayah 1 genuinely IS the Bismillah - nothing to split) and
 * At-Tawbah (which has none at all). Used to render it as a separate
 * heading instead of running into the ayah's actual content, e.g.
 * Al-Baqarah's real ayah 1 is "الٓمٓ" (Alif, Laam, Miim), not the Bismillah.
 */
export function splitBismillah(text: string): BismillahSplit {
  const stripped = text.replace(/^﻿/, '')
  const bomOffset = text.length - stripped.length

  if (!stripped.normalize('NFC').startsWith(BISMILLAH_NFC)) {
    return { bismillah: null, contentStart: 0 }
  }

  let contentStart = BISMILLAH.length
  while (contentStart < stripped.length && stripped[contentStart] === ' ') contentStart++

  if (contentStart >= stripped.length) {
    // Ayah 1 IS just the Bismillah (Al-Fatihah) — nothing to split.
    return { bismillah: null, contentStart: 0 }
  }

  const contentStartInOriginal = contentStart + bomOffset

  return { bismillah: text.slice(0, contentStartInOriginal), contentStart: contentStartInOriginal }
}
