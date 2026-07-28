<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useMemorisationStore } from '../stores/memorisation'

const auth = useAuthStore()
const memorisation = useMemorisationStore()
const router = useRouter()

onMounted(() => {
  if (!auth.user) {
    router.push('/login')
    return
  }
  memorisation.fetchDue()
})

function reviewNow(surahNumber: number, ayahNumber: number) {
  router.push({ path: '/session', query: { surah: surahNumber, ayahNumber } })
}
</script>

<template>
  <section class="space-y-4">
    <h1 class="text-2xl font-semibold">Muraja'ah</h1>
    <p class="text-stone-600 dark:text-stone-400">
      Ayat due for review today, weakest memory strength first.
    </p>

    <p v-if="memorisation.isLoading" class="text-stone-500">Loading…</p>
    <p v-else-if="memorisation.dueRecords.length === 0" class="text-stone-500">
      Nothing due for review right now. Start a new Sabak session to add ayat here.
    </p>
    <ul v-else class="space-y-2">
      <li
        v-for="r in [...memorisation.dueRecords].sort((a, b) => a.memory_strength_score - b.memory_strength_score)"
        :key="r.id"
        class="flex items-center justify-between rounded border border-stone-200 px-4 py-3 dark:border-stone-800"
      >
        <div>
          <p class="font-medium">Surah {{ r.surah_number }}, Ayah {{ r.number_in_surah }}</p>
          <p class="text-xs text-stone-500">
            {{ r.classification }} · strength {{ r.memory_strength_score }}/100 · due {{ r.next_review_date }}
          </p>
        </div>
        <button
          class="rounded bg-emerald-700 px-3 py-1.5 text-sm text-white"
          @click="reviewNow(r.surah_number, r.number_in_surah)"
        >
          Review now
        </button>
      </li>
    </ul>
  </section>
</template>
