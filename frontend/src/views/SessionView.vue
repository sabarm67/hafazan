<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useQuranStore, type Ayah } from '../stores/quran'
import { useMemorisationStore, type MemorisationRecord } from '../stores/memorisation'
import { useSessionStore, type ReviewLogResult, type RecitationEvaluation } from '../stores/session'
import { useSpeechRecognition } from '../composables/useSpeechRecognition'

const AI_CORRECT_THRESHOLD = 70

type Step = 'intention' | 'select' | 'listen' | 'repeat' | 'recall' | 'reflect' | 'done'

const auth = useAuthStore()
const quran = useQuranStore()
const memorisation = useMemorisationStore()
const session = useSessionStore()
const router = useRouter()
const route = useRoute()

onMounted(async () => {
  if (!auth.user) {
    router.push('/login')
    return
  }
  await quran.fetchSurahs()

  const surahQuery = route.query.surah
  const ayahQuery = route.query.ayahNumber
  if (surahQuery && ayahQuery) {
    await startReviewFor(Number(surahQuery), Number(ayahQuery))
  }
})

/** Entry point from Muraja'ah — skips straight to Listen for an ayah already being memorised. */
async function startReviewFor(surahNumber: number, ayahNumber: number) {
  error.value = null
  busy.value = true
  try {
    const ayat = await quran.fetchAyat(surahNumber)
    const ayah = ayat.find((a) => a.number_in_surah === ayahNumber)
    if (!ayah) throw new Error('Ayah not found')

    currentAyah.value = ayah
    record.value = await memorisation.startMemorising(ayah.id) // idempotent — returns the existing record
    await session.start(record.value.classification === 'manzil' ? 'manzil' : 'sabqi')

    step.value = 'listen'
  } catch (e) {
    error.value = 'Could not load this ayah for review.'
  } finally {
    busy.value = false
  }
}

const step = ref<Step>('intention')
const busy = ref(false)
const error = ref<string | null>(null)

// --- Step 2: select ---
const selectedSurah = ref<number | null>(null)
const selectedAyahNumber = ref<number>(1)
const surahAyat = ref<Ayah[]>([])
const currentAyah = ref<Ayah | null>(null)
const record = ref<MemorisationRecord | null>(null)

async function onSurahChange() {
  if (!selectedSurah.value) return
  surahAyat.value = await quran.fetchAyat(selectedSurah.value)
  selectedAyahNumber.value = 1
}

async function beginMemorising() {
  if (!selectedSurah.value) return
  error.value = null
  busy.value = true
  try {
    if (surahAyat.value.length === 0) {
      surahAyat.value = await quran.fetchAyat(selectedSurah.value)
    }
    const ayah = surahAyat.value.find((a) => a.number_in_surah === selectedAyahNumber.value)
    if (!ayah) throw new Error('Ayah not found')
    currentAyah.value = ayah

    record.value = await memorisation.startMemorising(ayah.id)
    await session.start('sabak')

    step.value = 'listen'
  } catch (e) {
    error.value = 'Could not start this session. Please try again.'
  } finally {
    busy.value = false
  }
}

// --- Step 3/4: listen + repeat ---
const audioEl = ref<HTMLAudioElement | null>(null)
const isSlow = ref(false)
const isLooping = ref(false)
const repeatCount = ref(0)

function toggleSpeed() {
  isSlow.value = !isSlow.value
  if (audioEl.value) audioEl.value.playbackRate = isSlow.value ? 0.7 : 1
}

function toggleLoop() {
  isLooping.value = !isLooping.value
}

function onAudioEnded() {
  if (isLooping.value && audioEl.value) {
    audioEl.value.currentTime = 0
    audioEl.value.play()
  }
}

function markRepeated() {
  repeatCount.value += 1
}

// --- Step 5: active recall ---
const isRevealed = ref(false)
const recallStartedAt = ref(0)
const mistakeSeverity = ref<'minor' | 'major' | null>(null)
const confidenceLevel = ref(3)
const lastLogResult = ref<ReviewLogResult | null>(null)

