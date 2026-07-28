<script setup lang="ts">
import { useAuthStore } from './stores/auth'

// Auth state is resolved once in main.ts before the app mounts.
const auth = useAuthStore()

const navLinks = [
  { to: '/', label: 'Dashboard' },
  { to: '/mushaf', label: 'Mushaf' },
  { to: '/session', label: 'Sabak' },
  { to: '/murajaah', label: "Muraja'ah" },
  { to: '/teacher', label: 'Teacher' },
  { to: '/parent', label: 'Parent' },
  { to: '/settings', label: 'Settings' },
]
</script>

<template>
  <div class="mx-auto flex min-h-svh max-w-4xl flex-col gap-6 px-4 py-6">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-200 pb-4 dark:border-stone-800">
      <span class="text-lg font-semibold">Al-Quran Hafazan System</span>
      <nav class="flex flex-wrap gap-4 text-sm">
        <RouterLink v-for="link in navLinks" :key="link.to" :to="link.to" class="hover:underline">
          {{ link.label }}
        </RouterLink>
        <RouterLink v-if="!auth.user" to="/login" class="hover:underline">Log in</RouterLink>
        <button v-else class="hover:underline" @click="auth.logout()">Log out</button>
      </nav>
    </header>

    <main class="flex-1">
      <RouterView />
    </main>
  </div>
</template>
