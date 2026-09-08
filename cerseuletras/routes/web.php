<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;

/*
 * Lo que queda de Laravel en el navegador: el panel y la sesion.
 *
 * El sitio publico ya no pasa por aqui. Lo sirve Nginx desde el `dist/` que
 * genera Astro contra la API de contenido (routes/api.php), asi que estas
 * rutas son las unicas que devuelven HTML: /login y el flujo de contrasenas,
 * /profile para la cuenta propia, y /gestion para las dos cosas que sostienen
 * al panel sin caber dentro de un recurso de Filament.
 *
 * El panel es Filament, en /panel, y sus rutas las registra su PanelProvider.
 * El anterior —/admin, 19 controladores y 41 vistas Blade— se retiro cuando
 * este alcanzo paridad.
 *
 * Las redirecciones de las direcciones antiguas —/diplomados, /programas—
 * viven ahora en docker/nginx/sitio.conf, que es quien las recibe.
 */

/*
 * Lo que sostiene al panel sin ser el panel.
 *
 * Filament cubre la administracion, pero dos cosas no encajan dentro de un
 * recurso: la vista previa de borradores —que sirve HTML generado por el build,
 * con rutas con barras dentro— y la exportacion a CSV, que es una descarga.
 * Vivian bajo /admin, y al retirarlo se habrian ido con el.
 *
 * Prefijo propio y no /panel: ahi manda Filament, y una ruta nuestra colgada de
 * su espacio se rompe el dia que Filament reclame ese slug.
 *
 * Detras de la sesion, igual que antes: la vista previa sirve contenido sin
 * publicar y la exportacion, datos personales de quien dejo una solicitud.
 */
Route::middleware(['auth', 'isAdmin'])->prefix('gestion')->name('gestion.')->group(function () {
    /*
     * `archivo` lleva `where` con un comodin porque una ruta de vista previa
     * trae barras («_astro/algo.css»), y sin eso Laravel corta en la primera.
     */
    Route::get('vista-previa/programas/{programa}', [App\Http\Controllers\VistaPreviaController::class, 'programa'])
        ->name('vista-previa.programa');
    Route::get('vista-previa/estado', [App\Http\Controllers\VistaPreviaController::class, 'estado'])
        ->name('vista-previa.estado');
    Route::get('vista-previa/sitio/{ruta?}', [App\Http\Controllers\VistaPreviaController::class, 'archivo'])
        ->where('ruta', '.*')
        ->name('vista-previa.archivo');

    Route::get('solicitudes/exportar', App\Http\Controllers\ExportacionDeSolicitudesController::class)
        ->name('solicitudes.exportar');
});

// Breeze default routes (Profile)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Authentication Routes
require __DIR__ . '/auth.php';
