import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiFetch } from '../lib/api'

export interface MemorisationRecord {
  id: number
  ayah_id: number
  surah_number: number
  number_in_surah: number
  memory_strength_score: number
  classification: 'sabak' | 'sabqi' | 'manzil'
  current_interval_stage: string
  next_review_date: string | null
  last_recall_at: string | null
  recall_count: number
  mistake_count: number
}

export const useMemorisationStore = defineStore('memorisation', () => {
  const records = ref<MemorisationRecord[]>([])
  const dueRecords = ref<MemorisationRecord[]>([])
  const isLoading = ref(false)

  async function fetchAll(): Promise<void> {
    isLoading.value = true
    try {
      const { data } = await apiFetch<{ data: MemorisationRecord[] }>('/api/v1/memorisation-records')
      records.value = data
    } finally {
      isLoading.value = false
    }
  }

  async function fetchDue(): Promise<void> {
    isLoading.value = true
    try {
      const { data } = await apiFetch<{ data: MemorisationRecord[] }>('/api/v1/memorisation-records?due=1')
      dueRecords.value = data
    } finally {
      isLoading.value = false
    }
  }

  async function startMemorising(ayahId: number): Promise<MemorisationRecord> {
    const { data } = await apiFetch<{ data: MemorisationRecord }>('/api/v1/memorisation-records', {
      method: 'POST',
      body: JSON.stringify({ ayah_id: ayahId }),
    })
    return data
  }

  async function resetForReview(recordId: number): Promise<MemorisationRecord> {
    const { data } = await apiFetch<{ data: MemorisationRecord }>(`/api/v1/memorisation-records/${recordId}`, {
      method: 'PUT',
      body: JSON.stringify({ reset_for_review: true }),
    })
    return data
  }

  return { records, dueRecords, isLoading, fetchAll, fetchDue, startMemorising, resetForReview }
})
