<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useTheme } from '../composables/useTheme'
import { useAuthStore } from '../stores/auth'
import { isPasskeySupported } from '../lib/webauthn/webauthn'
import { refreshApp, updateAvailable } from '../lib/pwa/registerServiceWorker'

const { theme, toggleTheme } = useTheme()
const auth = useAuthStore()

const passkeySupported = isPasskeySupported()
const passkeyBusy = ref(false)
const passkeyError = ref<string | null>(null)
const isRefreshing = ref(false)

onMounted(() => {
  if (passkeySupported) void auth.fetchPasskeys()
})

async function addPasskey() {
  passkeyError.value = null
  passkeyBusy.value = true
  try {
    const name = window.prompt('Name this passkey (e.g. "My iPhone" or "Work laptop")', 'This device') ?? 'This device'
    await auth.addPasskey(name)
  } catch {
    passkeyError.value = 'Could not add a passkey. Please try again.'
  } finally {
    passkeyBusy.value = false
  }
}

async function removePasskey(id: number) {
  passkeyBusy.value = true
  try {
    await auth.removePasskey(id)
  } finally {
    passkeyBusy.value = false
  }
}

async function refreshTheApp() {
  isRefreshing.value = true
  try {
    await refreshApp()
  } finally {
    isRefreshing.value = false
  }
}
</script>

<template>
  <section class="space-y-6">
    <h1 class="text-2xl font-semibold">Settings</h1>

    <!-- Appearance -->
    <div class="rounded border border-stone-200 p-4 dark:border-stone-800">
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-500">Appearance</h2>
      <div class="flex items-center gap-3">
        <span>Theme: {{ theme }}</span>
        <button
          class="rounded border border-stone-300 px-3 py-1 dark:border-stone-700"
          @click="toggleTheme"
        >
          Toggle dark mode
        </button>
      </div>
      <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">
        Large font / senior-friendly mode and colour-blind friendly palettes are
        future-phase UI work (see docs/01-requirements-analysis.md).
      </p>
    </div>

    <!-- Security -->
    <div class="rounded border border-stone-200 p-4 dark:border-stone-800">
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-500">Security</h2>

      <div v-if="!passkeySupported" class="text-sm text-stone-400">
        Passkeys (Face ID / Touch ID / Windows Hello) aren't supported in this browser.
      </div>
      <div v-else class="space-y-3">
        <div class="flex items-center justify-between">
          <p class="text-sm text-stone-600 dark:text-stone-400">
            Sign in without a password using your device's biometrics.
          </p>
          <button
            class="rounded border border-stone-300 px-3 py-1 text-sm disabled:opacity-50 dark:border-stone-700"
            :disabled="passkeyBusy"
            @click="addPasskey"
          >
            {{ passkeyBusy ? 'Adding…' : '+ Add a passkey' }}
          </button>
        </div>

        <p v-if="passkeyError" class="text-sm text-red-600 dark:text-red-400">{{ passkeyError }}</p>
        <p v-if="auth.passkeysLoading" class="text-sm text-stone-400">Loading…</p>

        <ul v-else-if="auth.passkeys.length" class="space-y-2">
          <li
            v-for="passkey in auth.passkeys"
            :key="passkey.id"
            class="flex items-center justify-between rounded border border-stone-200 px-3 py-2 text-sm dark:border-stone-800"
          >
            <div>
              <p class="font-medium">{{ passkey.name }}</p>
              <p class="text-xs text-stone-500">
                {{ passkey.authenticator ?? 'Passkey' }} ·
                {{ passkey.last_used_at ? `last used ${new Date(passkey.last_used_at).toLocaleString()}` : 'never used' }}
              </p>
            </div>
            <button
              class="text-xs text-red-600 disabled:opacity-50 dark:text-red-400"
              :disabled="passkeyBusy"
              @click="removePasskey(passkey.id)"
            >
              Remove
            </button>
          </li>
        </ul>
        <p v-else class="text-sm text-stone-400">No passkeys registered yet.</p>
      </div>
    </div>

    <!-- App -->
    <div class="rounded border border-stone-200 p-4 dark:border-stone-800">
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-500">App</h2>
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm text-stone-600 dark:text-stone-400">
            Install this app from your browser's menu ("Add to Home Screen" /
            "Install app") for offline access and a full-screen experience.
          </p>
          <p v-if="updateAvailable" class="mt-1 text-xs text-emerald-700 dark:text-emerald-500">
            An update is ready to install.
          </p>
        </div>
        <button
          class="shrink-0 rounded border border-stone-300 px-3 py-1 text-sm disabled:opacity-50 dark:border-stone-700"
          :disabled="isRefreshing"
          @click="refreshTheApp"
        >
          {{ isRefreshing ? 'Refreshing…' : 'Refresh app' }}
        </button>
      </div>
    </div>
  </section>
</template>
