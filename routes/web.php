<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    UsuarioController,
    ProveedorController,
    CertificadoController,
    EtiquetaController,
    IngresoController,
    DashboardDipiiController
};

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardDipiiController::class, 'index'])
        ->name('dashboard');

    Route::get('/dashboard/data', [DashboardDipiiController::class, 'data'])
        ->name('dashboard.data');

    // === Usuarios ===
    Route::resource('usuarios', UsuarioController::class)->except(['show']);

    // === Proveedores ===
    Route::resource('proveedores', ProveedorController::class)
        ->parameters(['proveedores' => 'proveedor'])
        ->except(['show']);

    // === Ingresos ===
    Route::resource('ingresos', IngresoController::class)->except(['show']);

    // === Certificados ===
    Route::resource('certificados', CertificadoController::class)->except(['show']);
    Route::get('certificados/{certificado}/descargar', [CertificadoController::class, 'descargar'])
        ->name('certificados.descargar');
    Route::get('/certificados/ver/{certificado}', [CertificadoController::class, 'ver'])
     ->name('certificados.ver');


    // === Etiquetas ===
    Route::resource('etiquetas', EtiquetaController::class)
        ->only(['index', 'create', 'store', 'destroy'])
        ->parameters(['etiquetas' => 'lote']);
    Route::get('/etiquetas/ver/{lote}', [EtiquetaController::class, 'ver'])->name('etiquetas.ver');
    Route::get('/etiquetas/{lote}/descargar', [EtiquetaController::class, 'descargar'])->name('etiquetas.descargar');


    // Ver PDF (para abrir en modal o navegador)
    Route::get('etiquetas/{lote}/pdf', [EtiquetaController::class, 'verPdf'])
        ->name('etiquetas.verPdf');

    // Descargar PDF
    Route::get('etiquetas/{lote}/descargar', [EtiquetaController::class, 'descargar'])
        ->name('etiquetas.descargar');

});


require __DIR__.'/auth.php';
require __DIR__.'/profile.php';
