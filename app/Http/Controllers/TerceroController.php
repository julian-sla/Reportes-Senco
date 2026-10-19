<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use App\Services\TerceroPdfService;
use Illuminate\Validation\ValidationException;

class TerceroController extends Controller
{


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR FORMULARIO
    |--------------------------------------------------------------------------
    */




    public function create(Request $request)
    {
        $db = DB::connection('senco360');

        $tiposTercero = $db
            ->table('TER_tipo_tercero')
            ->select(
                'IdTipo_tercero',
                'tipo_tercero'
            )
            ->orderBy('IdTipo_tercero')
            ->get();

        $tiposFormulario = $db
            ->table('TER_tipo_frm')
            ->select(
                'IdTipo_frm',
                'Tipo_frm'
            )
            ->orderBy('IdTipo_frm')
            ->get();

            $tiposIdentificacion = $db
    ->table(DB::raw(
        'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Tipo_de_Documento'
    ))
    ->select(
        DB::raw('[Código] as codigo'),
        DB::raw('[Tipo de Documento] as nombre')
    )
    ->orderBy(DB::raw('[Código]'))
    ->get();

  $ciiu = $db
    ->table('TER_codigo_ciiu')
    ->select(
        'IdCodigo_ciiu',
        DB::raw('RTRIM(codigo_ciiu) as codigo_ciiu'),
        DB::raw('RTRIM(actividad_economica) as actividad_economica')
    )
    ->orderBy('codigo_ciiu')
    ->get();


    $vendedores = $db->select("
    SELECT
        LTRIM(RTRIM(SLSREP_26)) AS codigo,
        LTRIM(RTRIM(SLSNME_26)) AS nombre
    FROM MAXSENCO.dbo.Sales_Rep_Master
    WHERE RTRIM(UDFKEY_26) <> 'Inactivo'
      AND LTRIM(RTRIM(SLSREP_26)) <> '001'
      AND RTRIM(UDFREF_26) NOT LIKE '%cnico%'
    ORDER BY SLSNME_26
");

        return Inertia::render(
            'GestionClientes/RegistroTercero',
            [
                'tiposTercero' => $tiposTercero,
                'tiposFormulario' => $tiposFormulario,
                'tiposIdentificacion' => $tiposIdentificacion,
                'ciiu' => $ciiu,
                'vendedores' => $vendedores,
                'borradorId' => $request->query('borrador_id'),

            ]
        );
    }


/*
|--------------------------------------------------------------------------
| CONSULTAR FORMULARIO COMPLETO
|--------------------------------------------------------------------------
*/
public function verFormulario($id, $modoEdicion = false)
{
    $db = DB::connection('senco360');

    $existe = $db->table('TER_frm_tercero')
        ->where('IdFrm', $id)
        ->exists();

    if (!$existe) {
        abort(404, 'No se encontró el formulario solicitado.');
    }

    $tiposTercero = $db->table('TER_tipo_tercero')
        ->select('IdTipo_tercero', 'tipo_tercero')
        ->orderBy('IdTipo_tercero')
        ->get();

    $tiposFormulario = $db->table('TER_tipo_frm')
        ->select('IdTipo_frm', 'Tipo_frm')
        ->orderBy('IdTipo_frm')
        ->get();

    $tiposIdentificacion = $db
        ->table(DB::raw(
            'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Tipo_de_Documento'
        ))
        ->select(
            DB::raw('[Código] as codigo'),
            DB::raw('[Tipo de Documento] as nombre')
        )
        ->orderBy(DB::raw('[Código]'))
        ->get();

    $ciiu = $db->table('TER_codigo_ciiu')
        ->select(
            'IdCodigo_ciiu',
            DB::raw('RTRIM(codigo_ciiu) as codigo_ciiu'),
            DB::raw('RTRIM(actividad_economica) as actividad_economica')
        )
        ->orderBy('codigo_ciiu')
        ->get();

    $vendedores = $db->select("
        SELECT
            LTRIM(RTRIM(SLSREP_26)) AS codigo,
            LTRIM(RTRIM(SLSNME_26)) AS nombre
        FROM MAXSENCO.dbo.Sales_Rep_Master
        WHERE RTRIM(UDFKEY_26) <> 'Inactivo'
          AND LTRIM(RTRIM(SLSREP_26)) <> '001'
          AND RTRIM(UDFREF_26) NOT LIKE '%cnico%'
        ORDER BY SLSNME_26
    ");

    return Inertia::render(
        'GestionClientes/RegistroTercero',
        [
            'tiposTercero' => $tiposTercero,
            'tiposFormulario' => $tiposFormulario,
            'tiposIdentificacion' => $tiposIdentificacion,
            'ciiu' => $ciiu,
            'vendedores' => $vendedores,
            'modoConsulta' => !$modoEdicion,
'modoEdicion' => $modoEdicion,
'idFormulario' => (int) $id,
        ]
    );
}


public function editarFormulario($id)
{
abort_unless(
    Gate::allows('formularios-terceros.editar'),
    403,
    'No tienes permisos para editar formularios.'
);

    $db = DB::connection('senco360');

    $formulario = $db->table('TER_frm_tercero')
        ->where('IdFrm', $id)
        ->first();

    abort_unless($formulario, 404);

    abort_if(
        (int) $formulario->acepta_autorizacion !== 1,
        403,
        'No se pueden editar formularios en borrador.'
    );

    abort_unless(
        $this->estadoActual($db, (int) $id) === self::ESTADO_INGRESADO,
        403,
        'Solo se pueden editar formularios en estado Ingresado.'
    );

    return $this->verFormulario($id, true);
}

    /*
    |--------------------------------------------------------------------------
    | PAISES - MAX / 360
    |--------------------------------------------------------------------------
    */

public function paises()
{
    $paisColumn = json_decode('"Pa\u00eds"');
    $codigoDianColumn = json_decode('"C\u00f3digo DIAN"');

    $paises = DB::connection('senco360')
        ->table(DB::raw(
            'INTEGRACION_MAX_PSL.dbo.[DS_EF_TBL_' . $paisColumn . ']'
        ))
        ->select([
            DB::raw('[' . $codigoDianColumn . '] as codigo'),
            DB::raw('[' . $paisColumn . '] as nombre'),
            'Equivalencia as equivalencia',
        ])
        ->orderBy(DB::raw('[' . $paisColumn . ']'))
        ->get();

    return response()->json($paises);
}
    /*
    |--------------------------------------------------------------------------
    | DEPARTAMENTOS - MAX / 360
    |--------------------------------------------------------------------------
    */

   public function departamentos(Request $request)
{
    $query = DB::connection('senco360')
        ->table(DB::raw(
            'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Departamento'
        ))
        ->select([
            DB::raw('[Código DIAN] as codigo'),
            'Departamento as nombre',
            'País as codigo_pais',
        ]);

    if ($request->filled('pais')) {
        $query->where(
            'País',
            $request->pais
        );
    }

    $departamentos = $query
        ->orderBy('Departamento')
        ->get();

    return response()->json($departamentos);
}

    /*
    |--------------------------------------------------------------------------
    | MUNICIPIOS - MAX / 360
    |--------------------------------------------------------------------------
    */

    public function municipios($codigoDepartamento)
    {
        $municipios = DB::connection('senco360')
            ->table(DB::raw(
                'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Municipio'
            ))
            ->select([
                DB::raw('[Código DIAN] as codigo'),
                'Municipio as nombre',
                'Departamento as codigo_departamento',
            ])
            ->where(
                'Departamento',
                $codigoDepartamento
            )
            ->orderBy('Municipio')
            ->get();

        return response()->json($municipios);
    }

public function previsualizarPdf()
{
    $idFrm = session('registro_tercero_pdf_id');

    if (!$idFrm) {
        abort(
            404,
            'No se encontró un registro de tercero para generar el PDF.'
        );
    }

    $service = app(TerceroPdfService::class);

    // stream() lo muestra dentro del navegador (vista previa).
    return $service
        ->generarPdf((int) $idFrm)
        ->stream(
            $service->nombreArchivo((int) $idFrm)
        );
}

public function previsualizarPdfFormulario(Request $request)
{
    $form = $request->input('datos');

    if (is_string($form)) {
        $form = json_decode($form, true);
    }

    if (!is_array($form)) {
        abort(400, 'Los datos del formulario no son válidos.');
    }

    $db = DB::connection('senco360');

    /*
    |--------------------------------------------------------------------------
    | CATÁLOGOS
    |--------------------------------------------------------------------------
    */

    $tiposTercero = $db
        ->table('TER_tipo_tercero')
        ->select('IdTipo_tercero', 'tipo_tercero')
        ->get()
        ->keyBy(function ($item) {
            return (string) $item->IdTipo_tercero;
        });

    $tiposFormulario = $db
        ->table('TER_tipo_frm')
        ->select('IdTipo_frm', 'Tipo_frm')
        ->get()
        ->keyBy(function ($item) {
            return (string) $item->IdTipo_frm;
        });

    $tiposIdentificacion = $db
        ->table(DB::raw(
            'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Tipo_de_Documento'
        ))
        ->select(
            DB::raw('[Código] as codigo'),
            DB::raw('[Tipo de Documento] as nombre')
        )
        ->get()
        ->keyBy(function ($item) {
            return trim((string) $item->codigo);
        });

    /*
    |--------------------------------------------------------------------------
    | FUNCIONES PARA MOSTRAR NOMBRES Y SÍ / NO
    |--------------------------------------------------------------------------
    */

    $nombreTipoTercero = function ($id) use ($tiposTercero) {
        if ($id === null || $id === '') {
            return null;
        }

        return optional(
            $tiposTercero->get((string) $id)
        )->tipo_tercero;
    };

    $nombreTipoFormulario = function ($id) use ($tiposFormulario) {
        if ($id === null || $id === '') {
            return null;
        }

        return optional(
            $tiposFormulario->get((string) $id)
        )->Tipo_frm;
    };

    $nombreDocumento = function ($codigo) use ($tiposIdentificacion) {
        if ($codigo === null || $codigo === '') {
            return null;
        }

        return optional(
            $tiposIdentificacion->get(trim((string) $codigo))
        )->nombre ?? $codigo;
    };

    $siNo = function ($valor) {
        if ($valor === true || $valor === 1 || $valor === '1') {
            return 'Sí';
        }

        if (
            $valor === false ||
            $valor === 0 ||
            $valor === '0'
        ) {
            return 'No';
        }

        return $valor ?: null;
    };

    /*
    |--------------------------------------------------------------------------
    | GEOGRAFÍA
    | MAX / 360
    |--------------------------------------------------------------------------
    */

    $paisColumn = json_decode('"Pa\u00eds"');
    $codigoDianColumn = json_decode('"C\u00f3digo DIAN"');

    $paises = $db
        ->table(DB::raw(
            'INTEGRACION_MAX_PSL.dbo.[DS_EF_TBL_' . $paisColumn . ']'
        ))
        ->select([
            DB::raw('[' . $codigoDianColumn . '] as codigo'),
            DB::raw('[' . $paisColumn . '] as nombre'),
        ])
        ->get();

    $paisesPorCodigo = $paises->keyBy(function ($item) {
        return trim((string) $item->codigo);
    });

    $departamentos = $db
        ->table(DB::raw(
            'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Departamento'
        ))
        ->select([
            DB::raw('[Código DIAN] as codigo'),
            'Departamento as nombre',
            'País as codigo_pais',
        ])
        ->get();

    $departamentosPorCodigo = $departamentos->keyBy(function ($item) {
        return trim((string) $item->codigo);
    });

    $municipios = $db
        ->table(DB::raw(
            'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Municipio'
        ))
        ->select([
            DB::raw('[Código DIAN] as codigo'),
            'Municipio as nombre',
            'Departamento as codigo_departamento',
        ])
        ->get();

    $municipiosPorCodigo = $municipios->keyBy(function ($item) {
        return trim((string) $item->codigo);
    });

    $nombrePais = function ($codigo) use ($paisesPorCodigo) {
        if ($codigo === null || $codigo === '') {
            return null;
        }

        return optional(
            $paisesPorCodigo->get(trim((string) $codigo))
        )->nombre ?? $codigo;
    };

    $nombreDepartamento = function ($codigo) use ($departamentosPorCodigo) {
        if ($codigo === null || $codigo === '') {
            return null;
        }

        return optional(
            $departamentosPorCodigo->get(trim((string) $codigo))
        )->nombre ?? $codigo;
    };

    $nombreMunicipio = function ($codigo) use ($municipiosPorCodigo) {
        if ($codigo === null || $codigo === '') {
            return null;
        }

        return optional(
            $municipiosPorCodigo->get(trim((string) $codigo))
        )->nombre ?? $codigo;
    };

    /*
    |--------------------------------------------------------------------------
    | INFORMACIÓN GENERAL
    |--------------------------------------------------------------------------
    */

    $tercero = (object) [
        'Fecha_frm' =>
            $form['fecha_frm'] ?? null,

        'IdFrm' =>
            '-',

        // MOSTRAR NOMBRE, NO ID
        'IdTipo_tercero' =>
            $nombreTipoTercero(
                $form['id_tipo_tercero'] ?? null
            ),

        'tipo_persona' =>
            $form['tipo_persona'] ?? null,

        // MOSTRAR NOMBRE, NO ID
        'IdTipo_frm' =>
            $nombreTipoFormulario(
                $form['id_tipo_frm'] ?? null
            ),

        // MOSTRAR NOMBRE, NO CÓDIGO
        'tipo_doc' =>
            $nombreDocumento(
                $form['tipo_doc'] ?? null
            ),

        'numero_doc' =>
            $form['numero_doc'] ?? null,

        'razon_social' =>
            $form['razon_social'] ?? null,

        'tiene_establ_comercio' =>
            $siNo(
                $form['tiene_establecimiento'] ?? null
            ),

        'nombre_establecimiento' =>
            $form['nombre_establecimiento'] ?? null,

        'direccion' =>
            $form['direccion'] ?? null,

        'pais_nombre' =>
            $nombrePais(
                $form['id_pais'] ?? null
            ),

        'departamento_nombre' =>
            $nombreDepartamento(
                $form['id_departamento'] ?? null
            ),

        'ciudad_nombre' =>
            $nombreMunicipio(
                $form['id_ciudad'] ?? null
            ),

        'telefono' =>
            $form['telefono'] ?? null,

        'celular1' =>
            $form['celular1'] ?? null,

        'celular2' =>
            $form['celular2'] ?? null,

        'email' =>
            $form['email'] ?? null,

        'email_facturacion' =>
            $form['email_facturacion'] ?? null,

        'codigo_ciiu' =>
            isset($form['codigo_ciiu'])
                ? trim((string) $form['codigo_ciiu'])
                : null,

        'actividad_economica' =>
            null,

        'rl_nombre' =>
            $form['rl_nombre'] ?? null,

        'rl_tipo_doc' =>
            $nombreDocumento(
                $form['rl_tipo_doc'] ?? null
            ),

        'rl_numero_doc' =>
            $form['rl_numero_doc'] ?? null,

        'rl_email' =>
            $form['rl_email'] ?? null,

        'tesoreria_nombre' =>
            $form['tesoreria_nombre'] ?? null,

        'tesoreria_telefono' =>
            $form['tesoreria_telefono'] ?? null,

        'tesoreria_email' =>
            $form['tesoreria_email'] ?? null,

        'acepta_autorizacion' =>
            $form['acepta_autorizacion'] ?? false,

        'rl_firma_url' =>
            $form['rl_firma_url'] ?? null,
    ];

    /*
    |--------------------------------------------------------------------------
    | ACTIVIDAD ECONÓMICA
    |--------------------------------------------------------------------------
    */

    if (!empty($form['codigo_ciiu'])) {
        $tercero->actividad_economica = $db
            ->table('TER_codigo_ciiu')
            ->whereRaw(
                'RTRIM(codigo_ciiu) = ?',
                [trim((string) $form['codigo_ciiu'])]
            )
            ->value('actividad_economica');

        if ($tercero->actividad_economica !== null) {
            $tercero->actividad_economica =
                trim((string) $tercero->actividad_economica);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DIRECCIÓN PRINCIPAL
    |--------------------------------------------------------------------------
    */

    $departamentoPrincipal =
        $nombreDepartamento(
            $form['id_departamento'] ?? null
        );

    $ciudadPrincipal =
        $nombreMunicipio(
            $form['id_ciudad'] ?? null
        );

    /*
    |--------------------------------------------------------------------------
    | DIRECCIONES DE ENVÍO
    |--------------------------------------------------------------------------
    */

    $direcciones = collect(
        $form['direcciones_envio'] ?? []
    )->map(function ($direccion) use (
        $nombrePais,
        $nombreDepartamento,
        $nombreMunicipio
    ) {

        $pais = $direccion['id_pais'] ?? null;
        $departamento = $direccion['id_departamento'] ?? null;
        $municipio = $direccion['id_municipio'] ?? null;

        return (object) [
            'direccion' =>
                $direccion['direccion'] ?? null,

            'direccion_envio' =>
                $direccion['direccion'] ?? null,

            'barrio' =>
                $direccion['barrio'] ?? null,

            'observaciones' =>
                $direccion['observaciones'] ?? null,

            'Id_cod_pais' =>
                $pais,

            'Id_cod_dpto' =>
                $departamento,

            'Id_cod_mpio' =>
                $municipio,

            'pais_nombre' =>
                $nombrePais($pais),

            'departamento_nombre' =>
                $nombreDepartamento($departamento),

            'municipio_nombre' =>
                $nombreMunicipio($municipio),

            'es_extranjero' =>
                empty($departamento) && empty($municipio),
        ];
    });

    /*
    |--------------------------------------------------------------------------
    | SOCIOS
    |--------------------------------------------------------------------------
    */

    $socios = collect(
        $form['socios'] ?? []
    )->map(function ($socio) use ($nombreDocumento) {

        return (object) [
            'nombres' =>
                $socio['nombres'] ?? null,

            'apellidos' =>
                $socio['apellidos'] ?? null,

            'tipo_documento' =>
                $nombreDocumento(
                    $socio['tipo_identificacion']
                    ?? $socio['tipo_documento']
                    ?? null
                ),

            'numero_documento' =>
                $socio['numero_identificacion']
                ?? null,

            'porcentaje_participacion' =>
                $socio['porcentaje_participacion']
                ?? null,
        ];
    });

    /*
    |--------------------------------------------------------------------------
    | INFORMACIÓN FINANCIERA
    |--------------------------------------------------------------------------
    */

    $financiera = (object) [
        'fecha_corte' =>
            $form['fecha_corte'] ?? null,

        'activos' =>
            $form['activos'] ?? null,

        'pasivos' =>
            $form['pasivos'] ?? null,

        'ingresos' =>
            $form['ingresos'] ?? null,

        'egresos' =>
            $form['egresos'] ?? null,

        'responsable_iva' =>
            $siNo($form['responsable_iva'] ?? null),

        'declara_renta' =>
            $siNo($form['declara_renta'] ?? null),

        'gran_contribuyente' =>
            $siNo($form['gran_contribuyente'] ?? null),

        'autoretenedor' =>
            $siNo($form['autoretenedor'] ?? null),
    ];

    /*
    |--------------------------------------------------------------------------
    | PROVEEDORES
    |--------------------------------------------------------------------------
    */

    $proveedores = collect(
        $form['proveedores'] ?? []
    )->map(function ($referencia) use (
        $nombreDocumento,
        $nombrePais,
        $nombreDepartamento,
        $nombreMunicipio
    ) {

        $pais = $referencia['id_pais'] ?? null;
        $departamento = $referencia['id_departamento'] ?? null;
        $ciudad = $referencia['id_ciudad'] ?? null;

        return (object) [
            'tipo_referencia' =>
                'Proveedor',

            'nombre_razon_social' =>
                $referencia['nombre_razon_social'] ?? null,

            'tipo_doc' =>
                $nombreDocumento(
                    $referencia['tipo_identificacion'] ?? null
                ),

            'numero_doc' =>
                $referencia['numero_identificacion'] ?? null,

            'direccion' =>
                $referencia['direccion'] ?? null,

            'pais_nombre' =>
                $nombrePais($pais),

            'departamento_nombre' =>
                $nombreDepartamento($departamento),

            'ciudad_nombre' =>
                $nombreMunicipio($ciudad),

            'telefono' =>
                $referencia['telefono'] ?? null,

            'celular' =>
                $referencia['celular'] ?? null,

            'email' =>
                $referencia['email'] ?? null,
        ];
    })->filter(function ($referencia) {
        return
            !empty($referencia->nombre_razon_social) ||
            !empty($referencia->numero_doc) ||
            !empty($referencia->direccion);
    })->values();

    /*
    |--------------------------------------------------------------------------
    | CLIENTES
    |--------------------------------------------------------------------------
    */

    $clientes = collect(
        $form['clientes'] ?? []
    )->map(function ($referencia) use (
        $nombreDocumento,
        $nombrePais,
        $nombreDepartamento,
        $nombreMunicipio
    ) {

        $pais = $referencia['id_pais'] ?? null;
        $departamento = $referencia['id_departamento'] ?? null;
        $ciudad = $referencia['id_ciudad'] ?? null;

        return (object) [
            'tipo_referencia' =>
                'Cliente',

            'nombre_razon_social' =>
                $referencia['nombre_razon_social'] ?? null,

            'tipo_doc' =>
                $nombreDocumento(
                    $referencia['tipo_identificacion'] ?? null
                ),

            'numero_doc' =>
                $referencia['numero_identificacion'] ?? null,

            'direccion' =>
                $referencia['direccion'] ?? null,

            'pais_nombre' =>
                $nombrePais($pais),

            'departamento_nombre' =>
                $nombreDepartamento($departamento),

            'ciudad_nombre' =>
                $nombreMunicipio($ciudad),

            'telefono' =>
                $referencia['telefono'] ?? null,

            'celular' =>
                $referencia['celular'] ?? null,

            'email' =>
                $referencia['email'] ?? null,
        ];
    })->filter(function ($referencia) {
        return
            !empty($referencia->nombre_razon_social) ||
            !empty($referencia->numero_doc) ||
            !empty($referencia->direccion);
    })->values();

    /*
    |--------------------------------------------------------------------------
    | REFERENCIAS COMERCIALES
    |--------------------------------------------------------------------------
    |
    | Se mantienen separadas para no perder la diferencia entre
    | proveedores y clientes.
    |--------------------------------------------------------------------------
    */

    $referenciasComerciales = $proveedores
        ->concat($clientes)
        ->values();

    /*
    |--------------------------------------------------------------------------
    | REFERENCIAS BANCARIAS
    |--------------------------------------------------------------------------
    */

    $referenciasBancarias = collect(
        $form['referencias_bancarias'] ?? []
    )->filter(function ($referencia) {
        return
            !empty($referencia['nombre_banco']) ||
            !empty($referencia['numero_cuenta']);
    })->map(function ($referencia) {

        return (object) [
            'nombre_banco' =>
                $referencia['nombre_banco'] ?? null,

            'sucursal' =>
                $referencia['sucursal_banco'] ?? null,

            'clase_cuenta' =>
                $referencia['clase_cuenta'] ?? null,

            'numero_cuenta' =>
                $referencia['numero_cuenta'] ?? null,
        ];
    })->values();

    /*
    |--------------------------------------------------------------------------
    | INFORMACIÓN SGC
    |--------------------------------------------------------------------------
    */

    $sgc = (object) [
        'r_conoce_LA_FT' =>
            $siNo($form['r_conoce_LA_FT'] ?? null),

        'r_obligado_reportar' =>
            $siNo($form['r_obligado_reportar'] ?? null),

        'r_tiene_oficial_cumplimiento' =>
            $siNo($form['r_tiene_oficial_cumplimiento'] ?? null),

        'r_nombre_oficial_cumplimiento' =>
            $form['r_nombre_oficial_cumplimiento'] ?? null,

        'r_celular_oficial_cumplimiento' =>
            $form['r_celular_oficial_cumplimiento'] ?? null,

        'r_email_oficial_cumplimiento' =>
            $form['r_email_oficial_cumplimiento'] ?? null,

        'a_maneja_activos_viirtuales' =>
            $siNo($form['a_maneja_activos_viirtuales'] ?? null),

        'a_cuales_activos_virtuales' =>
            $form['a_cuales_activos_virtuales'] ?? null,

        'a_tranfiere_activos_virtuales' =>
            $siNo($form['a_tranfiere_activos_virtuales'] ?? null),

        'o_tiene_oea' =>
            $siNo($form['o_tiene_oea'] ?? null),

        'o_cual_oea' =>
            $form['o_cual_oea'] ?? null,

        'p_persona_publicamente_expuesta' =>
            $siNo(
                $form['p_persona_publicamente_expuesta'] ?? null
            ),

        'p_cargo_desempeña' =>
            $form['p_cargo_desempeña'] ?? null,

        'p_fecha_inicio_cargo' =>
            $form['p_fecha_inicio_cargo'] ?? null,

        'p_finalizacion_cargo' =>
            $form['p_finalizacion_cargo'] ?? null,

        'p_familiar_pep' =>
            $siNo($form['p_familiar_pep'] ?? null),
    ];

    /*
    |--------------------------------------------------------------------------
    | ADJUNTOS
    |--------------------------------------------------------------------------
    */

    $adjuntos = (object) [];

    foreach (
        array_keys($form['adjuntos'] ?? [])
        as $campo
    ) {
        $adjuntos->{$campo} =
            !empty($form['adjuntos'][$campo]);
    }

    /*
    |--------------------------------------------------------------------------
    | DATOS PARA EL PDF
    |--------------------------------------------------------------------------
    */

    $datos = [
        'tercero' =>
            $tercero,

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

        'proveedores' =>
            $proveedores,

        'clientes' =>
            $clientes,

        'referenciasBancarias' =>
            $referenciasBancarias,

        'sgc' =>
            $sgc,

        'adjuntos' =>
            $adjuntos,
    ];

    return app(TerceroPdfService::class)
        ->generarPdfDesdeDatos($datos)
        ->stream('registro-tercero.pdf');
}


public function descargarPdf()
{
    $idFrm = session('registro_tercero_pdf_id');

    if (!$idFrm) {
        abort(
            404,
            'No se encontró un registro de tercero para generar el PDF.'
        );
    }

    $service = app(TerceroPdfService::class);

    return $service
        ->generarPdf((int) $idFrm)
        ->download(
            $service->nombreArchivo((int) $idFrm)
        );
}

/*
|--------------------------------------------------------------------------
| BORRADOR EN CACHÉ DEL SERVIDOR (no en el navegador)
|--------------------------------------------------------------------------
*/

private function claveBorrador(?string $borradorId = null): string
{
    $base = 'tercero_borrador_usuario_' . auth()->id();

    // Mantener compatibilidad con el borrador antiguo.
    if ($borradorId === null || $borradorId === '') {
        return $base;
    }

    if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/', $borradorId)) {
        throw ValidationException::withMessages([
            'borrador_id' => 'El identificador del borrador no es válido.',
        ]);
    }

    return $base . '_' . $borradorId;
}

private function claveIndiceBorradores(): string
{
    return 'tercero_borradores_usuario_' . auth()->id();
}

private function registrarBorradorEnIndice(string $borradorId): void
{
    $clave = $this->claveIndiceBorradores();
    $ids = Cache::get($clave, []);

    if (!is_array($ids)) {
        $ids = [];
    }

    if (!in_array($borradorId, $ids, true)) {
        $ids[] = $borradorId;
    }

    Cache::put($clave, $ids, now()->addMonths(3));
}

public function obtenerBorrador(Request $request)
{
    $borradorId = $request->query('borrador_id');

    if ($borradorId !== null) {
        $borradorId = (string) $borradorId;
    }

    $clave = $this->claveBorrador($borradorId);
    $borrador = Cache::get($clave);

    // Decodificar el JSON almacenado, si existe.
    if (is_string($borrador)) {
        $borrador = json_decode($borrador, true);
    }

    return response()->json([
        'borrador' => is_array($borrador) ? $borrador : null,
    ]);
}

public function guardarBorrador(Request $request)
{
    $datosJson = $request->input('datos');

    if (!is_string($datosJson) || strlen($datosJson) > 4000000) {
        return response()->json(['ok' => false], 422);
    }

    $datos = json_decode($datosJson, true);
    if (!is_array($datos)) {
        return response()->json(['ok' => false], 422);
    }




    // Procesar la firma para el autoguardado sin duplicar archivos.
    if (!isset($datos['form']) || !is_array($datos['form'])) {
        $datos['form'] = [];
    }

    $borradorId = $request->input(
    'borrador_id',
    $datos['borrador_id'] ?? null
);

if ($borradorId !== null) {
    $borradorId = (string) $borradorId;
}

$clave = $this->claveBorrador($borradorId);

$anteriorJson = Cache::get($clave);

if (is_string($anteriorJson)) {
    $anterior = json_decode($anteriorJson, true);
} else {
    $anterior = is_array($anteriorJson) ? $anteriorJson : [];
}

if (!is_array($anterior)) {
    $anterior = [];
}

// Un borrador solo puede actualizar el IdFrm que ya tenía asociado.
$idFrmAnterior = (int) (
    $anterior['form']['id_frm']
    ?? $anterior['id_frm']
    ?? 0
);

$idFrmSolicitado = (int) ($datos['form']['id_frm'] ?? 0);

if (
    $idFrmSolicitado > 0 &&
    $idFrmSolicitado !== $idFrmAnterior
) {
    return response()->json([
        'ok' => false,
        'message' => 'El formulario no está asociado a este borrador.',
    ], 403);
}

if ($borradorId !== null && $borradorId !== '') {
    $datos['borrador_id'] = $borradorId;
}

    $firmaActual = $datos['firmaRepresentante']
        ?? ($datos['form']['firma'] ?? '');

    $firmaAnterior = $anterior['firmaRepresentante']
        ?? ($anterior['form']['firma'] ?? '');

    $rutaFirmaAnterior = $anterior['form']['rl_firma_url'] ?? '';

    if (
        is_string($firmaActual) &&
        preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $firmaActual)
    ) {
        if ($firmaActual === $firmaAnterior && $rutaFirmaAnterior !== '') {
            // La firma no cambió: reutilizar la ruta existente.
            $datos['form']['rl_firma_url'] = $rutaFirmaAnterior;
        } else {
            // La firma cambió: crear un archivo nuevo.
            $base64 = preg_replace(
                '/^data:image\/(png|jpeg|jpg);base64,/',
                '',
                $firmaActual
            );

            $contenido = base64_decode(
                str_replace(' ', '+', $base64),
                true
            );




$rutaFirma = $anterior['form']['rl_firma_url']
    ?? ($datos['form']['rl_firma_url'] ?? '');

$disco = Storage::disk('public');

if (
    $rutaFirma !== '' &&
    str_starts_with($rutaFirma, 'tercero/firma/firma_') &&
    $disco->exists($rutaFirma)
) {
    // Sobrescribir la firma del borrador existente.
    $guardado = $disco->put($rutaFirma, $contenido);
} else {
    // Crear el archivo solo si aún no existe uno para este borrador.
    $rutaFirma = 'tercero/firma/firma_'
        . hash('sha256', $contenido) . '.png';

    $guardado = $disco->exists($rutaFirma)
        || $disco->put($rutaFirma, $contenido);
}

if ($guardado) {
    $datos['form']['rl_firma_url'] = $rutaFirma;
}



        }
    } elseif ($firmaActual === '' || $firmaActual === null) {
        $datos['form']['rl_firma_url'] = '';
    }


    // Mantener los metadatos de adjuntos que se guardaron por el endpoint de archivos.

    if (!is_array($anterior)) $anterior = [];
    if (!empty($anterior['adjuntos']) && is_array($anterior['adjuntos'])) {
        $datos['adjuntos'] = array_replace_recursive(
            $anterior['adjuntos'],
            is_array($datos['adjuntos'] ?? null) ? $datos['adjuntos'] : []
        );
        $datos['adjuntosBorrador'] = array_replace_recursive(
            $anterior['adjuntos'],
            is_array($datos['adjuntosBorrador'] ?? null) ? $datos['adjuntosBorrador'] : []
        );
    }

    $idFrm = (int) ($datos['form']['id_frm'] ?? $anterior['id_frm'] ?? 0);
    $bdGuardada = false;
    $errorBd = null;
    try {

$idFrmSincronizado = $this->sincronizarBorradorConBaseDatos(
    $datos,
    $idFrm ?: null
);

if ($idFrmSincronizado) {
    $idFrm = $idFrmSincronizado;

    if (!isset($datos['form']) || !is_array($datos['form'])) {
        $datos['form'] = [];
    }

    $datos['form']['id_frm'] = $idFrm;
    $datos['id_frm'] = $idFrm;
    $bdGuardada = true;

    // Registrar el estado inicial solo si todavía no existe historial.
    $db = DB::connection('senco360');

    $hayHistorial = $db->table('TER_estado_historico')
        ->where('IdFrm', $idFrm)
        ->exists();

    if (
        !$hayHistorial &&
        $this->estadoActual($db, $idFrm) === self::ESTADO_BORRADOR
    ) {
        $this->registrarEstado(
            $db,
            $idFrm,
            self::ESTADO_BORRADOR,
            'Formulario guardado como borrador.'
        );
    }
}

    } catch (\Throwable $e) {
        $errorBd = $e->getMessage();
        Log::warning('No fue posible sincronizar parcialmente el borrador del tercero.', [
            'mensaje' => $errorBd,
            'usuario' => auth()->id(),
            'id_frm' => $idFrm ?: null,
        ]);
        // El borrador permanece en caché aunque falle una sección de SQL Server.
    }

    // La caché es independiente: siempre conservar el formulario aunque SQL Server falle.
    Cache::put(
    $clave,
    json_encode($datos, JSON_UNESCAPED_UNICODE),
    now()->addMonths(3)
);

if ($borradorId !== null && $borradorId !== '') {
    $this->registrarBorradorEnIndice($borradorId);
}

    return response()->json([
        'ok' => true,
        'id_frm' => $idFrm ?: null,
        'bd_guardada' => $bdGuardada,
        'error_bd' => $errorBd,
    ]);
}

/**
 * Sincroniza únicamente secciones que ya tienen datos suficientes.
 * Los campos incompletos permanecen en caché y no se convierten en ceros/false.
 */
private function sincronizarBorradorConBaseDatos(array $datos, ?int $idFrm): ?int
{
    $form = $datos['form'] ?? [];
    if (!is_array($form)) return $idFrm;

    $minimos = ['fecha_frm', 'id_tipo_frm', 'id_tipo_tercero', 'tipo_persona', 'vendedor', 'razon_social', 'tipo_doc', 'numero_doc', 'direccion', 'codigo_ciiu'];
    foreach ($minimos as $campo) {
        if (!isset($form[$campo]) || trim((string) $form[$campo]) === '') return $idFrm;
    }

    $db = DB::connection('senco360');
    $ciiu = $db->table('TER_codigo_ciiu')
        ->whereRaw('RTRIM(codigo_ciiu) = ?', [trim((string) $form['codigo_ciiu'])])
        ->first();
    if (!$ciiu) return $idFrm;

    $esExtranjero = $this->paisEsExtranjero($form['id_pais'] ?? null);
    $idCiudad = $esExtranjero ? null : $this->codigoEntero($form['id_ciudad'] ?? null);
    $idDepartamento = $esExtranjero ? null : $this->codigoEntero($form['id_departamento'] ?? null);
    // No crear el registro principal mientras falte la ubicación obligatoria de Colombia.
    if (!$esExtranjero && (!$idCiudad || !$idDepartamento)) return $idFrm;

    $fila = [
        'Fecha_frm' => $form['fecha_frm'],
        'IdTipo_frm' => (int) $form['id_tipo_frm'],
        'vendedor' => trim((string) $form['vendedor']),
        'IdTipo_tercero' => (int) $form['id_tipo_tercero'],
        'tipo_persona' => trim((string) $form['tipo_persona']),
        'tiene_establ_comercio' => !empty($form['tiene_establecimiento']) ? 'SI' : 'NO',
        'razon_social' => trim((string) $form['razon_social']),
        'tipo_doc' => trim((string) $form['tipo_doc']),
        'numero_doc' => trim((string) $form['numero_doc']),
        'nombre_establecimiento' => !empty($form['tiene_establecimiento']) ? trim((string) ($form['nombre_establecimiento'] ?? '')) : '',
        'direccion' => trim((string) $form['direccion']),
        'IdCiudad' => $idCiudad,
        'IdDepartamento' => $idDepartamento,
        'telefono' => $this->entero($form['telefono'] ?? null),
        'celular1' => $this->entero($form['celular1'] ?? null),
        'celular2' => $this->entero($form['celular2'] ?? null),
        'email' => trim((string) ($form['email'] ?? '')),
        'email_facturacion' => trim((string) ($form['email_facturacion'] ?? '')),
        'actividad_economica' => mb_substr(trim((string) $ciiu->actividad_economica), 0, 500),
        'IdCodigo_ciiu' => (int) $ciiu->IdCodigo_ciiu,
        'rl_nombre' => trim((string) ($form['rl_nombre'] ?? '')),
        'rl_tipo_doc' => trim((string) ($form['rl_tipo_doc'] ?? '')),
        'rl_numero_doc' => trim((string) ($form['rl_numero_doc'] ?? '')),
        'rl_email' => trim((string) ($form['rl_email'] ?? '')),
        'tesoreria_nombre' => trim((string) ($form['tesoreria_nombre'] ?? '')),
        'tesoreria_telefono' => $this->entero($form['tesoreria_telefono'] ?? null),
        'tesoreria_email' => trim((string) ($form['tesoreria_email'] ?? '')),
        'rl_firma_url' => (string) ($form['rl_firma_url'] ?? ''),
        'acepta_autorizacion' => !empty($form['acepta_autorizacion']),
    ];

    // Guardar primero la información general en su propia transacción.
    // Así, un error en una tabla secundaria no revierte el IdFrm principal.
    $idFrm = $db->transaction(function () use ($db, $fila, $idFrm) {
        if ($idFrm && $db->table('TER_frm_tercero')->where('IdFrm', $idFrm)->exists()) {
            $db->table('TER_frm_tercero')->where('IdFrm', $idFrm)->update($fila);
            return $idFrm;
        }

        return (int) $db->table('TER_frm_tercero')->insertGetId($fila, 'IdFrm');
    });

    $paso = (int) ($datos['pasoActual'] ?? 1);

    try {
        $db->transaction(function () use ($db, $idFrm, $form, $datos, $paso) {
        // Guardar los campos financieros que ya tienen valor, sin convertir vacíos en 0/false.
        $camposDinero = ['activos', 'pasivos', 'ingresos', 'egresos'];
        $camposBooleanos = ['responsable_iva', 'declara_renta', 'gran_contribuyente', 'autoretenedor'];
        $finanzas = ['IdFrm' => $idFrm];

        foreach ($camposDinero as $campo) {
            if (!array_key_exists($campo, $form) || $form[$campo] === null || $form[$campo] === '') continue;
            $valor = str_replace('.', '', (string) $form[$campo]);
            $valor = str_replace(',', '.', $valor);
            if (is_numeric($valor)) $finanzas[$campo] = $valor;
        }
        foreach ($camposBooleanos as $campo) {
            if (!array_key_exists($campo, $form) || $form[$campo] === null || $form[$campo] === '') continue;
            $finanzas[$campo] = filter_var($form[$campo], FILTER_VALIDATE_BOOLEAN);
        }
        if (Schema::connection('senco360')->hasColumn('TER_informacion_financiera', 'fecha_corte') && !empty($form['fecha_corte'])) {
            $finanzas['fecha_corte'] = $form['fecha_corte'];
        }

        $existeFinanzas = $db->table('TER_informacion_financiera')->where('IdFrm', $idFrm)->exists();
        if ($existeFinanzas) {
            unset($finanzas['IdFrm']);
            if (count($finanzas) > 0) {
                $db->table('TER_informacion_financiera')->where('IdFrm', $idFrm)->update($finanzas);
            }
        } elseif (array_key_exists('activos', $finanzas)) {
            // activos es NOT NULL; crear la fila cuando el usuario registre ese importe.
            $db->table('TER_informacion_financiera')->insert($finanzas);
        }
        });
    } catch (\Throwable $e) {
        Log::warning('Falló el guardado parcial de la sección: Guardar finanzas solo cuando la sección está completa.', [
            'mensaje' => $e->getMessage(), 'id_frm' => $idFrm, 'usuario' => auth()->id(),
        ]);
    }

    try {
        $db->transaction(function () use ($db, $idFrm, $form, $datos, $paso) {
            // Paso 2: direcciones de envío. Reemplazar filas del mismo IdFrm evita duplicados.
            if ($paso >= 2 && isset($form['direcciones_envio']) && is_array($form['direcciones_envio'])) {
                $db->table('TER_direccion_envio')->where('IdFrm', $idFrm)->delete();
                foreach ($form['direcciones_envio'] as $direccion) {
                    if (empty($direccion['direccion']) && empty($direccion['id_departamento']) && empty($direccion['id_municipio'])) continue;
                    $pais = $direccion['id_pais'] ?? $direccion['pais'] ?? null;
                    $extranjera = $this->paisEsExtranjero($pais);
                    $db->table('TER_direccion_envio')->insert([
                        'IdFrm' => $idFrm,
                        'direccion_envio' => trim((string) ($direccion['direccion'] ?? '')),
                        'Id_cod_pais' => $this->codigoEntero($pais),
                        'Id_cod_dpto' => $extranjera ? null : $this->codigoEntero($direccion['id_departamento'] ?? null),
                        'Id_cod_mpio' => $extranjera ? null : $this->codigoEntero($direccion['id_municipio'] ?? null),
                        'barrio' => $extranjera ? '' : trim((string) ($direccion['barrio'] ?? '')),
                        'observaciones' => trim((string) ($direccion['observaciones'] ?? '')),
                    ]);
                }
            }
        });
    } catch (\Throwable $e) {
        Log::warning('Falló el guardado parcial de la sección: Paso 2 direcciones de envío..', [
            'mensaje' => $e->getMessage(), 'id_frm' => $idFrm, 'usuario' => auth()->id(),
        ]);
    }

    try {
        $db->transaction(function () use ($db, $idFrm, $form, $datos, $paso) {
            // Paso 3: socios.
            if ($paso >= 3 && isset($form['socios']) && is_array($form['socios'])) {
                $db->table('TER_socio')->where('IdFrm', $idFrm)->delete();
                foreach ($form['socios'] as $socio) {
                    if (empty($socio['numero_identificacion']) && empty($socio['nombres']) && empty($socio['apellidos'])) continue;
                    $porcentaje = $socio['porcentaje_participacion'] ?? null;
                    $porcentaje = ($porcentaje === '' || $porcentaje === null) ? 0 : (float) str_replace(',', '.', (string) $porcentaje);
                    $db->table('TER_socio')->insert([
                        'IdFrm' => $idFrm,
                        'nombres' => trim((string) ($socio['nombres'] ?? '')),
                        'apellidos' => trim((string) ($socio['apellidos'] ?? '')),
                        'tipo_doc' => trim((string) ($socio['tipo_identificacion'] ?? '')),
                        'numero_doc' => trim((string) ($socio['numero_identificacion'] ?? '')),
                        'porcentaje_participacion' => round($porcentaje, 2),
                    ]);
                }
            }
        });
    } catch (\Throwable $e) {
        Log::warning('Falló el guardado parcial de la sección: Paso 3 socios..', [
            'mensaje' => $e->getMessage(), 'id_frm' => $idFrm, 'usuario' => auth()->id(),
        ]);
    }

    try {
        $db->transaction(function () use ($db, $idFrm, $form, $datos, $paso) {
            // Paso 5: referencias comerciales (proveedores y clientes comparten tabla).
            if ($paso >= 5) {
                $db->table('TER_referencia_comercial')->where('IdFrm', $idFrm)->delete();
                foreach (['proveedores' => 'Proveedor', 'clientes' => 'Cliente'] as $lista => $tipoReferencia) {
                    foreach (($form[$lista] ?? []) as $referencia) {
                        if (empty($referencia['nombre_razon_social']) && empty($referencia['numero_identificacion'])) continue;
                        $db->table('TER_referencia_comercial')->insert([
                            'IdFrm' => $idFrm,
                            'tipo_referencia' => $tipoReferencia,
                            'nombre_razon_social' => trim((string) ($referencia['nombre_razon_social'] ?? '')),
                            'tipo_doc' => trim((string) ($referencia['tipo_identificacion'] ?? '')),
                            'numero_doc' => trim((string) ($referencia['numero_identificacion'] ?? '')),
                            'direccion' => trim((string) ($referencia['direccion'] ?? '')),
                            'IdCiudad' => $this->codigoEntero($referencia['id_ciudad'] ?? null),
                            'IdDepartamento' => $this->codigoEntero($referencia['id_departamento'] ?? null),
                            'telefono' => $this->entero($referencia['telefono'] ?? null),
                            'celular' => $this->entero($referencia['celular'] ?? null),
                            'email' => trim((string) ($referencia['email'] ?? '')),
                        ]);
                    }
                }
            }
        });
    } catch (\Throwable $e) {
        Log::warning('Falló el guardado parcial de la sección: Paso 5 referencias comerciales.', [
            'mensaje' => $e->getMessage(), 'id_frm' => $idFrm, 'usuario' => auth()->id(),
        ]);
    }

    try {
        $db->transaction(function () use ($db, $idFrm, $form, $datos, $paso) {
            // Paso 6: referencias bancarias.
            if ($paso >= 6 && isset($form['referencias_bancarias']) && is_array($form['referencias_bancarias'])) {
                $db->table('TER_referencia_bancaria')->where('IdFrm', $idFrm)->delete();
                foreach ($form['referencias_bancarias'] as $referencia) {
                    if (empty($referencia['nombre_banco']) && empty($referencia['numero_cuenta'])) continue;
                    $db->table('TER_referencia_bancaria')->insert([
                        'IdFrm' => $idFrm,
                        'nombre_banco' => trim((string) ($referencia['nombre_banco'] ?? '')),
                        'sucursal' => trim((string) ($referencia['sucursal_banco'] ?? '')),
                        'clase_cuenta' => trim((string) ($referencia['clase_cuenta'] ?? '')),
                        'numero_cuenta' => $this->entero($referencia['numero_cuenta'] ?? null),
                    ]);
                }
            }
        });
    } catch (\Throwable $e) {
        Log::warning('Falló el guardado parcial de la sección: Paso 6 referencias bancarias..', [
            'mensaje' => $e->getMessage(), 'id_frm' => $idFrm, 'usuario' => auth()->id(),
        ]);
    }

    try {
        $db->transaction(function () use ($db, $idFrm, $form, $datos, $paso) {
            // Paso 7: SGC solo se persiste cuando las respuestas booleanas ya están definidas.
            $camposSgcBool = ['r_conoce_LA_FT','r_obligado_reportar','r_tiene_oficial_cumplimiento','a_maneja_activos_viirtuales','a_tranfiere_activos_virtuales','o_tiene_oea','p_persona_publicamente_expuesta','p_familiar_pep'];
            $sgcCompleto = $paso >= 7;
            foreach ($camposSgcBool as $campo) if (!array_key_exists($campo, $form) || $form[$campo] === null || $form[$campo] === '') $sgcCompleto = false;
            if ($sgcCompleto) {
                $sgc = ['IdFrm' => $idFrm];
                foreach ($camposSgcBool as $campo) $sgc[$campo] = filter_var($form[$campo], FILTER_VALIDATE_BOOLEAN);
                foreach (['r_nombre_oficial_cumplimiento','r_email_oficial_cumplimiento','a_cuales_activos_virtuales','o_cual_oea','p_cargo_desempeña'] as $campo) $sgc[$campo] = trim((string) ($form[$campo] ?? ''));
                $sgc['r_celular_oficial_cumplimiento'] = $this->entero($form['r_celular_oficial_cumplimiento'] ?? null);
                $sgc['p_fecha_inicio_cargo'] = $form['p_fecha_inicio_cargo'] ?? '1900-01-01';
                $sgc['p_finalizacion_cargo'] = $form['p_finalizacion_cargo'] ?? '1900-01-01';
                $db->table('TER_informacion_sgc')->where('IdFrm', $idFrm)->delete();
                $db->table('TER_informacion_sgc')->insert($sgc);
            }
        });
    } catch (\Throwable $e) {
        Log::warning('Falló el guardado parcial de la sección: Paso 7 SGC solo.', [
            'mensaje' => $e->getMessage(), 'id_frm' => $idFrm, 'usuario' => auth()->id(),
        ]);
    }

    try {
        $db->transaction(function () use ($db, $idFrm, $form, $datos, $paso) {
            // Paso 8: asociar a TER_adjunto las rutas de los archivos temporales ya guardados.
            // El borrador conserva los archivos en Storage; aquí solo se persisten columnas de texto/ruta.
            if ($paso >= 8) {
                $adjuntos = $datos['adjuntosBorrador'] ?? $datos['adjuntos'] ?? [];
                if (is_array($adjuntos) && count($adjuntos) > 0) {
                    $tiposColumnas = array_change_key_case(
                        $db->table('INFORMATION_SCHEMA.COLUMNS')->where('TABLE_NAME', 'TER_adjunto')->pluck('DATA_TYPE', 'COLUMN_NAME')->map(fn ($tipo) => strtolower($tipo))->all(),
                        CASE_LOWER
                    );
                    $filaAdjuntos = ['IdFrm' => $idFrm];
                    foreach (['carta_instrucciones','pagare','certificado_existencia','copia_rut','estados_financieros','cedula_rl','certificacion_bancaria','certificaciones'] as $campo) {
                        $ruta = $adjuntos[$campo]['ruta'] ?? null;
                        if (!$ruta || !Storage::disk('public')->exists($ruta)) continue;
                        $tipo = $tiposColumnas[strtolower($campo)] ?? 'varchar';
                        if (!in_array($tipo, ['varbinary','binary','image'], true)) $filaAdjuntos[$campo] = $ruta;
                    }
                    if (count($filaAdjuntos) > 1) {
                        $db->table('TER_adjunto')->where('IdFrm', $idFrm)->delete();
                        $db->table('TER_adjunto')->insert($filaAdjuntos);
                    }
                }
            }
        });
    } catch (\Throwable $e) {
        Log::warning('Falló el guardado parcial de la sección: Paso 8 asociar.', [
            'mensaje' => $e->getMessage(), 'id_frm' => $idFrm, 'usuario' => auth()->id(),
        ]);
    }

    return $idFrm;
}


public function guardarAdjuntoBorrador(Request $request)
{
    $camposPermitidos = [
        'carta_instrucciones',
        'pagare',
        'certificado_existencia',
        'copia_rut',
        'estados_financieros',
        'cedula_rl',
        'certificacion_bancaria',
        'certificaciones',
    ];

    $campo = $request->input('campo');

    if (!in_array($campo, $camposPermitidos, true)) {
        return response()->json([
            'ok' => false,
            'message' => 'Campo de adjunto no permitido.',
        ], 422);
    }

    $request->validate([
        'campo' => ['required', 'string'],
        'archivo' => [
            'required',
            'file',
            'mimes:pdf,jpg,jpeg,png',
            'max:10240',
        ],
    ]);

    $archivo = $request->file('archivo');

    if (!$archivo instanceof UploadedFile || !$archivo->isValid()) {
        return response()->json([
            'ok' => false,
            'message' => 'El archivo no es válido.',
        ], 422);
    }

    $extension = strtolower(
        $archivo->getClientOriginalExtension()
    );

    $nombreOriginal = $archivo->getClientOriginalName();

    $nombreArchivo =
    auth()->id() .
    '_' .
    $campo .
    '_' .
    date('YmdHis') .
    '_' .
    uniqid() .
    '.' .
    $extension;

$ruta = 'tercero/adjuntos/' . $nombreArchivo;

    $contenido = file_get_contents(
        $archivo->getRealPath()
    );

    if ($contenido === false) {
        return response()->json([
            'ok' => false,
            'message' => 'No fue posible leer el archivo.',
        ], 422);
    }

    $guardado = Storage::disk('public')->put(
        $ruta,
        $contenido
    );

    if (!$guardado) {
        return response()->json([
            'ok' => false,
            'message' => 'No fue posible guardar el archivo.',
        ], 500);
    }

     $borradorId = $request->input('borrador_id');

    if (!is_string($borradorId) || $borradorId === '') {
        return response()->json([
            'ok' => false,
            'message' => 'Falta el identificador del borrador.',
        ], 422);
    }

    $clave = $this->claveBorrador($borradorId);

    $borradorActual = Cache::get($clave);

    if (is_string($borradorActual)) {
        $datos = json_decode(
            $borradorActual,
            true
        );
    } else {
        $datos = is_array($borradorActual)
            ? $borradorActual
            : [];
    }

    if (!is_array($datos)) {
        $datos = [];
    }

    if (!isset($datos['adjuntos']) || !is_array($datos['adjuntos'])) {
        $datos['adjuntos'] = [];
    }

    /*
     * Si ya había un archivo para este campo,
     * eliminamos el temporal anterior.
     */
    if (
        !empty($datos['adjuntos'][$campo]['ruta']) &&
        Storage::disk('public')->exists(
            $datos['adjuntos'][$campo]['ruta']
        )
    ) {
        Storage::disk('public')->delete(
            $datos['adjuntos'][$campo]['ruta']
        );
    }

  $datos['adjuntos'][$campo] = [
    'nombre' => $nombreOriginal,
    'ruta' => $ruta,
    'fecha' => now()->toIso8601String(),
];

       $datos['borrador_id'] = $borradorId;

    Cache::put(
        $clave,
        json_encode($datos, JSON_UNESCAPED_UNICODE),
        now()->addMonths(3)
    );

    $this->registrarBorradorEnIndice($borradorId);

    return response()->json([
        'ok' => true,
        'adjunto' => $datos['adjuntos'][$campo],
    ]);
}


public function eliminarBorrador(Request $request)
{
    $borradorId = $request->query('borrador_id');

    if (!is_string($borradorId) || $borradorId === '') {
        return response()->json([
            'ok' => false,
            'message' => 'Falta el identificador del borrador.',
        ], 422);
    }

    $clave = $this->claveBorrador($borradorId);
    $datos = Cache::get($clave);

    if (is_string($datos)) {
        $datos = json_decode($datos, true);
    }

    if (!is_array($datos)) {
        return response()->json([
            'ok' => false,
            'message' => 'El borrador no existe o ya expiró. No se eliminó ningún registro.',
        ], 404);
    }

    $form = is_array($datos['form'] ?? null)
        ? $datos['form']
        : [];

    $idFrm = (int) ($form['id_frm'] ?? $datos['id_frm'] ?? 0);

    $db = DB::connection('senco360');
    $rutasEliminar = [];

    // Recopilar únicamente rutas conocidas del almacenamiento del formulario.
    $agregarRuta = function ($ruta) use (&$rutasEliminar) {
        if (!is_string($ruta)) {
            return;
        }

        $ruta = ltrim(str_replace('\\', '/', trim($ruta)), '/');

        if (
            str_starts_with($ruta, 'tercero/adjuntos/') ||
            str_starts_with($ruta, 'tercero/firma/')
        ) {
            $rutasEliminar[] = $ruta;
        }
    };

    $agregarRuta($form['rl_firma_url'] ?? null);

    foreach (['adjuntos', 'adjuntosBorrador'] as $grupo) {
        foreach (($datos[$grupo] ?? []) as $adjunto) {
            if (is_array($adjunto)) {
                $agregarRuta($adjunto['ruta'] ?? null);
            }
        }
    }

    if ($idFrm > 0) {
        $existe = $db->table('TER_frm_tercero')
            ->where('IdFrm', $idFrm)
            ->exists();

        if (!$existe) {
            return response()->json([
                'ok' => false,
                'message' => 'El formulario asociado no existe en la base de datos.',
            ], 404);
        }

        // Solo se permite eliminar formularios realmente en borrador.
        $estado = trim($this->estadoActual($db, $idFrm));

        if (mb_strtolower($estado) !== mb_strtolower(self::ESTADO_BORRADOR)) {
            return response()->json([
                'ok' => false,
                'message' => 'Solo se pueden eliminar formularios en borrador.',
            ], 403);
        }

        try {
            $db->transaction(function () use ($db, $idFrm) {
                // Recopilar rutas de adjuntos registradas en SQL Server.
                $adjuntos = $db->table('TER_adjunto')
                    ->where('IdFrm', $idFrm)
                    ->get();

                foreach ($adjuntos as $adjunto) {
                    foreach ([
                        'carta_instrucciones',
                        'pagare',
                        'certificado_existencia',
                        'copia_rut',
                        'estados_financieros',
                        'cedula_rl',
                        'certificacion_bancaria',
                        'certificaciones',
                    ] as $campo) {
                        $ruta = $adjunto->{$campo} ?? null;

                        if (
                            is_string($ruta) &&
                            (
                                str_starts_with(ltrim($ruta, '/'), 'tercero/adjuntos/') ||
                                str_starts_with(ltrim($ruta, '/'), 'tercero/firma/')
                            )
                        ) {
                            // La eliminación física se realiza después
                            // de que la transacción termine correctamente.
                            // Las rutas se procesan fuera de esta clausura.
                        }
                    }
                }

                foreach ([
                    'TER_adjunto',
                    'TER_direccion_envio',
                    'TER_estado_historico',
                    'TER_informacion_financiera',
                    'TER_informacion_sgc',
                    'TER_referencia_bancaria',
                    'TER_referencia_comercial',
                    'TER_socio',
                ] as $tabla) {
                    $db->table($tabla)->where('IdFrm', $idFrm)->delete();
                }

                $db->table('TER_frm_tercero')
                    ->where('IdFrm', $idFrm)
                    ->delete();
            });
        } catch (\Throwable $e) {
            Log::error('Error al eliminar un formulario en borrador.', [
                'id_frm' => $idFrm,
                'usuario' => auth()->id(),
                'mensaje' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No fue posible eliminar el formulario. No se limpió la caché.',
            ], 500);
        }
    }

    // Limpiar la caché y el índice solo después de eliminar correctamente.
    Cache::forget($clave);

    $claveIndice = $this->claveIndiceBorradores();
    $ids = Cache::get($claveIndice, []);

    if (is_array($ids)) {
        $ids = array_values(array_filter(
            $ids,
            fn ($id) => $id !== $borradorId
        ));

        Cache::put($claveIndice, $ids, now()->addMonths(3));
    }

    return response()->json(['ok' => true]);
}


/*
|--------------------------------------------------------------------------
| PAÍS DIFERENTE A COLOMBIA
|--------------------------------------------------------------------------
*/

private function paisEsExtranjero($codigoPais): bool
{
    if ($codigoPais === null || $codigoPais === '') {
        return false;
    }

    static $memo = [];

    $clave = (string) $codigoPais;

    if (array_key_exists($clave, $memo)) {
        return $memo[$clave];
    }

    $esExtranjero = false;

    try {
        $paisColumn = json_decode('"Pa\u00eds"');
        $codigoDianColumn = json_decode('"C\u00f3digo DIAN"');

        $pais = DB::connection('senco360')
            ->table(DB::raw(
                'INTEGRACION_MAX_PSL.dbo.[DS_EF_TBL_' . $paisColumn . ']'
            ))
            ->select(DB::raw('[' . $paisColumn . '] as nombre'))
            ->where(DB::raw('[' . $codigoDianColumn . ']'), $clave)
            ->first();

        if ($pais && $pais->nombre) {
            $esExtranjero = Str::of($pais->nombre)
                ->trim()
                ->ascii()
                ->lower()
                ->value() !== 'colombia';
        }
    } catch (Throwable $e) {
        report($e);
    }

    return $memo[$clave] = $esExtranjero;
}

    /*
    |--------------------------------------------------------------------------
    | NORMALIZAR CÓDIGO MAX / 360
    |--------------------------------------------------------------------------
    |
    | Max/360 puede entregar códigos con ceros iniciales:
    |
    | 05    -> 5
    | 05001 -> 5001
    |
    | SENCO360P guarda estos campos como INT.
    |
    |--------------------------------------------------------------------------
    */

    private function codigoEntero($valor)
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (!is_numeric($valor)) {
            return null;
        }

        return (int) $valor;
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZAR ENTEROS
    |--------------------------------------------------------------------------
    */

    private function entero($valor, $default = 0)
    {
        if ($valor === null || $valor === '') {
            return $default;
        }

        if (!is_numeric($valor)) {
            return $default;
        }

        return (int) $valor;
    }



        /*
    |--------------------------------------------------------------------------
    | LISTADO DE FORMULARIOS DE TERCEROS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ESTADOS DEL FORMULARIO
    |--------------------------------------------------------------------------
    | En borrador -> Ingresado (al enviar) -> Validado por vendedor
    |                                      -> Aceptado por vendedor
    | Las descripciones deben existir en la tabla TER_estado.
    */
    private const ESTADO_BORRADOR = 'En borrador';
    private const ESTADO_INGRESADO = 'Ingresado';
   private const ESTADOS_ASESOR = [
    'Validado por vendedor',
    'Rechazado por vendedor',
];

    /**
     * Estado vigente del formulario (el último registrado). Si aún no hay
     * historial, se deduce de acepta_autorizacion.
     */
    private function estadoActual($db, int $idFrm): string
    {
        $descripcion = $db->table('TER_estado_historico as ie')
            ->join('TER_estado as es', 'es.IdEstado', '=', 'ie.IdEstado')
            ->where('ie.IdFrm', $idFrm)
            ->orderByDesc('ie.fecha_estado')
            ->orderByDesc('ie.IdInt_estado')
            ->value(DB::raw('RTRIM(es.descripcion)'));

        if ($descripcion !== null && $descripcion !== '') {
            return trim((string) $descripcion);
        }

        $acepto = (int) $db->table('TER_frm_tercero')
            ->where('IdFrm', $idFrm)
            ->value('acepta_autorizacion');

        return $acepto === 1 ? self::ESTADO_INGRESADO : self::ESTADO_BORRADOR;
    }

    /**
     * Agrega un registro al historial de estados. Nunca lanza excepción:
     * si el estado no existe en TER_estado solo deja un aviso en el log.
     */
    private function registrarEstado($db, int $idFrm, string $descripcion, ?string $observaciones = null): bool
    {
        try {
            $idEstado = $db->table('TER_estado')
                ->whereRaw('LOWER(LTRIM(RTRIM(descripcion))) = ?', [mb_strtolower($descripcion)])
                ->value('IdEstado');

            if (!$idEstado) {
                Log::warning('El estado no existe en TER_estado.', [
                    'estado' => $descripcion,
                    'id_frm' => $idFrm,
                ]);

                return false;
            }

            $db->table('TER_estado_historico')->insert([
                'IdFrm' => $idFrm,
                'IdEstado' => $idEstado,
                'fecha_estado' => now(),
                'observaciones' => $observaciones,
            ]);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * El asesor marca un formulario Ingresado como validado o aceptado.
     */
    public function cambiarEstado(Request $request, $id)
    {
        abort_unless(
            Gate::allows('formularios-terceros.validar')
                || Gate::allows('formularios-terceros.editar'),
            403,
            'No tienes permisos para cambiar el estado.'
        );

        $datos = $request->validate([
            'estado' => ['required', 'string', 'in:' . implode(',', self::ESTADOS_ASESOR)],
            'observaciones' => [
                'nullable',
                'string',
                'max:500',
                'required_if:estado,Rechazado por vendedor',
            ],
        ]);

        $db = DB::connection('senco360');

        abort_unless(
            $db->table('TER_frm_tercero')->where('IdFrm', $id)->exists(),
            404,
            'No se encontró el formulario.'
        );

        if ($this->estadoActual($db, (int) $id) !== self::ESTADO_INGRESADO) {
            return response()->json([
                'message' => 'Solo se puede cambiar el estado de formularios en estado Ingresado.',
            ], 422);
        }

        $usuario = $request->user()?->name ?? ('usuario ' . auth()->id());
        $observacion = trim(
            'Cambiado por ' . $usuario . '. ' . ($datos['observaciones'] ?? '')
        );

        if (!$this->registrarEstado($db, (int) $id, $datos['estado'], $observacion)) {
            return response()->json([
                'message' => 'No fue posible registrar el estado. Verifique que exista en la tabla TER_estado.',
            ], 500);
        }

        return response()->json([
            'mensaje' => 'Estado actualizado correctamente.',
            'estado' => $datos['estado'],
        ]);
    }

    public function indexFormularios(Request $request)
    {
        $puedeEditar = Gate::allows('formularios-terceros.editar');

        $db = DB::connection('senco360');

        $consulta = $db->table('TER_frm_tercero as f')
            ->leftJoin(
                'TER_tipo_frm as tf',
                'tf.IdTipo_frm',
                '=',
                'f.IdTipo_frm'
            )
            ->leftJoin(
                'TER_tipo_tercero as tt',
                'tt.IdTipo_tercero',
                '=',
                'f.IdTipo_tercero'
            )
           ->leftJoin('TER_estado_historico as ie', function ($join) {
                // Solo el estado más reciente de cada formulario.
                $join->on('ie.IdInt_estado', '=', DB::raw(
                    '(SELECT TOP 1 x.IdInt_estado FROM TER_estado_historico x '
                    . 'WHERE x.IdFrm = f.IdFrm '
                    . 'ORDER BY x.fecha_estado DESC, x.IdInt_estado DESC)'
                ));
            })

            ->leftJoin(
                'TER_estado as es',
                'es.IdEstado',
                '=',
                'ie.IdEstado'
            )
            ->select([
                'f.IdFrm as id',
                'f.Fecha_frm as fecha',
                'f.IdTipo_frm',
                'f.IdTipo_tercero',
                'f.acepta_autorizacion',
                DB::raw('RTRIM(f.razon_social) as razon_social'),
                DB::raw('RTRIM(f.numero_doc) as numero_doc'),
                DB::raw('RTRIM(f.vendedor) as vendedor'),

               DB::raw("
    (
        SELECT TOP 1 LTRIM(RTRIM(s.SLSNME_26))
        FROM MAXSENCO.dbo.Sales_Rep_Master s
        WHERE LTRIM(RTRIM(s.SLSREP_26)) COLLATE SQL_Latin1_General_CP1_CI_AS
            = LTRIM(RTRIM(f.vendedor)) COLLATE SQL_Latin1_General_CP1_CI_AS
    ) as vendedor_nombre
"),
                DB::raw('RTRIM(tf.Tipo_frm) as tipo_formulario'),
                DB::raw('RTRIM(tt.tipo_tercero) as tipo_tercero'),
                DB::raw("
                    COALESCE(
                        RTRIM(es.descripcion),
                        CASE
                            WHEN f.acepta_autorizacion = 1
                                THEN 'Ingresado'
                            ELSE 'En borrador'
                        END
                    ) as estado
                "),
            ]);

        if ($request->filled('buscar')) {
            $buscar = trim($request->input('buscar'));

            $consulta->where(function ($q) use ($buscar) {
                $q->where('f.razon_social', 'like', "%{$buscar}%")
                    ->orWhere('f.numero_doc', 'like', "%{$buscar}%")
                    ->orWhere('f.IdFrm', 'like', "%{$buscar}%");
            });
        }


if ($request->filled('fecha_formulario')) {
    $consulta->whereDate(
        'f.Fecha_frm',
        '=',
        $request->input('fecha_formulario')
    );
}




$ordenar = $request->input('ordenar', 'fecha');
$direccion = strtolower($request->input('direccion', 'desc'));

if (!in_array($direccion, ['asc', 'desc'], true)) {
    $direccion = 'desc';
}

$camposOrden = [
    'fecha' => 'fecha',
    'id' => 'id',
    'vendedor' => 'vendedor_nombre',
    'numero_doc' => 'numero_doc',
    'razon_social' => 'razon_social',
    'tipo_formulario' => 'tipo_formulario',
    'estado' => 'estado',
];

$campoOrden = $camposOrden[$ordenar] ?? 'fecha';

$formularios = $consulta
    ->distinct()
    ->orderBy($campoOrden, $direccion)
    ->when($campoOrden !== 'id', function ($q) use ($direccion) {
        $q->orderBy('f.IdFrm', $direccion);
    })
    ->paginate(15)
    ->withQueryString();




          $idsBorradores = Cache::get($this->claveIndiceBorradores(), []);
$borradores = [];
$borradoresAsociados = [];

if (is_array($idsBorradores)) {
    foreach ($idsBorradores as $borradorId) {
        $datos = Cache::get($this->claveBorrador($borradorId));

        if (is_string($datos)) {
            $datos = json_decode($datos, true);
        }

        if (!is_array($datos)) {
            continue;
        }

        $form = $datos['form'] ?? [];

        if (!is_array($form)) {
            $form = [];
        }

        // Si ya existe en SQL, se mostrará desde el listado normal.
        $idFrm = (int) ($form['id_frm'] ?? $datos['id_frm'] ?? 0);

       if (
    $idFrm > 0 &&
    $db->table('TER_frm_tercero')
        ->where('IdFrm', $idFrm)
        ->exists()
) {
    $borradoresAsociados[$idFrm] = [
        'borrador_id' => $borradorId,
        'razon_social' => $form['razon_social'] ?? '',
    ];

    continue;
}

        $borradores[] = [
            'borrador_id' => $borradorId,
            'razon_social' => $form['razon_social'] ?? '',
            'numero_doc' => $form['numero_doc'] ?? '',
            'fecha' => $datos['fecha'] ?? null,
        ];
    }
}


$formularios->getCollection()->transform(
    function ($formulario) use ($borradoresAsociados, $db) {
        $idFrm = (int) ($formulario->id ?? 0);

        if (isset($borradoresAsociados[$idFrm])) {
            // Consultar el estado real, sin forzarlo a "En borrador".
            $estadoReal = $this->estadoActual($db, $idFrm);

            $formulario->estado = $estadoReal;

            // Solo asociar acciones de borrador si sigue en ese estado.
            if (mb_strtolower(trim($estadoReal)) === mb_strtolower(self::ESTADO_BORRADOR)) {
                $formulario->borrador_id =
                    $borradoresAsociados[$idFrm]['borrador_id'];
            }
        }

        return $formulario;
    }
);
        return Inertia::render('GestionClientes/IndexFormularios', [
            'formularios' => $formularios,
            'borradores' => $borradores,
               'filtros' => $request->only([
                'fecha_formulario',
                'ordenar',
                'direccion',
            ]),
            'permisos' => [
                'crear' => true,
                'editar' => (bool) $puedeEditar,
                'validar' => Gate::allows('formularios-terceros.validar')
                    || (bool) $puedeEditar,
            ],
            'tiposTercero' => $db->table('TER_tipo_tercero')
                ->select('IdTipo_tercero', DB::raw('RTRIM(tipo_tercero) as tipo_tercero'))
                ->orderBy('IdTipo_tercero')
                ->get(),
            'tiposFormulario' => $db->table('TER_tipo_frm')
                ->select('IdTipo_frm', DB::raw('RTRIM(Tipo_frm) as Tipo_frm'))
                ->orderBy('IdTipo_frm')
                ->get(),
            'tiposIdentificacion' => $db
                ->table(DB::raw('INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Tipo_de_Documento'))
                ->select(
                    DB::raw('LTRIM(RTRIM([Código])) as codigo'),
                    DB::raw('[Tipo de Documento] as nombre')
                )
                ->orderBy(DB::raw('[Código]'))
                ->get(),
            'ciiu' => $db->table('TER_codigo_ciiu')
                ->select(
                    'IdCodigo_ciiu',
                    DB::raw('RTRIM(codigo_ciiu) as codigo_ciiu'),
                    DB::raw('RTRIM(actividad_economica) as actividad_economica')
                )
                ->orderBy('codigo_ciiu')
                ->get(),
            'vendedores' => $db->select("
                SELECT
                    LTRIM(RTRIM(SLSREP_26)) AS codigo,
                    LTRIM(RTRIM(SLSNME_26)) AS nombre
                FROM MAXSENCO.dbo.Sales_Rep_Master
                WHERE RTRIM(UDFKEY_26) <> 'Inactivo'
                  AND LTRIM(RTRIM(SLSREP_26)) <> '001'
                  AND RTRIM(UDFREF_26) NOT LIKE '%cnico%'
                ORDER BY SLSNME_26
            "),
              ]);
    }

    /**
     * Consultar los datos guardados de un formulario por su IdFrm.
     */
    public function detalleFormulario($id)
    {
        $db = DB::connection('senco360');

        $general = $db->table('TER_frm_tercero')
            ->where('IdFrm', $id)
            ->first();

        if (!$general) {
            abort(404, 'No se encontró el formulario solicitado.');
        }

        $relaciones = [
            'direcciones' => 'TER_direccion_envio',
            'socios' => 'TER_socio',
            'financiera' => 'TER_informacion_financiera',
            'referencias_comerciales' => 'TER_referencia_comercial',
            'referencias_bancarias' => 'TER_referencia_bancaria',
            'sgc' => 'TER_informacion_sgc',
            'adjuntos' => 'TER_adjunto',
        ];


$general = (array) $general;

// Convertir los identificadores de catálogos a nombres descriptivos.
$general['tipo_formulario'] = $db->table('TER_tipo_frm')
    ->where('IdTipo_frm', $general['IdTipo_frm'])
    ->value('Tipo_frm');

$general['tipo_tercero'] = $db->table('TER_tipo_tercero')
    ->where('IdTipo_tercero', $general['IdTipo_tercero'])
    ->value('tipo_tercero');

$general['tipo_doc_nombre'] = $db
    ->table(DB::raw(
        'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Tipo_de_Documento'
    ))
    ->whereRaw('LTRIM(RTRIM([Código])) = ?', [
        trim((string) ($general['tipo_doc'] ?? ''))
    ])
    ->value('Tipo de Documento');

    $general['rl_tipo_doc_nombre'] = $db
    ->table(DB::raw(
        'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Tipo_de_Documento'
    ))
    ->whereRaw('LTRIM(RTRIM([Código])) = ?', [
        trim((string) ($general['rl_tipo_doc'] ?? ''))
    ])
    ->value('Tipo de Documento');

$general['vendedor_nombre'] = DB::connection('senco360')
    ->selectOne("
        SELECT TOP 1
            LTRIM(RTRIM(SLSREP_26)) AS codigo,
            LTRIM(RTRIM(SLSNME_26)) AS nombre
        FROM MAXSENCO.dbo.Sales_Rep_Master
        WHERE LTRIM(RTRIM(SLSREP_26)) = ?
    ", [trim((string) ($general['vendedor'] ?? ''))])
    ?->nombre ?? null;

    $general['pais_nombre'] = null;

if (!empty($general['IdDepartamento'])) {
    $paisCodigo = DB::connection('senco360')
        ->table(DB::raw('INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Departamento'))
        ->whereRaw('[Código DIAN] = ?', [
            trim((string) $general['IdDepartamento'])
        ])
        ->value('País');

    if ($paisCodigo !== null) {
        $general['pais_nombre'] = DB::connection('senco360')
            ->table(DB::raw('INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_País'))
            ->whereRaw('[Código DIAN] = ?', [
                trim((string) $paisCodigo)
            ])
            ->value('País');
    }
}

$general['departamento_nombre'] = DB::connection('senco360')
    ->table(DB::raw('INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Departamento'))
    ->whereRaw('[Código DIAN] = ?', [
        trim((string) ($general['IdDepartamento'] ?? ''))
    ])
    ->value('Departamento');

$general['ciudad_nombre'] = DB::connection('senco360')
    ->table(DB::raw('INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Municipio'))
    ->whereRaw('[Código DIAN] = ?', [
        trim((string) ($general['IdCiudad'] ?? ''))
    ])
    ->value('Municipio');

    $general['codigo_ciiu_nombre'] = DB::connection('senco360')
    ->table('TER_codigo_ciiu')
    ->where('IdCodigo_ciiu', $general['IdCodigo_ciiu'] ?? null)
    ->value('codigo_ciiu');


$detalle = [
    'general' => (object) $general,
];

foreach ($relaciones as $clave => $tabla) {
    $detalle[$clave] = $db->table($tabla)
        ->where('IdFrm', $id)
        ->get();

    if ($clave === 'socios') {
        $detalle[$clave] = $detalle[$clave]->map(function ($socio) use ($db) {
            $socio->tipo_doc_nombre = $db
                ->table(DB::raw(
                    'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Tipo_de_Documento'
                ))
                ->whereRaw('LTRIM(RTRIM([Código])) = ?', [
                    trim((string) ($socio->tipo_doc ?? ''))
                ])
                ->value('Tipo de Documento');

            return $socio;
        });
    }


if ($clave === 'referencias_comerciales') {
    $detalle[$clave] = $detalle[$clave]->map(function ($referencia) use ($db) {
        $referencia->tipo_doc_nombre = $db
            ->table(DB::raw(
                'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Tipo_de_Documento'
            ))
            ->whereRaw('LTRIM(RTRIM([Código])) = ?', [
                trim((string) ($referencia->tipo_doc ?? ''))
            ])
            ->value('Tipo de Documento');

        $departamentoCodigo = trim(
            (string) ($referencia->IdDepartamento ?? '')
        );
        $municipioCodigo = trim(
            (string) ($referencia->IdCiudad ?? '')
        );

        $departamento = $departamentoCodigo !== ''
            ? $db->table(DB::raw(
                'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Departamento'
            ))
                ->whereRaw('TRY_CONVERT(INT, [Código DIAN]) = ?', [
                    (int) $departamentoCodigo
                ])
                ->first()
            : null;

        $referencia->departamento_nombre = $departamento->Departamento ?? null;

        $referencia->pais_nombre = !empty($departamento->País)
            ? $db->table(DB::raw(
                'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_País'
            ))
                ->whereRaw('LTRIM(RTRIM([Código DIAN])) = ?', [
                    trim((string) $departamento->País)
                ])
                ->value('País')
            : null;

        $referencia->ciudad_nombre = $municipioCodigo !== ''
            ? $db->table(DB::raw(
                'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Municipio'
            ))
                ->whereRaw('TRY_CONVERT(INT, [Código DIAN]) = ?', [
                    (int) $municipioCodigo
                ])
                ->value('Municipio')
            : null;

$referencia->pais_nombre = !empty($referencia->IdDepartamento)
    ? $db->table(DB::raw(
        'INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Departamento'
    ))
        ->whereRaw('TRY_CONVERT(INT, [Código DIAN]) = ?', [
            (int) $referencia->IdDepartamento
        ])
        ->value('País')
    : null;
        return $referencia;
    });
}
}

// Mostrar las direcciones con los nombres de los catálogos.
$detalle['direcciones'] = $detalle['direcciones']->map(
    function ($direccion) use ($db) {
        $paisCodigo = trim((string) ($direccion->Id_cod_pais ?? ''));
        $departamentoCodigo = trim((string) ($direccion->Id_cod_dpto ?? ''));
        $municipioCodigo = trim((string) ($direccion->Id_cod_mpio ?? ''));

        $direccion->pais_nombre = $paisCodigo !== ''
            ? $db->table(DB::raw('INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_País'))
                ->whereRaw('TRY_CONVERT(INT, [Código DIAN]) = ?', [(int) $paisCodigo])
                ->value('País')
            : null;

        $direccion->departamento_nombre = $departamentoCodigo !== ''
            ? $db->table(DB::raw('INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Departamento'))
                ->whereRaw('TRY_CONVERT(INT, [Código DIAN]) = ?', [(int) $departamentoCodigo])
                ->value('Departamento')
            : null;

        $direccion->municipio_nombre = $municipioCodigo !== ''
            ? $db->table(DB::raw('INTEGRACION_MAX_PSL.dbo.DS_EF_TBL_Municipio'))
                ->whereRaw('TRY_CONVERT(INT, [Código DIAN]) = ?', [(int) $municipioCodigo])
                ->value('Municipio')
            : null;

        return $direccion;
    }
);


        $detalle['estados'] = $db->table('TER_estado_historico as ie')
            ->leftJoin(
                'TER_estado as es',
                'es.IdEstado',
                '=',
                'ie.IdEstado'
            )
            ->where('ie.IdFrm', $id)
            ->select([
                'ie.IdInt_estado',
                'ie.IdFrm',
                'ie.IdEstado',
                'ie.fecha_estado',
                'ie.observaciones',
                'es.descripcion as estado',
            ])
            ->orderBy('ie.fecha_estado')
            ->get();


            // Disponibilidad real de los documentos adjuntos.
$camposAdjuntos = [
    'carta_instrucciones',
    'pagare',
    'certificado_existencia',
    'copia_rut',
    'estados_financieros',
    'cedula_rl',
    'certificacion_bancaria',
    'certificaciones',
];

$detalle['adjuntos'] = $detalle['adjuntos']->map(
    function ($adjunto) use ($camposAdjuntos) {
        foreach ($camposAdjuntos as $campo) {
            $ruta = trim((string) ($adjunto->{$campo} ?? ''));

            $rutaPermitida =
                str_starts_with($ruta, 'tercero/adjunto/') ||
                str_starts_with($ruta, 'tercero/adjuntos/');

            $adjunto->{$campo . '_disponible'} =
                $ruta !== '' &&
                $rutaPermitida &&
                Storage::disk('public')->exists($ruta);
        }

        return $adjunto;
    }
);
        return response()->json($detalle);
    }



    /**
     * Adapta los valores vacíos a lo que admite cada columna (NULL o no),
     * para que un campo borrado en pantalla no rompa el UPDATE.
     */
    private function ajustarCambiosATabla($db, string $tabla, array $cambios): array
    {
        static $columnas = [];

        if (!isset($columnas[$tabla])) {
            $columnas[$tabla] = [];

            $filas = $db->table('INFORMATION_SCHEMA.COLUMNS')
                ->where('TABLE_NAME', $tabla)
                ->get(['COLUMN_NAME', 'DATA_TYPE', 'IS_NULLABLE']);

            foreach ($filas as $fila) {
                $columnas[$tabla][strtolower($fila->COLUMN_NAME)] = [
                    strtolower((string) $fila->DATA_TYPE),
                    strtoupper((string) $fila->IS_NULLABLE) === 'YES',
                ];
            }
        }

        $numericos = ['int', 'bigint', 'smallint', 'tinyint', 'decimal', 'numeric', 'float', 'real', 'money', 'smallmoney', 'bit'];
        $fechas = ['date', 'datetime', 'datetime2', 'smalldatetime'];

        foreach ($cambios as $campo => $valor) {
            $info = $columnas[$tabla][strtolower((string) $campo)] ?? null;

            if (!$info) {
                continue;
            }

            [$tipo, $admiteNull] = $info;

            $vacio = $valor === null
                || (is_string($valor) && trim($valor) === '');

            if (!$vacio) {
                continue;
            }

            if (in_array($tipo, $numericos, true) || in_array($tipo, $fechas, true)) {
                $cambios[$campo] = $admiteNull
                    ? null
                    : (in_array($tipo, $fechas, true) ? '1900-01-01' : 0);
            } else {
                $cambios[$campo] = $admiteNull ? $valor : '';
            }
        }

        return $cambios;
    }

public function actualizarFormulario(Request $request, $id)
{
    abort_unless(
        Gate::allows('formularios-terceros.editar'),
        403,
        'No tienes permisos para editar formularios.'
    );

    $db = DB::connection('senco360');

    $formulario = $db->table('TER_frm_tercero')
        ->where('IdFrm', $id)
        ->first();

    abort_unless($formulario, 404, 'No se encontró el formulario.');

    abort_if(
        (int) ($formulario->acepta_autorizacion ?? 0) !== 1,
        403,
        'Solo se pueden editar formularios ingresados.'
    );

    abort_unless(
        $this->estadoActual($db, (int) $id) === self::ESTADO_INGRESADO,
        403,
        'Solo se pueden editar formularios en estado Ingresado.'
    );

    $entrada = $request->all();

    $general = $entrada['general'] ?? $entrada;

    $camposGenerales = [
        'Fecha_frm',
        'IdTipo_frm',
        'IdTipo_tercero',
        'tipo_persona',
        'vendedor',
        'tipo_doc',
        'numero_doc',
        'razon_social',
        'tiene_establ_comercio',
        'nombre_establecimiento',
        'direccion',
        'IdCiudad',
        'IdDepartamento',
        'telefono',
        'celular1',
        'celular2',
        'email',
        'email_facturacion',
        'actividad_economica',
        'IdCodigo_ciiu',
        'rl_nombre',
        'rl_tipo_doc',
        'rl_numero_doc',
        'rl_email',
        'tesoreria_nombre',
        'tesoreria_telefono',
        'tesoreria_email',
    ];

    $tablas = [
        'direcciones' => [
            'tabla' => 'TER_direccion_envio',
            'pk' => 'IdDireccion',
            'campos' => [
                'direccion_envio',
                'Id_cod_pais',
                'Id_cod_dpto',
                'Id_cod_mpio',
                'barrio',
                'observaciones',
            ],
        ],
        'socios' => [
            'tabla' => 'TER_socio',
            'pk' => 'IdSocio',
            'campos' => [
                'nombres',
                'apellidos',
                'tipo_doc',
                'numero_doc',
                'porcentaje_participacion',
            ],
        ],
        'referencias_comerciales' => [
            'tabla' => 'TER_referencia_comercial',
            'pk' => 'IdReferencia_comercial',
            'campos' => [
                'tipo_referencia',
                'nombre_razon_social',
                'tipo_doc',
                'numero_doc',
                'direccion',
                'IdCiudad',
                'IdDepartamento',
                'telefono',
                'celular',
                'email',
            ],
        ],
        'referencias_bancarias' => [
            'tabla' => 'TER_referencia_bancaria',
            'pk' => 'IdReferencia_bancaria',
            'campos' => [
                'nombre_banco',
                'sucursal',
                'clase_cuenta',
                'numero_cuenta',
            ],
        ],
    ];

    $db->beginTransaction();

    try {
        /*
         * INFORMACIÓN GENERAL
         * Nunca se modifican la firma ni la aceptación del formulario.
         */
        $actualizarGeneral = array_intersect_key(
            $general,
            array_flip($camposGenerales)
        );

        if (!empty($actualizarGeneral)) {
            $actualizarGeneral = $this->ajustarCambiosATabla(
                $db,
                'TER_frm_tercero',
                $actualizarGeneral
            );

            $db->table('TER_frm_tercero')
                ->where('IdFrm', $id)
                ->update($actualizarGeneral);
        }

        /*
         * DIRECCIONES, SOCIOS Y REFERENCIAS
         * Solo actualiza registros existentes pertenecientes al formulario.
         * No elimina ni inserta filas.
         */
        foreach ($tablas as $seccion => $config) {
            if (!array_key_exists($seccion, $entrada)) {
                continue;
            }

            $filas = $entrada[$seccion];

            if (!is_array($filas)) {
                throw new \InvalidArgumentException(
                    "La sección {$seccion} tiene un formato inválido."
                );
            }

            foreach ($filas as $fila) {
                if (!is_array($fila)) {
                    continue;
                }

                $pk = $config['pk'];
                $idFila = $fila[$pk] ?? null;

                if (!$idFila) {
                    throw new \InvalidArgumentException(
                        "Falta el identificador de un registro de {$seccion}."
                    );
                }

                $cambios = array_intersect_key(
                    $fila,
                    array_flip($config['campos'])
                );

                if (empty($cambios)) {
                    continue;
                }

                $cambios = $this->ajustarCambiosATabla(
                    $db,
                    $config['tabla'],
                    $cambios
                );

                $actualizado = $db->table($config['tabla'])
                    ->where($pk, $idFila)
                    ->where('IdFrm', $id)
                    ->update($cambios);

                $existe = $db->table($config['tabla'])
                    ->where($pk, $idFila)
                    ->where('IdFrm', $id)
                    ->exists();

                if (!$existe) {
                    throw new \RuntimeException(
                        "No se encontró un registro válido de {$seccion}."
                    );
                }
            }
        }

        /*
         * INFORMACIÓN FINANCIERA
         * Actualiza el registro existente; no crea otro.
         */
        if (array_key_exists('financiera', $entrada)) {
            $financiera = $entrada['financiera'];

            if (is_array($financiera) && array_is_list($financiera)) {
                $financiera = $financiera[0] ?? [];
            }

            if (!is_array($financiera)) {
                throw new \InvalidArgumentException(
                    'La información financiera tiene un formato inválido.'
                );
            }

            $campos = [
                'fecha_corte',
                'activos',
                'pasivos',
                'ingresos',
                'egresos',
                'responsable_iva',
                'declara_renta',
                'gran_contribuyente',
                'autoretenedor',
            ];

            $cambios = array_intersect_key(
                $financiera,
                array_flip($campos)
            );

            if ($cambios) {
                $cambios = $this->ajustarCambiosATabla(
                    $db,
                    'TER_informacion_financiera',
                    $cambios
                );

                $db->table('TER_informacion_financiera')
                    ->where('IdFrm', $id)
                    ->update($cambios);
            }
        }

        /*
         * INFORMACIÓN SGC
         * Actualiza la fila existente y conserva los adjuntos y la firma.
         */
        if (array_key_exists('sgc', $entrada)) {
            $sgc = $entrada['sgc'];

            if (is_array($sgc) && array_is_list($sgc)) {
                $sgc = $sgc[0] ?? [];
            }

            if (!is_array($sgc)) {
                throw new \InvalidArgumentException(
                    'La información SGC tiene un formato inválido.'
                );
            }

            $campos = [
                'r_conoce_LA_FT',
                'r_obligado_reportar',
                'r_tiene_oficial_cumplimiento',
                'r_nombre_oficial_cumplimiento',
                'r_celular_oficial_cumplimiento',
                'r_email_oficial_cumplimiento',
                'a_maneja_activos_viirtuales',
                'a_cuales_activos_virtuales',
                'a_tranfiere_activos_virtuales',
                'o_tiene_oea',
                'o_cual_oea',
                'p_persona_publicamente_expuesta',
                'p_cargo_desempeña',
                'p_fecha_inicio_cargo',
                'p_finalizacion_cargo',
                'p_familiar_pep',
            ];

            $cambios = array_intersect_key(
                $sgc,
                array_flip($campos)
            );

            if ($cambios) {
                $cambios = $this->ajustarCambiosATabla(
                    $db,
                    'TER_informacion_sgc',
                    $cambios
                );

                $db->table('TER_informacion_sgc')
                    ->where('IdFrm', $id)
                    ->update($cambios);
            }
        }

        $db->commit();

        return response()->json([
            'mensaje' => 'Los cambios se guardaron correctamente.',
            'id' => (int) $id,
        ]);
    } catch (\Throwable $e) {
        $db->rollBack();

        report($e);

        Log::error('Error al actualizar el formulario de tercero.', [
            'id_frm' => $id,
            'usuario' => auth()->id(),
            'mensaje' => $e->getMessage(),
        ]);

        return response()->json([
            'message' => 'No fue posible guardar los cambios. No se conservaron actualizaciones parciales.'
                . (config('app.debug') ? ' Detalle: ' . $e->getMessage() : ''),
        ], 500);
    }
}



    private function obtenerRutaAdjuntoFormulario($id, $campo): string
{
    $camposPermitidos = [
        'carta_instrucciones',
        'pagare',
        'certificado_existencia',
        'copia_rut',
        'estados_financieros',
        'cedula_rl',
        'certificacion_bancaria',
        'certificaciones',
    ];

    abort_unless(
        in_array($campo, $camposPermitidos, true),
        404
    );

    $adjunto = DB::connection('senco360')
        ->table('TER_adjunto')
        ->where('IdFrm', $id)
        ->first();

    abort_unless($adjunto, 404);

    $ruta = trim((string) ($adjunto->{$campo} ?? ''));

    $rutaPermitida =
        str_starts_with($ruta, 'tercero/adjunto/') ||
        str_starts_with($ruta, 'tercero/adjuntos/');

    abort_unless(
        $ruta !== '' &&
        $rutaPermitida &&
        Storage::disk('public')->exists($ruta),
        404,
        'El archivo no está disponible.'
    );

    return $ruta;
}


public function verAdjuntoFormulario($id, $campo)
{
    $ruta = $this->obtenerRutaAdjuntoFormulario($id, $campo);

    /** @var \Illuminate\Filesystem\FilesystemAdapter $disco */
    $disco = Storage::disk('public');

    return response()->file(
        $disco->path($ruta)
    );
}

public function descargarAdjuntoFormulario($id, $campo)
{
    $ruta = $this->obtenerRutaAdjuntoFormulario($id, $campo);

    /** @var \Illuminate\Filesystem\FilesystemAdapter $disco */
    $disco = Storage::disk('public');

    return $disco->download(
        $ruta,
        basename($ruta)
    );
}



    /*
    |--------------------------------------------------------------------------
    | GUARDAR TERCERO
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
{
    Log::info('RegistroTercero: solicitud recibida', [
        'campos_recibidos' => count($request->all(), COUNT_RECURSIVE),
        'tiene_firma' => $request->filled('firma'),
        'acepta_autorizacion' => $request->input('acepta_autorizacion'),
        'archivos' => array_keys($request->allFiles()),
    ]);

    $request->merge([
        'acepta_autorizacion' => $request->boolean('acepta_autorizacion'),
    ]);





    /*
    |--------------------------------------------------------------------------
    | VALIDACIÓN
    |--------------------------------------------------------------------------
    */

    $esExtranjero = $this->paisEsExtranjero(
        $request->input('id_pais')
    );



    $validated = $request->validate([

            'id_frm' => ['nullable', 'integer', 'min:1'],

            /*
            |--------------------------------------------------------------------------
            | SELECCIÓN INICIAL
            |--------------------------------------------------------------------------
            */

            'id_tipo_tercero' => [
                'required',
                'integer',
            ],

            'id_tipo_frm' => [
                'required',
                'integer',
            ],

            'tipo_persona' => [
                'required',
                'string',
                'max:20',
            ],

            /*
            |--------------------------------------------------------------------------
            | INFORMACIÓN GENERAL
            |--------------------------------------------------------------------------
            */

            'fecha_frm' => [
                'required',
                'date',
            ],

            'vendedor' => [
    'required',
    'string',
    'max:100',
],


            'tiene_establecimiento' => [
                'nullable',
                'boolean',
            ],

            'razon_social' => [
                'required',
                'string',
                'max:100',
            ],

            'tipo_doc' => [
                'required',
                'string',
                'max:15',
            ],

            'numero_doc' => [
                'required',
                'string',
                'max:30',
            ],

            'nombre_establecimiento' => [
                'nullable',
                'string',
                'max:50',
            ],

            'direccion' => [
                'required',
                'string',
                'max:200',
            ],

            'id_pais' => [
    'nullable',
    'regex:/^\d+$/',
],

'id_ciudad' => [
    $esExtranjero ? 'nullable' : 'required',
    'regex:/^\d+$/',
],

'id_departamento' => [
    $esExtranjero ? 'nullable' : 'required',
    'regex:/^\d+$/',
],

'telefono' => [
    'nullable',
    'integer',
],

'celular1' => [
    'nullable',
    'integer',
],

'celular2' => [
    'nullable',
    'integer',
],

            'email' => [
                'nullable',
                'email',
                'max:50',
            ],

            'email_facturacion' => [
                'nullable',
                'email',
                'max:50',
            ],



            'codigo_ciiu' => [
    'required',
    'string',
    'max:10',
],


            /*
            |--------------------------------------------------------------------------
            | REPRESENTANTE LEGAL
            |--------------------------------------------------------------------------
            */

            'rl_nombre' => [
                'nullable',
                'string',
                'max:50',
            ],

            'rl_tipo_doc' => [
                'nullable',
                'string',
                'max:15',
            ],

            'rl_numero_doc' => [
                'nullable',
                'string',
                'max:30',
            ],

            'rl_email' => [
                'nullable',
                'email',
                'max:50',
            ],

            /*
            |--------------------------------------------------------------------------
            | TESORERÍA
            |--------------------------------------------------------------------------
            */

            'tesoreria_nombre' => [
                'nullable',
                'string',
                'max:50',
            ],

           'tesoreria_telefono' => [
    'nullable',
    'integer',

            ],

            'tesoreria_email' => [
                'nullable',
                'email',
                'max:50',
            ],

            /*
            |--------------------------------------------------------------------------
            | FINANCIERA
            |--------------------------------------------------------------------------
            */

            'fecha_corte' => [
                'nullable',
                'date',
            ],

            'activos' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'pasivos' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'ingresos' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'egresos' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'responsable_iva' => [
                'nullable',
                'boolean',
            ],

            'declara_renta' => [
                'nullable',
                'boolean',
            ],

            'gran_contribuyente' => [
                'nullable',
                'boolean',
            ],

            'autoretenedor' => [
                'nullable',
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | SGC
            |--------------------------------------------------------------------------
            */

            'r_conoce_LA_FT' => [
                'nullable',
                'boolean',
            ],

            'r_obligado_reportar' => [
                'nullable',
                'boolean',
            ],

            'r_tiene_oficial_cumplimiento' => [
                'nullable',
                'boolean',
            ],

            'r_nombre_oficial_cumplimiento' => [
                'nullable',
                'string',
                'max:50',
            ],

            'r_celular_oficial_cumplimiento' => [
                'nullable',
                'integer',
            ],

            'r_email_oficial_cumplimiento' => [
                'nullable',
                'email',
                'max:50',
            ],

            'a_maneja_activos_viirtuales' => [
                'nullable',
                'boolean',
            ],

            'a_cuales_activos_virtuales' => [
                'nullable',
                'string',
                'max:100',
            ],

            'a_tranfiere_activos_virtuales' => [
                'nullable',
                'boolean',
            ],

            'o_tiene_oea' => [
                'nullable',
                'boolean',
            ],

            'o_cual_oea' => [
                'nullable',
                'string',
                'max:100',
            ],

            'p_persona_publicamente_expuesta' => [
                'nullable',
                'boolean',
            ],

            'p_cargo_desempeña' => [
                'nullable',
                'string',
                'max:50',
            ],

            'p_fecha_inicio_cargo' => [
                'nullable',
                'date',
            ],

            'p_finalizacion_cargo' => [
                'nullable',
                'date',
            ],

            'p_familiar_pep' => [
                'nullable',
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | DIRECCIONES DE ENVÍO
            |--------------------------------------------------------------------------
            */

          'direcciones_envio' => [
    'nullable',
    'array',
],

'direcciones_envio.*.direccion' => [
    'nullable',
    'string',
    'max:500',
],

'direcciones_envio.*.pais' => [
    'nullable',
],

'direcciones_envio.*.id_pais' => [
    'nullable',
    'regex:/^\d+$/',
],

'direcciones_envio.*.barrio' => [
    'nullable',
    'string',
    'max:10',
],

'direcciones_envio.*.id_departamento' => [
    'nullable',
    'regex:/^\d+$/',
],

'direcciones_envio.*.id_municipio' => [
    'nullable',
    'regex:/^\d+$/',
],

'direcciones_envio.*.observaciones' => [
    'nullable',
    'string',
    'max:100',
],
            /*
            |--------------------------------------------------------------------------
            | SOCIOS
            |--------------------------------------------------------------------------
            */

            'socios' => [
                'nullable',
                'array',
            ],

            'socios.*.nombres' => [
                'nullable',
                'string',
                'max:30',
            ],

            'socios.*.apellidos' => [
                'nullable',
                'string',
                'max:30',
            ],


            'socios.*.tipo_identificacion' => [
                'nullable',
                'string',
                'max:10',
            ],

            'socios.*.numero_identificacion' => [
                'nullable',
                'string',
                'max:30',
            ],

          'socios.*.porcentaje_participacion' => [
    'nullable',
    'numeric',
    'between:0,100',
    'regex:/^\d{1,3}(\.\d{1,2})?$/',
],
            /*
            |--------------------------------------------------------------------------
            | REFERENCIAS BANCARIAS
            |--------------------------------------------------------------------------
            */

            'referencias_bancarias' => [
    'nullable',
    'array',
],

'referencias_bancarias.*.nombre_banco' => [
    'nullable',
    'string',
    'max:100',
],

'referencias_bancarias.*.sucursal_banco' => [
    'nullable',
    'string',
    'max:100',
],

'referencias_bancarias.*.clase_cuenta' => [
    'nullable',
    'string',
    'max:40',
],

'referencias_bancarias.*.numero_cuenta' => [
    'nullable',
    'regex:/^\d{1,18}$/',
],

/*
|--------------------------------------------------------------------------
| PROVEEDORES
|--------------------------------------------------------------------------
*/

'proveedores' => [
    'nullable',
    'array',
],

'proveedores.*.nombre_razon_social' => [
    'nullable',
    'string',
    'max:100',
],

'proveedores.*.tipo_identificacion' => [
    'nullable',
    'string',
    'max:15',
],

'proveedores.*.numero_identificacion' => [
    'nullable',
    'string',
    'max:30',
],

'proveedores.*.direccion' => [
    'nullable',
    'string',
    'max:200',
],

'proveedores.*.id_pais' => [
    'nullable',
    'regex:/^\d+$/',
],

'proveedores.*.id_departamento' => [
    'nullable',
    'regex:/^\d+$/',
],

'proveedores.*.id_ciudad' => [
    'nullable',
    'regex:/^\d+$/',
],
'proveedores.*.telefono' => [
    'nullable',
    'integer',
],

'proveedores.*.celular' => [
    'nullable',
    'integer',
],

'proveedores.*.email' => [
    'nullable',
    'email',
    'max:50',
],

/*
|--------------------------------------------------------------------------
| CLIENTES
|--------------------------------------------------------------------------
*/

'clientes' => [
    'nullable',
    'array',
],

'clientes.*.nombre_razon_social' => [
    'nullable',
    'string',
    'max:100',
],

'clientes.*.tipo_identificacion' => [
    'nullable',
    'string',
    'max:15',
],

'clientes.*.numero_identificacion' => [
    'nullable',
    'string',
    'max:30',
],

'clientes.*.direccion' => [
    'nullable',
    'string',
    'max:200',
],

'clientes.*.id_pais' => [
    'nullable',
    'regex:/^\d+$/',
],

'clientes.*.id_departamento' => [
    'nullable',
    'regex:/^\d+$/',
],

'clientes.*.id_ciudad' => [
    'nullable',
    'regex:/^\d+$/',
],

'clientes.*.telefono' => [
    'nullable',
    'integer',
],

'clientes.*.celular' => [
    'nullable',
    'integer',
],

'clientes.*.email' => [
    'nullable',
    'email',
    'max:50',
],
            /*
            |--------------------------------------------------------------------------
            | AUTORIZACIÓN
            |--------------------------------------------------------------------------
            */

          'firma' => [
    'required',
    'string',
],

            'acepta_autorizacion' => [
                'required',
                'boolean',
                'accepted',
            ],


            /*
|--------------------------------------------------------------------------
| ADJUNTOS
|--------------------------------------------------------------------------
*/

'carta_instrucciones' => [
    'nullable',
    'file',
    'mimes:pdf,jpg,jpeg,png',
    'max:10240',
],

'pagare' => [
    'nullable',
    'file',
    'mimes:pdf,jpg,jpeg,png',
    'max:10240',
],

'certificado_existencia' => [
    'nullable',
    'file',
    'mimes:pdf,jpg,jpeg,png',
    'max:10240',
],

'copia_rut' => [
    'nullable',
    'file',
    'mimes:pdf,jpg,jpeg,png',
    'max:10240',
],

'estados_financieros' => [
    'nullable',
    'file',
    'mimes:pdf,jpg,jpeg,png',
    'max:10240',
],

'cedula_rl' => [
    'nullable',
    'file',
    'mimes:pdf,jpg,jpeg,png',
    'max:10240',
],

'certificacion_bancaria' => [
    'nullable',
    'file',
    'mimes:pdf,jpg,jpeg,png',
    'max:10240',
],

'certificaciones' => [
    'nullable',
    'file',
    'mimes:pdf,jpg,jpeg,png',
    'max:10240',
],
        ], [], [
            'id_tipo_tercero' => 'tipo de tercero',
            'id_tipo_frm' => 'tipo de formulario',
            'tipo_persona' => 'tipo de persona',
            'razon_social' => 'razón social',
            'tipo_doc' => 'tipo de documento',
            'numero_doc' => 'número de documento',
            'direccion' => 'dirección',
            'id_ciudad' => 'ciudad',
            'id_departamento' => 'departamento',
            'codigo_ciiu' => 'código CIIU',
            'firma' => 'firma',
            'acepta_autorizacion' => 'autorización de datos',
            'direcciones_envio.*.direccion' => 'dirección de envío',
            'direcciones_envio.*.barrio' => 'barrio (máx. 10 caracteres)',
            'direcciones_envio.*.observaciones' => 'observaciones de la dirección de envío',
            'id_pais' => 'país',
            'socios.*.nombres' => 'nombres del socio',
            'socios.*.apellidos' => 'apellidos del socio',
            'socios.*.porcentaje_participacion' => 'porcentaje de participación',
            'referencias_bancarias.*.numero_cuenta' => 'número de cuenta',
            'referencias_bancarias.*.nombre_banco' => 'nombre del banco',
        ]);

        $db = DB::connection('senco360');

$ciiuSeleccionado = $db
    ->table('TER_codigo_ciiu')
    ->select(
        'IdCodigo_ciiu',
        DB::raw('RTRIM(codigo_ciiu) AS codigo_ciiu'),
        DB::raw('RTRIM(actividad_economica) AS actividad_economica')
    )
    ->whereRaw(
        'RTRIM(codigo_ciiu) = ?',
        [trim($validated['codigo_ciiu'])]
    )
    ->first();

if (!$ciiuSeleccionado) {
    throw ValidationException::withMessages([
        'codigo_ciiu' => 'El código CIIU seleccionado no es válido.',
    ]);
}


        /*
        |--------------------------------------------------------------------------
        | VALIDAR CATÁLOGOS
        |--------------------------------------------------------------------------
        */

        $tipoTerceroExiste = $db
            ->table('TER_tipo_tercero')
            ->where(
                'IdTipo_tercero',
                $validated['id_tipo_tercero']
            )
            ->exists();

        $tipoFormularioExiste = $db
            ->table('TER_tipo_frm')
            ->where(
                'IdTipo_frm',
                $validated['id_tipo_frm']
            )
            ->exists();

        if (!$tipoTerceroExiste || !$tipoFormularioExiste) {
            return back()
                ->withInput()
                ->withErrors([
                    'general' =>
                        'El tipo de tercero o tipo de formulario seleccionado no es válido.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | CONVERTIR UBICACIONES MAX / 360 A INT
        |--------------------------------------------------------------------------
        */

        // País diferente a Colombia: no se diligencia departamento ni ciudad.
        $idCiudad = $esExtranjero
    ? null
    : $this->codigoEntero($validated['id_ciudad'] ?? null);

$idDepartamento = $esExtranjero
    ? null
    : $this->codigoEntero($validated['id_departamento'] ?? null);
        if (!$esExtranjero && ($idCiudad === null || $idDepartamento === null)) {
            return back()
                ->withInput()
                ->withErrors([
                    'general' =>
                        'La ciudad o el departamento seleccionado no es válido.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSACCIÓN
        |--------------------------------------------------------------------------
        */
/*
|--------------------------------------------------------------------------
| GUARDAR FIRMA
|--------------------------------------------------------------------------
*/

$firmaBase64 = $validated['firma'];

if (
    !preg_match(
        '/^data:image\/(png|jpeg|jpg);base64,/',
        $firmaBase64
    )
) {
    return back()
        ->withInput()
        ->withErrors([
            'firma' => 'La firma no tiene un formato válido.',
        ]);
}

$firmaBase64 = preg_replace(
    '/^data:image\/(png|jpeg|jpg);base64,/',
    '',
    $firmaBase64
);

$firmaBase64 = str_replace(' ', '+', $firmaBase64);

$contenidoFirma = base64_decode(
    $firmaBase64,
    true
);

if ($contenidoFirma === false) {
    return back()
        ->withInput()
        ->withErrors([
            'firma' => 'No fue posible procesar la firma.',
        ]);
}

$nombreFirma =
    'tercero/firma/firma_' .
    date('YmdHis') .
    '_' .
    uniqid() .
    '.png';

Storage::disk('public')->put(
    $nombreFirma,
    $contenidoFirma
);
        $rutasAdjuntos = [];

        try {

    $idFrm = $db->transaction(function () use (
    $db,
    $validated,
    $idCiudad,
    $idDepartamento,
    $nombreFirma,
    $request,
    &$rutasAdjuntos
) {


$ciiuSeleccionado = $db
    ->table('TER_codigo_ciiu')
    ->where(
        'codigo_ciiu',
        trim($validated['codigo_ciiu'])
    )
    ->first();

if (!$ciiuSeleccionado) {
    throw ValidationException::withMessages([
        'codigo_ciiu' => 'El código CIIU seleccionado no es válido.',
    ]);
}
                /*
                |--------------------------------------------------------------------------
                | 1. TER_frm_tercero
                |--------------------------------------------------------------------------
                */

                $datosTercero = [

                            'Fecha_frm' =>
                                $validated['fecha_frm'],

                            'IdTipo_frm' =>
                                (int) $validated['id_tipo_frm'],

                            'vendedor' =>
                                 isset($validated['vendedor'])
                                 ? trim($validated['vendedor'])
                                 : null,

                            'IdTipo_tercero' =>
                                (int) $validated['id_tipo_tercero'],

                            'tipo_persona' =>
                                trim($validated['tipo_persona']),

                            'tiene_establ_comercio' =>
                                ($validated['tiene_establecimiento'] ?? false)
                                    ? 'SI'
                                    : 'NO',

                            'razon_social' =>
                                trim($validated['razon_social']),

                            'tipo_doc' =>
                                trim($validated['tipo_doc']),

                            'numero_doc' =>
                                trim($validated['numero_doc']),

                            'nombre_establecimiento' =>
                                trim(
                                    ($validated['tiene_establecimiento'] ?? false)
                                        ? ($validated['nombre_establecimiento'] ?? '')
                                        : ''
                                ),

                            'direccion' =>
                                trim($validated['direccion']),

                            'IdCiudad' =>
                                $idCiudad,

                            'IdDepartamento' =>
                                $idDepartamento,

                            'telefono' =>
                                $this->entero(
                                    $validated['telefono'] ?? null
                                ),

                            'celular1' =>
                                $this->entero(
                                    $validated['celular1'] ?? null
                                ),

                            'celular2' =>
                                $this->entero(
                                    $validated['celular2'] ?? null
                                ),

                            'email' =>
                                trim($validated['email'] ?? ''),

                            'email_facturacion' =>
    trim(
        $validated['email_facturacion'] ?? ''
    ),

'actividad_economica' =>
    mb_substr(
        trim($ciiuSeleccionado->actividad_economica),
        0,
        500
    ),

'IdCodigo_ciiu' =>
    (int) $ciiuSeleccionado->IdCodigo_ciiu,

'rl_nombre' =>
    trim($validated['rl_nombre'] ?? ''),

                            'rl_tipo_doc' =>
                                trim($validated['rl_tipo_doc'] ?? ''),

                            'rl_numero_doc' =>
                                trim(
                                    $validated['rl_numero_doc'] ?? ''
                                ),

                            'rl_email' =>
                                trim($validated['rl_email'] ?? ''),

                            'tesoreria_nombre' =>
                                trim(
                                    $validated['tesoreria_nombre'] ?? ''
                                ),

                            'tesoreria_telefono' =>
                                $this->entero(
                                    $validated['tesoreria_telefono'] ?? null
                                ),

                            'tesoreria_email' =>
                                trim(
                                    $validated['tesoreria_email'] ?? ''
                                ),

                           'rl_firma_url' =>
                            $nombreFirma,

                            'acepta_autorizacion' =>
                                (bool) $validated['acepta_autorizacion'],
                ];

                $idFrmExistente = (int) ($validated['id_frm'] ?? 0);
                if (
                    $idFrmExistente > 0 &&
                    $db->table('TER_frm_tercero')->where('IdFrm', $idFrmExistente)->exists()
                ) {
                    $idFrm = $idFrmExistente;
                    $db->table('TER_frm_tercero')
                        ->where('IdFrm', $idFrm)
                        ->update($datosTercero);
                } else {
                    $idFrm = $db->table('TER_frm_tercero')
                        ->insertGetId($datosTercero, 'IdFrm');
                }

                // El envío final reemplaza los hijos del mismo IdFrm en vez de duplicarlos.
                foreach ([

                    'TER_informacion_financiera',
                    'TER_informacion_sgc',
                    'TER_direccion_envio',
                    'TER_socio',
                    'TER_referencia_bancaria',
                    'TER_referencia_comercial',
                ] as $tablaHija) {
                    $db->table($tablaHija)->where('IdFrm', $idFrm)->delete();
                }
/*
|--------------------------------------------------------------------------
| 2. ADJUNTOS
|--------------------------------------------------------------------------
*/

$adjuntos = [
    'carta_instrucciones',
    'pagare',
    'certificado_existencia',
    'copia_rut',
    'estados_financieros',
    'cedula_rl',
    'certificacion_bancaria',
    'certificaciones',
];

$archivosAdjuntos = [];

foreach ($adjuntos as $campo) {
    if ($request->hasFile($campo)) {
        $archivosAdjuntos[$campo] = $request->file($campo);
    }
}

if (count($archivosAdjuntos) > 0) {

    // Tipo real de cada columna de TER_adjunto (texto o binario).
    $tiposColumnas = array_change_key_case(
        $db->table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_NAME', 'TER_adjunto')
            ->pluck('DATA_TYPE', 'COLUMN_NAME')
            ->map(fn ($tipo) => strtolower($tipo))
            ->all(),
        CASE_LOWER
    );

    $columnasAdjuntos = [];

    foreach ($archivosAdjuntos as $campo => $archivo) {

        if (!$archivo instanceof UploadedFile) {
            continue;
        }

        $extension = strtolower(
            $archivo->getClientOriginalExtension()
                ?: $archivo->extension()
                ?: 'pdf'
        );

        $nombreArchivo =
            'tercero/adjunto/adjunto_' .
            date('YmdHis') .
            '_' .
            uniqid() .
            '.' .
            $extension;

        $contenidoAdjunto = file_get_contents(
            $archivo->getRealPath()
        );

        if ($contenidoAdjunto === false) {
            throw new \Exception(
                "No fue posible leer el archivo adjunto: {$campo}"
            );
        }

        $guardado = Storage::disk('public')->put(
            $nombreArchivo,
            $contenidoAdjunto
        );

        if (!$guardado) {
            throw new \Exception(
                "No fue posible guardar el archivo adjunto: {$campo}"
            );
        }

        $ruta = $nombreArchivo;

        $rutasAdjuntos[] = $ruta;

        $tipo = $tiposColumnas[strtolower($campo)] ?? 'varchar';

        $columnasAdjuntos[$campo] = [
            'ruta' => $ruta,
            'archivo' => $archivo,
            'binario' => in_array(
                $tipo,
                ['varbinary', 'binary', 'image'],
                true
            ),
        ];
    }

    $camposAdjuntos = array_keys($columnasAdjuntos);

    $existeAdjunto = $db->table('TER_adjunto')
        ->where('IdFrm', $idFrm)
        ->exists();

    if ($existeAdjunto) {
        $sqlAdjuntos = 'UPDATE TER_adjunto SET ' .
            implode(', ', array_map(
                fn ($c) => "[$c] = ?",
                $camposAdjuntos
            )) .
            ' WHERE [IdFrm] = ?';
    } else {
        $sqlAdjuntos = 'INSERT INTO TER_adjunto ([IdFrm], ' .
            implode(', ', array_map(
                fn ($c) => "[$c]",
                $camposAdjuntos
            )) .
            ') VALUES (?' .
            str_repeat(', ?', count($camposAdjuntos)) .
            ')';
    }

    $stmtAdjuntos = $db->getPdo()->prepare($sqlAdjuntos);
    $flujos = [];

   foreach ($camposAdjuntos as $i => $campo) {
    $posicion = $existeAdjunto ? $i + 1 : $i + 2;

        if ($columnasAdjuntos[$campo]['binario']) {
            $flujos[$i] = fopen(
                $columnasAdjuntos[$campo]['archivo']->getRealPath(),
                'rb'
            );

            if ($flujos[$i] === false) {
                throw new \Exception(
                    "No fue posible leer el archivo adjunto: {$campo}"
                );
            }

            if (defined('PDO::SQLSRV_ENCODING_BINARY')) {
                $stmtAdjuntos->bindParam(
                    $posicion,
                    $flujos[$i],
                    \PDO::PARAM_LOB,
                    0,
                    \PDO::SQLSRV_ENCODING_BINARY
                );
            } else {
                $stmtAdjuntos->bindParam(
                    $posicion,
                    $flujos[$i],
                    \PDO::PARAM_LOB
                );
            }
        } else {
            $stmtAdjuntos->bindValue(
                $posicion,
                $columnasAdjuntos[$campo]['ruta'],
                \PDO::PARAM_STR
            );
        }
    }

    if ($existeAdjunto) {
        $stmtAdjuntos->bindValue(
            count($camposAdjuntos) + 1,
            (int) $idFrm,
            \PDO::PARAM_INT
        );
    } else {
        $stmtAdjuntos->bindValue(
            count($camposAdjuntos) + 1,
            (int) $idFrm,
            \PDO::PARAM_INT
        );
    }

    try {
        $stmtAdjuntos->execute();
    } finally {
        foreach ($flujos as $flujo) {
            if (is_resource($flujo)) {
                fclose($flujo);
            }
        }
    }


                /*
                |--------------------------------------------------------------------------
                |3.INFORMACIÓN FINANCIERA
                |--------------------------------------------------------------------------
                */

                $datosFinancieros = [

                    'IdFrm' =>
                        $idFrm,

                    'activos' =>
                        $validated['activos'] ?? 0,

                    'pasivos' =>
                        $validated['pasivos'] ?? 0,

                    'ingresos' =>
                        $validated['ingresos'] ?? 0,

                    'egresos' =>
                        $validated['egresos'] ?? 0,

                    'responsable_iva' =>
                        (bool) ($validated['responsable_iva'] ?? false),

                    'declara_renta' =>
                        (bool) ($validated['declara_renta'] ?? false),

                    'gran_contribuyente' =>
                        (bool) ($validated['gran_contribuyente'] ?? false),

                    'autoretenedor' =>
                        (bool) ($validated['autoretenedor'] ?? false),
                ];

                // fecha_corte solo se guarda si la columna existe en la tabla.
                if (
                    !empty($validated['fecha_corte']) &&
                    Schema::connection('senco360')->hasColumn(
                        'TER_informacion_financiera',
                        'fecha_corte'
                    )
                ) {
                    $datosFinancieros['fecha_corte'] =
                        $validated['fecha_corte'];
                }

                $db->table('TER_informacion_financiera')
                    ->insert($datosFinancieros);

                /*
                |--------------------------------------------------------------------------
                | 3. INFORMACIÓN SGC
                |--------------------------------------------------------------------------
                */

                $db->table(
                    'TER_informacion_sgc'
                )->insert([

                    'IdFrm' =>
                        $idFrm,

                    'r_conoce_LA_FT' =>
                        (bool) (
                            $validated['r_conoce_LA_FT'] ?? false
                        ),

                    'r_obligado_reportar' =>
                        (bool) (
                            $validated['r_obligado_reportar'] ?? false
                        ),

                    'r_tiene_oficial_cumplimiento' =>
                        (bool) (
                            $validated[
                                'r_tiene_oficial_cumplimiento'
                            ] ?? false
                        ),

                    'r_nombre_oficial_cumplimiento' =>
                        trim(
                            $validated[
                                'r_nombre_oficial_cumplimiento'
                            ] ?? ''
                        ),

                    'r_celular_oficial_cumplimiento' =>
                        $this->entero(
                            $validated[
                                'r_celular_oficial_cumplimiento'
                            ] ?? null
                        ),

                    'r_email_oficial_cumplimiento' =>
                        trim(
                            $validated[
                                'r_email_oficial_cumplimiento'
                            ] ?? ''
                        ),

                    'a_maneja_activos_viirtuales' =>
                        (bool) (
                            $validated[
                                'a_maneja_activos_viirtuales'
                            ] ?? false
                        ),

                    'a_cuales_activos_virtuales' =>
                        trim(
                            $validated[
                                'a_cuales_activos_virtuales'
                            ] ?? ''
                        ),

                    'a_tranfiere_activos_virtuales' =>
                        (bool) (
                            $validated[
                                'a_tranfiere_activos_virtuales'
                            ] ?? false
                        ),

                    'o_tiene_oea' =>
                        (bool) (
                            $validated['o_tiene_oea'] ?? false
                        ),

                    'o_cual_oea' =>
                        trim(
                            $validated['o_cual_oea'] ?? ''
                        ),

                    'p_persona_publicamente_expuesta' =>
                        (bool) (
                            $validated[
                                'p_persona_publicamente_expuesta'
                            ] ?? false
                        ),

                    'p_cargo_desempeña' =>
                        trim(
                            $validated[
                                'p_cargo_desempeña'
                            ] ?? ''
                        ),

                    'p_fecha_inicio_cargo' =>
                        $validated[
                            'p_fecha_inicio_cargo'
                        ] ?? '1900-01-01',

                    'p_finalizacion_cargo' =>
                        $validated[
                            'p_finalizacion_cargo'
                        ] ?? '1900-01-01',

                    'p_familiar_pep' =>
                        (bool) (
                            $validated['p_familiar_pep'] ?? false
                        ),
                ]);

                /*
                |--------------------------------------------------------------------------
                | 4. DIRECCIONES DE ENVÍO
                |--------------------------------------------------------------------------
                */

                foreach (
                    ($validated['direcciones_envio'] ?? [])
                    as $direccion
                ) {

                    if (
                        empty($direccion['direccion']) &&
                        empty($direccion['id_departamento']) &&
                        empty($direccion['id_municipio'])
                    ) {
                        continue;
                    }

                    $codigoPais =
                        $this->codigoEntero(
                            $direccion['id_pais']
                                ?? $direccion['pais']
                                ?? null
                        );

                    $direccionExtranjera =
                        $this->paisEsExtranjero(
                            $direccion['id_pais']
                                ?? $direccion['pais']
                                ?? null
                        );

                    $codigoDepartamento =
                        $direccionExtranjera
                            ? null
                            : $this->codigoEntero(
                                $direccion['id_departamento']
                                    ?? null
                            );

                    $codigoMunicipio =
                        $direccionExtranjera
                            ? null
                            : $this->codigoEntero(
                                $direccion['id_municipio']
                                    ?? null
                            );

                    $db->table(
                        'TER_direccion_envio'
                    )->insert([

                        'IdFrm' =>
                            $idFrm,

                        'direccion_envio' =>
                            trim(
                                $direccion['direccion'] ?? ''
                            ),

                        'Id_cod_pais' =>
    $codigoPais,

                        'Id_cod_dpto' =>
                            $codigoDepartamento,

                        'Id_cod_mpio' =>
                            $codigoMunicipio,

                        'barrio' =>
                            $direccionExtranjera
                                ? ''
                                : trim(
                                    $direccion['barrio'] ?? ''
                                ),

                        'observaciones' =>
                            trim(
                                $direccion['observaciones'] ?? ''
                            ),
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | 5. SOCIOS
                |--------------------------------------------------------------------------
                */


foreach (
    ($validated['socios'] ?? [])
    as $socio
) {

    if (
        empty($socio['numero_identificacion']) &&
        empty($socio['nombres']) &&
        empty($socio['apellidos'])
    ) {
        continue;
    }

    $nombres = trim($socio['nombres'] ?? '');
    $apellidos = trim($socio['apellidos'] ?? '');

    $db->table('TER_socio')->insert([

        'IdFrm' =>
            $idFrm,

        'nombres' =>
            $nombres,

        'apellidos' =>
            $apellidos,

        'tipo_doc' =>
            trim(
                $socio[
                    'tipo_identificacion'
                ] ?? ''
            ),

        'numero_doc' =>
            trim(
                $socio[
                    'numero_identificacion'
                ] ?? ''
            ),

        'porcentaje_participacion' =>
            isset($socio['porcentaje_participacion'])
                && $socio['porcentaje_participacion'] !== ''
                ? round(
                    (float) $socio['porcentaje_participacion'],
                    2
                )
                : 0,
    ]);
}

                /*
                |--------------------------------------------------------------------------
                | 6. REFERENCIA BANCARIA
                |--------------------------------------------------------------------------
                */

                foreach (
                    ($validated['referencias_bancarias'] ?? [])
                    as $referencia
                ) {

                    if (
                        empty($referencia['nombre_banco']) &&
                        empty($referencia['numero_cuenta'])
                    ) {
                        continue;
                    }

                    $db->table(
                        'TER_referencia_bancaria'
                    )->insert([

                        'IdFrm' =>
                            $idFrm,

                        'nombre_banco' =>
                            trim($referencia['nombre_banco'] ?? ''),

                        'sucursal' =>
                            trim($referencia['sucursal_banco'] ?? ''),

                        'clase_cuenta' =>
                            trim($referencia['clase_cuenta'] ?? ''),

                        'numero_cuenta' =>
                            $this->entero(
                                $referencia['numero_cuenta'] ?? null
                            ),
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | 7. REFERENCIAS COMERCIALES
                |--------------------------------------------------------------------------
                |
                | Proveedores y clientes utilizan la misma tabla:
                | TER_referencia_comercial
                |
                |--------------------------------------------------------------------------
                */

                foreach (
                    ($validated['proveedores'] ?? [])
                    as $proveedor
                ) {

                    if (
                        empty(
                            $proveedor['nombre_razon_social']
                        ) &&
                        empty(
                            $proveedor['numero_identificacion']
                        )
                    ) {
                        continue;
                    }

                    $db->table(
                        'TER_referencia_comercial'
                    )->insert([

                        'IdFrm' =>
                            $idFrm,

                        'tipo_referencia' =>
                            'Proveedor',

                        'nombre_razon_social' =>
                            trim(
                                $proveedor[
                                    'nombre_razon_social'
                                ] ?? ''
                            ),

                        'tipo_doc' =>
                            trim(
                                $proveedor[
                                    'tipo_identificacion'
                                ] ?? ''
                            ),

                        'numero_doc' =>
                            trim(
                                $proveedor[
                                    'numero_identificacion'
                                ] ?? ''
                            ),

                        'direccion' =>
                            trim(
                                $proveedor['direccion'] ?? ''
                            ),

                        'IdCiudad' =>
    $this->codigoEntero(
        $proveedor[
            'id_ciudad'
        ] ?? null
    ),

'IdDepartamento' =>
    $this->codigoEntero(
        $proveedor[
            'id_departamento'
        ] ?? null
    ),

                        'telefono' =>
                            $this->entero(
                                $proveedor['telefono'] ?? null
                            ),

                        'celular' =>
                            $this->entero(
                                $proveedor['celular'] ?? null
                            ),

                        'email' =>
                            trim(
                                $proveedor['email'] ?? ''
                            ),
                    ]);
                }

                foreach (
                    ($validated['clientes'] ?? [])
                    as $cliente
                ) {

                    if (
                        empty(
                            $cliente['nombre_razon_social']
                        ) &&
                        empty(
                            $cliente['numero_identificacion']
                        )
                    ) {
                        continue;
                    }

                    $db->table(
                        'TER_referencia_comercial'
                    )->insert([

                        'IdFrm' =>
                            $idFrm,

                        'tipo_referencia' =>
                            'Cliente',

                        'nombre_razon_social' =>
                            trim(
                                $cliente[
                                    'nombre_razon_social'
                                ] ?? ''
                            ),

                        'tipo_doc' =>
                            trim(
                                $cliente[
                                    'tipo_identificacion'
                                ] ?? ''
                            ),

                        'numero_doc' =>
                            trim(
                                $cliente[
                                    'numero_identificacion'
                                ] ?? ''
                            ),

                        'direccion' =>
                            trim(
                                $cliente['direccion'] ?? ''
                            ),

                        'IdCiudad' =>
    $this->codigoEntero(
        $cliente[
            'id_ciudad'
        ] ?? null
    ),

'IdDepartamento' =>
    $this->codigoEntero(
        $cliente[
            'id_departamento'
        ] ?? null
    ),
                        'telefono' => $this->entero(
                            $cliente['telefono'] ?? null
                        ),

                        'celular' => $this->entero(
                            $cliente['celular'] ?? null
                        ),

                        'email' => trim(
                            $cliente['email'] ?? ''
                        ),
                    ]);
                }
            }

            return $idFrm;
        });



    /*
            |--------------------------------------------------------------------------
            | Exito
            |--------------------------------------------------------------------------
            */
// Estado inicial del formulario enviado (no interrumpe el envío si falla).
$this->registrarEstado(
    $db,
    (int) $idFrm,
    self::ESTADO_INGRESADO,
    'Formulario enviado por el usuario.'
);

$borradorId = $request->input('borrador_id');

if (is_string($borradorId) && $borradorId !== '') {
    $clave = $this->claveBorrador($borradorId);

    Cache::forget($clave);

    $claveIndice = $this->claveIndiceBorradores();
    $ids = Cache::get($claveIndice, []);

    if (is_array($ids)) {
        $ids = array_values(array_filter(
            $ids,
            fn ($id) => $id !== $borradorId
        ));

        Cache::put($claveIndice, $ids, now()->addMonths(3));
    }
}

session([
    'registro_tercero_pdf_id' => $idFrm,
]);

return redirect()
    ->route(
        'gestion-clientes.registro-tercero'
    )
    ->with(
        'success',
        "Tercero registrado correctamente. ID: {$idFrm}"
    );
        } catch (Throwable $e) {

            report($e);

            Storage::disk('public')->delete($nombreFirma);

            foreach ($rutasAdjuntos as $rutaAdjunto) {
                Storage::disk('public')->delete($rutaAdjunto);
            }

            return back()
                ->withInput()
                ->withErrors([
                    'general' =>
                        'No fue posible registrar el tercero. ' .
                        'La operación fue revertida.' .
                        (config('app.debug')
                            ? ' Detalle: ' . $e->getMessage()
                            : ' Intente nuevamente o contacte a soporte.'),
                ]);
        }
    }
}