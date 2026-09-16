<script setup>
import { ref, onMounted } from 'vue'
import { RouterView, useRouter } from 'vue-router'
import LoginForm from './components/LoginForm.vue'
import Sidebar from './components/Sidebar.vue'
import api from './api.js'

const isLoggedIn = ref(false)
const userName = ref('')
const isSidebarOpen = ref(true)
const router = useRouter()

onMounted(() => {
  const token = localStorage.getItem('auth_token')
  if (token) {
    isLoggedIn.value = true
    userName.value = localStorage.getItem('user_name') || ''
  }
  isSidebarOpen.value = window.innerWidth > 768
})

function handleLoggedIn() {
  isLoggedIn.value = true
  userName.value = localStorage.getItem('user_name') || ''
  router.push('/')
}

function toggleSidebar() {
  isSidebarOpen.value = !isSidebarOpen.value
}

async function logout() {
  try {
    await api.post('/logout')
  } catch (e) {
    // même si l'appel échoue, on déconnecte localement
  }
  localStorage.removeItem('auth_token')
  localStorage.removeItem('user_name')
  isLoggedIn.value = false
}
</script>

<template>
  <div id="app">
    <div v-if="!isLoggedIn" class="login-page">
  <LoginForm @logged-in="handleLoggedIn" />
</div>

    <div v-else class="layout">
      <Sidebar :is-open="isSidebarOpen" @close="isSidebarOpen = false" />
      <button v-if="isSidebarOpen" class="sidebar-backdrop" aria-label="Fermer le menu" @click="toggleSidebar"></button>

      <div class="main-content">
        <header class="topbar">
          <div class="topbar-left">
            <button v-if="!isSidebarOpen" class="toggle-btn" aria-label="Ouvrir le menu" @click="toggleSidebar">
              <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            <div>
              <span class="eyebrow">ESPACE DE GESTION</span>
              <h1>Tableau de bord</h1>
            </div>
          </div>
          <div class="user-info">
            <div class="user-avatar">{{ userName.charAt(0).toUpperCase() || 'E' }}</div>
            <span class="user-greeting"><small>Connecté en tant que</small>{{ userName || 'Utilisateur' }}</span>
            <button class="logout-btn" @click="logout">Déconnexion</button>
          </div>
        </header>

        <RouterView />
      </div>
    </div>
  </div>
</template>

<style>
.layout {
  display: flex;
  min-height: 100vh;
}

.main-content {
  flex: 1;
  min-width: 0;
  padding: 28px clamp(20px, 4vw, 56px) 48px;
}

.topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  margin: 0 auto 28px;
  max-width: 1280px;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.toggle-btn {
  background: var(--color-surface);
  color: var(--color-text);
  border: 1px solid var(--color-border);
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
  width: 42px;
  height: 42px;
  border-radius: 10px;
  font-size: 20px;
  line-height: 1;
}

.toggle-btn:hover { color: var(--color-primary); border-color: #9dd0ce; }

.eyebrow {
  display: block;
  color: var(--color-primary);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.14em;
  margin-bottom: 3px;
}

.topbar h1 {
  color: var(--color-text);
  font-size: clamp(21px, 2vw, 28px);
  font-weight: 750;
  letter-spacing: -0.03em;
}

.user-avatar {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  color: #fff;
  background: var(--color-primary);
  border-radius: 50%;
  font-weight: 700;
}

.user-greeting {
  display: flex;
  flex-direction: column;
  color: var(--color-text);
  font-weight: 650;
  line-height: 1.25;
}

.user-greeting small { color: var(--color-text-muted); font-size: 11px; font-weight: 500; }

.sidebar-backdrop { display: none; }

.logout-btn {
  background: transparent;
  color: var(--color-text-muted);
  border: 1px solid var(--color-border);
  padding: 9px 13px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 650;
}

.logout-btn:hover { color: #b42318; border-color: #f2b8b5; background: #fff8f7; }

@media (max-width: 640px) {
  .main-content { padding: 18px 14px 32px; }
  .topbar { align-items: flex-start; margin-bottom: 20px; }
  .topbar-left { padding-left: 56px; }
  .toggle-btn { position: fixed; top: 18px; left: 14px; z-index: 30; }
  .user-avatar, .user-greeting { display: none; }
  .logout-btn { display: block; }
  .user-info { margin-left: auto; }
}

.login-page {
  width: 100vw;
  height: 100vh;

  display: flex;
  justify-content: center;
  align-items: center;
}
</style>