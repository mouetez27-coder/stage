<script setup>
import { ref, onMounted } from 'vue'
import api from '../api.js'

const invoices = ref([])
const loading = ref(true)
const errorMessage = ref('')
const actionMessage = ref('')
const actionLoading = ref({}) // { [invoiceId]: 'xml' | 'pdf' | 'email' | null }

async function fetchInvoices() {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await api.get('/invoices')
    invoices.value = response.data
  } catch (error) {
    errorMessage.value = "Impossible de charger les factures."
  } finally {
    loading.value = false
  }
}

function setActionLoading(invoiceId, action) {
  actionLoading.value = { ...actionLoading.value, [invoiceId]: action }
}

async function generateXml(invoice) {
  setActionLoading(invoice.id, 'xml')
  actionMessage.value = ''
  try {
    await api.post(`/invoices/${invoice.id}/generate-xml`)
    actionMessage.value = `XML généré pour ${invoice.invoice_number}.`
    await fetchInvoices()
  } catch (error) {
    actionMessage.value = `Erreur lors de la génération XML pour ${invoice.invoice_number}.`
  } finally {
    setActionLoading(invoice.id, null)
  }
}

async function downloadFile(invoice, type) {
  setActionLoading(invoice.id, type)
  try {
    const response = await api.get(`/invoices/${invoice.id}/download-${type}`, {
      responseType: 'blob',
    })

    const extension = type === 'pdf' ? 'pdf' : 'xml'
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `${invoice.invoice_number}.${extension}`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (error) {
    actionMessage.value = `Le fichier ${type.toUpperCase()} n'existe pas encore pour ${invoice.invoice_number}. Générez-le d'abord.`
  } finally {
    setActionLoading(invoice.id, null)
  }
}

async function signXml(invoice) {
  setActionLoading(invoice.id, 'sign')
  actionMessage.value = ''
  try {
    await api.post(`/invoices/${invoice.id}/sign-xml`)
    actionMessage.value = `XML signé pour ${invoice.invoice_number}.`
    await fetchInvoices()
  } catch (error) {
    const msg = error.response?.data?.message || `Erreur lors de la signature de ${invoice.invoice_number}.`
    actionMessage.value = msg
  } finally {
    setActionLoading(invoice.id, null)
  }
}

async function sendEmail(invoice) {
  if (!invoice.client_email) {
    actionMessage.value = `Le client de ${invoice.invoice_number} n'a pas d'adresse email.`
    return
  }

  setActionLoading(invoice.id, 'email')
  actionMessage.value = ''
  try {
    await api.post(`/invoices/${invoice.id}/send-email`)
    actionMessage.value = `Facture ${invoice.invoice_number} envoyée par email à ${invoice.client_email}.`
    await fetchInvoices()
  } catch (error) {
    const msg = error.response?.data?.message || "Erreur lors de l'envoi de l'email."
    actionMessage.value = msg
  } finally {
    setActionLoading(invoice.id, null)
  }
}

onMounted(fetchInvoices)
</script>

<template>
  <div class="invoice-list">
    <h2>Factures</h2>

    <div v-if="actionMessage" class="alert info">{{ actionMessage }}</div>
    <div v-if="errorMessage" class="alert error">{{ errorMessage }}</div>

    <p v-if="loading">Chargement...</p>

    <table v-else>
      <thead>
        <tr>
          <th>N° Facture</th>
          <th>Client</th>
          <th>Date</th>
          <th>Total TTC</th>
          <th>Statut envoi</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="invoice in invoices" :key="invoice.id">
          <td>{{ invoice.invoice_number }}</td>
          <td>{{ invoice.client_name }}</td>
          <td>{{ new Date(invoice.invoice_date).toLocaleDateString('fr-FR') }}</td>
          <td>{{ Number(invoice.total_ttc).toFixed(3) }} {{ invoice.currency }}</td>
          <td>
            <span v-if="invoice.sent_at" class="badge sent">Envoyée</span>
            <span v-else class="badge draft">Non envoyée</span>
          </td>
          <td class="actions">
            <button
              @click="generateXml(invoice)"
              :disabled="actionLoading[invoice.id] === 'xml'"
            >
              {{ actionLoading[invoice.id] === 'xml' ? '...' : 'Générer XML' }}
            </button>


            <button
              @click="downloadFile(invoice, 'pdf')"
              :disabled="actionLoading[invoice.id] === 'pdf'"
            >
              {{ actionLoading[invoice.id] === 'pdf' ? '...' : 'PDF' }}
            </button>

            <button
              @click="downloadFile(invoice, 'xml')"
              :disabled="actionLoading[invoice.id] === 'xml_download'"
            >
              XML
            </button>

            <button
              class="btn-email"
              @click="sendEmail(invoice)"
              :disabled="actionLoading[invoice.id] === 'email'"
            >
              {{ actionLoading[invoice.id] === 'email' ? 'Envoi...' : 'Envoyer par email' }}
            </button>

            <button
              @click="signXml(invoice)"
              :disabled="actionLoading[invoice.id] === 'sign'"
            >
              {{ actionLoading[invoice.id] === 'sign' ? '...' : 'Signer' }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.invoice-list {
  width: 100%;
  max-width: 1100px;
  margin: 0 auto;
  padding: 20px;
}

table {
  width: 900px;
  border-collapse: collapse;
  margin-top: 15px;
}

th, td {
  border: 1px solid #ddd;
  padding: 8px 10px;
  text-align: left;
  font-size: 14px;
}

th {
  background: #f3f4f6;
}

.actions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.actions button {
  padding: 6px 10px;
  border: none;
  border-radius: 4px;
  background: #e5e7eb;
  cursor: pointer;
  font-size: 12px;
}

.actions button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-email {
  background: #16a34a !important;
  color: white;
}

.badge {
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 12px;
}

.badge.sent {
  background: #dcfce7;
  color: #166534;
}

.badge.draft {
  background: #fef3c7;
  color: #92400e;
}

.alert {
  padding: 10px 15px;
  border-radius: 4px;
  margin-bottom: 15px;
  font-size: 14px;
}

.alert.info {
  background: #dbeafe;
  color: #1e40af;
}

.alert.error {
  background: #fee2e2;
  color: #991b1b;
}
</style>