const speech = useSpeechRecognition('ar-SA')
const useManualFallback = ref(false)
const aiEvaluation = ref<RecitationEvaluation | null>(null)
const isEvaluating = ref(false)
const evaluationError = ref<string | null>(null)

function enterRecallStep() {
  step.value = 'recall'
  isRevealed.value = false
  mistakeSeverity.value = null
  recallStartedAt.value = Date.now()
  aiEvaluation.value = null
  evaluationError.value = null
  speech.transcript.value = ''
  useManualFallback.value = !speech.isSupported
}

function retryRecording() {
  aiEvaluation.value = null
  evaluationError.value = null
  speech.transcript.value = ''
}

async function checkRecitation() {
  if (!currentAyah.value || !speech.transcript.value.trim()) return
  isEvaluating.value = true
  evaluationError.value = null
  try {
    const result = await session.evaluateRecitation(
      currentAyah.value.surah_number,
      currentAyah.value.number_in_surah,
      speech.transcript.value
    )
    if (result === null) {
      evaluationError.value = 'AI evaluation is unavailable right now — please self-assess instead.'
      useManualFallback.value = true
    } else {
      aiEvaluation.value = result
    }
  } catch (e) {
    evaluationError.value = 'Something went wrong checking your recitation.'
    useManualFallback.value = true
  } finally {
    isEvaluating.value = false
  }
}

async function confirmAiResult() {
  if (!currentAyah.value || !session.current || !aiEvaluation.value) return
  error.value = null
  busy.value = true
  try {
    const timeToRecallMs = Date.now() - recallStartedAt.value

    lastLogResult.value = await session.submitLog(session.current.id, {
      ayah_id: currentAyah.value.id,
      is_correct: aiEvaluation.value.correctness_score >= AI_CORRECT_THRESHOLD,
      correctness_score: aiEvaluation.value.correctness_score,
      time_to_recall_ms: timeToRecallMs,
      confidence_level: confidenceLevel.value,
      ai_provider_used: aiEvaluation.value.provider_name,
      ai_evaluation_result: aiEvaluation.value,
    })

    step.value = 'reflect'
    await loadReflection()
  } catch (e) {
    error.value = 'Could not record this attempt. Please try again.'
  } finally {
    busy.value = false
  }
}

async function submitRecall(isCorrect: boolean) {
  if (!currentAyah.value || !session.current) return
  error.value = null
  busy.value = true
  try {
    const correctnessScore = isCorrect ? 95 : mistakeSeverity.value === 'major' ? 40 : 75
    const timeToRecallMs = Date.now() - recallStartedAt.value

    lastLogResult.value = await session.submitLog(session.current.id, {
      ayah_id: currentAyah.value.id,
      is_correct: isCorrect,
      correctness_score: correctnessScore,
      time_to_recall_ms: timeToRecallMs,
      confidence_level: confidenceLevel.value,
    })

    step.value = 'reflect'
    await loadReflection()
  } catch (e) {
    error.value = 'Could not record this attempt. Please try again.'
  } finally {
    busy.value = false
  }
}

// --- Step 6: reflect ---
const translation = ref<string | null>(null)
const notes = ref('')

async function loadReflection() {
  if (!currentAyah.value) return
  translation.value = await quran.fetchTranslation(currentAyah.value.surah_number, currentAyah.value.number_in_surah)
  notes.value = localStorage.getItem(notesKey(currentAyah.value.id)) ?? ''
}

function notesKey(ayahId: number): string {
  return `hafazan.notes.${ayahId}`
}

function saveNotes() {
  if (!currentAyah.value) return
  localStorage.setItem(notesKey(currentAyah.value.id), notes.value)
}

async function finishSession() {
  if (!session.current) return
  busy.value = true
  try {
    await session.finish(session.current.id, 'completed')
    step.value = 'done'
  } finally {
    busy.value = false
  }
}

function startAnother() {
  step.value = 'select'
  currentAyah.value = null
  record.value = null
  lastLogResult.value = null
  translation.value = null
  notes.value = ''
  repeatCount.value = 0
  isRevealed.value = false
}

