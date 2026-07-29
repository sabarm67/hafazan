<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useQuranStore, type Ayah } from '../stores/quran'

const route = useRoute()
const quran = useQuranStore()

const isLoading = ref(false)
const ayat = ref<Ayah[]>([])

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

    <p v-if="isLoading" class="text-center text-stone-500">Loading ayat…</p>

    <ol v-else class="space-y-4">
      <li
        v-for="ayah in ayat"
        :key="ayah.id"
        class="rounded border border-stone-200 p-4 dark:border-stone-800"
      >
        <p dir="rtl" class="font-arabic text-right text-3xl leading-loose">
          {{ ayah.text_arabic_uthmani }}
          <span class="font-arabic-ui text-base text-stone-400">﴿{{ ayah.number_in_surah }}﴾</span>
        </p>
        <p v-if="ayah.is_sajda" class="mt-2 text-center text-xs text-emerald-700 dark:text-emerald-500">
          Sajdah (prostration) verse
        </p>
      </li>
    </ol>
  </section>
</template>
