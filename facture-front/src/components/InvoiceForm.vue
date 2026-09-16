<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api.js'

const router = useRouter()

const today = new Date().toISOString().split('T')[0]

const form = ref({
  invoice_number: '',
  invoice_date: today,
  due_date: '',
  payment_method: 'Virement',
  currency: 'TND',

  client_name: '',
  client_tax_number: '',
  client_address: '',
  client_city: '',
  client_postal_code: '1000',
  client_country: 'TN',
  client_phone: '',
  client_email: '',
})

const items = ref([
  { code: 'ART-01', designation: 'Prestation de service', quantity: 1, unit: 'UNIT', unit_price: 150.000, vat_rate: 19, discount: 0 }
])

const successMessage = ref('')
const errorMessage = ref('')
const errors = ref({})
const loading = ref(false)

function addItem() {
  items.value.push({ code: '', designation: '', quantity: 1, unit: 'UNIT', unit_price: 0, vat_rate: 19, discount: 0 })
}

function removeItem(index) {
  if (items.value.length > 1) {
    items.value.splice(index, 1)
  }
}

function lineTotal(item) {
  const ht = (item.quantity * item.unit_price) - (item.discount || 0)
  return ht > 0 ? ht : 0
}

const totalHT = computed(() => {
  return items.value.reduce((sum, item) => sum + lineTotal(item), 0)
})

const totalVAT = computed(() => {
  return items.value.reduce((sum, item) => {
    return sum + (lineTotal(item) * (item.vat_rate / 100))
  }, 0)
})

const stampDuty = 1.000

const totalTTC = computed(() => {
  return totalHT.value + totalVAT.value + stampDuty
})

async function submitInvoice() {
  successMessage.value = ''
  errorMessage.value = ''
  errors.value = {}
  loading.value = true

  try {
    const payload = {
      ...form.value,
      items: items.value,
    }

    const response = await api.post('/invoices', payload)

    successMessage.value = response.data.message || 'Facture enregistrée avec succès.'
    resetForm()

    // Redirige vers la liste des factures après un bref instant
    setTimeout(() => {
      router.push('/invoices')
    }, 1200)
  } catch (error) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
      errorMessage.value = 'Veuillez vérifier les champs indiqués ci-dessous.'
    } else {
      errorMessage.value = error.response?.data?.message || 'Une erreur est survenue lors de la création de la facture.'
    }
  } finally {
    loading.value = false
  }
}

function resetForm() {
  form.value = {
    invoice_number: '',
    invoice_date: today,
    due_date: '',
    payment_method: 'Virement',
    currency: 'TND',

    client_name: '',
    client_tax_number: '',
    client_address: '',
    client_city: '',
    client_postal_code: '1000',
    client_country: 'TN',
    client_phone: '',
    client_email: '',
  }
  items.value = [
    { code: 'ART-01', designation: 'Prestation de service', quantity: 1, unit: 'UNIT', unit_price: 150.000, vat_rate: 19, discount: 0 }
  ]
}
</script>

