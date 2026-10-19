<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <title>Registro de Tercero</title>

    <style>
        @page {
            margin: 24px 34px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #111827;
            margin: 0;
        }

        .form-page {
            width: 100%;
        }

        /* ENCABEZADO TIPO FORMATO F-AN-001 */
        .header {
            width: 100%;
            border: 1px solid #111;
            margin-bottom: 7px;
            padding: 0;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            border-right: 1px solid #111;
            vertical-align: middle;
            padding: 4px 6px;
        }

        .header-table td:last-child {
            border-right: 0;
        }

        .logo-cell {
            width: 18%;
            text-align: center;
        }

        .logo {
            width: 82px;
            height: auto;
        }

        .header-title {
            width: 57%;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            line-height: 1.25;
        }

        .header-meta {
            width: 25%;
            padding: 0 !important;
        }

        .header-meta div {
            padding: 3px 4px;
            border-bottom: 1px solid #111;
            text-align: center;
            font-size: 6.8px;
            font-weight: bold;
        }

        .header-meta div:last-child {
            border-bottom: 0;
        }

        /* CAJAS SUPERIORES */
        .top-options {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-bottom: 6px;
        }

        .top-options td {
            border: 1px solid #111;
            border-radius: 7px;
            padding: 4px 7px;
            text-align: center;
            font-size: 7px;
        }

        /* CADA SECCIÓN DEL FORMATO */
        .section {
            border: 1px solid #111;
            border-radius: 5px;
            padding: 3px;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }

        .section-title {
            background: #b5b5b5;
            border: 1px solid #8a8a8a;
            border-radius: 2px;
            padding: 4px 5px;
            margin: 0 0 4px 0;
            text-align: center;
            font-size: 8px;
            font-weight: bold;
            color: #111;
            line-height: 1.1;
        }

        .pdf-form-section {
    width: 100%;
    border: 1px solid #111;
    border-radius: 5px;
    padding: 3px;
    margin-bottom: 7px;
    page-break-inside: avoid;
    box-sizing: border-box;
}

