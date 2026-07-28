<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { ApiError } from '../lib/api'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const isSubmitting = ref(false)

async function onSubmit() {
  error.value = null
  isSubmitting.value = true
  try {
    await auth.login(email.value, password.value)
    router.push('/')
  } catch (e) {
    error.value = e instanceof ApiError ? 'Invalid email or password.' : 'Something went wrong.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <section class="mx-auto max-w-sm space-y-4">
    <h1 class="text-2xl font-semibold">Log in</h1>
    <form class="space-y-3" @submit.prevent="onSubmit">
      <input
        v-model="email"
        type="email"
        placeholder="Email"
        required
        class="w-full rounded border border-stone-300 px-3 py-2 dark:border-stone-700 dark:bg-stone-900"
      />
      <input
        v-model="password"
        type="password"
        placeholder="Password"
        required
        class="w-full rounded border border-stone-300 px-3 py-2 dark:border-stone-700 dark:bg-stone-900"
      />
      <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
      <button
        type="submit"
        :disabled="isSubmitting"
        class="w-full rounded bg-emerald-700 px-3 py-2 text-white disabled:opacity-50"
      >
        {{ isSubmitting ? 'Logging in…' : 'Log in' }}
      </button>
    </form>
    <p class="text-sm text-stone-500">
      No account? <RouterLink to="/register" class="underline">Register</RouterLink>
    </p>
  </section>
</template>