const classificationLabel = computed(() => {
  const c = lastLogResult.value?.memorisation_record.classification
  return c ? c.charAt(0).toUpperCase() + c.slice(1) : ''
})
</script>

<template>
  <section class="mx-auto max-w-xl space-y-6">
    <p v-if="error" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
      {{ error }}
    </p>

    <!-- 1. Intention -->
    <div v-if="step === 'intention'" class="space-y-4 text-center">
      <h1 class="text-2xl font-semibold">Niat (Intention)</h1>
      <p dir="rtl" class="text-2xl leading-relaxed">اللَّهُمَّ اجْعَلِ الْقُرْآنَ رَبِيعَ قَلْبِي</p>
      <p class="text-sm text-stone-600 dark:text-stone-400">
        "O Allah, make the Qur'an the spring of my heart" — a well-known dua for
        beginning Quran study. Take a moment to renew your intention (niat)
        before starting.
      </p>
      <button
        class="rounded bg-emerald-700 px-5 py-2 text-white"
        @click="step = 'select'"
      >
        Continue
      </button>
    </div>

    <!-- 2. Select ayah -->
    <div v-else-if="step === 'select'" class="space-y-4">
      <h1 class="text-2xl font-semibold">New Memorisation (Sabak)</h1>
      <div class="space-y-3">
        <label class="block text-sm">
          Surah
          <select
            v-model="selectedSurah"
            class="mt-1 w-full rounded border border-stone-300 px-3 py-2 dark:border-stone-700 dark:bg-stone-900"
            @change="onSurahChange"
          >
            <option :value="null" disabled>Choose a surah…</option>
            <option v-for="s in quran.surahs" :key="s.number" :value="s.number">
              {{ s.number }}. {{ s.name_transliteration }} — {{ s.name_translation_ms }}
            </option>
          </select>
        </label>
        <label v-if="selectedSurah" class="block text-sm">
          Ayah number
          <input
            v-model.number="selectedAyahNumber"
            type="number"
            min="1"
            :max="quran.surahs.find((s) => s.number === selectedSurah)?.total_ayat ?? 1"
            class="mt-1 w-full rounded border border-stone-300 px-3 py-2 dark:border-stone-700 dark:bg-stone-900"
          />
        </label>
      </div>
      <button
        :disabled="!selectedSurah || busy"
        class="rounded bg-emerald-700 px-5 py-2 text-white disabled:opacity-50"
        @click="beginMemorising"
      >
        {{ busy ? 'Starting…' : 'Begin' }}
      </button>
    </div>

    <!-- 3. Listen -->
    <div v-else-if="step === 'listen' && currentAyah" class="space-y-4 text-center">
      <h1 class="text-2xl font-semibold">Listen</h1>
      <p dir="rtl" class="text-3xl leading-loose">{{ currentAyah.text_arabic_uthmani }}</p>
      <audio ref="audioEl" :src="currentAyah.audio_url" controls class="mx-auto w-full" @ended="onAudioEnded" />
      <div class="flex justify-center gap-3 text-sm">
        <button class="rounded border border-stone-300 px-3 py-1 dark:border-stone-700" @click="toggleSpeed">
          {{ isSlow ? 'Slow ✓' : 'Slow' }}
        </button>
        <button class="rounded border border-stone-300 px-3 py-1 dark:border-stone-700" @click="toggleLoop">
          {{ isLooping ? 'Loop ✓' : 'Loop' }}
        </button>
      </div>
      <button class="rounded bg-emerald-700 px-5 py-2 text-white" @click="step = 'repeat'">
        Next: Repeat
      </button>
    </div>

    <!-- 4. Repeat -->
    <div v-else-if="step === 'repeat' && currentAyah" class="space-y-4 text-center">
      <h1 class="text-2xl font-semibold">Repeat After the Reciter</h1>
      <p dir="rtl" class="text-3xl leading-loose">{{ currentAyah.text_arabic_uthmani }}</p>
      <audio :src="currentAyah.audio_url" controls class="mx-auto w-full" />
      <p class="text-sm text-stone-600 dark:text-stone-400">Repeated {{ repeatCount }} time(s)</p>
      <button class="rounded border border-stone-300 px-4 py-1.5 dark:border-stone-700" @click="markRepeated">
        I repeated it
      </button>
      <div>
        <button class="rounded bg-emerald-700 px-5 py-2 text-white" @click="enterRecallStep">
          Next: Recall from Memory
        </button>
      </div>
    </div>

    <!-- 5. Active recall -->
    <div v-else-if="step === 'recall' && currentAyah" class="space-y-4 text-center">
      <h1 class="text-2xl font-semibold">Recite From Memory</h1>
      <div class="rounded border border-stone-300 p-6 dark:border-stone-700">
        <p v-if="isRevealed" dir="rtl" class="text-3xl leading-loose">{{ currentAyah.text_arabic_uthmani }}</p>
        <button v-else class="text-stone-500" @click="isRevealed = true">
          (Arabic text hidden — recite first, then tap to check)
        </button>
      </div>

      <!-- AI-assisted path -->
      <template v-if="!useManualFallback">
        <div v-if="!aiEvaluation" class="space-y-3">
          <p v-if="speech.error.value" class="text-sm text-red-600 dark:text-red-400">
            Microphone error ({{ speech.error.value }}).
          </p>
          <p v-if="evaluationError" class="text-sm text-red-600 dark:text-red-400">{{ evaluationError }}</p>

          <div v-if="!speech.transcript.value">
            <button
              v-if="!speech.isListening.value"
              class="rounded bg-emerald-700 px-4 py-2 text-white"
              @click="speech.start()"
            >
              🎙 Start Reciting
            </button>
            <button v-else class="rounded bg-red-700 px-4 py-2 text-white" @click="speech.stop()">
              ⏹ Stop
            </button>
            <p v-if="speech.isListening.value" class="mt-2 text-sm text-stone-500">Listening…</p>
          </div>

          <div v-else class="space-y-2">
            <p class="text-sm text-stone-500">We heard:</p>
            <p dir="rtl" class="text-xl">{{ speech.transcript.value }}</p>
            <div class="flex justify-center gap-3">
              <button
                :disabled="isEvaluating"
                class="rounded bg-emerald-700 px-4 py-2 text-white disabled:opacity-50"
                @click="checkRecitation"
              >
                {{ isEvaluating ? 'Checking…' : 'Check My Recitation' }}
              </button>
              <button class="rounded border border-stone-300 px-4 py-2 dark:border-stone-700" @click="retryRecording">
                Record Again
              </button>
            </div>
          </div>
        </div>

        <div v-else class="space-y-3 rounded border border-stone-200 p-4 text-left text-sm dark:border-stone-800">
          <p class="text-center font-medium">AI Evaluation</p>
          <p>Correctness score: <strong>{{ aiEvaluation.correctness_score }}</strong> / 100</p>
          <p v-if="aiEvaluation.missing_words.length">Missing words: {{ aiEvaluation.missing_words.join(', ') }}</p>
          <p v-if="aiEvaluation.extra_words.length">Extra words: {{ aiEvaluation.extra_words.join(', ') }}</p>
          <p v-if="aiEvaluation.repeated_words.length">Repeated words: {{ aiEvaluation.repeated_words.join(', ') }}</p>
          <p v-if="aiEvaluation.wrong_sequence_detected" class="text-amber-700 dark:text-amber-400">Word order looks off.</p>
          <p>Pronunciation confidence: {{ Math.round(aiEvaluation.pronunciation_confidence * 100) }}%</p>

          <label class="block pt-2 text-sm">
            Your confidence: {{ confidenceLevel }} / 5
            <input v-model.number="confidenceLevel" type="range" min="1" max="5" class="mt-1 w-full" />
          </label>

          <button
            :disabled="busy"
            class="w-full rounded bg-emerald-700 px-4 py-2 text-white disabled:opacity-50"
            @click="confirmAiResult"
          >
            {{ busy ? 'Saving…' : 'Continue' }}
          </button>
        </div>

        <button class="text-xs text-stone-500 underline" @click="useManualFallback = true">
          Prefer to self-assess instead?
        </button>
        <p class="text-xs text-stone-500">
          AI evaluation of recited Arabic is experimental and assistive
          only — not a Tajwid ruling.
        </p>
      </template>

      <!-- Manual fallback path -->
      <template v-else>
        <div v-if="mistakeSeverity === null" class="flex justify-center gap-3">
          <button class="rounded bg-emerald-700 px-4 py-2 text-white" @click="submitRecall(true)">
            I recited it correctly
          </button>
          <button class="rounded bg-amber-700 px-4 py-2 text-white" @click="mistakeSeverity = 'minor'">
            I made a mistake
          </button>
        </div>
        <div v-else class="space-y-3">
          <p class="text-sm">How significant was the mistake?</p>
          <div class="flex justify-center gap-3">
            <button class="rounded border border-amber-600 px-4 py-1.5 text-amber-700 dark:text-amber-400" @click="mistakeSeverity = 'minor'; submitRecall(false)">
              Minor slip
            </button>
            <button class="rounded border border-red-600 px-4 py-1.5 text-red-700 dark:text-red-400" @click="mistakeSeverity = 'major'; submitRecall(false)">
              Significant mistake
            </button>
          </div>
        </div>

        <label class="block text-sm">
          Confidence: {{ confidenceLevel }} / 5
          <input v-model.number="confidenceLevel" type="range" min="1" max="5" class="mt-1 w-full" />
        </label>
        <p class="text-xs text-stone-500">
          {{ speech.isSupported ? 'Self-assessed recall check.' : "Automatic recitation check isn't supported in this browser — using self-assessment." }}
        </p>
      </template>
    </div>

    <!-- 6. Reflect -->
    <div v-else-if="step === 'reflect' && currentAyah" class="space-y-4">
      <h1 class="text-2xl font-semibold text-center">Reflection</h1>
      <p dir="rtl" class="text-center text-2xl leading-loose">{{ currentAyah.text_arabic_uthmani }}</p>
      <p v-if="translation" class="text-center text-stone-700 dark:text-stone-300">{{ translation }}</p>
      <p v-else class="text-center text-stone-500">Loading translation…</p>

      <div v-if="lastLogResult" class="rounded border border-stone-200 p-4 text-sm dark:border-stone-800">
        <p>Status: <strong>{{ classificationLabel }}</strong></p>
        <p>Memory strength: <strong>{{ lastLogResult.memorisation_record.memory_strength_score }}</strong> / 100</p>
        <p>Next review: <strong>{{ lastLogResult.memorisation_record.next_review_date }}</strong></p>
      </div>

      <label class="block text-sm">
        Personal notes (saved on this device only)
        <textarea
          v-model="notes"
          rows="3"
          class="mt-1 w-full rounded border border-stone-300 px-3 py-2 dark:border-stone-700 dark:bg-stone-900"
          @blur="saveNotes"
        ></textarea>
      </label>

      <button :disabled="busy" class="w-full rounded bg-emerald-700 px-5 py-2 text-white disabled:opacity-50" @click="finishSession">
        {{ busy ? 'Finishing…' : 'Finish Session' }}
      </button>
    </div>

    <!-- 7. Done -->
    <div v-else-if="step === 'done'" class="space-y-4 text-center">
      <h1 class="text-2xl font-semibold">Session Complete</h1>
      <p class="text-stone-600 dark:text-stone-400">
        Total ayat reviewed this session: {{ session.current?.total_ayat_reviewed ?? 0 }}
      </p>
      <div class="flex justify-center gap-3">
        <button class="rounded bg-emerald-700 px-4 py-2 text-white" @click="startAnother">
          Memorise Another Ayah
        </button>
        <RouterLink to="/" class="rounded border border-stone-300 px-4 py-2 dark:border-stone-700">
          Back to Dashboard
        </RouterLink>
      </div>
    </div>
  </section>
</template>
