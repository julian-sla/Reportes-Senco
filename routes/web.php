<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesignPriorityController;
use App\Http\Controllers\DesignRequestController;
use App\Http\Controllers\DesignStateController;
use App\Http\Controllers\DesignTaskController;
use App\Http\Controllers\DesignTimeStateController;
use App\Http\Controllers\ImportReportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportFilterController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ListaPreciosController;
use App\Http\Controllers\RutaTecnicaController;
use App\Http\Controllers\TerceroController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
|
*/

// Redirección inicial
Route::get('/', function () {
    return redirect()->route('login');
});


/*
|--------------------------------------------------------------------------
| Rutas principales autenticadas
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified'
])->group(function () {

    /**
     * Dashboard
     */
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');


    /**
     * Roles
     */
    Route::prefix('roles')->group(function () {

        Route::get('', [RoleController::class, 'index'])
            ->name('roles.index')
            ->middleware(
                'role_or_permission:super-admin|role.index|role.create|role.update|role.destroy'
            );

        Route::post('', [RoleController::class, 'store'])
            ->name('roles.store')
            ->middleware(
                'role_or_permission:super-admin|role.index|role.create'
            );

        Route::put('{id}', [RoleController::class, 'update'])
            ->name('roles.update')
            ->middleware(
                'role_or_permission:super-admin|role.index|role.update'
            );

        Route::delete('{id}', [RoleController::class, 'destroy'])
            ->name('roles.destroy')
            ->middleware(
                'role_or_permission:super-admin|role.index|role.destroy'
            );
    });


    /**
     * Users
     */
    Route::prefix('users')->group(function () {

        Route::get('', [UserController::class, 'index'])
            ->name('users.index')
            ->middleware(
                'role_or_permission:super-admin|user.index|user.create|user.update|user.destroy'
            );

        Route::get('{id}/show', [UserController::class, 'show'])
            ->name('users.show')
            ->middleware(
                'role_or_permission:super-admin|user.index|user.show'
            );

        Route::post('', [UserController::class, 'store'])
            ->name('users.store')
            ->middleware(
                'role_or_permission:super-admin|user.index|user.create'
            );

        Route::put('{id}', [UserController::class, 'update'])
            ->name('users.update')
            ->middleware(
                'role_or_permission:super-admin|user.index|user.update'
            );

        Route::delete('{id}', [UserController::class, 'destroy'])
            ->name('users.destroy')
            ->middleware(
                'role_or_permission:super-admin|user.index|user.destroy'
            );

        Route::post('update-reports', [UserController::class, 'update_reports'])
            ->name('user.update-reports')
            ->middleware(
                'role_or_permission:super-admin|update-reports'
            );

        Route::post('report/update-filters', [UserController::class, 'update_filters'])
            ->name('user.report.update-filters')
            ->middleware(
                'role_or_permission:super-admin|update-filters'
            );

        Route::post('report/set-default', [UserController::class, 'set_default'])
            ->name('user.report.set-default')
            ->middleware(
                'role_or_permission:super-admin|set-default'
            );
    });


    /**
     * Reports
     */
    Route::prefix('reports')->group(function () {

        Route::get('', [ReportController::class, 'index'])
            ->name('report.index')
            ->middleware(
                'role_or_permission:super-admin|report.create|report.edit|report.destroy'
            );

        Route::post('', [ReportController::class, 'store'])
            ->name('report.store')
            ->middleware(
                'role_or_permission:super-admin|report.create|report.edit'
            );

        Route::delete('{id}', [ReportController::class, 'destroy'])
            ->name('report.destroy')
            ->middleware(
                'role_or_permission:super-admin|report.create|report.destroy'
            );

        Route::put('{id}', [ReportController::class, 'update'])
            ->name('report.update')
            ->middleware(
                'role_or_permission:super-admin|report.create|report.edit'
            );

        Route::get('{groupId}/{reportId}/view', [ReportController::class, 'view'])
            ->name('report.view');


        /**
         * Import reports
         */
        Route::prefix('import')->group(function () {

            Route::get('', [ImportReportController::class, 'index'])
                ->name('report.import.index')
                ->middleware(
                    'role_or_permission:super-admin|import-report'
                );

            Route::post('', [ImportReportController::class, 'store'])
                ->name('report.import.store')
                ->middleware(
                    'role_or_permission:super-admin|import-report'
                );

            Route::get('get-reports', [ImportReportController::class, 'get_reports'])
                ->name('report.import.get-reports')
                ->middleware(
                    'role_or_permission:super-admin|import-report'
                );
        });


        /**
         * Filters
         */
        Route::prefix('filters')->group(function () {

            Route::get('', [ReportFilterController::class, 'index'])
                ->name('report.filter.index')
                ->middleware(
                    'role_or_permission:super-admin|report.filter.index|report.filter.create|report.filter.update|report.filter.destroy'
                );

            Route::post('', [ReportFilterController::class, 'store'])
                ->name('report.filter.store')
                ->middleware(
                    'role_or_permission:super-admin|report.filter.index|report.filter.create'
                );

            Route::delete('{id}', [ReportFilterController::class, 'destroy'])
                ->name('report.filter.destroy')
                ->middleware(
                    'role_or_permission:super-admin|report.filter.index|report.filter.destroy'
                );

            Route::put('{id}', [ReportFilterController::class, 'update'])
                ->name('report.filter.update')
                ->middleware(
                    'role_or_permission:super-admin|report.filter.index|report.filter.update'
                );
        });
    });


    /**
     * Design
     */
    Route::prefix('design')->group(function () {

        Route::resource('request', DesignRequestController::class)
            ->only('index', 'store', 'update', 'destroy', 'show');

        Route::post(
            'request/update-state',
            [DesignRequestController::class, 'update_state']
        )->name('design.request.update-state');

        Route::resource('priority', DesignPriorityController::class)
            ->only('index', 'store', 'update', 'destroy');

        Route::resource('state', DesignStateController::class)
            ->only('index', 'store', 'update', 'destroy');

        Route::resource('time-state', DesignTimeStateController::class)
            ->only('index', 'store', 'update', 'destroy');

        Route::resource('task', DesignTaskController::class)
            ->only('store', 'update');

        Route::delete(
            'task/{request_id}/{id}',
            [DesignTaskController::class, 'destroy']
        )->name('task.destroy');


    });


    /**
     * Rutas Técnicas
     */
    Route::prefix('rutas-tecnicas')
        ->name('rutas-tecnicas.')
        ->group(function () {

            Route::get(
                '/',
                [RutaTecnicaController::class, 'index']
            )->name('index');

            Route::get(
                '/create',
                [RutaTecnicaController::class, 'create']
            )->name('create');

            Route::post(
                '/',
                [RutaTecnicaController::class, 'store']
            )->name('store');

            Route::get(
                '/{id}',
                [RutaTecnicaController::class, 'show']
            )->name('show');

            Route::get(
                '/{id}/edit',
                [RutaTecnicaController::class, 'edit']
            )->name('edit');

            Route::put(
                '/{id}',
                [RutaTecnicaController::class, 'update']
            )->name('update');

            Route::delete(
                '/{id}',
                [RutaTecnicaController::class, 'destroy']
            )->name('destroy');


            /**
             * API clientes
             */
            Route::get(
                '/api/clientes/buscar',
                [RutaTecnicaController::class, 'buscarClientes']
            )->name('buscar-clientes');

            Route::get(
                '/api/clientes/{clienteId}/direcciones',
                [RutaTecnicaController::class, 'obtenerDirecciones']
            )->name('direcciones');

            Route::get(
                '/api/clientes/{clienteId}/contacto-recomendado',
                [RutaTecnicaController::class, 'obtenerDatosContactoRecomendados']
            )->name('contacto-recomendado');
        });
});


