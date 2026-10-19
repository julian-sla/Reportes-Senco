<?php
namespace App\Http\Controllers\VisitasTecnicas;

use App\Http\Controllers\Controller;
use App\Models\RutaTecnica;
use App\Models\Senco360\TipoServicio;
use App\Models\Senco360\TipoFalla;
use App\Models\Senco360\TipoMant;
use App\Models\Senco360\TipoSolucion;
use App\Models\Senco360\VisitaEncab;
use App\Models\Senco360\VisitaEstado;
use App\Models\Senco360\VisitaEstadoHistorico;
use App\Repositories\VisitasTecnicas\VisitaRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\InformeTecnicoVisitaMail;
use App\Mail\NotificacionSolicitudCotizacion;
use App\Services\VisitasTecnicas\InformeTecnicoVisitaService;
use App\Models\Senco360\SolicitudParte;

class VisitaController extends Controller
{
    private const ESTADO_PENDIENTE_POR_INICIAR = 0;
    private const ESTADO_EN_PROCESO = 1;
    private const ESTADO_COMPLETADO = 2;
    private const ESTADO_PENDIENTE_REPUESTOS = 6;

    public function __construct(
        protected VisitaRepository $repo,
        protected InformeTecnicoVisitaService $informeTecnicoService
    )
    {
        $this->middleware('permission:visitastecnicas.ver');
    }

    private function puedeVerTodasLasVisitas($user): bool
    {
        return $user->hasRole('super-admin')
            || $user->hasPermissionTo('visitastecnicas.ver-todos');
    }

    private function codigoTecnicoDelUsuario($user): ?string
    {
        $codigo = trim((string) ($user->codigo_vendedor ?? ''));

        return $codigo !== '' ? $codigo : null;
    }

    private function obtenerRutaAsignadaOAbortar(string $idVisita): RutaTecnica
    {
        $user = auth()->user();
        $query = RutaTecnica::with('visitaEncab')->where('IdVisita', $idVisita);

        if (!$this->puedeVerTodasLasVisitas($user)) {
            $codigoTecnico = $this->codigoTecnicoDelUsuario($user);
            abort_if(!$codigoTecnico, 403, 'Tu usuario no tiene un código técnico asignado.');
            $query->where('CodTecnico', $codigoTecnico);
        }

        return $query->firstOrFail();
    }

    private function obtenerVisitaOAbortar(int $id)
    {
        $user = auth()->user();
        $codigoTecnico = $this->puedeVerTodasLasVisitas($user)
            ? ''
            : ($this->codigoTecnicoDelUsuario($user) ?? '__sin_codigo__');

        abort_if($codigoTecnico === '__sin_codigo__', 403, 'Tu usuario no tiene un código técnico asignado.');

        $visita = $this->repo->findById($id, $codigoTecnico);
        abort_if(!$visita, 404, 'Visita no encontrada.');

        return $visita;
    }

    private function abortarSiNoFinalizada($visita): void
    {
        abort_if(
            (int) $visita->ID_ESTADO_ACTUAL !== self::ESTADO_COMPLETADO,
            403,
            'El informe técnico solo está disponible para visitas finalizadas.'
        );
    }

