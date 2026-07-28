<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useMemorisationStore } from '../stores/memorisation'

const auth = useAuthStore()
const memorisation = useMemorisationStore()

onMounted(() => {
  if (auth.user) {
    memorisation.fetchDue()
    memorisation.fetchAll()
  }
})
</script>

<template>
  <section class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold">Dashboard</h1>
      <p class="text-stone-600 dark:text-stone-400">
        Welcome{{ auth.user ? `, ${auth.user.name}` : '' }}.
      </p>
    </div>

    <div v-if="auth.user" class="grid grid-cols-2 gap-4 sm:grid-cols-3">
      <div class="rounded border border-stone-200 p-4 dark:border-stone-800">
        <p class="text-2xl font-semibold">{{ memorisation.records.length }}</p>
        <p class="text-xs text-stone-500">Ayat being memorised</p>
      </div>
      <div class="rounded border border-stone-200 p-4 dark:border-stone-800">
        <p class="text-2xl font-semibold">{{ memorisation.dueRecords.length }}</p>
        <p class="text-xs text-stone-500">Due for review today</p>
      </div>
      <div class="rounded border border-stone-200 p-4 dark:border-stone-800">
        <p class="text-2xl font-semibold">
          {{ memorisation.records.filter((r) => r.classification === 'manzil').length }}
        </p>
        <p class="text-xs text-stone-500">In Manzil (long-term)</p>
      </div>
    </div>

    <div v-if="auth.user" class="flex flex-wrap gap-3">
      <RouterLink to="/session" class="rounded bg-emerald-700 px-4 py-2 text-sm text-white">
        Start a Sabak session
      </RouterLink>
      <RouterLink to="/murajaah" class="rounded border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">
        Go to Muraja'ah ({{ memorisation.dueRecords.length }} due)
      </RouterLink>
    </div>

    <p v-else class="text-stone-600 dark:text-stone-400">
      <RouterLink to="/login" class="underline">Log in</RouterLink> or
      <RouterLink to="/register" class="underline">register</RouterLink> to start memorising.
    </p>

    <p class="text-xs text-stone-500">
      Full analytics (streaks, retention, heat maps) land in a future phase.
    </p>
  </section>
</template>
