<template>
    <div class="card shadow-sm mb-4">

        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                Informations du client
            </h5>
        </div>

        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">
                        Nom du client
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        v-model="invoice.client_name"
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Matricule fiscal
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        v-model="invoice.client_tax_number"
                    >
                </div>

                <div class="col-md-8">
                    <label class="form-label">
                        Adresse
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        v-model="invoice.client_address"
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label">
                        Ville
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        v-model="invoice.client_city"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Code postal
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        v-model="invoice.client_postal_code"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Pays
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        v-model="invoice.client_country"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Téléphone
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        v-model="invoice.client_phone"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label">
                        Email
                    </label>

                    <input
                        type="email"
                        class="form-control"
                        v-model="invoice.client_email"
                    >
                </div>

            </div>

        </div>

    </div>
</template>

<script setup>
import { inject } from "vue";

const invoice = inject("invoice");
</script>