.pdf-form-section .section-title {
    margin-bottom: 4px;
}

        table.data {
            width: 100%;
            border: 1px solid #222;
            border-collapse: collapse;
            margin: 0;
        }

        table.data th,
        table.data td {
            border: 1px solid #222;
            padding: 4px 5px;
            vertical-align: middle;
            line-height: 1.15;
        }

        table.data th {
            background: #fff;
            font-weight: normal;
        }

        .label-cell {
            font-weight: bold;
            background: #f5f5f5;
        }

        .field-box {
            border: 1px solid #999;
            border-radius: 5px;
            padding: 4px 5px;
            min-height: 11px;
        }

        .yes-no-box {
            display: inline-block;
            width: 8px;
            height: 8px;
            border: 1px solid #111;
            margin: 0 2px 0 5px;
            vertical-align: middle;
        }

        .empty {
            color: #555;
            font-style: italic;
            padding: 4px;
        }

        .page-break {
            page-break-before: always;
        }

        .declaration-box {
            border: 1px solid #222;
            border-radius: 5px;
            padding: 7px;
            margin-bottom: 7px;
            line-height: 1.35;
            text-align: justify;
            font-size: 7.4px;
        }

        .declaration-box h4 {
            margin: 0 0 4px 0;
            font-size: 8px;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .signature-table td {
            width: 50%;
            text-align: center;
            padding: 15px 10px 3px;
            border: 0;
            border-top: 1px solid #111;
        }

        .important-box {
            border: 1px solid #222;
            border-radius: 4px;
            padding: 7px;
            margin-top: 7px;
            font-size: 7.5px;
            line-height: 1.35;
        }

        .small {
            font-size: 7px;
        }

        .footer {
            margin-top: 7px;
            font-size: 6.5px;
            color: #555;
            text-align: right;
        }
    </style>
</head>

<body>

    {{-- ===================================================== --}}
    {{-- ENCABEZADO --}}
    {{-- ===================================================== --}}

    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <img
                        class="logo"
                        src="{{ public_path('images/logo-blue.png') }}"
                        alt="SENCO"
                    >
                </td>

                <td class="header-title">
                    FORMULARIO PARA EL CONOCIMIENTO DEL CLIENTE NACIONAL
                </td>

                <td class="header-meta">
                    <div>Código: F-AN-001</div>
                    <div>Versión: 003</div>
                    {{-- <div>Fecha de formulario: {{ $tercero->Fecha_frm ?? '-' }}</div> --}}

                </td>
            </tr>
        </table>
    </div>

    <table class="top-options">
        <tr>
            <td>Tipo de tercero: <strong>{{ $tercero->IdTipo_tercero ?? '-' }}</strong></td>
            <td>Tipo de persona: <strong>{{ $tercero->tipo_persona ?? '-' }}</strong></td>
            <td>Tipo de formulario: <strong>{{ $tercero->IdTipo_frm ?? '-' }}</strong></td>
            <td>Fecha: <strong>{{ $tercero->Fecha_frm ?? '-' }}</strong></td>
        </tr>
    </table>


   {{-- ===================================================== --}}
{{-- 1. INFORMACIÓN GENERAL --}}
{{-- ===================================================== --}}

<div class="pdf-form-section">

    <div class="section-title">
        1. Información general
    </div>

    <table class="data">

        <tr>
            <th>Razón social</th>
            <td>{{ $tercero->razon_social ?? '-' }}</td>

            <th>Tipo de documento</th>
            <td>{{ $tercero->tipo_doc ?? '-' }}</td>
        </tr>

        <tr>
            <th>Número de documento</th>
            <td colspan="3">
                {{ $tercero->numero_doc ?? '-' }}
            </td>
        </tr>

        <tr>
            <th>Establecimiento de comercio</th>
            <td>
                {{ $tercero->tiene_establ_comercio ?? '-' }}
            </td>

            <th>Nombre establecimiento</th>
            <td>
                {{ $tercero->nombre_establecimiento ?: '-' }}
            </td>
        </tr>

        <tr>
            <th>Dirección</th>
            <td colspan="3">
                {{ $tercero->direccion ?? '-' }}
            </td>
        </tr>

        <tr>
            <th>País</th>
            <td>
                {{ $tercero->pais_nombre ?: '-' }}
            </td>

            <th>Departamento</th>
            <td>
                {{ $departamentoPrincipal ?: '-' }}
            </td>
        </tr>

        <tr>
            <th>Ciudad</th>
            <td colspan="3">
                {{ $ciudadPrincipal ?: '-' }}
            </td>
        </tr>

        <tr>
            <th>Teléfono</th>
            <td>{{ $tercero->telefono ?? '-' }}</td>

            <th>Celular 1</th>
            <td>{{ $tercero->celular1 ?? '-' }}</td>
        </tr>

        <tr>
            <th>Celular 2</th>
            <td>{{ $tercero->celular2 ?? '-' }}</td>

            <th>Correo electrónico</th>
            <td>{{ $tercero->email ?? '-' }}</td>
        </tr>

        <tr>
            <th>Correo facturación</th>
            <td colspan="3">
                {{ $tercero->email_facturacion ?? '-' }}
            </td>
        </tr>

        <tr>
            <th>Actividad económica</th>
            <td colspan="3">
                {{ $tercero->actividad_economica ?? '-' }}
            </td>
        </tr>

        <tr>
            <th>Código CIIU</th>
            <td colspan="3">
                {{ $tercero->codigo_ciiu ?? '-' }}
            </td>
        </tr>

    </table>

</div>

    {{-- ===================================================== --}}
    {{-- REPRESENTANTE LEGAL --}}
    {{-- ===================================================== --}}

    <div class="section-title">
        Representante legal
    </div>

    <table class="data">

        <tr>
            <th>Nombre</th>
            <td>{{ $tercero->rl_nombre ?? '-' }}</td>

            <th>Tipo documento</th>
            <td>{{ $tercero->rl_tipo_doc ?? '-' }}</td>
        </tr>

        <tr>
            <th>Número documento</th>
            <td>{{ $tercero->rl_numero_doc ?? '-' }}</td>

            <th>Correo electrónico</th>
            <td>{{ $tercero->rl_email ?? '-' }}</td>
        </tr>

    </table>


    {{-- ===================================================== --}}
    {{-- TESORERÍA --}}
    {{-- ===================================================== --}}

    <div class="section-title">
        Información de tesorería
    </div>

    <table class="data">

        <tr>
            <th>Nombre</th>
            <td>{{ $tercero->tesoreria_nombre ?? '-' }}</td>

            <th>Teléfono</th>
            <td>{{ $tercero->tesoreria_telefono ?? '-' }}</td>
        </tr>

        <tr>
            <th>Correo electrónico</th>
            <td colspan="3">
                {{ $tercero->tesoreria_email ?? '-' }}
            </td>
        </tr>

    </table>


    {{-- ===================================================== --}}
    {{-- 2. DIRECCIONES DE ENVÍO --}}
    {{-- ===================================================== --}}

    <div class="section-title">
        2. Direcciones de envío
    </div>

    @if($direcciones->count())

        @foreach($direcciones as $index => $direccion)

            <table class="data">

                <tr>
                    <th colspan="4">
                        Dirección {{ $index + 1 }}
                    </th>
                </tr>

                <tr>
                    <th>Dirección</th>
                    <td colspan="3">
                        {{ $direccion->direccion ?? '-' }}
                    </td>
                </tr>

                <tr>
                    <th>País</th>
                    <td @if($direccion->es_extranjero ?? false) colspan="3" @endif>
                        {{ $direccion->pais_nombre ?: ($direccion->Id_cod_pais ?: '-') }}
                    </td>

                    @unless($direccion->es_extranjero ?? false)
                        <th>Departamento</th>
                        <td>
                            {{ $direccion->departamento_nombre ?: ($direccion->Id_cod_dpto ?: '-') }}
                        </td>
                    @endunless
                </tr>

                @unless($direccion->es_extranjero ?? false)
                <tr>
                    <th>Municipio</th>
                    <td>
                        {{ $direccion->municipio_nombre ?: ($direccion->Id_cod_mpio ?: '-') }}
                    </td>

                    <th>Barrio</th>
                    <td>
                        {{ trim($direccion->barrio ?? '') ?: '-' }}
                    </td>
                </tr>
                @endunless

                <tr>
                    <th>Observaciones</th>
                    <td colspan="3">
                        {{ $direccion->observaciones ?? '-' }}
                    </td>
                </tr>

            </table>

        @endforeach

    @else

        <div class="empty">
            No se registraron direcciones de envío.
        </div>

    @endif


    {{-- ===================================================== --}}
    {{-- 3. SOCIOS --}}
    {{-- ===================================================== --}}

    <div class="section-title">
        3. Socios
    </div>

    @if($socios->count())

        <table class="data">

            <tr>
                <th>Nombres</th>
                <th>Apellidos</th>
                <th>Tipo documento</th>
                <th>Número documento</th>
                <th>% participación</th>
            </tr>

            @foreach($socios as $socio)

                <tr>
                    <td>{{ $socio->nombres ?? '-' }}</td>
                    <td>{{ $socio->apellidos ?? '-' }}</td>
                    <td>{{ $socio->tipo_documento ?? '-' }}</td>
                    <td>{{ $socio->numero_documento ?? '-' }}</td>
                    <td>{{ $socio->porcentaje_participacion ?? '-' }}</td>
                </tr>

            @endforeach

        </table>

    @else

        <div class="empty">
            No se registraron socios.
        </div>

    @endif


    {{-- ===================================================== --}}
{{-- 4. INFORMACIÓN FINANCIERA --}}
{{-- ===================================================== --}}

<div class="pdf-form-section">

    <div class="section-title">
        4. Información financiera y tributaria
    </div>

    @if($financiera)

        <table class="data">

            <tr>
                <th>Fecha de corte</th>
                <td>{{ $financiera->fecha_corte ?? '-' }}</td>

                <th>Activos</th>
                <td>{{ $financiera->activos ?? '-' }}</td>
            </tr>

            <tr>
                <th>Pasivos</th>
                <td>{{ $financiera->pasivos ?? '-' }}</td>

                <th>Ingresos</th>
                <td>{{ $financiera->ingresos ?? '-' }}</td>
            </tr>

            <tr>
                <th>Egresos</th>
                <td>{{ $financiera->egresos ?? '-' }}</td>

                <th>Responsable de IVA</th>
                <td>{{ $financiera->responsable_iva ?? '-' }}</td>
            </tr>

            <tr>
                <th>Declara renta</th>
                <td>{{ $financiera->declara_renta ?? '-' }}</td>

                <th>Gran contribuyente</th>
                <td>{{ $financiera->gran_contribuyente ?? '-' }}</td>
            </tr>

            <tr>
                <th>Autoretenedor</th>
                <td colspan="3">
                    {{ $financiera->autoretenedor ?? '-' }}
                </td>
            </tr>

        </table>

    @else

        <div class="empty">
            No se registró información financiera.
        </div>

    @endif

</div>

    {{-- ===================================================== --}}
{{-- 5. PROVEEDORES --}}
{{-- ===================================================== --}}

<div class="section-title">
    5. Proveedores
</div>

@if($proveedores->count())

    @foreach($proveedores as $index => $proveedor)

        <table class="data">

            <tr>
                <th colspan="4">
                    Proveedor {{ $index + 1 }}
                </th>
            </tr>

            <tr>
                <th>Nombre / Razón social</th>
                <td>
                    {{ $proveedor->nombre_razon_social ?? '-' }}
                </td>

                <th>Tipo documento</th>
                <td>
                    {{ $proveedor->tipo_doc ?? '-' }}
                </td>
            </tr>

            <tr>
                <th>Número documento</th>
                <td>
                    {{ $proveedor->numero_doc ?? '-' }}
                </td>

                <th>Dirección</th>
                <td>
                    {{ $proveedor->direccion ?? '-' }}
                </td>
            </tr>

            <tr>
                <th>País</th>
                <td>
                    {{ $proveedor->pais_nombre ?? '-' }}
                </td>

                <th>Departamento</th>
                <td>
                    {{ $proveedor->departamento_nombre ?? '-' }}
                </td>
            </tr>

            <tr>
                <th>Ciudad</th>
                <td>
                    {{ $proveedor->ciudad_nombre ?? '-' }}
                </td>

                <th>Teléfono</th>
                <td>
                    {{ $proveedor->telefono ?? '-' }}
                </td>
            </tr>

            <tr>
                <th>Celular</th>
                <td>
                    {{ $proveedor->celular ?? '-' }}
                </td>

                <th>Correo electrónico</th>
                <td>
                    {{ $proveedor->email ?? '-' }}
                </td>
            </tr>

        </table>

    @endforeach

@else

    <div class="empty">
        No se registraron proveedores.
    </div>

@endif


{{-- ===================================================== --}}
{{-- 6. CLIENTES --}}
{{-- ===================================================== --}}

<div class="section-title">
    6. Clientes
</div>

@if($clientes->count())

    @foreach($clientes as $index => $cliente)

        <table class="data">

            <tr>
                <th colspan="4">
                    Cliente {{ $index + 1 }}
                </th>
            </tr>

            <tr>
                <th>Nombre / Razón social</th>
                <td>
                    {{ $cliente->nombre_razon_social ?? '-' }}
                </td>

                <th>Tipo documento</th>
                <td>
                    {{ $cliente->tipo_doc ?? '-' }}
                </td>
            </tr>

            <tr>
                <th>Número documento</th>
                <td>
                    {{ $cliente->numero_doc ?? '-' }}
                </td>

                <th>Dirección</th>
                <td>
                    {{ $cliente->direccion ?? '-' }}
                </td>
            </tr>

            <tr>
                <th>País</th>
                <td>
                    {{ $cliente->pais_nombre ?? '-' }}
                </td>

                <th>Departamento</th>
                <td>
                    {{ $cliente->departamento_nombre ?? '-' }}
                </td>
            </tr>

            <tr>
                <th>Ciudad</th>
                <td>
                    {{ $cliente->ciudad_nombre ?? '-' }}
                </td>

                <th>Teléfono</th>
                <td>
                    {{ $cliente->telefono ?? '-' }}
                </td>
            </tr>

            <tr>
                <th>Celular</th>
                <td>
                    {{ $cliente->celular ?? '-' }}
                </td>

                <th>Correo electrónico</th>
                <td>
                    {{ $cliente->email ?? '-' }}
                </td>
            </tr>

        </table>

    @endforeach

@else

    <div class="empty">
        No se registraron clientes.
    </div>

@endif


    {{-- ===================================================== --}}
    {{-- 6. REFERENCIAS BANCARIAS --}}
    {{-- ===================================================== --}}

    <div class="section-title page-break">
        6. Referencias bancarias
    </div>

    @if($referenciasBancarias->count())

        <table class="data">

            <tr>
                <th>Banco</th>
                <th>Sucursal</th>
                <th>Clase de cuenta</th>
                <th>Número de cuenta</th>
            </tr>

            @foreach($referenciasBancarias as $referencia)

                <tr>
                    <td>{{ $referencia->nombre_banco ?? '-' }}</td>
                    <td>{{ $referencia->sucursal ?? '-' }}</td>
                    <td>{{ $referencia->clase_cuenta ?? '-' }}</td>
                    <td>{{ $referencia->numero_cuenta ?? '-' }}</td>
                </tr>

            @endforeach

        </table>

    @else

        <div class="empty">
            No se registraron referencias bancarias.
        </div>

    @endif


    {{-- ===================================================== --}}
    {{-- 7. INFORMACIÓN SGC / LA-FT / OEA / PEP --}}
    {{-- ===================================================== --}}

    <div class="section-title">
        7. Información SGC / LA-FT / Activos virtuales / OEA / PEP
    </div>

    @if($sgc)

        <table class="data">

            <tr>
                <th>Conoce LA/FT</th>
                <td>{{ $sgc->r_conoce_LA_FT ?? '-' }}</td>

                <th>Obligado a reportar</th>
                <td>{{ $sgc->r_obligado_reportar ?? '-' }}</td>
            </tr>

            <tr>
                <th>Tiene oficial de cumplimiento</th>
                <td>{{ $sgc->r_tiene_oficial_cumplimiento ?? '-' }}</td>

                <th>Nombre oficial</th>
                <td>{{ $sgc->r_nombre_oficial_cumplimiento ?? '-' }}</td>
            </tr>

            <tr>
                <th>Celular oficial</th>
                <td>{{ $sgc->r_celular_oficial_cumplimiento ?? '-' }}</td>

                <th>Correo oficial</th>
                <td>{{ $sgc->r_email_oficial_cumplimiento ?? '-' }}</td>
            </tr>

            <tr>
                <th>Maneja activos virtuales</th>
                <td>{{ $sgc->a_maneja_activos_viirtuales ?? '-' }}</td>

                <th>Transfiere activos virtuales</th>
                <td>{{ $sgc->a_tranfiere_activos_virtuales ?? '-' }}</td>
            </tr>

            <tr>
                <th>Cuáles activos virtuales</th>
                <td colspan="3">
                    {{ $sgc->a_cuales_activos_virtuales ?? '-' }}
                </td>
            </tr>

            <tr>
                <th>Tiene condición OEA</th>
                <td>{{ $sgc->o_tiene_oea ?? '-' }}</td>

                <th>Cuál OEA</th>
                <td>{{ $sgc->o_cual_oea ?? '-' }}</td>
            </tr>

            <tr>
                <th>Persona públicamente expuesta</th>
                <td>{{ $sgc->p_persona_publicamente_expuesta ?? '-' }}</td>

                <th>Cargo</th>
                <td>{{ $sgc->p_cargo_desempeña ?? '-' }}</td>
            </tr>

            <tr>
                <th>Fecha inicio cargo</th>
                <td>{{ $sgc->p_fecha_inicio_cargo ?? '-' }}</td>

                <th>Finalización cargo</th>
                <td>{{ $sgc->p_finalizacion_cargo ?? '-' }}</td>
            </tr>

            <tr>
                <th>Familiar PEP</th>
                <td colspan="3">
                    {{ $sgc->p_familiar_pep ?? '-' }}
                </td>
            </tr>

        </table>

    @else

        <div class="empty">
            No se registró información SGC.
        </div>

    @endif


    {{-- ===================================================== --}}
    {{-- 8. ADJUNTOS --}}
    {{-- ===================================================== --}}

    <div class="section-title">
        8. Adjuntos y validaciones
    </div>

    @if($adjuntos)

        <table class="data">

            <tr>
                <th>Carta de instrucciones</th>
                <td>{{ $adjuntos->carta_instrucciones ? 'Adjunto' : 'No adjunto' }}</td>

                <th>Pagaré</th>
                <td>{{ $adjuntos->pagare ? 'Adjunto' : 'No adjunto' }}</td>
            </tr>

            <tr>
                <th>Certificado de existencia</th>
                <td>{{ $adjuntos->certificado_existencia ? 'Adjunto' : 'No adjunto' }}</td>

                <th>Copia RUT</th>
                <td>{{ $adjuntos->copia_rut ? 'Adjunto' : 'No adjunto' }}</td>
            </tr>

            <tr>
                <th>Estados financieros</th>
                <td>{{ $adjuntos->estados_financieros ? 'Adjunto' : 'No adjunto' }}</td>

                <th>Cédula representante legal</th>
                <td>{{ $adjuntos->cedula_rl ? 'Adjunto' : 'No adjunto' }}</td>
            </tr>

            <tr>
                <th>Certificación bancaria</th>
                <td>{{ $adjuntos->certificacion_bancaria ? 'Adjunto' : 'No adjunto' }}</td>

                <th>Certificaciones</th>
                <td>{{ $adjuntos->certificaciones ? 'Adjunto' : 'No adjunto' }}</td>
            </tr>

        </table>

    @else

        <div class="empty">
            No se registraron adjuntos.
        </div>

    @endif


    {{-- ===================================================== --}}
    {{-- 10. DECLARACIONES Y AUTORIZACIONES --}}
    {{-- ===================================================== --}}

    <div class="section-title page-break">
        10. Declaraciones y autorizaciones
    </div>

    <div class="declaration-box">
        <h4>1. DECLARACIONES DE ORIGEN DE FONDOS:</h4>
        <div>Los recursos que poseo y los de la sociedad que represento y que permiten realizar la presente transacción o vinculación comercial con Senco Latin America S.A.S., provienen exclusivamente del ejercicio de actividades lícitas y no están relacionados con operaciones de lavado de activos, financiación del terrorismo o proliferación de armas de destrucción masiva.</div>
        <br>
        <div>Tanto mi actividad, profesión u oficio es lícita y la ejerzo dentro del marco legal y los recursos que poseo no provienen de actividades ilícitas, por lo tanto no admitiré que terceros efectúen depósitos en mis cuentas con fondos provenientes de las actividades ilícitas contempladas en el Código Penal colombiano o en cualquier otra norma que lo adicione; ni efectuaré transacciones destinadas a tales actividades o a favor de las personas relacionadas con las mismas.</div>
        <br>
        <div>Además declaro que ni la sociedad que represento, ni yo mismo nos encontramos en algún proceso en instancias nacionales o internacionales por aspecto de lavado de activos o Financiación del Terrorismo.</div>
    </div>

    <div class="declaration-box">
        <h4>2. TRATAMIENTO DATOS PERSONALES</h4>
        <div>Con el diligenciamiento del presente documento, Autorizo de manera libre, previa, expresa, voluntaria e informada a Senco Latin America S.A.S. para que trate, almacene, use, circule, transmita y transfiera los datos personales y corporativos suministrados o que llegaren a suministrarse, con las siguientes finalidades: (i) la evaluación y aprobación de productos y/o servicios; (ii) el desarrollo y ejecución de la relación contractual correspondiente a los productos y/o servicios contratados; (iii) el envío de información relacionada con novedades o cambios en los productos y/o servicios; (iv) el envío de información sobre eventos, campañas promocionales y publicidad; (v) el envío de información legal o administrativa; y (vi) la realización de estudios estadísticos, de comportamiento, necesidades, consumo y preferencias.</div>
        <br>
        <div>Así mismo, autorizo que dicha información pueda ser compartida con empresas y/o entidades en Colombia o en el exterior, con el fin de ofrecer productos y/o servicios relacionados. Para las finalidades anteriormente descritas, autorizo el uso de los datos de contacto suministrados, tales como correo electrónico, teléfono, celular, SMS, redes sociales y demás medios físicos o electrónicos conocidos o por conocerse.</div>
        <br>
        <div>Declaro que he sido informado(a) de que Senco Latin America S.A.S., directamente o a través de terceros, actuará como Responsable del Tratamiento de los datos personales, de conformidad con su Política de Tratamiento de Datos Personales, disponible en https://sencolatinamerica.co/politica-de-tratamiento-de-datos-personales/, y que ha puesto a disposición los canales de atención correspondientes para el ejercicio de los derechos de conocimiento, actualización, rectificación, supresión y revocatoria de la autorización otorgada, a través de la línea de atención en Marinilla, Colombia 448-15-70 ext. 135 y el correo electrónico protecciondedatos@senco.co. Igualmente, autorizo a Senco Latin America S.A.S. para consultar y verificar información en listas restrictivas y vinculantes nacionales e internacionales, incluyendo listas OFAC, ONU y demás listas de prevención de lavado de activos y financiación del terrorismo.</div>
        <br>
        <div>Finalmente, certifico que la información suministrada en la solicitud y en el presente documento es veraz, completa y verificable, y me comprometo, en representación de la sociedad, a informar cualquier modificación relevante de la misma cuando sea requerido por Senco Latin America S.A.S. Declaro igualmente haber leído, entendido y aceptado el contenido del presente documento.</div>
    </div>

    <div class="declaration-box">
        <h4>3. AUTORIZACIÓN CONSULTA Y REPORTE ANTE CENTRALES DE RIESGO</h4>
        <div>Con la firma del presente documento, Autorizo de manera expresa e irrevocables a Senco Latin America S.A.S. y/o a quien en el futuro ostente la facultad de acreedor de las obligaciones por mi contraídas con Senco Latin America S.A.S., para que consulte, solicite, suministre, reporte, procese o divulgue, en cualquier tiempo, y en cualquier central de información de riesgo legalmente autorizada, toda la información que se refiera a su comportamiento crediticio, financiero, comercial y de servicios, relevante para conocer su desempeño como deudor, su capacidad de pago o para valorar el riesgo futuro de conceder un crédito; así como reportar a cualquier central de información de riesgo legalmente autorizada, datos sobre el cumplimiento oportuno, como sobre el incumplimiento, si lo hubiere, de sus obligaciones crediticias, o de sus deberes legales de contenido patrimonial, de tal forma que estas presenten una información veraz, pertinente, completa actualizada y exacta de su desempeño como deudor.</div>
    </div>

    {{-- ===================================================== --}}
    {{-- FIRMA --}}
    {{-- ===================================================== --}}

    <div class="section-title">
        Firma y autorización
    </div>

    <table class="data">

        <tr>
            <th>Acepta autorización</th>
            <td>
                {{ !empty($tercero->acepta_autorizacion) ? 'Sí' : 'No' }}
            </td>

            <th>Firma</th>
            <td>
                {{ !empty($tercero->rl_firma_url) ? 'Registrada' : 'No registrada' }}
            </td>
        </tr>

    </table>


    <div class="footer">
        Documento generado automáticamente por el sistema.
        Formulario No. {{ $tercero->IdFrm }}
    </div>

</body>
</html>