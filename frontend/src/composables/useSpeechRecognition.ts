import { onBeforeUnmount, ref } from 'vue'

/**
 * Thin wrapper around the browser's Web Speech API for capturing a spoken
 * recitation as text (feeds App\Contracts\AI\AiProviderInterface's
 * evaluateRecitation() on the backend, which expects transcribed text, not
 * audio). Support is inconsistent across browsers — Chrome-family browsers
 * support Arabic recognition reasonably well, Firefox/Safari often don't.
 * Accuracy on Quranic Arabic (tashkeel, tajweed) is best-effort, not
 * authoritative — callers must offer a manual fallback.
 */

interface SpeechRecognitionResultLike {
  resultIndex: number
  results: ArrayLike<ArrayLike<{ transcript: string }>>
}

interface SpeechRecognitionLike extends EventTarget {
  lang: string
  interimResults: boolean
  continuous: boolean
  maxAlternatives: number
  start(): void
  stop(): void
  onresult: ((event: SpeechRecognitionResultLike) => void) | null
  onerror: ((event: { error: string }) => void) | null
  onend: (() => void) | null
}

function getSpeechRecognitionCtor(): (new () => SpeechRecognitionLike) | null {
  const w = window as unknown as {
    SpeechRecognition?: new () => SpeechRecognitionLike
    webkitSpeechRecognition?: new () => SpeechRecognitionLike
  }
  return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null
}

export function useSpeechRecognition(lang = 'ar-SA') {
  const Ctor = getSpeechRecognitionCtor()
  const isSupported = Ctor !== null

  const isListening = ref(false)
  const transcript = ref('')
  const error = ref<string | null>(null)

  let recognition: SpeechRecognitionLike | null = null

  function start() {
    if (!Ctor) {
      error.value = 'not-supported'
      return
    }

    error.value = null
    transcript.value = ''

    recognition = new Ctor()
    recognition.lang = lang
    recognition.interimResults = true
    recognition.continuous = false
    recognition.maxAlternatives = 1

    recognition.onresult = (event) => {
      let combined = ''
      for (let i = event.resultIndex; i < event.results.length; i++) {
        combined += event.results[i][0].transcript
      }
      transcript.value = combined
    }
    recognition.onerror = (event) => {
      error.value = event.error || 'unknown-error'
      isListening.value = false
    }
    recognition.onend = () => {
      isListening.value = false
    }

    isListening.value = true
    recognition.start()
  }

  function stop() {
    recognition?.stop()
    isListening.value = false
  }

  onBeforeUnmount(() => {
    recognition?.stop()
  })

  return { isSupported, isListening, transcript, error, start, stop }
}
