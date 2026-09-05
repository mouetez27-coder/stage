<template>
    <div class="card shadow-sm">

        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">
                Totaux de la facture
            </h5>
        </div>

        <div class="card-body">

            <div class="row justify-content-end">

                <div class="col-md-5">

                    <table class="table table-bordered">

                        <tbody>

                            <tr>
                                <th>Total HT</th>
                                <td class="text-end">
                                    {{ totalHT.toFixed(3) }} TND
                                </td>
                            </tr>

                            <tr>
                                <th>Total TVA</th>
                                <td class="text-end">
                                    {{ totalTVA.toFixed(3) }} TND
                                </td>
                            </tr>

                            <tr>
                                <th>Droit de timbre</th>
                                <td class="text-end">
                                    {{ stampDuty.toFixed(3) }} TND
                                </td>
                            </tr>

                            <tr class="table-primary">

                                <th>Total TTC</th>

                                <th class="text-end">
                                    {{ totalTTC.toFixed(3) }} TND
                                </th>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>
</template>

<script setup>

import { inject, computed } from "vue";

const invoice = inject("invoice");

const stampDuty = 1.000;

const totalHT = computed(() => {

    return invoice.items.reduce((total, item) => {

        return total + ((item.quantity * item.unit_price) - item.discount);

    }, 0);

});

const totalTVA = computed(() => {

    return invoice.items.reduce((total, item) => {

        const ht = (item.quantity * item.unit_price) - item.discount;

        return total + (ht * item.vat_rate / 100);

    }, 0);

});

const totalTTC = computed(() => {

    return totalHT.value + totalTVA.value + stampDuty;

});

</script>