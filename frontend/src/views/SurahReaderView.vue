<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useQuranStore, type Ayah } from '../stores/quran'

type DisplayMode = 'arabic-translation' | 'translation-only'

const route = useRoute()
const quran = useQuranStore()

const isLoading = ref(false)
const ayat = ref<Ayah[]>([])
const mode = ref<DisplayMode>('arabic-translation')

const surahNumber = computed(() => Number(route.params.number))
const surah = computed(() => quran.surahs.find((s) => s.number === surahNumber.value))

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

    <div class="flex justify-center gap-2 text-sm">
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

    <p v-if="isLoading" class="text-center text-stone-500">Loading ayat…</p>

    <ol v-else class="space-y-4">
      <li
        v-for="ayah in ayat"
        :key="ayah.id"
        class="rounded border border-stone-200 p-4 dark:border-stone-800"
      >
        <p v-if="mode === 'arabic-translation'" dir="rtl" class="font-arabic text-right text-3xl leading-loose">
          {{ ayah.text_arabic_uthmani }}
          <span class="font-arabic-ui text-base text-stone-400">﴿{{ ayah.number_in_surah }}﴾</span>
        </p>
        <p
          v-if="ayah.translation_ms"
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
      </li>
    </ol>
  </section>
</template>
