<script setup>
import { ref, onMounted } from 'vue'
import api from '../api.js'

const invoices = ref([])
const loading = ref(true)
const errorMessage = ref('')
const actionMessage = ref('')
const actionLoading = ref({}) // { [invoiceId]: 'xml' | 'pdf' | 'email' | 'sign' | 'verify' | null }

// État de la modale d'audit de signature
const isVerifyModalOpen = ref(false)
const selectedInvoiceNumber = ref('')
const verificationReport = ref(null)
const verificationLoading = ref(false)

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
    let urlPath = `/invoices/${invoice.id}/download-${type}`
    let filename = `${invoice.invoice_number}.${type}`

    if (type === 'signed-xml') {
      urlPath = `/invoices/${invoice.id}/download-signed-xml`
      filename = `${invoice.invoice_number}-signed.xml`
    }

    const response = await api.get(urlPath, {
      responseType: 'blob',
    })

    const blobUrl = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = blobUrl
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (error) {
    actionMessage.value = `Le fichier ${type.toUpperCase()} n'est pas disponible pour ${invoice.invoice_number}.`
  } finally {
    setActionLoading(invoice.id, null)
  }
}

async function signXml(invoice) {
  setActionLoading(invoice.id, 'sign')
  actionMessage.value = ''
  try {
    const response = await api.post(`/invoices/${invoice.id}/sign-xml`)
    actionMessage.value = `Facture ${invoice.invoice_number} signée électroniquement avec succès (XAdES-B TTN).`
    await fetchInvoices()
  } catch (error) {
    const msg = error.response?.data?.message || `Erreur lors de la signature de ${invoice.invoice_number}.`
    actionMessage.value = msg
  } finally {
    setActionLoading(invoice.id, null)
  }
}

async function verifySignature(invoice) {
  setActionLoading(invoice.id, 'verify')
  selectedInvoiceNumber.value = invoice.invoice_number
  verificationLoading.value = true
  isVerifyModalOpen.value = true
  verificationReport.value = null

  try {
    const response = await api.post(`/invoices/${invoice.id}/verify-signature`)
    verificationReport.value = response.data.report
  } catch (error) {
    verificationReport.value = {
      is_valid: false,
      errors: [error.response?.data?.message || 'Impossible de vérifier la signature.'],
      checks: {},
    }
  } finally {
    verificationLoading.value = false
    setActionLoading(invoice.id, null)
  }
}

