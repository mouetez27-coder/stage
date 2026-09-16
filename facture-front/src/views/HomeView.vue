<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../api.js'

const invoices = ref([])
const loading = ref(true)

async function fetchStats() {
  try {
    const response = await api.get('/invoices')
    invoices.value = response.data
  } catch (e) {
    // Ignorer si non connecté ou erreur
  } finally {
    loading.value = false
  }
}

const totalCount = computed(() => invoices.value.length)
const signedCount = computed(() => invoices.value.filter(inv => inv.is_signed || inv.signed_at).length)
const totalAmount = computed(() => invoices.value.reduce((sum, inv) => sum + Number(inv.total_ttc || 0), 0))

onMounted(fetchStats)
</script>

<template>
  <div class="home">
    <div class="welcome-header">
      <div>
        <h2>Bienvenue sur ELFATOORA</h2>
        <p class="subtitle">Plateforme de facturation électronique et de signature conforme TTN (Tunisie TradeNet)</p>
      </div>
      <div class="badge-compliance">
        <span class="compliance-dot"></span> Conforme TEIF v1.9.0 & XAdES-B v3.0
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon stat-icon-blue"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i></div>
        <div class="stat-content">
          <span class="stat-value">{{ loading ? '...' : totalCount }}</span>
          <span class="stat-label">Total Factures</span>
        </div>
      </div>

      <div class="stat-card stat-signed">
        <div class="stat-icon stat-icon-green"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
        <div class="stat-content">
          <span class="stat-value">{{ loading ? '...' : signedCount }}</span>
          <span class="stat-label">Factures Signées XAdES-B</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon stat-icon-gold"><i class="fa-solid fa-coins" aria-hidden="true"></i></div>
        <div class="stat-content">
          <span class="stat-value">{{ loading ? '...' : totalAmount.toFixed(3) }} TND</span>
          <span class="stat-label">Volume Total TTC</span>
        </div>
      </div>
    </div>

    <div class="quick-actions">
      <h3>Accès Rapide</h3>
      <div class="actions-buttons">
        <RouterLink to="/invoices/create" class="btn-primary">
          <i class="fa-solid fa-plus" aria-hidden="true"></i> Nouvelle Facture
        </RouterLink>
        <RouterLink to="/invoices" class="btn-secondary">
          <i class="fa-solid fa-list-check" aria-hidden="true"></i> Liste des Factures & Signatures
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<style scoped>
.home {
  max-width: 1280px;
  margin: 0 auto;
  background: var(--color-surface);
  padding: clamp(22px, 4vw, 42px);
  border: 1px solid var(--color-border);
  border-radius: 14px;
  box-shadow: var(--shadow-soft);
}

.welcome-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 28px;
  flex-wrap: wrap;
  gap: 16px;
}

.welcome-header h2 {
  margin: 0 0 6px 0;
  color: var(--color-text);
  font-size: clamp(24px, 3vw, 32px);
  font-weight: 750;
  letter-spacing: -0.04em;
}

.subtitle {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 14px;
  max-width: 650px;
}

.badge-compliance {
  background: #edf8f5;
  color: #17655e;
  border: 1px solid #c5e7df;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}

.compliance-dot {
  width: 8px;
  height: 8px;
  background: #22c55e;
  border-radius: 50%;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 38px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 22px;
  background: var(--color-surface-muted);
  border: 1px solid var(--color-border);
  border-radius: 10px;
  min-height: 92px;
}

.stat-signed {
  background: #edf8f5;
  border-color: #c5e7df;
}

.stat-icon {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  background: #fff;
  border-radius: 10px;
  font-size: 23px;
}

.stat-content {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-text);
}

.stat-label {
  font-size: 13px;
  color: #64748b;
}

.quick-actions h3 {
  font-size: 16px;
  color: var(--color-text);
  margin: 0 0 14px 0;
}

.actions-buttons {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
}

.btn-primary, .btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s;
}

.btn-primary {
  background: var(--color-primary);
  color: white;
}

.btn-primary:hover {
  background: var(--color-primary-dark);
}

@media (max-width: 560px) {
  .home { padding: 20px 16px; }
  .welcome-header { margin-bottom: 22px; }
  .badge-compliance { width: 100%; justify-content: center; text-align: center; }
  .actions-buttons, .btn-primary, .btn-secondary { width: 100%; }
  .btn-primary, .btn-secondary { justify-content: center; }
}

.btn-secondary {
  background: #f1f5f9;
  color: #334155;
  border: 1px solid #cbd5e1;
}

.btn-secondary:hover {
  background: #e2e8f0;
}
</style>