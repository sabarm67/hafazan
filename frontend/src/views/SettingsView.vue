<script setup lang="ts">
import { useTheme } from '../composables/useTheme'

const { theme, toggleTheme } = useTheme()
</script>

<template>
  <section class="space-y-4">
    <h1 class="text-2xl font-semibold">Settings</h1>
    <div class="flex items-center gap-3">
      <span>Theme: {{ theme }}</span>
      <button
        class="rounded border border-stone-300 px-3 py-1 dark:border-stone-700"
        @click="toggleTheme"
      >
        Toggle dark mode
      </button>
    </div>
    <p class="text-stone-600 dark:text-stone-400">
      Large font / senior-friendly mode and colour-blind friendly palettes are
      future-phase UI work (see docs/01-requirements-analysis.md).
    </p>
  </section>
</template>