/*
|--------------------------------------------------------------------------
| Registro de Tercero
|--------------------------------------------------------------------------
|
| Nuevo módulo dentro de Gestión de Clientes.
| Utiliza el controlador TerceroController.
|
*/

Route::middleware(['auth'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Formulario de registro
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/gestion-clientes/registro-tercero',
        [TerceroController::class, 'create']
    )->name('gestion-clientes.registro-tercero');

    Route::get(
    '/gestion-clientes/formularios-terceros',
    [TerceroController::class, 'indexFormularios']
)->name('gestion-clientes.formularios-terceros.index');

Route::get(
    '/gestion-clientes/formularios-terceros/{id}/detalle',
    [TerceroController::class, 'detalleFormulario']
)->name('gestion-clientes.formularios-terceros.show');

Route::get(
    '/gestion-clientes/formularios-terceros/{id}/adjuntos/{campo}/ver',
    [TerceroController::class, 'verAdjuntoFormulario']
)->name('gestion-clientes.formularios-terceros.adjunto.ver');

Route::get(
    '/gestion-clientes/formularios-terceros/{id}/adjuntos/{campo}/descargar',
    [TerceroController::class, 'descargarAdjuntoFormulario']
)->name('gestion-clientes.formularios-terceros.adjunto.descargar');

Route::get(
    '/gestion-clientes/formularios-terceros/{id}/editar',
    [TerceroController::class, 'editarFormulario']
)->name('gestion-clientes.formularios-terceros.editar');

Route::post(
    '/gestion-clientes/formularios-terceros',
    [TerceroController::class, 'actualizarFormularioPost']
)->name('gestion-clientes.formularios-terceros.actualizar-post');

Route::patch(
    '/gestion-clientes/formularios-terceros/{id}',
    [TerceroController::class, 'actualizarFormulario']
)->name('gestion-clientes.formularios-terceros.actualizar');


Route::post(
    '/gestion-clientes/formularios-terceros/{id}',
    [TerceroController::class, 'actualizarFormulario']
)->name('gestion-clientes.formularios-terceros.actualizar');




/*
|--------------------------------------------------------------------------
| Países
|--------------------------------------------------------------------------
*/

Route::get(
    '/gestion-clientes/paises',
    [TerceroController::class, 'paises']
)->name('gestion-clientes.paises');

    /*
    |--------------------------------------------------------------------------
    | Departamentos
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/gestion-clientes/departamentos',
        [TerceroController::class, 'departamentos']
    )->name('gestion-clientes.departamentos');


    /*
    |--------------------------------------------------------------------------
    | Municipios
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/gestion-clientes/municipios/{codigoDepartamento}',
        [TerceroController::class, 'municipios']
    )->name('gestion-clientes.municipios');


    /*
    |--------------------------------------------------------------------------
    | Registrar tercero
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/gestion-clientes/registro-tercero',
        [TerceroController::class, 'store']
    )->name('gestion-clientes.registro-tercero.store');


    /*
    |--------------------------------------------------------------------------
    | Generar PDF - Vista previa del formulario
    |--------------------------------------------------------------------------
    */

Route::get(
    '/gestion-clientes/registro-tercero/pdf',
    [TerceroController::class, 'previsualizarPdf']
)->name('gestion-clientes.registro-tercero.pdf');

Route::get(
    '/gestion-clientes/registro-tercero/pdf/descargar',
    [TerceroController::class, 'descargarPdf']
)->name('gestion-clientes.registro-tercero.pdf.descargar');
Route::post(
    '/gestion-clientes/registro-tercero/pdf/previsualizar',
    [TerceroController::class, 'previsualizarPdfFormulario']
)->name('gestion-clientes.registro-tercero.pdf.previsualizar');
/*
|--------------------------------------------------------------------------
| Borrador del formulario (caché del servidor)
|--------------------------------------------------------------------------
*/

Route::get(
    '/gestion-clientes/registro-tercero/borrador',
    [TerceroController::class, 'obtenerBorrador']
)->name('gestion-clientes.registro-tercero.borrador.show');

Route::post(
    '/gestion-clientes/registro-tercero/borrador',
    [TerceroController::class, 'guardarBorrador']
)->name('gestion-clientes.registro-tercero.borrador.store');

Route::post(
    '/gestion-clientes/registro-tercero/borrador/adjunto',
    [TerceroController::class, 'guardarAdjuntoBorrador']
)->name('gestion-clientes.registro-tercero.borrador.adjunto.store');

Route::delete(
    '/gestion-clientes/registro-tercero/borrador',
    [TerceroController::class, 'eliminarBorrador']
)->name('gestion-clientes.registro-tercero.borrador.destroy');

});
/*
|--------------------------------------------------------------------------
| Lista de Precios
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'permission:ver-lista-precios'
])
    ->prefix('lista-precios')
    ->group(function () {

        Route::get(
            '/',
            [ListaPreciosController::class, 'index']
        )->name('lista-precios.index');

        Route::get(
            '/search',
            [ListaPreciosController::class, 'search']
        )->name('lista-precios.search');

        Route::get(
            '/producto/{codigo}',
            [ListaPreciosController::class, 'show']
        )->name('lista-precios.show');

        Route::get(
            '/relacionados/{codigo}',
            [ListaPreciosController::class, 'relacionados']
        )->name('lista-precios.relacionados');

        Route::get(
            '/export',
            [ListaPreciosController::class, 'export']
        )->name('lista-precios.export');

        Route::get(
            '/api/filtros',
            [ListaPreciosController::class, 'filtros']
        )->name('lista-precios.filtros');

        Route::get(
            '/api/estadisticas',
            [ListaPreciosController::class, 'estadisticas']
        )->name('lista-precios.estadisticas');

        Route::get(
            '/api/filtros-dinamicos',
            [ListaPreciosController::class, 'filtrosDinamicos']
        )->name('lista-precios.filtros-dinamicos');

        Route::get(
            '/lista-precios/pdf',
            [ListaPreciosController::class, 'exportPdf']
        )->name('lista-precios.pdf');
    });


/*
|--------------------------------------------------------------------------
| Módulo Visitas Técnicas
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified'
])
    ->prefix('visitas-tecnicas')
    ->name('visitastecnicas.')
    ->group(function () {

        /**
         * Visitas
         */
        Route::get(
            '/visitas',
            [
                \App\Http\Controllers\VisitasTecnicas\VisitaController::class,
                'index'
            ]
        )->name('visitas.index');

        Route::get(
            '/visitas/create/{id_visita}',
            [
                \App\Http\Controllers\VisitasTecnicas\VisitaController::class,
                'create'
            ]
        )->name('visitas.create');

        Route::post(
            '/visitas',
            [
                \App\Http\Controllers\VisitasTecnicas\VisitaController::class,
                'store'
            ]
        )->name('visitas.store');

        Route::get(
            '/visitas/{id}',
            [
                \App\Http\Controllers\VisitasTecnicas\VisitaController::class,
                'show'
            ]
        )->name('visitas.show');

        Route::post(
            '/visitas/{id}/guardar-borrador',
            [
                \App\Http\Controllers\VisitasTecnicas\VisitaController::class,
                'guardarBorrador'
            ]
        )->name('visitas.guardar-borrador');

        Route::post(
            '/visitas/{id}/finalizar',
            [
                \App\Http\Controllers\VisitasTecnicas\VisitaController::class,
                'finalizar'
            ]
        )->name('visitas.finalizar');

        Route::get(
            '/visitas/{id}/informe-tecnico',
            [
                \App\Http\Controllers\VisitasTecnicas\VisitaController::class,
                'descargarInforme'
            ]
        )->name('visitas.informe-tecnico.descargar');

        Route::post(
            '/visitas/{id}/informe-tecnico/reenviar',
            [
                \App\Http\Controllers\VisitasTecnicas\VisitaController::class,
                'reenviarInforme'
            ]
        )->name('visitas.informe-tecnico.reenviar');

        Route::post(
            '/visitas/{id}/solicitar-cotizacion',
            [
                \App\Http\Controllers\VisitasTecnicas\VisitaController::class,
                'solicitarCotizacion'
            ]
        )->name('visitas.solicitar-cotizacion');

        Route::post(
            '/visitas/{id}/reprogramar',
            [
                \App\Http\Controllers\VisitasTecnicas\VisitaController::class,
                'reprogramar'
            ]
        )->name('visitas.reprogramar');


        /**
         * Equipos
         */
        Route::get(
            '/visitas/{id}/seriales-sugeridos',
            [
                \App\Http\Controllers\VisitasTecnicas\EquipoController::class,
                'serialesSugeridos'
            ]
        )->name('equipos.seriales-sugeridos');

        Route::post(
            '/equipos',
            [
                \App\Http\Controllers\VisitasTecnicas\EquipoController::class,
                'store'
            ]
        )->name('equipos.store');

        Route::put(
            '/equipos/{id}',
            [
                \App\Http\Controllers\VisitasTecnicas\EquipoController::class,
                'update'
            ]
        )->name('equipos.update');

        Route::put(
            '/equipos/{id}/soluciones-complementarias',
            [
                \App\Http\Controllers\VisitasTecnicas\EquipoController::class,
                'agregarSolucionesComplementarias'
            ]
        )->name('equipos.soluciones-complementarias');

        Route::delete(
            '/equipos/{id}',
            [
                \App\Http\Controllers\VisitasTecnicas\EquipoController::class,
                'destroy'
            ]
        )->name('equipos.destroy');


        /**
         * Solicitudes de partes
         */
        Route::post(
            '/solicitudes-partes',
            [
                \App\Http\Controllers\VisitasTecnicas\SolicitudParteController::class,
                'store'
            ]
        )->name('solicitudes-partes.store');

        Route::put(
            '/solicitudes-partes/{id}',
            [
                \App\Http\Controllers\VisitasTecnicas\SolicitudParteController::class,
                'update'
            ]
        )->name('solicitudes-partes.update');

        Route::delete(
            '/solicitudes-partes/{id}',
            [
                \App\Http\Controllers\VisitasTecnicas\SolicitudParteController::class,
                'destroy'
            ]
        )->name('solicitudes-partes.destroy');


        /**
         * Búsqueda SENCO360 para equipos y repuestos
         */
        Route::get(
            '/senco360/herramientas/buscar',
            [
                \App\Http\Controllers\VisitasTecnicas\Senco360Controller::class,
                'buscarHerramientas'
            ]
        )->name('senco360.herramientas.buscar');

        Route::get(
            '/senco360/repuestos/buscar',
            [
                \App\Http\Controllers\VisitasTecnicas\Senco360Controller::class,
                'buscarRepuestos'
            ]
        )->name('senco360.repuestos.buscar');

        Route::get(
            '/senco360/herramientas/descripcion',
            [
                \App\Http\Controllers\VisitasTecnicas\Senco360Controller::class,
                'obtenerDescripcionHerramienta'
            ]
        )->name('senco360.herramientas.descripcion');

        Route::get(
            '/senco360/repuestos/descripcion',
            [
                \App\Http\Controllers\VisitasTecnicas\Senco360Controller::class,
                'obtenerDescripcionRepuesto'
            ]
        )->name('senco360.repuestos.descripcion');


        /**
         * Gestión de repuestos
         */
        Route::get(
            '/repuestos',
            [
                \App\Http\Controllers\VisitasTecnicas\RepuestoGestionController::class,
                'index'
            ]
        )->name('repuestos.index');

        Route::put(
            '/repuestos/estado/masivo',
            [
                \App\Http\Controllers\VisitasTecnicas\RepuestoGestionController::class,
                'actualizarEstadoMasivo'
            ]
        )->name('repuestos.estado.masivo');

        Route::put(
            '/repuestos/{id}/estado',
            [
                \App\Http\Controllers\VisitasTecnicas\RepuestoGestionController::class,
                'actualizarEstado'
            ]
        )->name('repuestos.estado');

        Route::get(
            '/repuestos/{id}/observaciones',
            [
                \App\Http\Controllers\VisitasTecnicas\RepuestoGestionController::class,
                'obtenerObservacionesRepuesto'
            ]
        )->name('repuestos.observaciones');


        /**
         * Fotos
         */
        Route::post(
            '/fotos',
            [
                \App\Http\Controllers\VisitasTecnicas\FotoController::class,
                'store'
            ]
        )->name('fotos.store');

        Route::delete(
            '/fotos/{id}',
            [
                \App\Http\Controllers\VisitasTecnicas\FotoController::class,
                'destroy'
            ]
        )->name('fotos.destroy');


        /**
         * Reprogramar ruta
         */
        Route::post(
            '/visitas/reprogramar-ruta/{id_visita}',
            [
                \App\Http\Controllers\VisitasTecnicas\VisitaController::class,
                'reprogramarRuta'
            ]
        )->name('visitas.reprogramar-ruta');


        /**
         * Módulo de Capacitaciones para Asesores
         */
        Route::prefix('capacitaciones')
            ->name('capacitaciones.')
            ->group(function () {

                Route::get(
                    '/',
                    [
                        \App\Http\Controllers\VisitasTecnicas\CapacitacionAsesorController::class,
                        'index'
                    ]
                )->name('index');

                Route::get(
                    '/libre/create',
                    [
                        \App\Http\Controllers\VisitasTecnicas\CapacitacionAsesorController::class,
                        'createLibre'
                    ]
                )
                    ->name('create-libre')
                    ->middleware('permission:capacitaciones.crear');

                Route::post(
                    '/libre',
                    [
                        \App\Http\Controllers\VisitasTecnicas\CapacitacionAsesorController::class,
                        'storeLibre'
                    ]
                )
                    ->name('store-libre')
                    ->middleware('permission:capacitaciones.crear');

                Route::get(
                    '/{id_visita}',
                    [
                        \App\Http\Controllers\VisitasTecnicas\CapacitacionAsesorController::class,
                        'show'
                    ]
                )->name('show');

                Route::get(
                    '/{id_visita}/edit',
                    [
                        \App\Http\Controllers\VisitasTecnicas\CapacitacionAsesorController::class,
                        'edit'
                    ]
                )->name('edit');

                Route::post(
                    '/{id_visita}',
                    [
                        \App\Http\Controllers\VisitasTecnicas\CapacitacionAsesorController::class,
                        'update'
                    ]
                )->name('update');

                Route::post(
                    '/{id_visita}/finalizar',
                    [
                        \App\Http\Controllers\VisitasTecnicas\CapacitacionAsesorController::class,
                        'finalizar'
                    ]
                )->name('finalizar');

                Route::delete(
                    '/{id_visita}',
                    [
                        \App\Http\Controllers\VisitasTecnicas\CapacitacionAsesorController::class,
                        'destroy'
                    ]
                )->name('destroy');
            });
    });