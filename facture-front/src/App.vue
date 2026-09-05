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
      <Sidebar :is-open="isSidebarOpen" />

      <div class="main-content">
        <header class="topbar">
          <div class="topbar-left">
            <button class="toggle-btn" @click="toggleSidebar">☰</button>
            <h1>ELFATOORA</h1>
          </div>
          <div class="user-info">
            <span>Bonjour, {{ userName }}</span>
            <button class="logout-btn" @click="logout">Déconnexion</button>
          </div>
        </header>

        <RouterView />
      </div>
    </div>
  </div>
</template>

<style>
body {
  font-family: Arial, sans-serif;
  background-color: #f4f4f4;
  margin: 0;
  padding: 20px;
}

.layout {
  display: flex;
  gap: 20px;
  max-width: 1100px;
  margin: 0 auto;
}

.main-content {
  flex: 1;
  min-width: 0;
}

.topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.toggle-btn {
  background: #1e293b;
  color: white;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 4px;
  font-size: 16px;
  cursor: pointer;
}

.toggle-btn:hover {
  background: #334155;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logout-btn {
  background: #dc2626;
  color: white;
  border: none;
  padding: 6px 12px;
  border-radius: 4px;
  cursor: pointer;
}

#app {
  width: 100%;
  min-height: 100vh;
}

.login-page {
  width: 100vw;
  height: 100vh;

  display: flex;
  justify-content: center;
  align-items: center;
}


</style>