    private function enviarInformeTecnico(VisitaEncab $visita, array $destinatarios): void
    {
        $destinatarios = collect($destinatarios)
            ->filter(fn ($email) => filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->map(fn ($email) => strtolower(trim($email)))
            ->unique()
            ->values();

        if ($destinatarios->isEmpty()) {
            Log::info('No hay destinatarios válidos para informe técnico de visita', ['visita_id' => $visita->ID]);
            return;
        }

        $pdfContenido = $this->informeTecnicoService->generarPdf($visita)->output();
        $nombreArchivo = $this->informeTecnicoService->nombreArchivo($visita);

        foreach ($destinatarios as $email) {
            Mail::to($email)->send(new InformeTecnicoVisitaMail($visita, $pdfContenido, $nombreArchivo));
        }
    }

    public function reprogramarRuta(Request $request, string $id_visita): RedirectResponse
    {
        $request->validate([
            'fecha_reprogramacion' => 'required|date',
            'motivo'               => 'required|string|max:500',
        ]);

        $estadoReprogramadoId = $this->obtenerEstadoId('Reprogramado');
        $ruta = $this->obtenerRutaAsignadaOAbortar($id_visita);

        DB::connection('senco360')->transaction(function () use ($ruta, $request, $estadoReprogramadoId) {
            $ruta->update([
                'FechaVisita' => $request->fecha_reprogramacion,
            ]);

            if ($ruta->visitaEncab) {
                $this->repo->actualizar($ruta->visitaEncab->ID, [
                    'FECHA_REPROGRAMACION' => $request->fecha_reprogramacion,
                ]);

                $this->repo->cambiarEstado(
                    $ruta->visitaEncab->ID,
                    $estadoReprogramadoId,
                    $request->motivo
                );
                return;
            }

            $visita = $this->repo->crear([
                'ID_VISITA'            => $ruta->IdVisita,
                'FECHA_REPROGRAMACION' => $request->fecha_reprogramacion,
                'ID_ESTADO_ACTUAL'     => $estadoReprogramadoId,
                'OBSERVACIONES'        => null,
            ]);

            VisitaEstadoHistorico::create([
                'ID_ENC_VISITA' => $visita->ID,
                'ID_ESTADO'     => $estadoReprogramadoId,
                'FECHA'         => now(),
                'OBSERVACIONES' => $request->motivo,
                'ID_USUARIO'    => auth()->id(),
            ]);
        });

        return back()->with('success', 'Visita reprogramada correctamente.');
    }


    /**
     * Index — lista de rutas cerradas del técnico
     */
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $puedeVerTodas = $this->puedeVerTodasLasVisitas($user);
        $estadoReprogramadoId = $this->obtenerEstadoId('Reprogramado');
       $estadoFiltro = (string) $request->get('estado', 'todos');
        $codigoTecnico = $puedeVerTodas ? null : $this->codigoTecnicoDelUsuario($user);

        $query = RutaTecnica::query()
            ->where('cerrada', 1)
            ->where('invalido', 0)
            ->with('visitaEncab.estadoActual', 'visitaEncab.tipoServicio');

        if ($codigoTecnico) {
            $query->where('CodTecnico', $codigoTecnico);
        } elseif (!$puedeVerTodas) {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('NombreCliente', 'like', "%{$buscar}%")
                    ->orWhere('Nit', 'like', "%{$buscar}%")
                    ->orWhere('NumeroRuta', 'like', "%{$buscar}%")
                    ->orWhere('CodTecnico', 'like', "%{$buscar}%");
            });
        }

        if ($estadoFiltro === (string) self::ESTADO_PENDIENTE_POR_INICIAR || $estadoFiltro === '') {
            $query->whereDoesntHave('visitaEncab');
        } elseif ($estadoFiltro !== 'todos') {
            $query->whereHas('visitaEncab', fn ($q) => $q->where('ID_ESTADO_ACTUAL', (int) $estadoFiltro));
        }

        $rutas = $query
            ->orderBy('FechaVisita')
            ->orderBy('IdVisita')
            ->get();

        return Inertia::render('VisitasTecnicas/Visitas/Index', [
            'visitas' => $rutas->map(fn ($ruta) => [
    'id' => $ruta->IdVisita,
    'id_visita' => $ruta->IdVisita,

    'numero_ruta' => $ruta->NumeroRuta,

    'fecha_visita' => $ruta->FechaVisita
        ? \Carbon\Carbon::parse($ruta->FechaVisita)->format('Y-m-d')
        : null,

    'fecha_inicio_ruta' => $ruta->FechaInicio
        ? \Carbon\Carbon::parse($ruta->FechaInicio)->format('Y-m-d')
        : null,

    'fecha_fin_ruta' => $ruta->FechaFin
        ? \Carbon\Carbon::parse($ruta->FechaFin)->format('Y-m-d')
        : null,

    'hora_inicio' => $ruta->visitaEncab?->HORA_INICIO,
    'hora_fin' => $ruta->visitaEncab?->HORA_FIN,

    'fecha_inicio_visita' => $ruta->visitaEncab?->FECHA_INICIO,
    'fecha_fin_visita' => $ruta->visitaEncab?->FECHA_FIN,

    'fecha_reprogramacion' => $ruta->visitaEncab?->FECHA_REPROGRAMACION,

    'tipo_servicio' => $ruta->visitaEncab?->tipoServicio?->TIPO_SERVICIO ?? null,

    'cliente' => [
        'nombre' => $ruta->NombreCliente,
        'nit' => $ruta->Nit,
    ],

    'direccion' => $ruta->DireccionCompleta,

    'contacto' => $ruta->NomContacto,
    'telefono_contacto' => $ruta->TelContacto,

    'tecnico' => $ruta->CodTecnico,
    'vendedor' => $ruta->CodVendedor,

    'observaciones_ruta' => $ruta->Observaciones,
    'observaciones_visita' => $ruta->visitaEncab?->OBSERVACIONES,

    'correo_cliente' => $ruta->visitaEncab?->CORREO,

    'motivo_reprogramacion' => $ruta->visitaEncab?->MOTIVO_REPROGRAMACION,

    'estado' => $ruta->visitaEncab?->estadoActual?->ESTADO ?? 'Pendiente por Iniciar',

    'estado_id' => $ruta->visitaEncab?->ID_ESTADO_ACTUAL
        ?? self::ESTADO_PENDIENTE_POR_INICIAR,

    'puede_iniciar' => is_null($ruta->visitaEncab)
        || (int) ($ruta->visitaEncab?->ID_ESTADO_ACTUAL ?? 0) === $estadoReprogramadoId,

    'visita_id' => $ruta->visitaEncab?->ID,

    'cerrada' => (bool) $ruta->cerrada,

    'fecha_cierre' => $ruta->fecha_cierre
        ? \Carbon\Carbon::parse($ruta->fecha_cierre)->format('Y-m-d H:i:s')
        : null,
]),
            'filtros' => [
                'buscar' => $request->buscar ?? '',
                'estado' => $estadoFiltro,
            ],
            'estados_visita' => VisitaEstado::where('descripcion', 'Visita')
                ->where('ID', '!=', 28)
                ->get(['ID', 'ESTADO']),
            'puedeCrearCapacitacionLibre' => $user->permission_names->contains('capacitaciones.crear'),
            'esCapacitacionModulo' => false,
        ]);
    }

    /**
     * Create — formulario para iniciar visita
     */
    public function create(string $id_visita): Response
    {
        $estadoReprogramadoId = $this->obtenerEstadoId('Reprogramado');
        $ruta = $this->obtenerRutaAsignadaOAbortar($id_visita);

        $visitaExistente = $ruta->visitaEncab;
        abort_if(
            $visitaExistente && (int) $visitaExistente->ID_ESTADO_ACTUAL !== $estadoReprogramadoId,
            403,
            'Esta visita ya fue iniciada.'
        );

        return Inertia::render('VisitasTecnicas/Visitas/Create', [
            'id_visita'      => $id_visita,
            'ruta_tecnica'   => [
                'numero_ruta'  => $ruta->NumeroRuta,
                'cliente'      => $ruta->NombreCliente,
                'nit'          => $ruta->Nit,
                'direccion'    => $ruta->DireccionCompleta,
                'fecha_visita' => $ruta->FechaVisita
                    ? \Carbon\Carbon::parse($ruta->FechaVisita)->format('Y-m-d')
                    : null,
                'nom_contacto' => $ruta->NomContacto,
                'tel_contacto' => $ruta->TelContacto,
                'es_propia'    => trim((string) $ruta->CodVendedor) === trim((string) $ruta->CodTecnico),
            ],
            'tipos_servicio' => TipoServicio::orderBy('TIPO_SERVICIO')->get(['ID', 'TIPO_SERVICIO']),
        ]);
    }

    /**
     * Store — crear encabezado de visita
     */