<template>
  <div class="invoice-form">
    <h2>Créer une facture</h2>

    <div v-if="successMessage" class="alert success">{{ successMessage }}</div>
    <div v-if="errorMessage" class="alert error">{{ errorMessage }}</div>

    <form @submit.prevent="submitInvoice">

      <fieldset>
        <legend>Informations de la facture</legend>

        <div class="field">
          <label>Numéro de facture</label>
          <input v-model="form.invoice_number" type="text" placeholder="FAC-0001" />
          <span class="field-error" v-if="errors.invoice_number">{{ errors.invoice_number[0] }}</span>
        </div>

        <div class="field">
          <label>Date de facture</label>
          <input v-model="form.invoice_date" type="date" />
          <span class="field-error" v-if="errors.invoice_date">{{ errors.invoice_date[0] }}</span>
        </div>

        <div class="field">
          <label>Date limite de paiement</label>
          <input v-model="form.due_date" type="date" />
        </div>

        <div class="field">
          <label>Mode de paiement</label>
          <input v-model="form.payment_method" type="text" />
        </div>

        <div class="field">
          <label>Devise</label>
          <input v-model="form.currency" type="text" maxlength="3" />
        </div>
      </fieldset>

      <fieldset>
        <legend>Informations du client</legend>

        <div class="field">
          <label>Nom du client</label>
          <input v-model="form.client_name" type="text" />
          <span class="field-error" v-if="errors.client_name">{{ errors.client_name[0] }}</span>
        </div>

        <div class="field">
          <label>Matricule fiscal</label>
          <input v-model="form.client_tax_number" type="text" />
        </div>

        <div class="field">
          <label>Adresse</label>
          <input v-model="form.client_address" type="text" />
          <span class="field-error" v-if="errors.client_address">{{ errors.client_address[0] }}</span>
        </div>

        <div class="field">
          <label>Ville</label>
          <input v-model="form.client_city" type="text" />
          <span class="field-error" v-if="errors.client_city">{{ errors.client_city[0] }}</span>
        </div>

        <div class="field">
          <label>Code postal</label>
          <input v-model="form.client_postal_code" type="text" required />
          <span class="field-error" v-if="errors.client_postal_code">
            {{ errors.client_postal_code[0] }}
          </span>
        </div>

        <div class="field">
          <label>Pays</label>
          <input
            v-model="form.client_country"
            type="text"
            placeholder="TN"
          />
          <span class="field-error" v-if="errors.client_country">
            {{ errors.client_country[0] }}
          </span>
        </div>

        <div class="field">
          <label>Téléphone</label>
          <input v-model="form.client_phone" type="text" />
        </div>

        <div class="field">
          <label>Email</label>
          <input v-model="form.client_email" type="email" />
        </div>
      </fieldset>

      <fieldset class="invoice-items-fieldset">
        <legend>Lignes de facture</legend>

        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Désignation</th>
              <th>Qté</th>
              <th>Unité</th>
              <th>PU HT</th>
              <th>TVA %</th>
              <th>Remise</th>
              <th>Total HT</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in items" :key="index">
              <td><input v-model="item.code" type="text" size="6" /></td>
              <td><input v-model="item.designation" type="text" /></td>
              <td><input v-model.number="item.quantity" type="text" step="0.001" min="0" /></td>
              <td><input v-model="item.unit" type="text" placeholder="PCE"/></td>
              <td><input v-model.number="item.unit_price" type="text" step="0.001" min="0" /></td>
              <td><input v-model.number="item.vat_rate" type="text" step="0.01" min="0" /></td>
              <td><input v-model.number="item.discount" type="text" step="0.001" min="0" /></td>
              <td class="line-total">{{ lineTotal(item).toFixed(3) }}</td>
              <td>
                <button type="button" @click="removeItem(index)" class="btn-remove" aria-label="Supprimer cette ligne" title="Supprimer cette ligne">
                  <i class="fa-solid fa-trash" aria-hidden="true"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <button type="button" @click="addItem" class="btn-add">
          <i class="fa-solid fa-plus" aria-hidden="true"></i> Ajouter une ligne
        </button>
      </fieldset>

      <fieldset class="totals">
        <legend>Totaux</legend>
        <p>Total HT : <strong>{{ totalHT.toFixed(3) }} {{ form.currency }}</strong></p>
        <p>Total TVA : <strong>{{ totalVAT.toFixed(3) }} {{ form.currency }}</strong></p>
        <p>Droit de timbre : <strong>{{ stampDuty.toFixed(3) }} {{ form.currency }}</strong></p>
        <p class="ttc">Total TTC : <strong>{{ totalTTC.toFixed(3) }} {{ form.currency }}</strong></p>
      </fieldset>

      <button type="submit" class="btn-submit" :disabled="loading">
        <i v-if="!loading" class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
        <i v-else class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
        {{ loading ? 'Enregistrement...' : 'Enregistrer la facture' }}
      </button>

    </form>
  </div>
