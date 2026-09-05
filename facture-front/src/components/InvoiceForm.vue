<script setup>
import { ref, computed } from 'vue'
import api from '../api.js'

const form = ref({
  invoice_number: '',
  invoice_date: '',
  due_date: '',
  payment_method: 'Especes',
  currency: 'TND',

  client_name: '',
  client_tax_number: '',
  client_address: '',
  client_city: '',
  client_country: 'TN',   // ← AJOUTER ICI
  client_phone: '',
  client_email: '',
})

const items = ref([
  { code: '', designation: '', quantity: 1,unit:'PCE', unit_price: 0, vat_rate: 19, discount: 0 }
])

const successMessage = ref('')
const errorMessage = ref('')
const errors = ref({})
const loading = ref(false)

function addItem() {
  items.value.push({ code: '', designation: '', quantity: 1, unit:'PCE', unit_price: 0, vat_rate: 19, discount: 0 })
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

    successMessage.value = response.data.message
    resetForm()
  } catch (error) {
    console.log(error);
    console.log(error.response);
    console.log(error.response?.data);

    alert(JSON.stringify(error.response?.data, null, 2));
}
}

function resetForm() {
  form.value = {
  invoice_number: '',
  invoice_date: '',
  due_date: '',
  payment_method: 'Especes',
  currency: 'TND',

  client_name: '',
  client_tax_number: '',
  client_address: '',
  client_city: '',
  client_country: 'TN',
  client_phone: '',
  client_email: '',
}
  items.value = [
    { code: '', designation: '', quantity: 1, unit:'PCE' , unit_price: 0, vat_rate: 19, discount: 0 }
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

      <fieldset>
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
                <button type="button" @click="removeItem(index)" class="btn-remove">✕</button>
              </td>
            </tr>
          </tbody>
        </table>

        <button type="button" @click="addItem" class="btn-add">+ Ajouter une ligne</button>
      </fieldset>

      <fieldset class="totals">
        <legend>Totaux</legend>
        <p>Total HT : <strong>{{ totalHT.toFixed(3) }} {{ form.currency }}</strong></p>
        <p>Total TVA : <strong>{{ totalVAT.toFixed(3) }} {{ form.currency }}</strong></p>
        <p>Droit de timbre : <strong>{{ stampDuty.toFixed(3) }} {{ form.currency }}</strong></p>
        <p class="ttc">Total TTC : <strong>{{ totalTTC.toFixed(3) }} {{ form.currency }}</strong></p>
      </fieldset>

      <button type="submit" class="btn-submit" :disabled="loading">
        {{ loading ? 'Enregistrement...' : 'Enregistrer la facture' }}
      </button>

    </form>
  </div>
</template>

<style scoped>
.invoice-form {
  width: 900px;
  margin: 0 auto;
  background: white;
  padding: 20px;
  border-radius: 8px;
}

fieldset {
  margin-bottom: 20px;
  border: 1px solid #ddd;
  border-radius: 6px;
  padding: 15px;
}

legend {
  font-weight: bold;
  padding: 0 8px;
}

.field {
  margin-bottom: 10px;
  display: flex;
  flex-direction: column;
}

.field label {
  font-size: 14px;
  margin-bottom: 4px;
  color: #333;
}

input {
  padding: 6px;
  border: 1px solid #ccc;
  border-radius: 4px;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
}

th, td {
  border: 1px solid #ddd;
  padding: 6px;
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
  background: #2563eb;
  color: white;
  border: none;
  padding: 8px 14px;
  border-radius: 4px;
  cursor: pointer;
}

.btn-remove {
  background: #dc2626;
  color: white;
  border: none;
  padding: 4px 8px;
  border-radius: 4px;
  cursor: pointer;
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