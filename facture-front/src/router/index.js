import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import InvoiceView from '../views/InvoiceView.vue'
import InvoiceList from '../components/InvoiceList.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/invoices/create',
      name: 'invoice-create',
      component: InvoiceView,
    },
    {
      path: '/invoices',
      name: 'invoices',
      component: InvoiceList,
      meta: { requiresAuth: true },
    },
  ],
})

export default router