import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiFetch } from '../lib/api'
import type { TajweedRule } from '../lib/tajweed/tajweed'

export interface Surah {
  number: number
  name_arabic: string
  name_transliteration: string
  name_translation_ms: string
  revelation_type: 'meccan' | 'medinan'
  total_ayat: number
}

export interface Ayah {
  id: number
  surah_number: number
  number_in_surah: number
  number_in_quran: number
  text_arabic_uthmani: string
  juz_number: number
  hizb_number: number
  page_number: number
  ruku_number: number
  is_sajda: boolean
  audio_url: string
  tajweed_rules: TajweedRule[]
  /** Only present where the backend bothered to eager-load it (surah ayat listing) — absent, not null, elsewhere. */
  translation_ms?: string
}

export const useQuranStore = defineStore('quran', () => {
  const surahs = ref<Surah[]>([])
  const ayatBySurah = ref<Record<number, Ayah[]>>({})
  const isLoading = ref(false)

  async function fetchSurahs(): Promise<void> {
    if (surahs.value.length > 0) return
    isLoading.value = true
    try {
      const { data } = await apiFetch<{ data: Surah[] }>('/api/v1/surahs')
      surahs.value = data
    } finally {
      isLoading.value = false
    }
  }

  async function fetchAyat(surahNumber: number): Promise<Ayah[]> {
    if (ayatBySurah.value[surahNumber]) return ayatBySurah.value[surahNumber]
    isLoading.value = true
    try {
      const { data } = await apiFetch<{ data: Ayah[] }>(`/api/v1/surahs/${surahNumber}/ayat`)
      ayatBySurah.value[surahNumber] = data
      return data
    } finally {
      isLoading.value = false
    }
  }

  async function fetchTranslation(surahNumber: number, ayahNumber: number, locale = 'ms'): Promise<string> {
    const { data } = await apiFetch<{ data: { translation_text: string } }>(
      `/api/v1/surahs/${surahNumber}/ayat/${ayahNumber}/translation?locale=${locale}`
    )
    return data.translation_text
  }

  return { surahs, ayatBySurah, isLoading, fetchSurahs, fetchAyat, fetchTranslation }
})
