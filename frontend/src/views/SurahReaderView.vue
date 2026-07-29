<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useQuranStore, type Ayah } from '../stores/quran'
import { buildTajweedSegments, TAJWEED_COLORS, TAJWEED_RULE_LABELS, type TajweedRule } from '../lib/tajweed/tajweed'
import { splitBismillah, stripHiddenMarks } from '../lib/quran/displayText'

type DisplayMode = 'arabic-only' | 'arabic-translation' | 'translation-only'

const route = useRoute()
const quran = useQuranStore()

const isLoading = ref(false)
const ayat = ref<Ayah[]>([])
const mode = ref<DisplayMode>('arabic-only')
const showTajweed = ref(true)
const showLegend = ref(false)

const showArabic = computed(() => mode.value !== 'translation-only')
const showTranslation = computed(() => mode.value !== 'arabic-only')

const surahNumber = computed(() => Number(route.params.number))
const surah = computed(() => quran.surahs.find((s) => s.number === surahNumber.value))

/**
 * Bismillah heading text for ayah 1, or null if this surah doesn't have one
 * prefixed (At-Tawbah) or ayah 1 IS the Bismillah itself (Al-Fatihah).
 */
function bismillahHeading(ayah: Ayah): string | null {
  if (ayah.number_in_surah !== 1) return null
  const { bismillah } = splitBismillah(ayah.text_arabic_uthmani)
  return bismillah ? stripHiddenMarks(bismillah) : null
}

/** The ayah's actual content — Bismillah prefix stripped for ayah 1 where present, with tajweed rules re-indexed to match. */
function ayahContent(ayah: Ayah): { text: string; rules: TajweedRule[] } {
  if (ayah.number_in_surah === 1) {
    const { bismillah, contentStart } = splitBismillah(ayah.text_arabic_uthmani)
    if (bismillah !== null) {
      return {
        text: ayah.text_arabic_uthmani.slice(contentStart),
        rules: ayah.tajweed_rules
          .filter((r) => r.start >= contentStart)
          .map((r) => ({ rule: r.rule, start: r.start - contentStart, end: r.end - contentStart })),
      }
    }
  }
  return { text: ayah.text_arabic_uthmani, rules: ayah.tajweed_rules }
}

function tajweedSegments(ayah: Ayah) {
  const { text, rules } = ayahContent(ayah)
  return buildTajweedSegments(text, rules)
}

function plainText(ayah: Ayah) {
  return stripHiddenMarks(ayahContent(ayah).text)
}

async function load() {
  isLoading.value = true
  try {
    if (quran.surahs.length === 0) await quran.fetchSurahs()
    ayat.value = await quran.fetchAyat(surahNumber.value)
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
watch(surahNumber, load)
</script>

<template>
  <section class="mx-auto max-w-2xl space-y-4">
    <RouterLink to="/mushaf" class="text-sm text-emerald-700 hover:underline dark:text-emerald-500">
      ← Back to Mushaf
    </RouterLink>

    <header v-if="surah" class="space-y-1 text-center">
      <h1 class="text-2xl font-semibold">{{ surah.number }}. {{ surah.name_transliteration }}</h1>
      <p dir="rtl" class="font-arabic-ui text-2xl">{{ surah.name_arabic }}</p>
      <p class="text-sm text-stone-500">
        {{ surah.name_translation_ms }} &middot;
        {{ surah.revelation_type === 'meccan' ? 'Makkiyah' : 'Madaniyah' }} &middot;
        {{ surah.total_ayat }} ayat
      </p>
    </header>

    <div class="flex flex-wrap justify-center gap-2 text-sm">
      <button
        class="rounded px-3 py-1"
        :class="mode === 'arabic-only'
          ? 'bg-emerald-700 text-white'
          : 'border border-stone-300 text-stone-600 dark:border-stone-700 dark:text-stone-400'"
        @click="mode = 'arabic-only'"
      >
        Arabic Only
      </button>
      <button
        class="rounded px-3 py-1"
        :class="mode === 'arabic-translation'
          ? 'bg-emerald-700 text-white'
          : 'border border-stone-300 text-stone-600 dark:border-stone-700 dark:text-stone-400'"
        @click="mode = 'arabic-translation'"
      >
        Arabic + Translation
      </button>
      <button
        class="rounded px-3 py-1"
        :class="mode === 'translation-only'
          ? 'bg-emerald-700 text-white'
          : 'border border-stone-300 text-stone-600 dark:border-stone-700 dark:text-stone-400'"
        @click="mode = 'translation-only'"
      >
        Translation Only
      </button>
    </div>

    <div v-if="showArabic" class="flex flex-wrap items-center justify-center gap-3 text-xs text-stone-500">
      <label class="flex items-center gap-1.5">
        <input v-model="showTajweed" type="checkbox" class="rounded" />
        Tajweed colours
      </label>
      <button v-if="showTajweed" class="underline" @click="showLegend = !showLegend">
        {{ showLegend ? 'Hide' : 'Show' }} legend
      </button>
    </div>

    <div v-if="showArabic && showTajweed && showLegend" class="rounded border border-stone-200 p-3 text-xs dark:border-stone-800">
      <p class="mb-2 flex flex-wrap gap-x-4 gap-y-1">
        <span v-for="(label, rule) in TAJWEED_RULE_LABELS" :key="rule" class="flex items-center gap-1">
          <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: TAJWEED_COLORS[rule] }" />
          {{ label }}
        </span>
      </p>
      <p class="text-stone-400">
        Colour scheme from the
        <a href="https://github.com/cpfair/quran-tajweed" target="_blank" rel="noopener" class="underline">
          quran-tajweed
        </a>
        project (CC BY 4.0) — conventions vary somewhat between publishers.
      </p>
    </div>

    <p v-if="isLoading" class="text-center text-stone-500">Loading ayat…</p>

    <ol v-else class="space-y-4">
      <li v-for="ayah in ayat" :key="ayah.id">
        <p
          v-if="showArabic && bismillahHeading(ayah)"
          dir="rtl"
          class="font-arabic mb-3 text-center text-2xl text-stone-500 dark:text-stone-400"
        >
          {{ bismillahHeading(ayah) }}
        </p>
        <div class="rounded border border-stone-200 p-4 dark:border-stone-800">
          <p v-if="showArabic" dir="rtl" class="font-arabic text-right text-3xl leading-loose">
            <template v-if="showTajweed">
              <span
                v-for="(segment, i) in tajweedSegments(ayah)"
                :key="i"
                :style="segment.rule ? { color: TAJWEED_COLORS[segment.rule] } : undefined"
              >{{ segment.text }}</span>
            </template>
            <template v-else>{{ plainText(ayah) }}</template>
            <span class="font-arabic-ui text-base text-stone-400">﴿{{ ayah.number_in_surah }}﴾</span>
          </p>
          <p
            v-if="showTranslation && ayah.translation_ms"
            class="leading-tight"
            :class="mode === 'arabic-translation'
              ? 'mt-1 text-right text-sm text-stone-500 dark:text-stone-400'
              : 'text-left text-base text-stone-700 dark:text-stone-300'"
          >
            <span v-if="mode === 'translation-only'" class="mr-1 text-xs text-stone-400">{{ ayah.number_in_surah }}.</span>
            {{ ayah.translation_ms }}
          </p>
          <p v-if="ayah.is_sajda" class="mt-2 text-center text-xs text-emerald-700 dark:text-emerald-500">
            Sajdah (prostration) verse
          </p>
        </div>
      </li>
    </ol>
  </section>
</template>