public function store(Request $request): JsonResponse|RedirectResponse
{
    $esCapacitacion = $request->boolean('es_capacitacion');
    $estadoReprogramadoId = $this->obtenerEstadoId('Reprogramado');

    $rules = [
        'id_visita'        => 'required|string',
        'id_tipo_servicio' => 'required|integer',
        'hora_inicio'      => 'nullable|date_format:H:i',
        'hora_fin'         => 'nullable|date_format:H:i',
        'correo_cliente'   => 'nullable|email',
    ];

    if ($esCapacitacion) {
        $rules['fecha_inicio'] = 'required|date';
        $rules['fecha_fin'] = 'required|date|after_or_equal:fecha_inicio';
        $rules['hora_inicio'] = 'required|date_format:H:i';
        $rules['hora_fin'] = 'required|date_format:H:i';
        $rules['titulo'] = 'required|string|max:200';
        $rules['temas']  = 'nullable|string|max:1000';
        $rules['firma']  = 'nullable|string'; // base64
        $rules['fotos']  = 'nullable|array';
        $rules['fotos.*']= 'nullable|image|max:5120';
        $rules['observaciones'] = 'nullable|string|max:1000';
    }

    $request->validate($rules);

    $user = auth()->user();
    $rutaAsignada = $this->obtenerRutaAsignadaOAbortar($request->id_visita);
    $esRutaPropia = trim((string) $rutaAsignada->CodVendedor) === trim((string) $rutaAsignada->CodTecnico);

    if ($esRutaPropia) {
        $tipoServicio = TipoServicio::find($request->id_tipo_servicio);
        $esTipoCapacitacion = str_contains(strtolower($tipoServicio?->TIPO_SERVICIO ?? ''), 'capacit');

        abort_if(
            !$esCapacitacion || !$esTipoCapacitacion,
            422,
            'Las visitas asignadas al mismo asesor solo pueden registrarse como capacitación.'
        );
    }

    $visitaExistente = \App\Models\Senco360\VisitaEncab::where('ID_VISITA', $request->id_visita)->first();

    abort_if(
        $visitaExistente && (int) $visitaExistente->ID_ESTADO_ACTUAL !== $estadoReprogramadoId,
        403,
        'Esta visita ya fue iniciada.'
    );

    if ($esCapacitacion) {
        // Estado directo: Completado (2)
        $estadoFinal = VisitaEstado::find(2);

        $dataVisita = [
            'ID_VISITA'        => $request->id_visita,
            'CORREO'           => $request->correo_cliente,
            'HORA_INICIO'      => $request->hora_inicio,
            'HORA_FIN'         => $request->hora_fin,
            'FECHA_INICIO'     => $request->fecha_inicio,
            'FECHA_FIN'        => $request->fecha_fin,
            'ID_TIPO_SERVICIO' => $request->id_tipo_servicio,
            'OBSERVACIONES'    => null,
            'ID_ESTADO_ACTUAL' => 2,
        ];

        $visita = $visitaExistente
            ? $this->repo->actualizar($visitaExistente->ID, $dataVisita)
            : $this->repo->crear($dataVisita);

        // 1. Crear registro de capacitación PRIMERO
        $detal = \App\Models\Senco360\VisitaDetal::create([
            'ID_ENC_VISITA'    => $visita->ID,
            'ID_COD_MAX'       => 'CAP',
            'TITULO'           => $request->titulo,
            'DESCRIPCION_FALLA'=> $request->temas,
            'ID_SOLUCION'      => null,
            'OBSERVACIONES'    => $request->observaciones,
        ]);

        // 2. Guardar firma si fue diligenciada
        if ($request->filled('firma')) {
            $firmaBase64 = $request->firma;
            if (str_contains($firmaBase64, ',')) {
                $firmaBase64 = explode(',', $firmaBase64)[1];
            }
            $firmaBlob = base64_decode($firmaBase64);
            $rutaFirma = 'visitas/firmas/' . $visita->ID . '_firma.png';
            \Illuminate\Support\Facades\Storage::disk('public')->put($rutaFirma, $firmaBlob);
            \App\Models\Senco360\VisitaFoto::create([
                'ID_DETALLE_VISITA' => $detal->ID,
                'TIPO'              => 'FIRMA',
                'RUTA_FOTO'         => $rutaFirma,
            ]);
        }

        // 3. Guardar fotos
        if ($request->hasFile('fotos')) {
            foreach ($request->file('fotos') as $foto) {
                $ruta = $foto->store('visitas/equipos/' . $detal->ID, 'public');
                \App\Models\Senco360\VisitaFoto::create([
                    'ID_DETALLE_VISITA' => $detal->ID,
                    'TIPO'              => 'DESPUES',
                    'RUTA_FOTO'         => $ruta,
                ]);
            }
        }

        // Historial
        VisitaEstadoHistorico::create([
            'ID_ENC_VISITA' => $visita->ID,
            'ID_ESTADO'     => 2,
            'FECHA'         => now(),
            'OBSERVACIONES' => $request->observaciones ?? 'Capacitación completada',
            'ID_USUARIO'    => $user->id,
        ]);

        $visitaFinalizada = $this->repo->findById($visita->ID, '');
        if ($visitaFinalizada) {
            $this->enviarInformeTecnico(
                $visitaFinalizada,
                $this->informeTecnicoService->destinatariosFinalizacion($visitaFinalizada)
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Informe enviado exitosamente.',
                'redirect' => route('visitastecnicas.visitas.index'),
            ]);
        }

        return redirect()->route('visitastecnicas.visitas.index')
            ->with('success', 'Capacitación registrada correctamente.');
    }

    // Flujo normal (mantenimiento/correctivo)
    $estadoEnProceso = VisitaEstado::where('ESTADO', 'En proceso')->firstOrFail();

    $dataVisita = [
        'ID_VISITA'        => $request->id_visita,
        'CORREO'           => $request->correo_cliente,
        'HORA_INICIO'      => $request->hora_inicio,
        'HORA_FIN'         => $request->hora_fin,
        'FECHA_INICIO'     => now()->format('Y-m-d'),
        'ID_TIPO_SERVICIO' => $request->id_tipo_servicio,
        'OBSERVACIONES'    => null,
        'ID_ESTADO_ACTUAL' => $estadoEnProceso->ID,
    ];

    $visita = $visitaExistente
        ? $this->repo->actualizar($visitaExistente->ID, $dataVisita)
        : $this->repo->crear($dataVisita);

    VisitaEstadoHistorico::create([
        'ID_ENC_VISITA' => $visita->ID,
        'ID_ESTADO'     => $estadoEnProceso->ID,
        'FECHA'         => now(),
        'OBSERVACIONES' => 'Visita iniciada',
        'ID_USUARIO'    => $user->id,
    ]);

    return redirect()->route('visitastecnicas.visitas.show', $visita->ID)
        ->with('success', 'Visita registrada correctamente.');
}
    /**
     * Show — pantalla principal de trabajo
     */
