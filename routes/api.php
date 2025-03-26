<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FirmaController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\Nomina\NominaController;
use App\Http\Controllers\Nomina\XmlNominaController;
use App\Http\Controllers\NotaCreditoController;
use App\Http\Controllers\DocumentoNoObligadoController;

Route::get('/saludo', function () {
    return response()->json(['mensaje' => 'hola']);
});
Route::post('/firmarXmlPrueba', [FacturaController::class, 'firmarXmlPrueba']);

Route::post('/cufe', [FacturaController::class, 'generarCUFE']);

Route::post('/crear-factura', [FacturaController::class, 'crearFacturaElectronica']);

//Route::post('/crear-nota-credito', [NotaCreditoController::class, 'crearNotaCredito']);

Route::post('/generarCUNE', [NominaController::class, 'generarCUNE']);

Route::post('/crear-documento-no-obligado', [DocumentoNoObligadoController::class, 'crearNoObligado']);
Route::post('/cuds', [DocumentoNoObligadoController::class, 'generarCUDS']);

Route::get('/test', function () {
    return response()->json(['message' => 'API funcionando correctamente']);
});
Route::post('/crear-nomina', [NominaController::class, 'crearNominaElectronica']);
Route::post('/notas', [XmlNominaController::class, 'formNotas']);
