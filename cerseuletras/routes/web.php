<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;

/*
 * Lo que queda de Laravel en el navegador: el panel y la sesion.
 *
 * El sitio publico ya no pasa por aqui. Lo sirve Nginx desde el `dist/` que
 * genera Astro contra la API de contenido (routes/api.php), asi que estas
 * rutas son las unicas que devuelven HTML: /admin para quien administra,
 * /login y el flujo de contrasenas para llegar hasta el, y /profile para la
 * cuenta propia.
 *
 * Las redirecciones de las direcciones antiguas —/diplomados, /programas—
 * viven ahora en docker/nginx/sitio.conf, que es quien las recibe.
 */

/*
 * El segmento {tipoOferta} llega como slug en plural («talleres», «cursos»),
 * mientras que el enum se respalda en el singular. El binding implícito de
 * Laravel resuelve con tryFrom() sobre ese respaldo, así que sin este binder
 * /admin/admision/talleres da 404.
 *
 * Se declaraba junto a las rutas públicas; al retirarlas se fue con ellas y
 * la pantalla de admisión del panel dejó de abrirse. Vive aquí porque el panel
 * es el único que sigue usando ese parámetro.
 *
 * Se llama {tipoOferta} y no {tipo} porque la papelera ya usa {tipo} para el
 * tipo de contenido borrado, y un binder global con ese nombre la rompía.
 */
Route::bind('tipoOferta', function ($valor) {
    if ($valor instanceof App\Models\TipoOferta) {
        return $valor;
    }

    return App\Models\TipoOferta::desdeSlug((string) $valor) ?? abort(404);
});

