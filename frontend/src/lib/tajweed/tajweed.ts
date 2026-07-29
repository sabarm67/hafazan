export interface TajweedRule {
  rule: string
  start: number
  end: number
}

export interface TajweedSegment {
  text: string
  rule: string | null
}

/**
 * Colours per rule — proposed by a contributor to the upstream data project
 * (cpfair/quran-tajweed issue #6), not a single universal industry standard
 * (schemes vary a bit between publishers/apps). Chosen because it's the
 * only complete mapping covering this exact 18-rule taxonomy.
 */
export const TAJWEED_COLORS: Record<string, string> = {
  idghaam_shafawi: '#be0000',
  idghaam_mutajanisayn: '#be0000',
  idghaam_mutaqaribayn: '#be0000',
  idghaam_ghunnah: '#be0000',
  idghaam_no_ghunnah: '#963c3c',
  ghunnah: '#00b400',
  qalqalah: '#0850aa',
  iqlab: '#8c32aa',
  ikhfa: '#32b4a0',
  ikhfa_shafawi: '#32b4a0',
  madd_6: '#c80000',
  madd_246: '#ffb43c',
  madd_2: '#32b4a0',
  madd_muttasil: '#ff0000',
  madd_munfasil: '#00dd93',
  hamzat_wasl: '#96fc00',
  lam_shamsiyyah: '#af00dd',
  silent: '#b4b4b4',
}

export const TAJWEED_RULE_LABELS: Record<string, string> = {
  idghaam_shafawi: 'Idghaam Shafawi',
  idghaam_mutajanisayn: 'Idghaam Mutajaanisain',
  idghaam_mutaqaribayn: 'Idghaam Mutaqaaribain',
  idghaam_ghunnah: 'Idghaam (with Ghunnah)',
  idghaam_no_ghunnah: 'Idghaam (without Ghunnah)',
  ghunnah: 'Ghunnah',
  qalqalah: 'Qalqalah',
  iqlab: 'Iqlab',
  ikhfa: 'Ikhfa',
  ikhfa_shafawi: 'Ikhfa Shafawi',
  madd_6: 'Madd (6 harakat)',
  madd_246: "Madd al-'Aarid/al-Leen",
  madd_2: 'Madd (2 harakat)',
  madd_muttasil: 'Madd al-Muttasil',
  madd_munfasil: 'Madd al-Munfasil',
  hamzat_wasl: 'Hamzat al-Wasl',
  lam_shamsiyyah: 'Lam al-Shamsiyyah',
  silent: 'Silent',
}

/**
 * Splits `text` into runs sharing the same tajweed rule (or none), so the
 * caller can render each run as a single coloured <span> instead of one per
 * character. `rules` may overlap (ghunnah co-occurs with iqlab/idghaam/ikhfa
 * in ~0.07% of annotations in the bundled data) — ghunnah is applied first
 * and more specific rules win where they overlap it.
 *
 * Indices in `rules` are Unicode codepoint offsets into `text`, matching how
 * they were generated (see scripts/tajweed-build/remap.py). Plain JS string
 * indexing is used rather than codepoint-aware iteration because every
 * character in the bundled Uthmani text is within the Basic Multilingual
 * Plane (verified — no surrogate pairs), so string indices and codepoint
 * indices coincide here.
 */
export function buildTajweedSegments(text: string, rules: TajweedRule[]): TajweedSegment[] {
  const ruleByIndex: (string | null)[] = new Array(text.length).fill(null)

  const ghunnah = rules.filter((r) => r.rule === 'ghunnah')
  const others = rules.filter((r) => r.rule !== 'ghunnah')

  for (const r of ghunnah) {
    for (let i = r.start; i < r.end && i < text.length; i++) ruleByIndex[i] = r.rule
  }
  for (const r of others) {
    for (let i = r.start; i < r.end && i < text.length; i++) ruleByIndex[i] = r.rule
  }

  const segments: TajweedSegment[] = []
  let currentRule: string | null = null
  let currentText = ''

  for (let i = 0; i < text.length; i++) {
    const rule = ruleByIndex[i]
    if (rule !== currentRule) {
      if (currentText) segments.push({ text: currentText, rule: currentRule })
      currentRule = rule
      currentText = text[i]
    } else {
      currentText += text[i]
    }
  }
  if (currentText) segments.push({ text: currentText, rule: currentRule })

  return segments
}
