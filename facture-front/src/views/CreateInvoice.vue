<script setup>
import { reactive, provide } from "vue";

import InvoiceHeader from "@/components/invoice/InvoiceHeader.vue";
import ClientForm from "@/components/invoice/ClientForm.vue";
import InvoiceItems from "@/components/invoice/InvoiceItems.vue";
import InvoiceTotals from "@/components/invoice/InvoiceTotals.vue";

import invoiceService from "@/services/invoiceService";

const invoice = reactive({
    invoice_number: "",
    invoice_date: "",
    due_date: "",

    payment_method: "Espèces",
    currency: "TND",

    client_name: "",
    client_tax_number: "",
    client_address: "",
    client_city: "",
    client_postal_code: "",
    client_country: "TN",
    client_phone: "",
    client_email: "",

    items: []
});

provide("invoice", invoice);

async function saveInvoice() {
    try {

        const response = await invoiceService.create(invoice);

        alert(response.data.message);

        console.log(response.data);

    } catch (error) {

        console.error(error);

        if (error.response) {
            alert("Erreur : " + JSON.stringify(error.response.data));
        } else {
            alert("Impossible de contacter le serveur.");
        }
    }
}
</script>

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

        <button
            class="btn btn-primary btn-lg"
            @click="saveInvoice"
        >
            Enregistrer la facture
        </button>

    </div>

</div>
</template>