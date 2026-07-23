<?php

namespace App\Services\VisitasTecnicas;

use App\Models\Senco360\SolicitudParte;
use App\Models\Senco360\VisitaEncab;
use App\Models\Senco360\VisitaEstadoHistorico;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InformeTecnicoVisitaService
{
    public const CORREO_FIJO_INFORME = 'practicanteti@senco.co';

    public function generarPdf(VisitaEncab $visita)
    {
        return Pdf::loadView('pdf.informe-tecnico-visita', $this->datosPdf($visita))
            ->setPaper('a4', 'portrait');
    }

    public function nombreArchivo(VisitaEncab $visita): string
    {
        return 'Informe de visita técnica #' . ($visita->ID ?? 'sin-id') . '.pdf';
    }

    public function obtenerTecnicoAsignado(VisitaEncab $visita): ?User
    {
        $codigoTecnico = trim((string) $visita->rutaTecnica?->CodTecnico);

        if ($codigoTecnico === '') {
            return null;
        }

        return User::whereRaw('LTRIM(RTRIM(codigo_vendedor)) = ?', [$codigoTecnico])
            ->orWhere('cedula', $codigoTecnico)
            ->orWhere('username', $codigoTecnico)
            ->first();
    }

    public function obtenerAsesorVisita(VisitaEncab $visita): ?User
    {
        $codigoVendedor = trim((string) $visita->rutaTecnica?->CodVendedor);

        if ($codigoVendedor === '') {
            return null;
        }

        return User::whereRaw('LTRIM(RTRIM(codigo_vendedor)) = ?', [$codigoVendedor])
            ->orWhere('cedula', $codigoVendedor)
            ->orWhere('username', $codigoVendedor)
            ->first();
    }

    public function nombreTecnico(VisitaEncab $visita): ?string
    {
        $tecnico = $this->obtenerTecnicoAsignado($visita);
        $codigoTecnico = trim((string) $visita->rutaTecnica?->CodTecnico);

        return $tecnico?->name ?? ($codigoTecnico !== '' ? $codigoTecnico : null);
    }

    public function esCapacitacion(VisitaEncab $visita): bool
    {
        return str_contains(
            strtolower($visita->tipoServicio?->TIPO_SERVICIO ?? ''),
            'capacit'
        );
    }

    public function destinatariosFinalizacion(VisitaEncab $visita): array
    {
        $tecnico = $this->obtenerTecnicoAsignado($visita);
        $asesor = $this->obtenerAsesorVisita($visita);

        return collect([
            $visita->CORREO,
            $tecnico?->email,
            $asesor?->email,
            self::CORREO_FIJO_INFORME,
        ])
            ->filter(fn ($email) => filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->map(fn ($email) => strtolower(trim($email)))
            ->unique()
            ->values()
            ->all();
    }

    public function datosPdf(VisitaEncab $visita): array
    {
        $visita->loadMissing([
            'rutaTecnica',
            'tipoServicio',
            'estadoActual',
            'detalle.tipoSolucion',
            'detalle.tiposSolucion',
            'detalle.tiposFalla',
            'detalle.tipoMant',
            'detalle.solicitudesPartes.estado',
            'detalle.solicitudesPartes.historialEstados.estado',
            'detalle.fotos',
        ]);

        $codigosEquipos = $visita->detalle
            ->pluck('ID_COD_MAX')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $equiposInfo = empty($codigosEquipos)
            ? collect()
            : DB::connection('senco360')
                ->table('vHerramientasMax')
                ->select([
                    'Cod Parte as codigo',
                    'Descripcion Parte as descripcion',
                    'Codigo Proveedor as codigo_comodidad',
                ])
                ->whereIn('Cod Parte', $codigosEquipos)
                ->get()
                ->keyBy(fn ($equipo) => trim((string) $equipo->codigo));

        $repuestosVisita = $visita->detalle
            ->flatMap(fn ($detalle) => $detalle->solicitudesPartes)
            ->filter(fn (SolicitudParte $repuesto) => filled($repuesto->ID_COD_MAX_PARTES));

        $codigosRepuestos = $repuestosVisita
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
                    'Codigo Proveedor as codigo_comodidad',
                ])
                ->whereIn('Codigo Repuesto', $codigosRepuestos)
                ->get()
                ->keyBy(fn ($repuesto) => trim((string) $repuesto->codigo));

        return [
            'visita' => $visita,
            'tecnico' => $this->obtenerTecnicoAsignado($visita),
            'tecnicoNombre' => $this->nombreTecnico($visita),
            'esCapacitacion' => $this->esCapacitacion($visita),
            'firmaDataUri' => $this->firmaDataUri($visita),
            'logoDataUri' => $this->imagenDataUri(public_path('images/logo-blue.png')),
            'equipos' => $visita->detalle->map(function ($detalle) use ($equiposInfo, $repuestosInfo) {
                $infoEquipo = $equiposInfo[trim((string) $detalle->ID_COD_MAX)] ?? null;

                return [
                    'codigo' => $detalle->ID_COD_MAX,
                    'nombre' => $infoEquipo?->descripcion ?: ($detalle->TITULO ?: 'Equipo sin nombre'),
                    'descripcion' => $infoEquipo?->descripcion,
                    'codigo_comodidad' => $infoEquipo?->codigo_comodidad ?? null,
                    'serial' => $detalle->SERIAL,
                    'falla' => $detalle->tiposFalla
                        ->map(fn ($falla) => $falla->pivot?->DESCRIPCION_OTROS && (int) $falla->ID === 34
                            ? $falla->pivot->DESCRIPCION_OTROS
                            : $falla->DESCRIPCION)
                        ->filter()
                        ->implode(', ') ?: $detalle->DESCRIPCION_FALLA,
                    'tipo_mant' => $detalle->tipoMant?->TIPO_MANT,
                    'observaciones' => $detalle->OBSERVACIONES,
                    'soluciones' => $detalle->tiposSolucion->isNotEmpty()
                        ? $detalle->tiposSolucion->pluck('TIPO_SOLUCION')->filter()->values()
                        : collect([$detalle->tipoSolucion?->TIPO_SOLUCION])->filter()->values(),
                    'repuestos' => $detalle->solicitudesPartes
                        ->map(function (SolicitudParte $repuesto) use ($repuestosInfo) {
                            $infoRepuesto = $repuestosInfo[trim((string) $repuesto->ID_COD_MAX_PARTES)] ?? null;

                            return [
                                'descripcion' => $infoRepuesto?->descripcion ?? 'Sin nombre disponible',
                                'codigo_max' => $repuesto->ID_COD_MAX_PARTES,
                                'cantidad' => $repuesto->CANTIDAD,
                                'estado' => $repuesto->estado?->ESTADO ?? 'Sin estado',
                                'es_urgente' => (bool) $repuesto->ES_URGENTE,
                                'observaciones' => ((
                                    (int) $repuesto->ID_ESTADO === 19
                                        ? ($repuesto->OBSERVACION ?: null)
                                        : VisitaEstadoHistorico::query()
                                            ->where('ID_SOLICITUD_PARTE', $repuesto->ID)
                                            ->where('ID_ESTADO', $repuesto->ID_ESTADO)
                                            ->whereNotNull('OBSERVACIONES')
                                            ->where('OBSERVACIONES', '<>', '')
                                            ->orderBy('FECHA', 'desc')
                                            ->value('OBSERVACIONES')
                                ) ?: null),
                            ];
                        })
                        ->values(),
                    'fotos_antes' => $this->fotosDataUri($detalle->fotos->where('TIPO', 'ANTES')),
                    'fotos_despues' => $this->fotosDataUri($detalle->fotos->where('TIPO', 'DESPUES')),
                ];
            })->values(),
        ];
    }

    private function fotosDataUri(Collection $fotos): Collection
    {
        return $fotos
            ->map(fn ($foto) => [
                'nombre' => basename((string) $foto->RUTA_FOTO),
                'data_uri' => $this->imagenDataUri(Storage::disk('public')->path($foto->RUTA_FOTO)),
            ])
            ->filter(fn (array $foto) => filled($foto['data_uri']))
            ->values();
    }

    private function firmaDataUri(VisitaEncab $visita): ?string
    {
        $firma = $visita->detalle
            ->flatMap(fn ($detalle) => $detalle->fotos->where('TIPO', 'FIRMA'))
            ->first();

        return $firma ? $this->imagenDataUri(Storage::disk('public')->path($firma->RUTA_FOTO)) : null;
    }

    private function imagenDataUri(?string $path): ?string
    {
        if (!$path || !is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
}