// Admin Routes - Protected by auth and isAdmin middleware
Route::middleware(['auth', 'isAdmin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/', [App\Http\Controllers\Admin\AdminController::class, 'index'])->name('dashboard');

    // Programas Management
    // Sin `show`: el panel edita, no expone fichas, y el controlador nunca
    // tuvo ese metodo. Route::resource lo registraba igual, de modo que
    // /admin/programas/{id} respondia 500 en vez de 404.
    Route::resource('programas', App\Http\Controllers\Admin\AdminProgramaController::class)
        ->except(['show']);
    Route::post('programas/{programa}/toggle', [App\Http\Controllers\Admin\AdminProgramaController::class, 'toggleActive'])->name('programas.toggle');

    // Vista previa de borradores.
    //
    // Dentro del grupo de admin, o sea detrás de la sesión: sirve contenido sin
    // publicar y no puede quedar al alcance de una URL adivinada.
    //
    // `archivo` lleva `where` con un comodín porque una ruta de vista previa
    // trae barras («_astro/algo.css»), y sin eso Laravel corta en la primera.
    Route::get('vista-previa/programas/{programa}', [App\Http\Controllers\Admin\AdminVistaPreviaController::class, 'programa'])
        ->name('vista-previa.programa');
    Route::get('vista-previa/estado', [App\Http\Controllers\Admin\AdminVistaPreviaController::class, 'estado'])
        ->name('vista-previa.estado');
    Route::get('vista-previa/sitio/{ruta?}', [App\Http\Controllers\Admin\AdminVistaPreviaController::class, 'archivo'])
        ->where('ruta', '.*')
        ->name('vista-previa.archivo');

    // Docentes Management
    // Sin `show`, por lo mismo que programas.
    Route::resource('docentes', App\Http\Controllers\Admin\AdminDocenteController::class)
        ->except(['show']);
    Route::post('docentes/{docente}/toggle', [App\Http\Controllers\Admin\AdminDocenteController::class, 'toggleActive'])->name('docentes.toggle');

    // Testimonios Management
    Route::resource('testimonios', App\Http\Controllers\Admin\AdminTestimonioController::class);
    Route::post('testimonios/{testimonio}/toggle', [App\Http\Controllers\Admin\AdminTestimonioController::class, 'togglePublished'])->name('testimonios.toggle');

    // Directorio Management
    Route::resource('directorio', App\Http\Controllers\Admin\AdminDirectorioController::class);
    Route::post('directorio/{directorio}/toggle', [App\Http\Controllers\Admin\AdminDirectorioController::class, 'toggleActive'])->name('directorio.toggle');
    Route::post('directorio/{directorio}/move-up', [App\Http\Controllers\Admin\AdminDirectorioController::class, 'moveUp'])->name('directorio.moveUp');
    Route::post('directorio/{directorio}/move-down', [App\Http\Controllers\Admin\AdminDirectorioController::class, 'moveDown'])->name('directorio.moveDown');

    // Site Settings
    Route::get('settings', [App\Http\Controllers\Admin\AdminSiteSettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [App\Http\Controllers\Admin\AdminSiteSettingsController::class, 'update'])->name('settings.update');

    // Documents Management
    Route::post('documents/upload-ajax', [App\Http\Controllers\Admin\AdminDocumentController::class, 'uploadAjax'])->name('documents.uploadAjax');
    Route::resource('documents', App\Http\Controllers\Admin\AdminDocumentController::class);
    Route::post('documents/{document}/toggle', [App\Http\Controllers\Admin\AdminDocumentController::class, 'togglePublished'])->name('documents.toggle');

    // Cronograma Management
    Route::get('cronograma', [App\Http\Controllers\Admin\AdminCronogramaController::class, 'index'])->name('cronograma.index');
    Route::put('cronograma', [App\Http\Controllers\Admin\AdminCronogramaController::class, 'update'])->name('cronograma.update');

    // Contenido de páginas largas (/tramites, /admision)
    Route::get('contenido', [App\Http\Controllers\Admin\AdminContentPageController::class, 'index'])->name('contenido.index');
    Route::get('contenido/{slug}', [App\Http\Controllers\Admin\AdminContentPageController::class, 'edit'])->name('contenido.edit');
    Route::put('contenido/{slug}', [App\Http\Controllers\Admin\AdminContentPageController::class, 'update'])->name('contenido.update');

    // Usuarios del panel
    Route::resource('users', App\Http\Controllers\Admin\AdminUserController::class)->except(['show']);
    Route::post('users/{user}/toggle', [App\Http\Controllers\Admin\AdminUserController::class, 'toggleActive'])->name('users.toggle');

    // Solicitudes de información de talleres y cursos (formulario público)
    Route::get('leads', [App\Http\Controllers\Admin\AdminLeadController::class, 'index'])->name('leads.index');
    Route::get('leads/export', [App\Http\Controllers\Admin\AdminLeadController::class, 'export'])->name('leads.export');
    Route::post('leads/{lead}/reenviar-aviso', [App\Http\Controllers\Admin\AdminLeadController::class, 'reenviarAviso'])->name('leads.reenviar');
    Route::delete('leads/{lead}', [App\Http\Controllers\Admin\AdminLeadController::class, 'destroy'])->name('leads.destroy');

    // Cronograma de Admisión (sección de la portada)
    // Papelera única: lo borrado en cualquier sección se recupera desde aquí.
    Route::get('papelera', [App\Http\Controllers\Admin\AdminPapeleraController::class, 'index'])->name('papelera.index');
    Route::post('papelera/{tipo}/{id}/restaurar', [App\Http\Controllers\Admin\AdminPapeleraController::class, 'restaurar'])->name('papelera.restaurar');

    // Anuncios del popup de la portada.
    Route::post('anuncios/ajustes', [App\Http\Controllers\Admin\AdminAnuncioController::class, 'ajustes'])->name('anuncios.ajustes');
    Route::get('anuncios/papelera', [App\Http\Controllers\Admin\AdminAnuncioController::class, 'papelera'])->name('anuncios.papelera');
    Route::post('anuncios/{id}/restaurar', [App\Http\Controllers\Admin\AdminAnuncioController::class, 'restaurar'])->name('anuncios.restaurar');
    Route::post('anuncios/{anuncio}/toggle', [App\Http\Controllers\Admin\AdminAnuncioController::class, 'toggle'])->name('anuncios.toggle');
    Route::resource('anuncios', App\Http\Controllers\Admin\AdminAnuncioController::class)->except(['show']);

    // Menú de navegación (antes escrito a mano en navbar.blade.php).
    Route::get('menu', [App\Http\Controllers\Admin\AdminMenuController::class, 'index'])->name('menu.index');
    Route::put('menu', [App\Http\Controllers\Admin\AdminMenuController::class, 'update'])->name('menu.update');

    Route::get('cronograma-admision', [App\Http\Controllers\Admin\AdminCronogramaAdmisionController::class, 'index'])->name('cronograma-admision.index');
    Route::put('cronograma-admision', [App\Http\Controllers\Admin\AdminCronogramaAdmisionController::class, 'update'])->name('cronograma-admision.update');

    // Admisión de cada módulo. El segmento {tipoOferta} vale «talleres» o «cursos»;
    // TipoOferta lo resuelve y un valor inventado da 404.
    Route::get('admision/{tipoOferta}', [App\Http\Controllers\Admin\AdminAdmisionController::class, 'index'])->name('admision.index');
    Route::put('admision/{tipoOferta}', [App\Http\Controllers\Admin\AdminAdmisionController::class, 'update'])->name('admision.update');

    // Informativos Management
    // Los informativos se crean y editan en linea desde el propio listado:
    // no hay pantallas create/show/edit y el controlador no las implementa.
    Route::resource('informativos', App\Http\Controllers\Admin\AdminInformativoController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('informativos/reorder', [App\Http\Controllers\Admin\AdminInformativoController::class, 'reorder'])->name('informativos.reorder');

    // Eventos Management
    Route::resource('eventos', App\Http\Controllers\Admin\AdminEventoController::class);
});

// Breeze default routes (Profile)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Authentication Routes
require __DIR__ . '/auth.php';