public function show(int $id): Response
{
    $visita = $this->obtenerVisitaOAbortar($id);

    $esCapacitacion = str_contains(
        strtolower($visita->tipoServicio?->TIPO_SERVICIO ?? ''),
        'capacit'
    );

    $codigosRepuestos = $visita->detalle
        ->flatMap(fn ($detalle) => $detalle->solicitudesPartes)
        ->pluck('ID_COD_MAX_PARTES')
        ->filter()
        ->unique()
        ->values()
        ->all();

    $repuestosInfo = empty($codigosRepuestos)
        ? collect()
        : DB::connection('senco360')
            ->table('vRepuestosMax')
            ->select([
                'Codigo Repuesto as codigo',
                'Descripcion Repuesto as descripcion',
                'Codigo Proveedor as codigo_proveedor',
                'Inventario as inventario',
            ])
            ->whereIn('Codigo Repuesto', $codigosRepuestos)
            ->get()
            ->keyBy(fn ($repuesto) => trim((string) $repuesto->codigo));

    return Inertia::render('VisitasTecnicas/Visitas/Show', [
        'visita' => [
            'id'                   => $visita->ID,
            'id_visita'            => $visita->ID_VISITA,
            'correo_cliente'       => $visita->CORREO,
            'propietario_firma'    => $visita->PROPIETARIOFIRMA,
            'hora_inicio'          => $visita->HORA_INICIO,
            'hora_fin'             => $visita->HORA_FIN,
            'fecha_inicio'         => $visita->FECHA_INICIO,
            'fecha_fin'            => $visita->FECHA_FIN,
            'observaciones'        => $esCapacitacion
                ? $visita->detalle->first()?->OBSERVACIONES
                : $visita->OBSERVACIONES,
            'tiene_firma' => $visita->detalle->some(fn($e) =>
                $e->fotos->where('TIPO', 'FIRMA')->isNotEmpty()
            ),
            'firma_url' => (function() use ($visita) {
                $foto = $visita->detalle
                    ->flatMap(fn($e) => $e->fotos->where('TIPO', 'FIRMA'))
                    ->first();
                return $foto ? asset('storage/' . $foto->RUTA_FOTO) : null;
            })(),
            'estado'               => $visita->estadoActual?->ESTADO,
            'estado_id'            => $visita->ID_ESTADO_ACTUAL,
            'detalle_publicado_cotizacion' => (bool) $visita->DETALLE_PUBLICADO_COTIZACION,
            'tipo_servicio'        => $visita->tipoServicio?->TIPO_SERVICIO,
            'tipo_servicio_id'     => $visita->ID_TIPO_SERVICIO,
            'es_capacitacion'      => $esCapacitacion,
            'puede_finalizar'      => in_array((int) $visita->ID_ESTADO_ACTUAL, [self::ESTADO_EN_PROCESO, self::ESTADO_PENDIENTE_REPUESTOS], true),
            'puede_reprogramar'    => in_array((int) $visita->ID_ESTADO_ACTUAL, [self::ESTADO_EN_PROCESO, self::ESTADO_PENDIENTE_REPUESTOS], true),
            'puede_agregar_equipo' => (int) $visita->ID_ESTADO_ACTUAL === self::ESTADO_EN_PROCESO && !(bool) $visita->DETALLE_PUBLICADO_COTIZACION,
            'puede_editar_detalle' => (int) $visita->ID_ESTADO_ACTUAL === self::ESTADO_EN_PROCESO && !(bool) $visita->DETALLE_PUBLICADO_COTIZACION,
            'puede_guardar_borrador' => (int) $visita->ID_ESTADO_ACTUAL === self::ESTADO_EN_PROCESO && !(bool) $visita->DETALLE_PUBLICADO_COTIZACION,
            'puede_gestionar_evidencia_final' => in_array((int) $visita->ID_ESTADO_ACTUAL, [self::ESTADO_EN_PROCESO, self::ESTADO_PENDIENTE_REPUESTOS], true),
            'puede_solicitar_cotizacion' => (int) $visita->ID_ESTADO_ACTUAL === self::ESTADO_EN_PROCESO && !(bool) $visita->DETALLE_PUBLICADO_COTIZACION,
            'cliente'      => $visita->rutaTecnica ? [
                'nombre' => $visita->rutaTecnica->NombreCliente,
                'nit'    => $visita->rutaTecnica->Nit,
            ] : null,
            'direccion'    => $visita->rutaTecnica?->DireccionCompleta,
            'numero_ruta'  => $visita->rutaTecnica?->NumeroRuta,
            'fecha_visita' => $visita->rutaTecnica?->FechaVisita
                ? \Carbon\Carbon::parse($visita->rutaTecnica->FechaVisita)->format('Y-m-d')
                : null,
            'nom_contacto'  => $visita->rutaTecnica?->NomContacto,
            'tel_contacto' => $visita->rutaTecnica?->TelContacto,
            'observaciones_ruta' => $visita->rutaTecnica?->Observaciones,
        ],
        'equipos' => $visita->detalle->map(fn($e) => [
            'soluciones'        => $e->tiposSolucion->isNotEmpty()
                ? $e->tiposSolucion->pluck('TIPO_SOLUCION')->values()
                : collect([$e->tipoSolucion?->TIPO_SOLUCION])->filter()->values(),
            'soluciones_ids'    => $e->tiposSolucion->isNotEmpty()
                ? $e->tiposSolucion->pluck('ID')->map(fn ($id) => (int) $id)->values()
                : collect([$e->ID_SOLUCION])->filter()->map(fn ($id) => (int) $id)->values(),
            'fallas'           => $e->tiposFalla->isNotEmpty()
                ? $e->tiposFalla->map(fn ($falla) => [
                    'id' => (int) $falla->ID,
                    'descripcion' => $falla->DESCRIPCION,
                    'descripcion_otros' => $falla->pivot?->DESCRIPCION_OTROS,
                ])->values()
                : collect([$e->DESCRIPCION_FALLA])->filter()->values(),
            'fallas_ids'       => $e->tiposFalla->pluck('ID')->map(fn ($id) => (int) $id)->values(),
            'id'               => $e->ID,
            'id_cod_max'       => $e->ID_COD_MAX,
            'titulo'           => $e->TITULO,
            'serial'           => $e->SERIAL,
            'tipo_mant'        => $e->tipoMant?->TIPO_MANT,
            'id_tipo_mant'     => $e->ID_TIPO_MANT,
            'descripcion_falla'=> $e->DESCRIPCION_FALLA,
            'solucion'         => $e->tipoSolucion?->TIPO_SOLUCION,
            'solucion_id'      => $e->ID_SOLUCION,
            'observaciones'    => $e->OBSERVACIONES,
            'fotos_antes'      => $e->fotos->where('TIPO', 'ANTES')->map(fn($f) => [
                'id' => $f->ID,
                'url' => asset('storage/' . $f->RUTA_FOTO)
            ])->values(),
            'fotos_despues'    => $e->fotos->where('TIPO', 'DESPUES')->map(fn($f) => [
                'id' => $f->ID,
                'url' => asset('storage/' . $f->RUTA_FOTO)
            ])->values(),
            'repuestos'        => $e->solicitudesPartes->map(function ($r) use ($repuestosInfo) {
                $infoRepuesto = $repuestosInfo->get(trim((string) $r->ID_COD_MAX_PARTES));

                return [
                    'id'         => $r->ID,
                    'id_cod_max' => $r->ID_COD_MAX_PARTES,
                    'descripcion'=> $infoRepuesto?->descripcion,
                    'codigo_proveedor' => $infoRepuesto?->codigo_proveedor,
                    'inventario' => $infoRepuesto?->inventario,
                    'cantidad'   => $r->CANTIDAD,
                    'estado'     => $r->estado?->ESTADO,
                    'estado_id'  => $r->ID_ESTADO,
                    'observacion'=> $r->OBSERVACION,
                    'es_urgente' => (bool) $r->ES_URGENTE,
                ];
            }),
        ]),
        'tipos_mant'          => TipoMant::orderBy('TIPO_MANT')->get(['ID', 'TIPO_MANT']),
        'tipos_falla'         => TipoFalla::orderBy('DESCRIPCION')->get(['ID', 'DESCRIPCION']),
        'tipos_solucion'      => TipoSolucion::orderBy('TIPO_SOLUCION')->get(['ID', 'TIPO_SOLUCION']),
        'estados_repuesto' => VisitaEstado::whereIn('ID', [13, 14, 15, 16, 17, 18, 19, 27])->get(['ID', 'ESTADO']),
        'historial'           => $visita->historialEstados->map(fn($h) => [
            'estado'        => $h->estado?->ESTADO,
            'fecha'         => $h->FECHA
                ? \Carbon\Carbon::parse($h->FECHA)->format('d/m/Y H:i')
                : null,
            'observaciones' => $h->OBSERVACIONES,
            'usuario'       => $h->usuario?->name ?? null,
            'repuesto'      => filled($h->ID_SOLICITUD_PARTE)
                ? ($h->solicitudParte?->ID_COD_MAX_PARTES ?? ('Repuesto #' . $h->ID_SOLICITUD_PARTE))
                : null,
            'repuesto_descripcion' => filled($h->ID_SOLICITUD_PARTE)
                ? optional($repuestosInfo->get(trim((string) $h->solicitudParte?->ID_COD_MAX_PARTES)))->descripcion
                : null,
        ]),
        'historial_repuestos' => $visita->detalle->flatMap(fn($e) =>
            $e->solicitudesPartes->map(fn($r) => [
                'equipo'    => $e->ID_COD_MAX,
                'repuesto'  => $r->ID_COD_MAX_PARTES,
                'cantidad'  => $r->CANTIDAD,
                'estado'    => $r->estado?->ESTADO,
                'estado_id' => $r->ID_ESTADO,
                'fecha'     => $r->FECHA
                    ? \Carbon\Carbon::parse($r->FECHA)->format('d/m/Y H:i')
                    : ($r->created_at
                        ? \Carbon\Carbon::parse($r->created_at)->format('d/m/Y H:i')
                        : null),
            ])
        )->values(),
    ]);
}

    /**
     * Finalizar visita
     */
    public function finalizar(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'observaciones' => 'nullable|string|max:1000',
            'correo_cliente' => 'nullable|email',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i',
            'propietario_firma' => 'nullable|string|max:255',
            'firma' => 'nullable|string',
        ]);

        $visita = $this->obtenerVisitaOAbortar($id);

        // Determinar estado final
        $esCapacitacion = $this->informeTecnicoService->esCapacitacion($visita);

        if ($esCapacitacion) {
            $estadoFinal = self::ESTADO_COMPLETADO;
        } else {
                        $todosResueltos = $visita->detalle->every(fn($e) =>
                $e->solicitudesPartes->isEmpty() ||
                $e->solicitudesPartes->every(fn($r) => in_array($r->ID_ESTADO, [
                    19, // Instalado
                    15, // Rechazado (no impide cierre)
                ]))
            );

            // Hay repuestos pendientes si alguno está en espera, solicitado, facturado o despachado
            $hayPendientes = $visita->detalle->some(fn($e) =>
                $e->solicitudesPartes->some(fn($r) => in_array($r->ID_ESTADO, [
                    13, // Solicitud Cotización
                    27, // Repuesto Cotizado
                    14, // Repuesto Solicitado
                    16, // Repuesto Facturado
                    17, // Repuesto En Espera
                    18, // Repuesto Despachado
                ]))
            );

            $estadoFinal = !$hayPendientes
                ? self::ESTADO_COMPLETADO
                : self::ESTADO_PENDIENTE_REPUESTOS;
        }

        $actualizacion = [];

        if (!$esCapacitacion) {
            $request->validate([
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
                'hora_inicio' => 'required|date_format:H:i',
                'hora_fin' => 'required|date_format:H:i',
                'propietario_firma' => 'nullable|string|max:255',
                'firma' => 'nullable|string',
            ]);

            // No permitir cambiar fecha_inicio y hora_inicio si ya fue enviado a cotización
            if ((int) $visita->ID_ESTADO_ACTUAL === self::ESTADO_PENDIENTE_REPUESTOS) {
                // Comparar solo HH:mm (sin segundos)
                $horaDbFormato = substr($visita->HORA_INICIO, 0, 5);
                abort_if(
                    $request->fecha_inicio !== $visita->FECHA_INICIO ||
                    $request->hora_inicio !== $horaDbFormato,
                    403,
                    'No se puede modificar fecha de inicio ni hora de inicio después de enviar a cotización.'
                );
            }

            $actualizacion['CORREO'] = $request->correo_cliente;
            $actualizacion['FECHA_INICIO'] = $request->fecha_inicio;
            $actualizacion['FECHA_FIN'] = $request->fecha_fin;
            $actualizacion['HORA_INICIO'] = $request->hora_inicio;
            $actualizacion['HORA_FIN'] = $request->hora_fin;
            $actualizacion['PROPIETARIOFIRMA'] = $request->propietario_firma;
        } else {
            $actualizacion['CORREO'] = $request->correo_cliente ?? $visita->CORREO;
            $actualizacion['FECHA_FIN'] = now()->format('Y-m-d');
            $actualizacion['HORA_FIN'] = now()->format('H:i:s');
        }

        $actualizacion['OBSERVACIONES'] = $request->observaciones;

        $this->repo->actualizar($id, $actualizacion);

        if (!$esCapacitacion && $request->filled('firma')) {
            $detalleFirma = $visita->detalle->first();

            if ($detalleFirma) {
                $detalleFirma->fotos()->where('TIPO', 'FIRMA')->each(function ($foto) {
                    Storage::disk('public')->delete($foto->RUTA_FOTO);
                    $foto->delete();
                });

                $firmaBase64 = $request->firma;
                if (str_contains($firmaBase64, ',')) {
                    $firmaBase64 = explode(',', $firmaBase64)[1];
                }

                $rutaFirma = 'visitas/firmas/' . $visita->ID . '_firma.png';
                Storage::disk('public')->put($rutaFirma, base64_decode($firmaBase64));

                \App\Models\Senco360\VisitaFoto::create([
                    'ID_DETALLE_VISITA' => $detalleFirma->ID,
                    'TIPO'              => 'FIRMA',
                    'RUTA_FOTO'         => $rutaFirma,
                ]);
            }
        }

        $this->repo->cambiarEstado($id, $estadoFinal, $request->observaciones ?? 'Visita finalizada');

        if ((int) $estadoFinal === self::ESTADO_COMPLETADO) {
            $visitaFinalizada = $this->repo->findById($id, '');
            if ($visitaFinalizada) {
                $this->enviarInformeTecnico(
                    $visitaFinalizada,
                    $this->informeTecnicoService->destinatariosFinalizacion($visitaFinalizada)
                );
            }
        }

        return redirect()->route('visitastecnicas.visitas.index')
            ->with('success', 'Visita finalizada correctamente.');
    }

    public function descargarInforme(int $id)
    {
        $visita = $this->obtenerVisitaOAbortar($id);
        $this->abortarSiNoFinalizada($visita);

        return $this->informeTecnicoService
            ->generarPdf($visita)
            ->download($this->informeTecnicoService->nombreArchivo($visita));
    }

    public function reenviarInforme(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $request->validate([
            'correo' => 'required|email',
        ]);

        $visita = $this->obtenerVisitaOAbortar($id);
        $this->abortarSiNoFinalizada($visita);

        $this->enviarInformeTecnico($visita, [$request->correo]);

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Informe reenviado correctamente.',
            ]);
        }

        return back()->with('success', 'Informe reenviado correctamente.');
    }

    public function guardarBorrador(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'correo_cliente' => 'nullable|email',
            'fecha_inicio' => 'nullable|date',
            'hora_inicio' => 'nullable|date_format:H:i',
        ]);

        $visita = $this->obtenerVisitaOAbortar($id);

        abort_if(
            (int) $visita->ID_ESTADO_ACTUAL !== self::ESTADO_EN_PROCESO || (bool) $visita->DETALLE_PUBLICADO_COTIZACION,
            403,
            'La visita no permite guardar cambios en el estado actual.'
        );

        $this->repo->actualizar($id, [
            'CORREO' => $request->correo_cliente,
            'FECHA_INICIO' => $request->fecha_inicio,
            'HORA_INICIO' => $request->hora_inicio,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Los cambios se guardaron correctamente.',
        ]);
    }

    public function solicitarCotizacion(Request $request, int $id): JsonResponse|RedirectResponse
    {
        Log::info('solicitarCotizacion called', [
            'method' => $request->method(),
            'id' => $id,
            'body' => $request->all(),
        ]);

        try {
            $validated = $request->validate([
                'observaciones' => 'nullable|string|max:1000',
                'correo_cliente' => 'nullable|email',
                'fecha_inicio' => 'required|date',
                'hora_inicio' => 'required|date_format:H:i',
            ]);
            Log::info('Validación pasada', ['datos' => $validated]);
        } catch (\Exception $e) {
            Log::error('Error validación', ['error' => $e->getMessage()]);
            throw $e;
        }

        $visita = $this->obtenerVisitaOAbortar($id);
        Log::info('Visita cargada', ['visita_id' => $visita?->ID, 'estado' => $visita?->ID_ESTADO_ACTUAL]);

        if ((int) $visita->ID_ESTADO_ACTUAL !== self::ESTADO_EN_PROCESO) {
            Log::warning('Estado incorrecto', ['estado_actual' => (int) $visita->ID_ESTADO_ACTUAL, 'esperado' => self::ESTADO_EN_PROCESO]);
            return response()->json([
                'success' => false,
                'message' => 'La visita no se puede enviar a cotización en este estado. Estado actual: ' . $visita->estadoActual?->ESTADO,
            ], 403);
        }

        if ((bool) $visita->DETALLE_PUBLICADO_COTIZACION) {
            return response()->json([
                'success' => false,
                'message' => 'El detalle de la visita ya fue enviado a cotización.',
            ], 403);
        }

        $hayPendientes = $visita->detalle->some(fn($e) =>
            $e->solicitudesPartes->some(fn($r) => in_array($r->ID_ESTADO, [
                13,
                27,
                14,
                16,
                17,
                18,
            ]))
        );

        Log::info('Verificación pendientes', ['hayPendientes' => $hayPendientes, 'detalles' => $visita->detalle->count()]);

        if (!$hayPendientes) {
            Log::warning('No hay repuestos pendientes', ['visita_id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'La visita no tiene repuestos pendientes para cotización. Debe agregar al menos un repuesto.',
            ], 403);
        }

        $this->repo->actualizar($id, [
            'CORREO'      => $request->correo_cliente,
            'FECHA_INICIO' => $request->fecha_inicio,
            'HORA_INICIO' => $request->hora_inicio,
            'DETALLE_PUBLICADO_COTIZACION' => 1,
        ]);
        Log::info('Visita actualizada');

        $this->repo->cambiarEstado($id, self::ESTADO_PENDIENTE_REPUESTOS, $request->observaciones ?? 'Visita enviada a cotización');
        Log::info('Estado cambiado a PENDIENTE_REPUESTOS');

        // Enviar notificación al asesor/técnico
        try {
            $ruta = $visita->rutaTecnica ?? RutaTecnica::find($visita->ID_VISITA);
            Log::info('Buscando ruta técnica para notificación', [
                'visita_id' => $id,
                'id_visita' => $visita->ID_VISITA,
                'ruta_id' => $ruta?->IdVisita,
                'CodVendedor' => $ruta?->CodVendedor,
            ]);

            if ($ruta && $ruta->CodVendedor) {
                $codigoVendedor = trim((string) $ruta->CodVendedor);
                $codigoTecnico = trim((string) $ruta->CodTecnico);

                $asesor = \App\Models\User::whereRaw('LTRIM(RTRIM(codigo_vendedor)) = ?', [$codigoVendedor])
                    ->orWhere('cedula', $codigoVendedor)
                    ->orWhere('username', $codigoVendedor)
                    ->first();

                $tecnico = null;
                if ($codigoTecnico !== '') {
                    $tecnico = \App\Models\User::whereRaw('LTRIM(RTRIM(codigo_vendedor)) = ?', [$codigoTecnico])
                        ->orWhere('cedula', $codigoTecnico)
                        ->orWhere('username', $codigoTecnico)
                        ->first();
                }

                $tecnicoNombre = $tecnico?->name ?? ($codigoTecnico !== '' ? $codigoTecnico : null);

                $repuestos = collect($visita->detalle)
                    ->flatMap(function ($detalle) {
                        return collect($detalle->solicitudesPartes)
                            ->filter(fn ($repuesto) => (int) $repuesto->ID_ESTADO === 13)
                            ->map(function ($repuesto) {
                                return [
                                    'id_cod_max' => $repuesto->ID_COD_MAX_PARTES,
                                    'cantidad' => $repuesto->CANTIDAD,
                                    'detalle_id' => $repuesto->ID,
                                ];
                            });
                    })
                    ->filter()
                    ->values();

                $repuestosInfo = collect();
                if ($repuestos->isNotEmpty()) {
                    $codigos = $repuestos->pluck('id_cod_max')->unique()->values()->all();
                    $repuestosInfo = DB::connection('senco360')
                        ->table('vRepuestosMax')
                        ->select([
                            'Codigo Repuesto as codigo',
                            'Descripcion Repuesto as descripcion',
                            'Codigo Proveedor as codigo_proveedor',
                        ])
                        ->whereIn('Codigo Repuesto', $codigos)
                        ->get()
                        ->keyBy(fn ($row) => trim((string) $row->codigo));
                }

                // Obtener observaciones desde las solicitudes de partes (SolicitudParte)
                $detalleIds = $repuestos->pluck('detalle_id')->filter()->unique()->values()->all();
                $solicitudesMap = [];
                if (!empty($detalleIds)) {
                    $solicitudesMap = SolicitudParte::whereIn('ID', $detalleIds)
                        ->get()
                        ->keyBy('ID');
                }

                $repuestosFinal = $repuestos->map(function ($repuesto) use ($repuestosInfo, $solicitudesMap) {
                    $info = $repuestosInfo->get(trim((string) $repuesto['id_cod_max']));
                    $observacion = $solicitudesMap[$repuesto['detalle_id']]->OBSERVACION ?? null;

                    return [
                        'codigo_max' => $repuesto['id_cod_max'],
                        'codigo_comodidad' => $info?->codigo_proveedor,
                        'nombre' => $info?->descripcion,
                        'cantidad' => $repuesto['cantidad'],
                        'observacion' => $observacion,
                    ];
                })->values()->all();

                $url = route('visitastecnicas.repuestos.index', ['visita_id' => $visita->ID]);

                if ($asesor && $asesor->email) {
                    Mail::to($asesor->email)->send(new NotificacionSolicitudCotizacion($visita, $asesor, $repuestosFinal, $url, $tecnicoNombre));
                    Log::info('Correo enviado al asesor', ['email' => $asesor->email, 'asesor' => $asesor->name]);
                } else {
                    Log::warning('No se encontró asesor con código o correo', [
                        'CodVendedor' => $codigoVendedor,
                        'asesor_encontrado' => $asesor ? true : false,
                        'email' => $asesor?->email,
                    ]);
                }
            } else {
                Log::warning('No se encontró ruta técnica', ['visita_id' => $id, 'ID_VISITA' => $visita->ID_VISITA]);
            }
        } catch (\Exception $e) {
            Log::error('Error enviando correo de notificación', ['error' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            Log::info('Respondiendo con JSON');
            return response()->json([
                'success' => true,
                'message' => 'Visita enviada a cotización correctamente.',
            ]);
        }

        return redirect()->route('visitastecnicas.visitas.show', $id)
            ->with('success', 'Visita enviada a cotización correctamente.');
    }

    /**
     * Reprogramar visita
     */
    public function reprogramar(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'fecha_reprogramacion' => 'required|date',
            'motivo'               => 'required|string|max:500',
        ]);

        $estadoReprogramadoId = $this->obtenerEstadoId('Reprogramado');
        $this->obtenerVisitaOAbortar($id);

        $this->repo->actualizar($id, [
            'FECHA_REPROGRAMACION' => $request->fecha_reprogramacion,
        ]);

        $this->repo->cambiarEstado($id, $estadoReprogramadoId, $request->motivo);

        return redirect()->route('visitastecnicas.visitas.index')
            ->with('success', 'Visita reprogramada correctamente.');
    }

    private function obtenerEstadoId(string $estado): int
    {
        return (int) VisitaEstado::where('ESTADO', $estado)->firstOrFail()->ID;
    }
}
