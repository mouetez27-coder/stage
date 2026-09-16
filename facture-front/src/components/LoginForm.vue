<script setup>
import { ref } from 'vue'
import api from '../api.js'

const emit = defineEmits(['logged-in'])

const email = ref('')
const password = ref('')
const errorMessage = ref('')
const loading = ref(false)

async function submitLogin() {
  errorMessage.value = ''
  loading.value = true

  try {
    const response = await api.post('/login', {
      email: email.value,
      password: password.value,
    })

    localStorage.setItem('auth_token', response.data.token)
    localStorage.setItem('user_name', response.data.user.name)

    emit('logged-in')
  } catch (error) {
    if (error.response && error.response.status === 422) {
      errorMessage.value = 'Email ou mot de passe incorrect.'
    } else {
      errorMessage.value = 'Une erreur est survenue. Vérifiez que le serveur Laravel est bien lancé.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-container">
    <div class="login-card">
      <div class="brand">
        <div class="brand-mark">EF</div>
        <div class="brand-text">
          <span class="brand-name">ELFATOORA</span>
          <span class="brand-tagline">Facturation électronique</span>
        </div>
      </div>

      <form class="login-form" @submit.prevent="submitLogin">
        <div class="form-header">
          <h1>Connexion</h1>
          <p>Accédez à votre espace de facturation</p>
        </div>

        <div v-if="errorMessage" class="alert error">
          <i class="alert-icon fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          {{ errorMessage }}
        </div>

        <div class="field">
          <label for="email">Adresse email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            placeholder="nom@entreprise.tn"
            required
            autofocus
          />
        </div>

        <div class="field">
          <label for="password">Mot de passe</label>
          <input
            id="password"
            v-model="password"
            type="password"
            placeholder="••••••••"
            required
          />
        </div>

        <button type="submit" :disabled="loading">
          <span v-if="loading" class="spinner"></span>
          {{ loading ? 'Connexion en cours...' : 'Se connecter' }}
        </button>
      </form>

      <p class="footer-note">© 2026 ELFATOORA — Système de facturation TEIF</p>
    </div>
  </div>
</template>

<style scoped>
* {
  box-sizing: border-box;
}

.login-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  width: 100%;
  padding: 24px;
  background: #102f35;
  background-image:
    linear-gradient(135deg, rgba(231, 183, 91, 0.18), transparent 42%),
    radial-gradient(circle at 85% 80%, rgba(122, 205, 194, 0.16) 0%, transparent 38%);
}
.login-card {
  width: min(100%, 440px);
  min-height: auto;
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.7);
  box-shadow:
    0 4px 12px rgba(15, 23, 42, 0.08),
    0 20px 40px rgba(15, 23, 42, 0.12);
  overflow: hidden;
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 30px 32px 0;
}

.brand-mark {
  width: 40px;
  height: 40px;
  border-radius: 9px;
  background: var(--color-accent);
  color: #173a3c;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  letter-spacing: 0.5px;
  flex-shrink: 0;
}

.brand-text {
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}

.brand-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-text);
  letter-spacing: 0.3px;
}

.brand-tagline {
  font-size: 12px;
  color: #94a3b8;
}

.login-form {
  padding: 24px 32px 32px;
}

.form-header {
  margin-bottom: 24px;
}

.form-header h1 {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 600;
  color: var(--color-text);
  font-size: 25px;
  font-weight: 750;
  letter-spacing: -0.04em;
}

.form-header p {
  margin: 0;
  font-size: 13.5px;
  color: #64748b;
}

.field {
  display: flex;
  flex-direction: column;
  margin-bottom: 18px;
}

.field label {
  margin-bottom: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

input {
  padding: 11px 13px;
  border: 1px solid #d8dee8;
  border-radius: 8px;
  font-size: 14.5px;
  color: #0f172a;
  background: #fbfcfe;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
  outline: none;
  font-family: inherit;
}

input::placeholder {
  color: #a3adba;
}

input:focus {
  border-color: #334155;
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(51, 65, 85, 0.08);
}

button {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px;
  margin-top: 6px;
  border: none;
  border-radius: 8px;
  background: var(--color-primary);
  color: white;
  font-size: 14.5px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.1s ease;
  font-family: inherit;
}

button:hover:not(:disabled) {
  background: var(--color-primary-dark);
}

button:active:not(:disabled) {
  transform: scale(0.99);
}

button:disabled {
  background: #cbd5e1;
  cursor: not-allowed;
}

.spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.alert {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 20px;
  padding: 11px 13px;
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #b91c1c;
  border-radius: 8px;
  font-size: 13.5px;
  line-height: 1.4;
}

.alert-icon {
  font-size: 14px;
  flex-shrink: 0;
}

.footer-note {
  text-align: center;
  padding: 14px 0 22px;
  margin: 0;
  font-size: 11.5px;
  color: #b0b8c4;
  border-top: 1px solid #f1f4f8;
}

@media (max-width: 480px) {
  .login-container { padding: 16px; }
  .brand {
    padding: 22px 24px 0;
  }

  .login-form {
    padding: 20px 24px 24px;
  }
}
</style>