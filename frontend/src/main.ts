import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'

const app = createApp(App)
app.use(createPinia())
app.use(router)

// Resolve auth state before mounting so route-guard checks in views (which
// mount before App.vue's own onMounted would otherwise fire) see the real
// logged-in/out state instead of a default null.
const auth = useAuthStore()
auth.fetchCurrentUser().finally(() => {
  app.mount('#app')
})
