<template>
    <div class="container-fluid py-4">

        <h2 class="mb-4">
            Nouvelle facture
        </h2>

        <InvoiceHeader />

        <ClientForm />

        <InvoiceItems />

        <InvoiceTotals />

        <div class="text-end mt-4">

            <button class="btn btn-primary me-2">
                Enregistrer
            </button>

            <button class="btn btn-danger me-2" disabled>
                PDF
            </button>

            <button class="btn btn-success me-2" disabled>
                Email
            </button>

            <button class="btn btn-dark" disabled>
                ELFATOORA
            </button>

        </div>

    </div>
</template>

<script setup>
import InvoiceHeader from '@/components/invoice/InvoiceHeader.vue'
import ClientForm from '@/components/invoice/ClientForm.vue'
import InvoiceItems from '@/components/invoice/InvoiceItems.vue'
import InvoiceTotals from '@/components/invoice/InvoiceTotals.vue'
</script>