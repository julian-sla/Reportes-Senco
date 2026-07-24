<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de visita técnica</title>
    <style>
        @page { margin: 18px; }
        body { margin: 0; background: #fff; color: #222; font-family: Arial, sans-serif; font-size: 10px; }
        body * { font-family: Arial, sans-serif; }
        .sheet { border: 1px solid #cfcfcf; border-radius: 6px; padding: 18px 22px; }
        .header { width: 100%; border-bottom: 1px solid #9f9f9f; padding-bottom: 14px; margin-bottom: 14px; }
        .brand, .title { vertical-align: top; }
        .brand-name { margin-top: 5px; font-size: 11px; font-weight: 700; }
        .brand-sub { font-size: 8px; color: #555; }
        .logo { max-height: 28px; max-width: 96px; }
        .title { text-align: right; }
        .title h1 { margin: 0 0 5px; font-size: 14px; }
        .service-type { font-size: 8px; font-weight: 700; color: #444; text-transform: uppercase; }
        .visit-ref { margin-top: 4px; font-size: 8px; color: #555; }
        .section-title { margin: 13px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #c8c8c8; font-size: 9px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: #444; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { width: 50%; padding: 2px 0 5px; vertical-align: top; }
        .label { display: block; color: #666; font-size: 8px; }
        .value { display: block; font-weight: 700; }
        .equipment { margin-top: 8px; border: 1px solid #cfcfcf; border-radius: 5px; overflow: hidden; page-break-inside: avoid; }
        .equipment-head { padding: 8px 11px; background: #f2f2f2; border-bottom: 1px solid #d7d7d7; font-size: 9px; font-weight: 700; text-transform: uppercase; color: #444; }
        .equipment-body { padding: 9px 11px; }
        .equipment-detail { margin-bottom: 6px; line-height: 1.45; }
        .solution { margin: 7px 0 9px; color: #222; line-height: 1.45; }
        .solution-label { font-weight: 700; }
        .solution-list { margin: 3px 0 0 14px; padding: 0; list-style: none; }
        .solution-list li { margin-bottom: 2px; }
        .check { font-family: "DejaVu Sans", Arial, sans-serif; font-weight: 700; }
        .parts { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .parts th { background: #ededed; color: #444; font-size: 8px; text-align: left; padding: 5px 6px; border-bottom: 1px solid #d0d0d0; }
        .parts td { padding: 5px 6px; border-bottom: 1px solid #dfdfdf; }
        .photos-title { margin-top: 11px; font-size: 8px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; color: #444; }
        .photo-label { margin: 5px 0 4px; font-size: 8px; color: #444; }
        .photo-columns { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-top: 4px; }
        .photo-column { width: 50%; vertical-align: top; }
        .photo-grid { width: 100%; border-collapse: separate; border-spacing: 5px; }
        .photo-cell { width: 50%; height: 155px; padding: 4px; border: 1px solid #d4d4d4; border-radius: 4px; background: #f7f7f7; text-align: center; vertical-align: middle; overflow: hidden; line-height: 0; }
        .photo-cell img { max-width: 100%; max-height: 147px; width: auto; height: auto; }
        .closing { margin-top: 14px; border: 1px solid #cfcfcf; border-radius: 5px; page-break-inside: avoid; }
        .closing-title { padding: 7px 10px; background: #f2f2f2; border-bottom: 1px solid #d7d7d7; font-size: 8px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; color: #444; }
        .closing-body { padding: 9px 10px; }
        .technical-description { margin: 0 0 10px; line-height: 1.45; }
        .signature-block { margin-top: 8px; }
        .signature-label { margin-bottom: 5px; color: #666; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .signature-image { max-width: 240px; max-height: 82px; border: 1px solid #d7d7d7; background: #fff; padding: 6px; }
        .empty { color: #777; font-size: 8px; font-style: italic; }
    </style>
</head>
<body>
    <div class="sheet">
        <table class="header">
            <tr>
                <td class="brand">
                    @if($logoDataUri)
                        <img class="logo" src="{{ $logoDataUri }}" alt="SENCO">
                    @endif
                    <div class="brand-name">Senco Latinamérica S.A.S</div>
                </td>
                <td class="title">
                    <h1>Informe de visita técnica</h1>
                    <div class="service-type">{{ $visita->tipoServicio->TIPO_SERVICIO ?? 'Servicio técnico' }}</div>
                    <div class="visit-ref">Visita #{{ $visita->ID ?? '—' }} - Emitido: {{ now()->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>

        <div class="section-title">Información del cliente</div>
        <table class="info-table">
            <tr>
                <td><span class="label">Empresa</span><span class="value">{{ $visita->rutaTecnica->NombreCliente ?? '—' }}</span></td>
                <td><span class="label">NIT</span><span class="value">{{ $visita->rutaTecnica->Nit ?? '—' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Dirección</span><span class="value">{{ $visita->rutaTecnica->DireccionCompleta ?? '—' }}</span></td>
                <td><span class="label">Teléfono de contacto</span><span class="value">{{ $visita->rutaTecnica->TelContacto ?? '—' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Nombre contacto</span><span class="value">{{ $visita->rutaTecnica->NomContacto ?? '—' }}</span></td>
                <td><span class="label">Correo contácto</span><span class="value">{{ $visita->CORREO ?? '—' }}</span></td>
            </tr>
        </table>

        <div class="section-title">Información del servicio</div>
        <table class="info-table">
            <tr>
                <td><span class="label">Personal responsable</span><span class="value">{{ $tecnicoNombre ?? '—' }}</span></td>
                <td><span class="label">Tipo de servicio</span><span class="value">{{ $visita->tipoServicio->TIPO_SERVICIO ?? '—' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Fecha inicio</span><span class="value">{{ $visita->FECHA_INICIO ?? '—' }}</span></td>
                <td><span class="label">Fecha fin</span><span class="value">{{ $visita->FECHA_FIN ?? '—' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Hora inicio</span><span class="value">{{ $visita->HORA_INICIO ? substr($visita->HORA_INICIO, 0, 5) : '—' }}</span></td>
                <td><span class="label">Hora fin</span><span class="value">{{ $visita->HORA_FIN ? substr($visita->HORA_FIN, 0, 5) : '—' }}</span></td>
            </tr>
        </table>

        <div class="section-title">Detalles del servicio</div>
        @foreach($equipos as $index => $equipo)
            <div class="equipment">
                <div class="equipment-head">{{ $esCapacitacion ? 'Capacitación' : 'Equipo atendido #' . ($index + 1) }}</div>
                <div class="equipment-body">
                    @if($esCapacitacion)
                        <div class="equipment-detail"><span class="solution-label">Título de la capacitación:</span> {{ $equipo['nombre'] ?: '—' }}</div>
                        <div class="equipment-detail"><span class="solution-label">Temas tratados:</span> {{ $equipo['falla'] ?: '—' }}</div>

                        @if($equipo['fotos_despues']->isNotEmpty())
                            <div class="photos-title">Evidencia Capacitación</div>
                            <table class="photo-grid">
                                @foreach($equipo['fotos_despues']->chunk(2) as $fila)
                                    <tr>
                                        @foreach($fila as $foto)
                                            <td class="photo-cell"><img src="{{ $foto['data_uri'] }}" alt="{{ $foto['nombre'] }}"></td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                    @else
                        <div class="equipment-detail"><span class="solution-label">Nombre:</span> {{ $equipo['nombre'] ?: '—' }}</div>
                        <div class="equipment-detail"><span class="solution-label">Serial:</span> {{ $equipo['serial'] ?: '—' }}</div>
                        <div class="equipment-detail"><span class="solution-label">Tipo mantenimiento:</span> {{ $equipo['tipo_mant'] ?: '—' }}</div>
                        <div class="equipment-detail"><span class="solution-label">Fallas:</span> {{ $equipo['falla'] ?: '—' }}</div>

@if($equipo['soluciones']->isNotEmpty())
                             <div class="solution">
                                 <span class="solution-label">Tipo solución:</span>
                                 <ul class="solution-list">
                                     @foreach($equipo['soluciones'] as $solucion)
                                         <li><span class="check">&#10003;</span> {{ $solucion }}</li>
                                     @endforeach
                                 </ul>
                             </div>
                         @else
                             <div class="solution"><span class="solution-label">Tipo solución:</span> <span class="empty">Sin soluciones registradas.</span></div>
                         @endif

                         @if(!empty($equipo['observaciones']))
                             <div class="solution">
                                 <span class="solution-label">Observaciones:</span>
                                 {{ $equipo['observaciones'] }}
                             </div>
                         @endif

                        @if($equipo['repuestos']->isNotEmpty())
                            <div class="photos-title">Repuestos instalados</div>
                            <table class="parts">
                                <thead>
                                    <tr>
                                        <th>Descripción</th>
                                        <th>Cód. Max</th>
                                        <th>Cantidad</th>
                                        <th>Estado</th>
                                        <th>Observ.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($equipo['repuestos'] as $repuesto)
                                        <tr>
                                            <td>{{ $repuesto['descripcion'] }}</td>
                                            <td>{{ $repuesto['codigo_max'] }}</td>
                                            <td>{{ $repuesto['cantidad'] }}</td>
                                            <td>{{ $repuesto['estado'] }}</td>
                                            <td>{{ $repuesto['observaciones'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        @if($equipo['fotos_antes']->isNotEmpty() || $equipo['fotos_despues']->isNotEmpty())
                            <div class="photos-title">Evidencia fotográfica</div>
                            <table class="photo-columns">
                                <tr>
                                    @if($equipo['fotos_antes']->isNotEmpty())
                                        <td class="photo-column">
                                            <div class="photo-label">Antes</div>
                                            <table class="photo-grid">
                                                @foreach($equipo['fotos_antes']->chunk(2) as $fila)
                                                    <tr>
                                                        @foreach($fila as $foto)
                                                            <td class="photo-cell"><img src="{{ $foto['data_uri'] }}" alt="{{ $foto['nombre'] }}"></td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </td>
                                    @endif

                                    @if($equipo['fotos_despues']->isNotEmpty())
                                        <td class="photo-column">
                                            <div class="photo-label">Después</div>
                                            <table class="photo-grid">
                                                @foreach($equipo['fotos_despues']->chunk(2) as $fila)
                                                    <tr>
                                                        @foreach($fila as $foto)
                                                            <td class="photo-cell"><img src="{{ $foto['data_uri'] }}" alt="{{ $foto['nombre'] }}"></td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </td>
                                    @endif
                                </tr>
                            </table>
                        @endif
                    @endif
                </div>
            </div>
        @endforeach

        @php
            $observacionesFinales = $esCapacitacion
                ? ($equipos->first()['observaciones'] ?? null)
                : $visita->OBSERVACIONES;
            $personaFirma = trim((string) ($visita->PROPIETARIOFIRMA ?? ''));
        @endphp

        @if($observacionesFinales || $personaFirma !== '' || $firmaDataUri)
            <div class="closing">
                <div class="closing-title">FINAL</div>
                <div class="closing-body">
                    @if($observacionesFinales)
                        <div class="technical-description">
                            <span class="solution-label">Observaciones finales:</span>
                            {{ $observacionesFinales }}
                        </div>
                    @endif

                    @if($personaFirma !== '')
                        <div class="technical-description">
                            <span class="solution-label">Persona que firma:</span>
                            {{ $personaFirma }}
                        </div>
                    @endif

                    @if($firmaDataUri)
                        <div class="signature-block">
                            <div class="signature-label">Firma del cliente</div>
                            <img class="signature-image" src="{{ $firmaDataUri }}" alt="Firma del cliente">
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</body>
</html>
