<template>
    <div class="card shadow-sm mb-4">

        <div class="card-header bg-warning">
            <div class="d-flex justify-content-between align-items-center">

                <h5 class="mb-0">
                    Lignes de facture
                </h5>

                <button
                    class="btn btn-success btn-sm"
                    @click="addItem"
                >
                    + Ajouter une ligne
                </button>

            </div>
        </div>

        <div class="card-body p-0">

            <table class="table table-bordered table-hover mb-0">

                <thead class="table-light">

                    <tr>

                        <th>Code</th>

                        <th>Désignation</th>

                        <th width="90">Qté</th>

                        <th width="90">Unité</th>

                        <th width="120">Prix HT</th>

                        <th width="90">TVA %</th>

                        <th width="120">Remise</th>

                        <th width="130">Total HT</th>

                        <th width="70"></th>

                    </tr>

                </thead>

                <tbody>

                    <tr
                        v-for="(item,index) in invoice.items"
                        :key="index"
                    >

                        <td>
                            <input
                                class="form-control"
                                v-model="item.code"
                            >
                        </td>

                        <td>
                            <input
                                class="form-control"
                                v-model="item.designation"
                            >
                        </td>

                        <td>
                            <input
                                type="number"
                                class="form-control"
                                v-model.number="item.quantity"
                            >
                        </td>

                        <td>

                            <select
                                class="form-select"
                                v-model="item.unit"
                            >

                                <option>UNIT</option>
                                <option>KGM</option>
                                <option>LTR</option>
                                <option>MTR</option>
                                <option>HUR</option>

                            </select>

                        </td>

                        <td>
                            <input
                                type="number"
                                class="form-control"
                                v-model.number="item.unit_price"
                            >
                        </td>

                        <td>
                            <input
                                type="number"
                                class="form-control"
                                v-model.number="item.vat_rate"
                            >
                        </td>

                        <td>
                            <input
                                type="number"
                                class="form-control"
                                v-model.number="item.discount"
                            >
                        </td>

                        <td>

                            <strong>

                                {{ lineTotal(item).toFixed(3) }}

                            </strong>

                        </td>

                        <td>

                            <button
                                class="btn btn-danger btn-sm"
                                @click="removeItem(index)"
                            >
                                X
                            </button>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>
</template>

<script setup>

import { inject } from "vue";

const invoice = inject("invoice");

function addItem(){

    invoice.items.push({

        code:"",
        designation:"",
        quantity:1,
        unit:"UNIT",
        unit_price:0,
        vat_rate:19,
        discount:0

    });

}

function removeItem(index){

    invoice.items.splice(index,1);

}

function lineTotal(item){

    return (item.quantity * item.unit_price) - item.discount;

}

</script>