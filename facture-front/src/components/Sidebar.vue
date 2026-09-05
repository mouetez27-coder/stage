<script setup>
import { RouterLink } from 'vue-router'

defineProps({
  isOpen: {
    type: Boolean,
    default: true,
  },
})
</script>

<template>
  <aside class="sidebar" :class="{ closed: !isOpen }">
    <nav>
      <RouterLink to="/" class="menu-item">
        <span class="icon">🏠</span>
        <span class="label">Accueil</span>
      </RouterLink>
      <RouterLink to="/invoices/create" class="menu-item">
        <span class="icon">🧾</span>
        <span class="label">Générer facture</span>
      </RouterLink>
      <RouterLink to="/invoices" class="menu-item">
        <span class="icon">📄</span>
        <span class="label">Liste des factures</span>
      </RouterLink>
    </nav>
  </aside>
</template>

<style scoped>
.sidebar {
  width: 250px;
  min-height: calc(100vh - 40px);
  background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
  border-radius: 16px;
  padding: 20px 12px;
  overflow: hidden;
  transition: all 0.3s ease;
  flex-shrink: 0;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.sidebar.closed {
  width: 80px;
}

nav {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.menu-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 18px;
  color: #cbd5e1;
  text-decoration: none;
  font-size: 15px;
  font-weight: 500;
  border-radius: 12px;
  white-space: nowrap;
  transition: all 0.25s ease;
}

.sidebar.closed .menu-item {
  justify-content: center;
  padding: 14px;
}

.sidebar.closed .label {
  opacity: 0;
  width: 0;
  overflow: hidden;
}

.icon {
  font-size: 20px;
  min-width: 24px;
  text-align: center;
}

.menu-item:hover {
  background: rgba(59, 130, 246, 0.15);
  color: #ffffff;
  transform: translateX(4px);
}

.router-link-exact-active {
  background: #2563eb;
  color: white;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
}

.router-link-exact-active .icon {
  transform: scale(1.1);
}

.menu-item:active {
  transform: scale(0.98);
}

@media (max-width: 768px) {
  .sidebar {
    width: 80px;
  }

  .label {
    display: none;
  }

  .menu-item {
    justify-content: center;
    padding: 14px;
  }
}
</style>