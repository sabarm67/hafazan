import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiFetch } from '../lib/api'

export interface AuthUser {
  id: number
  name: string
  email: string
  locale: string
  timezone: string
  roles: string[]
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const isLoading = ref(false)

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

  return { user, isLoading, fetchCurrentUser, login, register, logout }
})
