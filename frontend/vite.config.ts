import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    VitePWA({
      registerType: 'autoUpdate',
      manifest: {
        name: 'Al-Quran Hafazan System',
        short_name: 'Hafazan',
        description: 'Adaptive Quran memorisation (Hifz) platform',
        theme_color: '#065f46',
        background_color: '#ffffff',
        display: 'standalone',
        start_url: '/',
        icons: [
          { src: 'icon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any maskable' },
        ],
      },
      workbox: {
        // Quran text/audio caching strategy is a future-phase concern (see
        // docs/01-requirements-analysis.md, Offline Support). This is just
        // the app-shell precache so the PWA installs and boots offline.
        globPatterns: ['**/*.{js,css,html,svg,png,ico}'],
      },
    }),
  ],
  server: {
    // Backend runs on :8000 (docker-compose nginx service, or `php artisan
    // serve`'s default port). Proxying here keeps the browser same-origin
    // against :5173 in dev, sidestepping CORS entirely.
    proxy: {
      '/api': { target: 'http://localhost:8000', changeOrigin: true },
      '/sanctum': { target: 'http://localhost:8000', changeOrigin: true },
    },
  },
  build: {
    // Production (Forge) serves frontend + API from one Laravel site — see
    // docs/02-system-architecture.md "Production Deployment". `emptyOutDir:
    // false` is required so this doesn't wipe Laravel's own public/index.php,
    // .htaccess, favicon.ico, robots.txt on every build.
    outDir: '../backend/public',
    emptyOutDir: false,
  },
})
