<script setup>
import { RouterLink } from 'vue-router'

const emit = defineEmits(['close'])

defineProps({
  isOpen: {
    type: Boolean,
    default: true,
  },
})
</script>

<template>
  <aside class="sidebar" :class="{ closed: !isOpen }">
    <div class="sidebar-brand">
      <div class="brand-mark">EF</div>
      <div class="brand-copy"><strong>ELFATOORA</strong><span>Gestion digitale</span></div>
      <button class="mobile-close" aria-label="Fermer le menu" @click="emit('close')">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
    </div>
    <p class="nav-caption">NAVIGATION</p>
    <nav>
      <RouterLink to="/" class="menu-item">
        <i class="icon fa-solid fa-house" aria-hidden="true"></i>
        <span class="label">Accueil</span>
      </RouterLink>
      <RouterLink to="/invoices/create" class="menu-item">
        <i class="icon fa-solid fa-file-circle-plus" aria-hidden="true"></i>
        <span class="label">Générer facture</span>
      </RouterLink>
      <RouterLink to="/invoices" class="menu-item">
        <i class="icon fa-solid fa-file-invoice" aria-hidden="true"></i>
        <span class="label">Liste des factures</span>
      </RouterLink>
    </nav>
  </aside>
</template>

<style scoped>
.sidebar {
  width: 252px;
  min-height: 100vh;
  background: #102f35;
  padding: 28px 14px;
  overflow: hidden;
  transition: all 0.3s ease;
  flex-shrink: 0;
  box-shadow: 8px 0 24px rgba(15, 23, 42, 0.05);
  position: relative;
  z-index: 10;
}

.sidebar.closed {
  width: 80px;
}

.sidebar-brand { display: flex; align-items: center; gap: 11px; padding: 0 10px 34px; }
.mobile-close { display: grid; place-items: center; margin-left: auto; width: 34px; height: 34px; border: 1px solid rgba(255, 255, 255, 0.16); border-radius: 8px; background: transparent; color: #b7cdcd; }
.mobile-close:hover { color: #fff; background: rgba(255, 255, 255, 0.08); }
.sidebar.closed .mobile-close { display: none; }
.brand-mark { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 10px; background: var(--color-accent); color: #173a3c; font-weight: 800; font-size: 12px; }
.brand-copy { display: flex; flex-direction: column; color: #fff; line-height: 1.2; white-space: nowrap; }
.brand-copy strong { font-size: 14px; letter-spacing: 0.04em; }
.brand-copy span { color: #98b5b6; font-size: 10px; margin-top: 3px; }
.sidebar.closed .brand-copy { opacity: 0; width: 0; overflow: hidden; }
.nav-caption { color: #709294; font-size: 10px; font-weight: 800; letter-spacing: 0.14em; padding: 0 14px; margin: 0 0 10px; white-space: nowrap; }

nav {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.menu-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 13px 14px;
  color: #b7cdcd;
  text-decoration: none;
  font-size: 15px;
  font-weight: 500;
  border-radius: 8px;
  white-space: nowrap;
  transition: all 0.25s ease;
}

.sidebar.closed .menu-item {
  justify-content: center;
  padding: 14px;
}

.sidebar.closed .nav-caption { opacity: 0; }

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
  background: rgba(255, 255, 255, 0.08);
  color: #ffffff;
  transform: translateX(3px);
}

.router-link-exact-active {
  background: var(--color-primary);
  color: white;
  font-weight: 600;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.16);
}

.router-link-exact-active .icon {
  transform: scale(1.1);
}

.menu-item:active {
  transform: scale(0.98);
}

@media (max-width: 768px) {
  .sidebar {
    width: 252px;
    position: fixed;
    inset: 0 auto 0 0;
    transform: translateX(0);
    z-index: 20;
  }
  .sidebar.closed {
    width: 252px;
    transform: translateX(-100%);
  }
  .sidebar-backdrop { display: block; position: fixed; inset: 0; border: 0; background: rgba(15, 23, 42, 0.42); z-index: 15; }
}
</style>