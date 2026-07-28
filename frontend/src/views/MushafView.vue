<script setup lang="ts">
import { onMounted } from 'vue'
import { useQuranStore } from '../stores/quran'

const quran = useQuranStore()

onMounted(() => {
  if (quran.surahs.length === 0) quran.fetchSurahs()
})
</script>

<template>
  <section class="space-y-4">
    <h1 class="text-2xl font-semibold">Mushaf</h1>
    <p v-if="quran.isLoading" class="text-stone-500">Loading surahs…</p>
    <ul v-else class="grid grid-cols-1 gap-1 sm:grid-cols-2">
      <li
        v-for="surah in quran.surahs"
        :key="surah.number"
        class="flex items-center justify-between rounded border border-stone-200 px-3 py-2 dark:border-stone-800"
      >
        <span>{{ surah.number }}. {{ surah.name_transliteration }} — {{ surah.name_translation_ms }}</span>
        <span dir="rtl" class="font-arabic text-lg">{{ surah.name_arabic }}</span>
      </li>
    </ul>
  </section>
</template>
