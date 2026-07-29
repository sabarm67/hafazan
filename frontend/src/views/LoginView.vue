<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { ApiError } from '../lib/api'
import { isPasskeySupported } from '../lib/webauthn/webauthn'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const isSubmitting = ref(false)
const passkeyAvailable = ref(false)
const isPasskeySubmitting = ref(false)

onMounted(() => {
  passkeyAvailable.value = isPasskeySupported()
})

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

async function onSubmitWithPasskey() {
  error.value = null
  isPasskeySubmitting.value = true
  try {
    await auth.loginWithPasskey()
    router.push('/')
  } catch {
    error.value = 'Could not sign in with a passkey. Try your password instead.'
  } finally {
    isPasskeySubmitting.value = false
  }
}
</script>

<template>
  <section class="mx-auto max-w-sm space-y-4">
    <h1 class="text-2xl font-semibold">Log in</h1>

    <template v-if="passkeyAvailable">
      <button
        type="button"
        :disabled="isPasskeySubmitting"
        class="flex w-full items-center justify-center gap-2 rounded border border-stone-300 px-3 py-2 disabled:opacity-50 dark:border-stone-700"
        @click="onSubmitWithPasskey"
      >
        <span aria-hidden="true">🔐</span>
        {{ isPasskeySubmitting ? 'Waiting for biometrics…' : 'Sign in with Face ID / Touch ID / Windows Hello' }}
      </button>
      <div class="flex items-center gap-2 text-xs text-stone-400">
        <span class="h-px flex-1 bg-stone-200 dark:bg-stone-700" />
        or use your password
        <span class="h-px flex-1 bg-stone-200 dark:bg-stone-700" />
      </div>
    </template>

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
