<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::post('/invoices', [InvoiceController::class, 'store']);

    Route::post('/invoices/{invoice}/generate-xml', [InvoiceController::class, 'generateXml']);
    Route::post('/invoices/{invoice}/validate-xml', [InvoiceController::class, 'validateXml']);
    Route::post('/invoices/{invoice}/generate-pdf', [InvoiceController::class, 'generatePdf']);
    Route::get('/invoices/{invoice}/download-pdf', [InvoiceController::class, 'downloadPdf']);
    Route::get('/invoices/{invoice}/download-xml', [InvoiceController::class, 'downloadXml']);
    Route::get('/invoices/{invoice}/download-signed-xml', [InvoiceController::class, 'downloadSignedXml']);
    Route::post('/invoices/{invoice}/send-email', [InvoiceController::class, 'sendEmail']);
    Route::post('/invoices/{invoice}/sign-xml', [InvoiceController::class, 'signXml']);
    Route::post('/invoices/{invoice}/verify-signature', [InvoiceController::class, 'verifySignature']);
});