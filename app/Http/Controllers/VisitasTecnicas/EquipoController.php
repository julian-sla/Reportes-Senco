<?php
namespace App\Http\Controllers\VisitasTecnicas;

use App\Http\Controllers\Controller;
use App\Models\Senco360\SolicitudParte;
use App\Models\Senco360\VisitaDetal;
use App\Models\Senco360\VisitaEncab;
use App\Models\Senco360\VisitaFoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EquipoController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:visitastecnicas.ver');
    }

    private function puedeVerTodasLasVisitas($user): bool
    {
        return $user->hasRole('super-admin')
            || $user->hasPermissionTo('visitastecnicas.ver-todos');
    }

    private function validarAccesoVisita(VisitaEncab $visita): void
    {
        $user = auth()->user();

        if ($this->puedeVerTodasLasVisitas($user)) {
            return;
        }

        $codigoTecnico = trim((string) ($user->codigo_vendedor ?? ''));
        abort_if($codigoTecnico === '', 403, 'Tu usuario no tiene un código técnico asignado.');
        abort_if(trim((string) $visita->rutaTecnica?->CodTecnico) !== $codigoTecnico, 403, 'No tienes acceso a esta visita.');
    }

    private function validarEdicionDetalle(VisitaEncab $visita): void
    {
        $this->validarAccesoVisita($visita);
        abort_if(!in_array((int) $visita->ID_ESTADO_ACTUAL, [1, 6], true), 403, 'No se pueden modificar equipos en este estado.');
        abort_if((bool) $visita->DETALLE_PUBLICADO_COTIZACION, 403, 'El detalle de la visita ya fue enviado a cotización.');
    }

    private function validarGestionSolucionesComplementarias(VisitaEncab $visita): void
    {
        $this->validarAccesoVisita($visita);
        abort_if(!in_array((int) $visita->ID_ESTADO_ACTUAL, [1, 6], true), 403, 'No se pueden agregar soluciones complementarias en este estado.');
    }

    public function serialesSugeridos(Request $request, int $id): JsonResponse
    {
        $visita = VisitaEncab::with('rutaTecnica')->findOrFail($id);
        $this->validarAccesoVisita($visita);

        $direccion = trim((string) ($request->input('direccion') ?: $visita->rutaTecnica?->DireccionCompleta ?: ''));
        if ($direccion === '') {
            return response()->json([]);
        }

        $direccionNormalizada = mb_strtolower(preg_replace('/\s+/', '', $direccion));

        $seriales = DB::connection('senco360')
            ->table('RT_visita_detal as d')
            ->join('RT_visita_encab as e', 'd.ID_ENC_VISITA', '=', 'e.ID')
            ->join('RT_rutastecnicas as r', 'e.ID_VISITA', '=', 'r.IdVisita')
            ->where('e.ID', '!=', $visita->ID)
            ->whereNotNull('d.SERIAL')
            ->where('d.SERIAL', '!=', '')
            ->whereRaw("LOWER(REPLACE(REPLACE(COALESCE(r.DireccionCompleta, ''), ' ', ''), CHAR(9), '')) = ?", [$direccionNormalizada])
            ->select('d.SERIAL')
            ->distinct()
            ->orderBy('d.SERIAL')
            ->limit(10)
            ->pluck('SERIAL');

        return response()->json(
            $seriales
                ->map(fn ($serial) => trim((string) $serial))
                ->filter(fn ($serial) => $serial !== '')
                ->values()
                ->all()
        );
    }

    public function store(Request $request): JsonResponse
    {
        // Decodificar repuestos si viene como JSON string
        if ($request->has('repuestos') && is_string($request->repuestos)) {
            $request->merge(['repuestos' => json_decode($request->repuestos, true)]);
        }

        $request->validate([
            'visita_id'        => 'required|integer',
            'id_cod_max'       => 'required|string|max:100',
            'titulo'           => 'nullable|string|max:200',
            'serial'           => 'required|string|max:100',
            'id_tipo_mant'     => 'required|integer|exists:senco360.RT_tipo_mant,ID',
            'id_tipo_falla'    => 'nullable|array',
            'id_tipo_falla.*'  => 'integer|exists:senco360.RT_tipo_falla,ID',
            'descripcion_falla'=> 'required_without:id_tipo_falla|nullable|string|max:500',
            'id_solucion'      => 'required|array|min:1',
            'id_solucion.*'    => 'required|integer|exists:senco360.RT_tipo_solucion,ID',
            'observaciones'    => 'nullable|string|max:1000',
            'fotos_antes'      => 'nullable|array|max:10',
            'fotos_antes.*'    => 'nullable|image|max:5120',
            'repuestos'                 => 'nullable|array',
            'repuestos.*.id_cod_max'    => 'required_with:repuestos|string|max:100',
            'repuestos.*.cantidad'      => 'required_with:repuestos|integer|min:1',
            'repuestos.*.observacion'   => 'nullable|string|max:255',
            'repuestos.*.resolver_en_campo' => 'nullable|boolean',
        ]);

        $visita = VisitaEncab::with('rutaTecnica')->findOrFail($request->visita_id);
        $this->validarEdicionDetalle($visita);

        $soluciones = collect($request->input('id_solucion', []))
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $fallas = collect($request->input('id_tipo_falla', []))
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $serial = filled($request->serial)
            ? trim((string) $request->serial)
            : null;

        $repuestos = collect($request->input('repuestos', []))
            ->map(fn ($repuesto) => [
                'id_cod_max'  => $repuesto['id_cod_max'],
                'cantidad'    => (int) $repuesto['cantidad'],
                'observacion' => $repuesto['observacion'] ?? null,
                'resolver_en_campo' => (bool) ($repuesto['resolver_en_campo'] ?? false),
                'es_urgente' => (bool) ($repuesto['es_urgente'] ?? false),
            ])
            ->values();

        $equipo = null;
        DB::connection('senco360')->transaction(function () use ($request, $soluciones, $fallas, $serial, $repuestos, &$equipo) {
            $equipo = VisitaDetal::create([
                'ID_ENC_VISITA'    => $request->visita_id,
                'ID_COD_MAX'       => $request->id_cod_max,
                'TITULO'           => $request->titulo,
                'SERIAL'           => $serial,
                'ID_TIPO_MANT'     => $request->id_tipo_mant,
                'DESCRIPCION_FALLA'=> $request->descripcion_falla,
                // Mantiene compatibilidad con integraciones que lean una sola solución.
                'ID_SOLUCION'      => $soluciones->first(),
                'OBSERVACIONES'    => $request->observaciones,
            ]);

            $equipo->tiposSolucion()->sync($soluciones->all());

            // Sincronizar fallas con datos adicionales (DESCRIPCION_OTROS)
            $descripcionOtros = $request->input('descripcion_otros');
            $fallasConPivot = $fallas->mapWithKeys(function ($fallaId) use ($descripcionOtros) {
                return [
                    $fallaId => [
                        'DESCRIPCION_OTROS' => (int) $fallaId === 34 && filled($descripcionOtros)
                            ? trim($descripcionOtros)
                            : null,
                    ],
                ];
            })->all();
            $equipo->tiposFalla()->sync($fallasConPivot);

            // Guardar evidencia inicial si existe
            if ($request->hasFile('fotos_antes')) {
                $fotosParaInsertar = [];

                foreach ($request->file('fotos_antes') as $foto) {
                    $fotosParaInsertar[] = [
                        'ID_DETALLE_VISITA' => $equipo->ID,
                        'TIPO'              => 'ANTES',
                        'RUTA_FOTO'         => $foto->store(
                            'visitas/equipos/' . $equipo->ID,
                            'public'
                        ),
                    ];
                }

                if ($fotosParaInsertar !== []) {
                    VisitaFoto::query()->insert($fotosParaInsertar);
                }
            }

            if ($repuestos->isNotEmpty()) {
                $repuestosParaInsertar = $repuestos->map(fn ($repuesto) => [
                    'ID_DETALLE_VISITA' => $equipo->ID,
                    'ID_COD_MAX_PARTES' => $repuesto['id_cod_max'],
                    'CANTIDAD'          => $repuesto['cantidad'],
                    'ID_ESTADO'         => $repuesto['resolver_en_campo'] ? 19 : 13,
                    'OBSERVACION'       => $repuesto['observacion'],
                    'ES_URGENTE'        => $repuesto['es_urgente'],
                ])->all();

                SolicitudParte::query()->insert($repuestosParaInsertar);
            }
        });

        return response()->json([
            'success' => true,
            'id'      => $equipo->ID,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'id_cod_max'       => 'required|string|max:100',
            'titulo'           => 'nullable|string|max:200',
            'serial'           => 'required|string|max:100',
            'id_tipo_mant'     => 'required|integer|exists:senco360.RT_tipo_mant,ID',
            'id_tipo_falla'    => 'nullable|array',
            'id_tipo_falla.*'  => 'integer|exists:senco360.RT_tipo_falla,ID',
            'descripcion_falla'=> 'required_without:id_tipo_falla|nullable|string|max:500',
            'id_solucion'      => 'required|array|min:1',
            'id_solucion.*'    => 'required|integer|exists:senco360.RT_tipo_solucion,ID',
            'observaciones'    => 'nullable|string|max:1000',
        ]);

        $equipo = VisitaDetal::with('encabezado.rutaTecnica')->findOrFail($id);
        $this->validarEdicionDetalle($equipo->encabezado()->firstOrFail());
        $soluciones = collect($request->input('id_solucion', []))
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();
        $fallas = collect($request->input('id_tipo_falla', []))
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        DB::connection('senco360')->transaction(function () use ($request, $equipo, $soluciones, $fallas) {
            $equipo->update([
                'ID_COD_MAX'       => $request->id_cod_max,
                'TITULO'           => $request->titulo,
                'SERIAL'           => $request->serial,
                'ID_TIPO_MANT'     => $request->id_tipo_mant,
                'DESCRIPCION_FALLA'=> $request->descripcion_falla,
                'ID_SOLUCION'      => $soluciones->first(),
                'OBSERVACIONES'    => $request->observaciones,
            ]);

            $equipo->tiposSolucion()->sync($soluciones->all());

            // Sincronizar fallas con datos adicionales (DESCRIPCION_OTROS)
            $descripcionOtros = $request->input('descripcion_otros');
            $fallasConPivot = $fallas->mapWithKeys(function ($fallaId) use ($descripcionOtros) {
                return [
                    $fallaId => [
                        'DESCRIPCION_OTROS' => (int) $fallaId === 34 && filled($descripcionOtros)
                            ? trim($descripcionOtros)
                            : null,
                    ],
                ];
            })->all();
            $equipo->tiposFalla()->sync($fallasConPivot);
        });

        return response()->json([
            'success' => true,
            'id'      => $equipo->ID,
        ]);
    }

    public function destroy(int $id): RedirectResponse
    {
        $equipo = VisitaDetal::with(['encabezado.rutaTecnica', 'fotos'])->findOrFail($id);
        $this->validarEdicionDetalle($equipo->encabezado()->firstOrFail());

        DB::connection('senco360')->transaction(function () use ($equipo): void {
            foreach ($equipo->fotos as $foto) {
                if (!empty($foto->RUTA_FOTO)) {
                    Storage::disk('public')->delete($foto->RUTA_FOTO);
                }

                $foto->delete();
            }

            $equipo->solicitudesPartes()->delete();
            $equipo->tiposSolucion()->detach();
            $equipo->tiposFalla()->detach();
            $equipo->delete();
        });

        return back()->with('success', 'Equipo eliminado correctamente.');
    }

    public function agregarSolucionesComplementarias(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $request->validate([
            'soluciones_adicionales'   => 'required|array|min:1',
            'soluciones_adicionales.*' => 'required|integer|exists:senco360.RT_tipo_solucion,ID',
            'observaciones'            => 'nullable|string|max:1000',
        ]);

        $equipo = VisitaDetal::with('encabezado.rutaTecnica')->findOrFail($id);
        $this->validarGestionSolucionesComplementarias($equipo->encabezado()->firstOrFail());

        $solucionesAdicionales = collect($request->input('soluciones_adicionales', []))
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        DB::connection('senco360')->transaction(function () use ($equipo, $solucionesAdicionales, $request) {
            $equipo->tiposSolucion()->syncWithoutDetaching($solucionesAdicionales->all());

            if (!$equipo->ID_SOLUCION) {
                $equipo->update([
                    'ID_SOLUCION' => $solucionesAdicionales->first(),
                ]);
            }

            if ($request->has('observaciones')) {
                $equipo->update([
                    'OBSERVACIONES' => $request->observaciones,
                ]);
            }
        });

        if (request()->expectsJson() || request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Soluciones complementarias guardadas correctamente.',
            ]);
        }

        return back()->with('success', 'Soluciones complementarias guardadas correctamente.');
    }
}
