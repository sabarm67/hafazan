import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiFetch } from '../lib/api'
import { createPasskeyCredential, getPasskeyAssertion } from '../lib/webauthn/webauthn'

export interface AuthUser {
  id: number
  name: string
  email: string
  locale: string
  timezone: string
  roles: string[]
}

export interface Passkey {
  id: number
  name: string
  authenticator: string | null
  last_used_at: string | null
  created_at: string
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const isLoading = ref(false)
  const passkeys = ref<Passkey[]>([])
  const passkeysLoading = ref(false)

  async function fetchCurrentUser(): Promise<void> {
    isLoading.value = true
    try {
      const { data } = await apiFetch<{ data: AuthUser }>('/api/v1/auth/me')
      user.value = data
    } catch {
      user.value = null
    } finally {
      isLoading.value = false
    }
  }

  async function login(email: string, password: string): Promise<void> {
    const { data } = await apiFetch<{ data: AuthUser }>('/api/v1/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    })
    user.value = data
  }

  async function register(payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<void> {
    const { data } = await apiFetch<{ data: AuthUser }>('/api/v1/auth/register', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
    user.value = data
  }

  async function logout(): Promise<void> {
    await apiFetch('/api/v1/auth/logout', { method: 'POST' })
    user.value = null
  }

  /** Runs the browser's passkey ("use a passkey") ceremony and signs in. */
  async function loginWithPasskey(): Promise<void> {
    const { data: options } = await apiFetch<{ data: Record<string, unknown> }>('/api/v1/auth/passkeys/login-options')
    const credential = await getPasskeyAssertion(options as never)
    const { data } = await apiFetch<{ data: AuthUser }>('/api/v1/auth/passkeys/login', {
      method: 'POST',
      body: JSON.stringify({ credential }),
    })
    user.value = data
  }

  async function fetchPasskeys(): Promise<void> {
    passkeysLoading.value = true
    try {
      const { data } = await apiFetch<{ data: Passkey[] }>('/api/v1/auth/passkeys')
      passkeys.value = data
    } finally {
      passkeysLoading.value = false
    }
  }

  /** Runs the browser's passkey ("create a passkey") ceremony and registers it. */
  async function addPasskey(name: string): Promise<void> {
    const { data: options } = await apiFetch<{ data: Record<string, unknown> }>('/api/v1/auth/passkeys/registration-options')
    const credential = await createPasskeyCredential(options as never)
    await apiFetch('/api/v1/auth/passkeys', {
      method: 'POST',
      body: JSON.stringify({ name, credential }),
    })
    await fetchPasskeys()
  }

  async function removePasskey(id: number): Promise<void> {
    await apiFetch(`/api/v1/auth/passkeys/${id}`, { method: 'DELETE' })
    await fetchPasskeys()
  }

  return {
    user,
    isLoading,
    passkeys,
    passkeysLoading,
    fetchCurrentUser,
    login,
    register,
    logout,
    loginWithPasskey,
    fetchPasskeys,
    addPasskey,
    removePasskey,
  }
})
