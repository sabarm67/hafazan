import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiFetch, ApiError } from '../lib/api'
import type { MemorisationRecord } from './memorisation'

export interface ReviewSession {
  id: number
  session_type: 'sabak' | 'sabqi' | 'manzil' | 'mixed'
  status: 'in_progress' | 'completed' | 'abandoned'
  started_at: string
  ended_at: string | null
  total_ayat_reviewed: number
}

export interface SubmitLogPayload {
  ayah_id: number
  is_correct: boolean
  correctness_score?: number
  time_to_recall_ms?: number
  confidence_level?: number
  ai_provider_used?: string
  ai_evaluation_result?: unknown
}

export interface RecitationEvaluation {
  ayah_id: number
  correctness_score: number
  wrong_sequence_detected: boolean
  missing_words: string[]
  extra_words: string[]
  repeated_words: string[]
  pronunciation_confidence: number
  provider_name: string
}

export interface ReviewLogResult {
  id: number
  interval_stage_before: string
  interval_stage_after: string
  memorisation_record: MemorisationRecord
}

export const useSessionStore = defineStore('session', () => {
  const current = ref<ReviewSession | null>(null)

  async function start(sessionType: ReviewSession['session_type']): Promise<ReviewSession> {
    const { data } = await apiFetch<{ data: ReviewSession }>('/api/v1/review-sessions', {
      method: 'POST',
      body: JSON.stringify({ session_type: sessionType }),
    })
    current.value = data
    return data
  }

  async function submitLog(sessionId: number, payload: SubmitLogPayload): Promise<ReviewLogResult> {
    const { data } = await apiFetch<{ data: ReviewLogResult }>(`/api/v1/review-sessions/${sessionId}/logs`, {
      method: 'POST',
      body: JSON.stringify(payload),
    })
    if (current.value && current.value.id === sessionId) {
      current.value.total_ayat_reviewed += 1
    }
    return data
  }

  async function finish(sessionId: number, status: 'completed' | 'abandoned' = 'completed'): Promise<ReviewSession> {
    const { data } = await apiFetch<{ data: ReviewSession }>(`/api/v1/review-sessions/${sessionId}`, {
      method: 'PUT',
      body: JSON.stringify({ status }),
    })
    current.value = data
    return data
  }

  /**
   * Returns null (rather than throwing) on a 503 — the caller should fall
   * back to manual self-assessment when AI evaluation isn't available.
   */
  async function evaluateRecitation(
    surahNumber: number,
    ayahNumber: number,
    transcribedText: string
  ): Promise<RecitationEvaluation | null> {
    try {
      const { data } = await apiFetch<{ data: RecitationEvaluation }>(
        `/api/v1/surahs/${surahNumber}/ayat/${ayahNumber}/evaluate-recitation`,
        { method: 'POST', body: JSON.stringify({ transcribed_text: transcribedText }) }
      )
      return data
    } catch (e) {
      if (e instanceof ApiError && e.status === 503) return null
      throw e
    }
  }

  return { current, start, submitLog, finish, evaluateRecitation }
})