</template>

<style scoped>
.invoice-form {
  width: min(100%, 980px);
  min-width: 0;
  margin: 0 auto;
  background: var(--color-surface);
  padding: clamp(18px, 3vw, 34px);
  border: 1px solid var(--color-border);
  border-radius: 14px;
  box-shadow: var(--shadow-soft);
}

.invoice-form > h2 { margin-bottom: 24px; color: var(--color-text); font-size: clamp(24px, 3vw, 32px); font-weight: 750; letter-spacing: -0.04em; }

fieldset {
  min-width: 0;
  max-width: 100%;
  margin-bottom: 20px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  padding: 20px;
}

legend {
  font-weight: bold;
  padding: 0 8px;
  color: var(--color-primary-dark);
}

fieldset:nth-of-type(1), fieldset:nth-of-type(2) { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 18px; }
fieldset:nth-of-type(1) legend, fieldset:nth-of-type(2) legend { grid-column: 1 / -1; }

.field {
  margin-bottom: 14px;
  display: flex;
  flex-direction: column;
}

.field label {
  font-size: 14px;
  margin-bottom: 4px;
  color: #334155;
  font-weight: 650;
}

input {
  width: 100%;
  min-height: 42px;
  padding: 9px 11px;
  border: 1px solid #cbd5e1;
  border-radius: 7px;
  color: var(--color-text);
  background: #fbfcfe;
}

input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(11, 107, 104, 0.1);
}

.invoice-items-fieldset { width: 100%; overflow: hidden; }
.invoice-items-fieldset table {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
  margin-bottom: 10px;
}

table th, table td {
  border: 1px solid var(--color-border);
  padding: 7px;
  text-align: left;
}

table input {
  width: 100%;
  box-sizing: border-box;
}

.line-total {
  font-weight: bold;
  white-space: nowrap;
}

.btn-add {
  min-height: 42px;
  background: var(--color-primary);
  color: white;
  border: none;
  padding: 9px 14px;
  border-radius: 7px;
  font-weight: 650;
}

.btn-add:hover { background: var(--color-primary-dark); }

.btn-remove {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  background: #fff1f0;
  color: #b42318;
  border: 1px solid #f2b8b5;
  border-radius: 7px;
  cursor: pointer;
}

.btn-remove:hover { background: #fee4e2; }

.btn-submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  min-height: 48px;
  background: var(--color-primary);
  color: white;
  border: none;
  padding: 11px 18px;
  border-radius: 8px;
  font-weight: 700;
}

.btn-submit:hover:not(:disabled) { background: var(--color-primary-dark); }
.btn-submit:disabled { opacity: 0.65; cursor: not-allowed; }

@media (max-width: 640px) {
  .invoice-form { padding: 20px 14px; border-radius: 10px; }
  fieldset { padding: 16px 12px; }
  fieldset:nth-of-type(1), fieldset:nth-of-type(2) { display: block; }
  .invoice-items-fieldset { overflow-x: auto; }
  .invoice-items-fieldset .btn-add { width: 100%; }
}

.totals p {
  margin: 4px 0;
}

.totals .ttc {
  font-size: 1.2em;
  color: #16a34a;
}

.btn-submit {
  background: #16a34a;
  color: white;
  border: none;
  padding: 12px 20px;
  border-radius: 4px;
  font-size: 16px;
  cursor: pointer;
  width: 100%;
}

.btn-submit:disabled {
  background: #999;
  cursor: not-allowed;
}

.alert {
  padding: 10px 15px;
  border-radius: 4px;
  margin-bottom: 15px;
}

.alert.success {
  background: #dcfce7;
  color: #166534;
}

.alert.error {
  background: #fee2e2;
  color: #991b1b;
}

.field-error {
  color: #dc2626;
  font-size: 13px;
  margin-top: 2px;
}
</style>