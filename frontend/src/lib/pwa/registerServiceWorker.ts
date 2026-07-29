import { ref } from 'vue'

type UpdateSWFunction = (reloadPage?: boolean) => Promise<void>

/** True once vite-plugin-pwa detects a new service worker waiting to activate. */
export const updateAvailable = ref(false)

let updateSWFn: UpdateSWFunction | null = null

/**
 * Thin wrapper around vite-plugin-pwa's virtual register module. Kept in its
 * own file so the rest of the app never imports the virtual module directly
 * (which would break plain `vue-tsc`/vitest runs that don't go through Vite's
 * plugin pipeline).
 */
export async function registerAppServiceWorker(): Promise<void> {
  if (import.meta.env.MODE === 'test') return

  try {
    const { registerSW } = await import('virtual:pwa-register')
    updateSWFn = registerSW({
      immediate: true,
      onRegisteredSW(swUrl: string) {
        console.info(`[pwa] service worker registered: ${swUrl}`)
      },
      onRegisterError(error: unknown) {
        console.error('[pwa] service worker registration failed', error)
      },
      onNeedRefresh() {
        updateAvailable.value = true
      },
      onOfflineReady() {
        console.info('[pwa] ready to work offline')
      },
    })
  } catch (error) {
    // virtual:pwa-register is only resolvable in a Vite build/dev context.
    console.warn('[pwa] service worker registration skipped', error)
  }
}

/**
 * Applies a pending service worker update and reloads, or — if there's
 * nothing registered to update (dev/test, or registration failed) — just
 * does a plain reload. Used by the Settings page's "Refresh app" button.
 */
export async function refreshApp(): Promise<void> {
  if (updateSWFn) {
    await updateSWFn(true)
    return
  }
  window.location.reload()
}