function closeVerifyModal() {
  isVerifyModalOpen.value = false
  verificationReport.value = null
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

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

function formatDateTime(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

onMounted(fetchInvoices)
</script>

<template>
  <div class="invoice-list">
    <div class="header-section">
      <div>
        <h2>Factures</h2>
        <p class="subtitle">Gestion et signature électronique conforme TTN (El Fatoora v3.0 / TEIF v1.9.0)</p>
      </div>
    </div>

    <div v-if="actionMessage" class="alert info">{{ actionMessage }}</div>
    <div v-if="errorMessage" class="alert error">{{ errorMessage }}</div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Chargement des factures...</p>
    </div>

    <div v-else class="table-container">
      <table>
        <thead>
          <tr>
            <th>N° Facture</th>
            <th>Client</th>
            <th>Date</th>
            <th>Total TTC</th>
            <th>Statut Envoi</th>
            <th>Signature Électronique</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="invoice in invoices" :key="invoice.id">
            <td class="font-bold">{{ invoice.invoice_number }}</td>
            <td>{{ invoice.client_name }}</td>
            <td>{{ formatDate(invoice.invoice_date) }}</td>
            <td class="font-bold">{{ Number(invoice.total_ttc).toFixed(3) }} {{ invoice.currency }}</td>
            <td>
              <span v-if="invoice.sent_at" class="badge sent"><i class="fa-solid fa-check" aria-hidden="true"></i> Envoyée</span>
              <span v-else class="badge draft">Non envoyée</span>
            </td>
            <td>
              <div v-if="invoice.is_signed || invoice.signed_at" class="signature-status">
                <span class="badge signed"><i class="fa-solid fa-check" aria-hidden="true"></i> Signée (XAdES-B)</span>
                <span class="signature-date">{{ formatDateTime(invoice.signed_at) }}</span>
              </div>
              <div v-else>
                <span class="badge unsigned">Non signée</span>
              </div>
            </td>
            <td class="actions">
              <div class="action-group">
                <span class="action-group-label">Documents</span>
                <div class="action-buttons">
                  <button class="action-button" @click="generateXml(invoice)" :disabled="actionLoading[invoice.id] === 'xml'" title="Générer le fichier TEIF XML">
                    <i class="fa-solid fa-file-code" aria-hidden="true"></i>
                    {{ actionLoading[invoice.id] === 'xml' ? 'Génération...' : 'Générer XML' }}
                  </button>
                  <button class="action-button" @click="downloadFile(invoice, 'pdf')" :disabled="actionLoading[invoice.id] === 'pdf'" title="Télécharger le PDF">
                    <i class="fa-solid fa-file-pdf" aria-hidden="true"></i> PDF
                  </button>
                  <button v-if="invoice.xml_path" class="action-button" @click="downloadFile(invoice, 'xml')" :disabled="actionLoading[invoice.id] === 'xml'" title="Télécharger le XML brut">
                    <i class="fa-solid fa-download" aria-hidden="true"></i> XML
                  </button>
                  <button v-if="invoice.is_signed || invoice.signed_xml_path" class="action-button btn-signed-xml" @click="downloadFile(invoice, 'signed-xml')" :disabled="actionLoading[invoice.id] === 'signed-xml'" title="Télécharger le XML officiel signé TTN">
                    <i class="fa-solid fa-file-shield" aria-hidden="true"></i> XML signé
                  </button>
                </div>
              </div>

              <div class="action-group">
                <span class="action-group-label">Signature</span>
                <div class="action-buttons">
                  <button class="action-button btn-sign" @click="signXml(invoice)" :disabled="actionLoading[invoice.id] === 'sign'" title="Signer électroniquement la facture (norme TTN XAdES-B)">
                    <i class="fa-solid fa-signature" aria-hidden="true"></i>
                    {{ actionLoading[invoice.id] === 'sign' ? 'Signature...' : (invoice.is_signed ? 'Re-signer' : 'Signer') }}
                  </button>
                  <button v-if="invoice.is_signed || invoice.signed_xml_path" class="action-button btn-verify" @click="verifySignature(invoice)" :disabled="actionLoading[invoice.id] === 'verify'" title="Vérifier la validité cryptographique de la signature">
                    <i class="fa-solid fa-shield-check" aria-hidden="true"></i>
                    {{ actionLoading[invoice.id] === 'verify' ? 'Vérification...' : 'Vérifier' }}
                  </button>
                </div>
              </div>

              <div class="action-group">
                <span class="action-group-label">Envoi</span>
                <div class="action-buttons">
                  <button class="action-button btn-email" @click="sendEmail(invoice)" :disabled="actionLoading[invoice.id] === 'email'" title="Envoyer la facture par email au client">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    {{ actionLoading[invoice.id] === 'email' ? 'Envoi...' : 'Envoyer par email' }}
                  </button>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modale de Vérification et d'Audit de Signature -->
    <div v-if="isVerifyModalOpen" class="modal-overlay" @click.self="closeVerifyModal">
      <div class="modal-card">
        <div class="modal-header">
          <div>
            <h3>Audit de la Signature Électronique</h3>
            <p class="modal-subtitle">Facture {{ selectedInvoiceNumber }} — Norme XAdES-B (TTN El Fatoora)</p>
          </div>
          <button class="close-btn" @click="closeVerifyModal">&times;</button>
        </div>

        <div class="modal-body">
          <div v-if="verificationLoading" class="modal-loading">
            <div class="spinner"></div>
            <p>Vérification cryptographique en cours...</p>
          </div>

          <div v-else-if="verificationReport">
            <!-- Statut Global -->
            <div
              class="validation-banner"
              :class="verificationReport.is_valid ? 'banner-success' : 'banner-error'"
            >
              <div class="banner-icon"><i :class="verificationReport.is_valid ? 'fa-solid fa-check' : 'fa-solid fa-xmark'" aria-hidden="true"></i></div>
              <div>
                <h4 class="banner-title">
                  {{ verificationReport.is_valid ? 'Signature Cryptographiquement Valide' : 'Signature Invalide ou Altérée' }}
                </h4>
                <p class="banner-desc">
                  {{ verificationReport.is_valid
                    ? 'Le document TEIF respecte l\'intégrité des données, la signature RSA-SHA256 est authentifiée et conforme aux spécifications TTN.'
                    : 'Des anomalies ont été détectées lors du contrôle de cohérence cryptographique.' }}
                </p>
              </div>
            </div>

            <!-- Erreurs éventuelles -->
            <div v-if="verificationReport.errors && verificationReport.errors.length > 0" class="report-errors">
              <h5>Anomalies détectées :</h5>
              <ul>
                <li v-for="(err, idx) in verificationReport.errors" :key="idx">{{ err }}</li>
              </ul>
            </div>

            <!-- Grille des détails -->
            <div class="details-grid">
              <!-- Signataire -->
              <div class="detail-card">
                <h5>Signataire & Rôle</h5>
                <p><strong>Nom (CN) :</strong> {{ verificationReport.signer?.common_name || '-' }}</p>
                <p><strong>Organisation :</strong> {{ verificationReport.signer?.organization || '-' }}</p>
                <p><strong>Pays :</strong> {{ verificationReport.signer?.country || 'TN' }}</p>
                <p><strong>Rôle revendiqué :</strong> {{ verificationReport.metadata?.claimed_role || 'Fournisseur' }}</p>
              </div>

              <!-- Certificat X.509 -->
              <div class="detail-card">
                <h5>Certificat Numérique X.509</h5>
                <p><strong>Émetteur :</strong> {{ verificationReport.certificate?.issuer || '-' }}</p>
                <p><strong>N° de série :</strong> {{ verificationReport.certificate?.serial_number || '-' }}</p>
                <p><strong>Valide du :</strong> {{ formatDateTime(verificationReport.certificate?.valid_from) }}</p>
                <p><strong>Valide jusqu'au :</strong> {{ formatDateTime(verificationReport.certificate?.valid_to) }}</p>
              </div>

              <!-- Horodatage & Politique TTN -->
              <div class="detail-card full-width">
                <h5>Politique & Horodatage Officiels TTN</h5>
                <p><strong>Date & Heure de Signature (UTC) :</strong> {{ verificationReport.signed_at || verificationReport.metadata?.signing_time || '-' }}</p>
                <p><strong>OID Politique :</strong> <code>{{ verificationReport.metadata?.policy_oid || 'urn:2.16.788.1.2.1.3' }}</code></p>
                <p v-if="verificationReport.metadata?.policy_url">
                  <strong>URL Politique :</strong>
                  <a :href="verificationReport.metadata.policy_url" target="_blank" rel="noopener noreferrer">Consulter la politique TTN</a>
                </p>
              </div>

              <!-- Contrôles Cryptographiques Individuels -->
              <div class="detail-card full-width">
                <h5>Contrôles d'Intégrité Détaillés</h5>
                <ul class="checks-list">
                  <li>
                    <span class="check-icon" :class="verificationReport.checks?.rsa_signature ? 'ok' : 'ko'">
                      <i :class="verificationReport.checks?.rsa_signature ? 'fa-solid fa-check' : 'fa-solid fa-xmark'" aria-hidden="true"></i>
                    </span>
                    <span>Signature RSA-SHA256 (SignedInfo) avec clé publique X.509</span>
                  </li>
                  <li>
                    <span class="check-icon" :class="verificationReport.checks?.document_digest ? 'ok' : 'ko'">
                      <i :class="verificationReport.checks?.document_digest ? 'fa-solid fa-check' : 'fa-solid fa-xmark'" aria-hidden="true"></i>
                    </span>
                    <span>Intégrité du document TEIF (Digest Reference <code>r-id-frs</code>)</span>
                  </li>
                  <li>
                    <span class="check-icon" :class="verificationReport.checks?.signed_properties_digest ? 'ok' : 'ko'">
                      <i :class="verificationReport.checks?.signed_properties_digest ? 'fa-solid fa-check' : 'fa-solid fa-xmark'" aria-hidden="true"></i>
                    </span>
                    <span>Intégrité des propriétés XAdES (Digest Reference <code>#xades-SigFrs</code>)</span>
                  </li>
                  <li>
                    <span class="check-icon" :class="verificationReport.checks?.certificate_digest ? 'ok' : 'ko'">
                      <i :class="verificationReport.checks?.certificate_digest ? 'fa-solid fa-check' : 'fa-solid fa-xmark'" aria-hidden="true"></i>
                    </span>
                    <span>Empreinte SHA-1 du certificat (<code>CertDigest</code> conforme Figure 22 TTN)</span>
                  </li>
                  <li>
                    <span class="check-icon" :class="verificationReport.checks?.certificate_validity ? 'ok' : 'ko'">
                      <i :class="verificationReport.checks?.certificate_validity ? 'fa-solid fa-check' : 'fa-solid fa-xmark'" aria-hidden="true"></i>
                    </span>
                    <span>Validité temporelle du certificat X.509 au moment du contrôle</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-close" @click="closeVerifyModal">Fermer</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.invoice-list {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px;
}

.header-section {
  margin-bottom: 20px;
}

.header-section h2 {
  margin: 0 0 4px 0;
  color: #0f172a;
  font-size: 24px;
}

.subtitle {
  margin: 0;
  color: #64748b;
  font-size: 14px;
}

.table-container {
  overflow-x: auto;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

th, td {
  padding: 12px 14px;
  border-bottom: 1px solid #e2e8f0;
  font-size: 13px;
}

th {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 11px;
  letter-spacing: 0.5px;
}

.font-bold {
  font-weight: 600;
  color: #1e293b;
}

.signature-status {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.signature-date {
  font-size: 11px;
  color: #64748b;
}

.actions {
  min-width: 330px;
  padding: 10px 12px;
  background: #f8fafc;
  vertical-align: top;
}

.action-group + .action-group {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #e2e8f0;
}

.action-group-label {
  display: block;
  margin-bottom: 5px;
  color: #94a3b8;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.action-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.action-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 30px;
  padding: 6px 9px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #f8fafc;
  color: #334155;
  font-size: 12px;
  font-weight: 500;
  transition: all 0.2s;
}

.action-button i {
  font-size: 11px;
}

.action-button:hover:not(:disabled) {
  background: #e2e8f0;
  border-color: #94a3b8;
}

.action-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-sign {
  background: var(--color-primary) !important;
  color: white !important;
  border-color: var(--color-primary-dark) !important;
}

.btn-sign:hover:not(:disabled) {
  background: var(--color-primary-dark) !important;
}

.btn-verify {
  background: #0f766e !important;
  color: white !important;
  border-color: #0b625d !important;
}

.btn-verify:hover:not(:disabled) {
  background: #0b625d !important;
}

.btn-signed-xml {
  background: #f0fdf4 !important;
  color: #166534 !important;
  border-color: #86efac !important;
  font-weight: 600;
}

.btn-email {
  background: #dcefe9 !important;
  color: #17655e !important;
  border-color: #a8d8ca !important;
}

.btn-email:hover:not(:disabled) {
  background: #c5e7df !important;
}

@media (max-width: 768px) {
  .invoice-list { padding: 18px 0; }
  .header-section { padding: 0 16px; }
  .table-container { border-radius: 0; }
  .actions { min-width: 330px; }
}

.badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
}

.badge.sent {
  background: #dcfce7;
  color: #166534;
}

.badge.draft {
  background: #fef3c7;
  color: #92400e;
}

.badge.signed {
  background: #dbeafe;
  color: #1e40af;
}

.badge.unsigned {
  background: #f1f5f9;
  color: #64748b;
}

.alert {
  padding: 12px 16px;
  border-radius: 6px;
  margin-bottom: 16px;
  font-size: 14px;
}

.alert.info {
  background: #eff6ff;
  color: #1d4ed8;
  border-left: 4px solid #3b82f6;
}

.alert.error {
  background: #fef2f2;
  color: #b91c1c;
  border-left: 4px solid #ef4444;
}

/* Modale */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 20px;
}

.modal-card {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 680px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
}

.modal-header {
  padding: 18px 24px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0 0 4px 0;
  font-size: 18px;
  color: #0f172a;
}

.modal-subtitle {
  margin: 0;
  font-size: 13px;
  color: #64748b;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  color: #64748b;
  cursor: pointer;
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
}

.validation-banner {
  display: flex;
  gap: 16px;
  align-items: center;
  padding: 14px 18px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.banner-success {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
}

.banner-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

.banner-icon {
  font-size: 28px;
  font-weight: bold;
}

.banner-title {
  margin: 0 0 4px 0;
  font-size: 15px;
}

.banner-desc {
  margin: 0;
  font-size: 13px;
  opacity: 0.9;
}

.report-errors {
  background: #fff1f2;
  border-left: 4px solid #f43f5e;
  padding: 12px 16px;
  border-radius: 4px;
  margin-bottom: 20px;
  font-size: 13px;
  color: #9f1239;
}

.report-errors h5 {
  margin: 0 0 6px 0;
}

.report-errors ul {
  margin: 0;
  padding-left: 20px;
}

.details-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.detail-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 14px;
  font-size: 12px;
}

.detail-card.full-width {
  grid-column: span 2;
}

.detail-card h5 {
  margin: 0 0 10px 0;
  font-size: 13px;
  color: #1e293b;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 6px;
}

.detail-card p {
  margin: 0 0 6px 0;
  color: #475569;
}

.detail-card code {
  background: #e2e8f0;
  padding: 2px 4px;
  border-radius: 3px;
  font-family: monospace;
}

.checks-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.checks-list li {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
  color: #334155;
}

.check-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  font-size: 11px;
  font-weight: bold;
}

.check-icon.ok {
  background: #dcfce7;
  color: #166534;
}

.check-icon.ko {
  background: #fee2e2;
  color: #991b1b;
}

.modal-footer {
  padding: 14px 24px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
}

.btn-close {
  padding: 8px 18px;
  background: #1e293b;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
}

.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #e2e8f0;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 10px auto;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.loading-state, .modal-loading {
  text-align: center;
  padding: 40px;
  color: #64748b;
  font-size: 14px;
}
</style>