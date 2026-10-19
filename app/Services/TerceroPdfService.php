<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Throwable;

class TerceroPdfService
{
    /**
     * Genera el PDF del formulario de registro de tercero.
     */
    public function generarPdf(int $idFrm)
    {
        $datos = $this->datosPdf($idFrm);

        return Pdf::loadView(
            'pdf.registro-tercero',
            $datos
        )->setPaper('a4', 'portrait');
    }


    /**
 * Genera el PDF directamente con los datos
 * del formulario que se está diligenciando.
 */
public function generarPdfDesdeDatos(array $datos)
{
    return Pdf::loadView(
        'pdf.registro-tercero',
        $datos
    )->setPaper('a4', 'portrait');
}

    /**
     * Obtiene toda la información necesaria para el PDF.
     */
    public function datosPdf(int $idFrm): array
    {
        $db = DB::connection('senco360');

        /*
        |--------------------------------------------------------------------------
        | INFORMACIÓN GENERAL
        |--------------------------------------------------------------------------
        */

        $tercero = $db
            ->table('TER_frm_tercero')
            ->where('IdFrm', $idFrm)
            ->first();

        if (!$tercero) {
            abort(404, 'No se encontró el formulario del tercero.');
        }

        /*
        |--------------------------------------------------------------------------
        | DIRECCIONES DE ENVÍO
        |--------------------------------------------------------------------------
        */

        $direcciones = $db
            ->table('TER_direccion_envio')
            ->where('IdFrm', $idFrm)
            ->get()
            ->map(function ($direccion) use ($db) {

                $direccion->pais_nombre = $this->nombreCatalogo(
                    $db,
                    json_decode('"DS_EF_TBL_Pa\u00eds"'),
                    json_decode('"Pa\u00eds"'),
                    $direccion->Id_cod_pais ?? null
                );

                $direccion->departamento_nombre = $this->nombreCatalogo(
                    $db,
                    'DS_EF_TBL_Departamento',
                    'Departamento',
                    $direccion->Id_cod_dpto ?? null
                );

                $direccion->municipio_nombre = $this->nombreCatalogo(
                    $db,
                    'DS_EF_TBL_Municipio',
                    'Municipio',
                    $direccion->Id_cod_mpio ?? null
                );

                $direccion->es_extranjero =
                    !empty($direccion->pais_nombre) &&
                    Str::of($direccion->pais_nombre)
                        ->trim()
                        ->ascii()
                        ->lower()
                        ->value() !== 'colombia';

                return $direccion;
            });

        /*
        |--------------------------------------------------------------------------
        | SOCIOS
        |--------------------------------------------------------------------------
        */

        $socios = $db
            ->table('TER_socio')
            ->where('IdFrm', $idFrm)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | INFORMACIÓN FINANCIERA
        |--------------------------------------------------------------------------
        */

        $financiera = $db
            ->table('TER_informacion_financiera')
            ->where('IdFrm', $idFrm)
            ->first();

        /*
        |--------------------------------------------------------------------------
        | REFERENCIAS COMERCIALES
        |--------------------------------------------------------------------------
        */

        $referenciasComerciales = $db
            ->table('TER_referencia_comercial')
            ->where('IdFrm', $idFrm)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | REFERENCIAS BANCARIAS
        |--------------------------------------------------------------------------
        */

        $referenciasBancarias = $db
            ->table('TER_referencia_bancaria')
            ->where('IdFrm', $idFrm)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | INFORMACIÓN SGC
        |--------------------------------------------------------------------------
        */

        $sgc = $db
            ->table('TER_informacion_sgc')
            ->where('IdFrm', $idFrm)
            ->first();

        /*
        |--------------------------------------------------------------------------
        | ADJUNTOS
        |--------------------------------------------------------------------------
        */

        $adjuntos = $db
            ->table('TER_adjunto')
            ->where('IdFrm', $idFrm)
            ->first();

        /*
        |--------------------------------------------------------------------------
        | NOMBRES DE CATÁLOGOS
        |--------------------------------------------------------------------------
        |
        | Los códigos de ciudad y departamento se guardan en TER_frm_tercero
        | y TER_direccion_envio. Los nombres se obtienen desde Max / 360.
        |
        */

       $departamentoPrincipal = $db
    ->table(DB::raw(
        'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Departamento'
    ))
    ->where(
        DB::raw('[Código DIAN]'),
        $tercero->IdDepartamento
    )
    ->value('Departamento');


        $ciudadPrincipal = $db
    ->table(DB::raw(
        'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Municipio'
    ))
    ->where(
        DB::raw('[Código DIAN]'),
        $tercero->IdCiudad
    )
    ->value('Municipio');

        /*
        |--------------------------------------------------------------------------
        | DATOS PARA LA VISTA
        |--------------------------------------------------------------------------
        */

        return [
            'tercero' => $tercero,

            'departamentoPrincipal' =>
                $departamentoPrincipal,

            'ciudadPrincipal' =>
                $ciudadPrincipal,

            'direcciones' =>
                $direcciones,

            'socios' =>
                $socios,

            'financiera' =>
                $financiera,

            'referenciasComerciales' =>
                $referenciasComerciales,

            'referenciasBancarias' =>
                $referenciasBancarias,

            'sgc' =>
                $sgc,

            'adjuntos' =>
                $adjuntos,
        ];
    }

    /**
     * Nombre de un registro de los catálogos de Max / 360 a partir de su
     * código DIAN. Devuelve null si no existe (o si el código es 0).
     */
    private function nombreCatalogo($db, string $tabla, string $columnaNombre, $codigo): ?string
    {
        if ($codigo === null || $codigo === '' || (int) $codigo === 0) {
            return null;
        }

        try {
            $codigoDian = json_decode('"C\u00f3digo DIAN"');

            $fila = $db
                ->table(DB::raw('INTEGRACION_MAX_PSL.dbo.[' . $tabla . ']'))
                ->select(DB::raw('[' . $columnaNombre . '] as nombre'))
                ->where(DB::raw('[' . $codigoDian . ']'), (int) $codigo)
                ->first();

            return $fila->nombre ?? null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Nombre del archivo PDF.
     */
    public function nombreArchivo(int $idFrm): string
    {
        return 'registro-tercero-' . $idFrm . '.pdf';
    }
}