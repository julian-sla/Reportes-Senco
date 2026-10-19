<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    formularios: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            current_page: 1,
            last_page: 1,
            total: 0
        })
    },
    borradores: {
        type: Array,
        default: () => []
    },
    filtros: {
        type: Object,
        default: () => ({})
    },
    permisos: {
        type: Object,
        default: () => ({
            crear: false,
            editar: false,
            validar: false
        })
    },
    // Catálogos para los campos de lista del modo edición.
    tiposTercero: { type: Array, default: () => [] },
    tiposFormulario: { type: Array, default: () => [] },
    tiposIdentificacion: { type: Array, default: () => [] },
    ciiu: { type: Array, default: () => [] },
    vendedores: { type: Array, default: () => [] }
});


const eliminarBorrador = async (borrador) => {
    const nombre = borrador.razon_social || 'Cliente sin nombre';

    if (!window.confirm(
    `¿Deseas eliminar el borrador de "${nombre}"? Se eliminarán el formulario y sus datos asociados de la base de datos. Esta acción no se puede deshacer.`
)) {
    return;
}

    try {
        await axios.delete(
            route('gestion-clientes.registro-tercero.borrador.destroy'),
            {
                params: {
                    borrador_id: borrador.borrador_id,
                },
            }
        );

        router.reload({
            only: ['formularios', 'borradores'],
            preserveScroll: true,
        });
    } catch (error) {
    console.error(error);

    const mensaje =
        error.response?.data?.message ||
        'No fue posible eliminar el borrador. Intenta nuevamente.';

    window.alert(mensaje);
}
};

const filtrosForm = ref({
    fecha_formulario: props.filtros?.fecha_formulario || '',
    ordenar: props.filtros?.ordenar || 'fecha',
    direccion: props.filtros?.direccion || 'desc',
});


const formularioSeleccionado = ref(null);
const modoEdicion = ref(false);
const detalleFormulario = ref(null);
const detalleEdicion = ref(null);
const guardandoCambios = ref(false);
const errorGuardado = ref('');
const cargandoDetalle = ref(false);
const errorDetalle = ref('');
const seccionAbierta = ref('general');

const seccionesDetalle = computed(() => {
    const detalle = modoEdicion.value
        ? detalleEdicion.value
        : detalleFormulario.value;

    if (!detalle) return [];

    return [
        { id: 'general', titulo: 'Información general', datos: detalle.general ? [detalle.general] : [] },
        { id: 'direcciones', titulo: 'Direcciones', datos: detalle.direcciones || [] },
        { id: 'socios', titulo: 'Socios', datos: detalle.socios || [] },
        { id: 'financiera', titulo: 'Información financiera y tributaria', datos: detalle.financiera || [] },
        { id: 'referencias_comerciales', titulo: 'Referencias comerciales', datos: detalle.referencias_comerciales || [] },
        { id: 'referencias_bancarias', titulo: 'Referencias bancarias', datos: detalle.referencias_bancarias || [] },
        { id: 'sgc', titulo: 'Información SGC', datos: detalle.sgc || [] },
        {
    id: 'adjuntos',
    titulo: 'Adjuntos y validaciones',
    datos: detalle.adjuntos?.length
        ? detalle.adjuntos.map(adjunto => ({
            ...(detalle.general || {}),
            ...adjunto,
            IdFrm: detalle.general?.IdFrm ?? adjunto.IdFrm,
        }))
        : detalle.general
            ? [detalle.general]
            : [],
},

    ];
});

const nombreCampo = (campo) => {
    return String(campo)
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, letra => letra.toUpperCase());
};

const valorCampo = (valor) => {
    if (valor === null || valor === undefined || valor === '') return 'Sin información';

    if (typeof valor === 'boolean' || valor === 0 || valor === 1) {
        if (valor === true || valor === 1) return 'Sí';
        if (valor === false || valor === 0) return 'No';
    }

    return String(valor);
};

const camposVisibles = (registro) => {
    if (!registro) return [];

    return Object.entries(registro).filter(([campo]) =>
        !['IdFrm', 'id_frm'].includes(campo)
    );
};



const camposGenerales = (registro) => {
    if (!registro) return [];

    const esJuridica = String(registro.tipo_persona ?? '')
        .trim()
        .toLowerCase() === 'jurídica';

    const campos = [
        { tipo: 'campo', etiqueta: 'Fecha del formulario *', valor: registro.Fecha_frm },
        { tipo: 'campo', etiqueta: 'Tipo de formulario', valor: registro.tipo_formulario },
        { tipo: 'campo', etiqueta: 'Tipo de tercero', valor: registro.tipo_tercero },
        {
            tipo: 'campo',
            etiqueta: 'Vendedor',
            valor: registro.vendedor_nombre
                ? `${String(registro.vendedor ?? '').trim()} - ${String(registro.vendedor_nombre).trim()}`
                : registro.vendedor
        },
        {
            tipo: 'campo',
            etiqueta: 'Tipo de persona *',
            valor: esJuridica ? 'Persona Jurídica' : 'Persona Natural'
        },
        {
            tipo: 'campo',
            etiqueta: 'Tipo de identificación *',
            valor: registro.tipo_doc_nombre || registro.tipo_doc
        },
        { tipo: 'campo', etiqueta: 'Número de identificación *', valor: registro.numero_doc },
        {
            tipo: 'campo',
            etiqueta: esJuridica ? 'Razón social *' : 'Nombres y apellidos *',
            valor: registro.razon_social
        },
        {
            tipo: 'campo',
            etiqueta: '¿Tiene establecimiento de comercio?',
            valor: valorCampo(registro.tiene_establ_comercio)
        },
        {
            tipo: 'campo',
            etiqueta: 'Nombre del establecimiento de comercio',
            valor: registro.nombre_establecimiento
        },
        { tipo: 'campo', etiqueta: 'Dirección *', valor: registro.direccion },
        { tipo: 'campo', etiqueta: 'País *', valor: registro.pais_nombre },
        { tipo: 'campo', etiqueta: 'Departamento *', valor: registro.departamento_nombre },
        { tipo: 'campo', etiqueta: 'Ciudad *', valor: registro.ciudad_nombre },
        { tipo: 'campo', etiqueta: 'Teléfono', valor: registro.telefono },
        { tipo: 'campo', etiqueta: 'Celular', valor: registro.celular1 },
        { tipo: 'campo', etiqueta: 'Celular alterno', valor: registro.celular2 },
        { tipo: 'campo', etiqueta: 'E-mail', valor: registro.email },
        {
            tipo: 'campo',
            etiqueta: 'Correo autorizado para recepción de la factura',
            valor: registro.email_facturacion
        },
        { tipo: 'campo', etiqueta: 'Código CIIU', valor: registro.codigo_ciiu_nombre },
        {
            tipo: 'campo',
            etiqueta: 'Actividad económica principal',
            valor: registro.actividad_economica
        },

        { tipo: 'subtitulo', etiqueta: 'Información del representante legal' },
        {
            tipo: 'campo',
            etiqueta: 'Nombre y apellidos',
            valor: registro.rl_nombre
        },
        {
            tipo: 'campo',
            etiqueta: 'Tipo de identificación',
            valor: registro.rl_tipo_doc_nombre || registro.rl_tipo_doc
        },
        {
            tipo: 'campo',
            etiqueta: 'Número de identificación',
            valor: registro.rl_numero_doc
        },
        {
            tipo: 'campo',
            etiqueta: 'E-mail',
            valor: registro.rl_email
        },

        { tipo: 'subtitulo', etiqueta: 'Información de tesorería' },
        {
            tipo: 'campo',
            etiqueta: 'Nombre y apellidos',
            valor: registro.tesoreria_nombre
        },
        {
            tipo: 'campo',
            etiqueta: 'Teléfono ',
            valor: registro.tesoreria_telefono
        },
        {
            tipo: 'campo',
            etiqueta: 'E-mail',
            valor: registro.tesoreria_email
        }
    ];

    return campos.filter(item =>
        item.tipo === 'subtitulo' ||
        (
            item.valor !== null &&
            item.valor !== undefined &&
            String(item.valor).trim() !== ''
        )
    );
};

const camposDirecciones = (registro) => {
    if (!registro) return [];

    const campos = [
        { tipo: 'campo', etiqueta: 'Dirección de envío', valor: registro.direccion_envio },
        { tipo: 'campo', etiqueta: 'País', valor: registro.pais_nombre },
        { tipo: 'campo', etiqueta: 'Departamento', valor: registro.departamento_nombre },
        { tipo: 'campo', etiqueta: 'Ciudad', valor: registro.municipio_nombre },
        { tipo: 'campo', etiqueta: 'Barrio', valor: registro.barrio },
        { tipo: 'campo', etiqueta: 'Observaciones', valor: registro.observaciones },
    ];

    return campos.filter(campo =>
        campo.valor !== null &&
        campo.valor !== undefined &&
        String(campo.valor).trim() !== ''
    );
};


const camposSocios = (registro) => {
    if (!registro) return [];

    const campos = [
        {
            tipo: 'campo',
            etiqueta: 'Nombres',
            valor: registro.nombres,
        },
        {
            tipo: 'campo',
            etiqueta: 'Apellidos',
            valor: registro.apellidos,
        },
        {
            tipo: 'campo',
            etiqueta: 'Tipo de identificación',
            valor: registro.tipo_doc_nombre || registro.tipo_doc,
        },
        {
            tipo: 'campo',
            etiqueta: 'Número de identificación',
            valor: registro.numero_doc,
        },
        {
            tipo: 'campo',
            etiqueta: 'Porcentaje de participación',
            valor:
                registro.porcentaje_participacion !== null &&
                registro.porcentaje_participacion !== undefined &&
                String(registro.porcentaje_participacion).trim() !== ''
                    ? `${registro.porcentaje_participacion} %`
                    : null,
        },
    ];

    return campos.filter(campo =>
        campo.valor !== null &&
        campo.valor !== undefined &&
        String(campo.valor).trim() !== ''
    );
};

const camposFinancieros = (registro) => {
    if (!registro) return [];

    const siNo = (valor) => {
        if (valor === null || valor === undefined || String(valor).trim() === '') {
            return null;
        }

        if ([true, 1, '1', 'true'].includes(valor)) return 'Sí';
        if ([false, 0, '0', 'false'].includes(valor)) return 'No';

        return valor;
    };

    const informacionFinanciera = [
        { etiqueta: 'Fecha de corte', valor: registro.fecha_corte },
        { etiqueta: 'Activos', valor: registro.activos },
        { etiqueta: 'Pasivos', valor: registro.pasivos },
        { etiqueta: 'Ingresos', valor: registro.ingresos },
        { etiqueta: 'Egresos', valor: registro.egresos },
    ];

    const informacionTributaria = [
        {
            etiqueta: '¿Es responsable de IVA?',
            valor: siNo(registro.responsable_iva),
        },
        { etiqueta: '¿Declara renta?', valor: siNo(registro.declara_renta) },
        {
            etiqueta: '¿Es gran contribuyente?',
            valor: siNo(registro.gran_contribuyente),
        },
        {
            etiqueta: '¿Es autoretenedor?',
            valor: siNo(registro.autoretenedor),
        },
    ];

    const convertirCampos = (campos) =>
        campos
            .filter(campo =>
                campo.valor !== null &&
                campo.valor !== undefined &&
                String(campo.valor).trim() !== ''
            )
            .map(campo => ({ tipo: 'campo', ...campo }));

    const financiera = convertirCampos(informacionFinanciera);
    const tributaria = convertirCampos(informacionTributaria);

    return [
        ...(financiera.length
            ? [{ tipo: 'subtitulo', etiqueta: 'Información financiera' }, ...financiera]
            : []),
        ...(tributaria.length
            ? [{ tipo: 'subtitulo', etiqueta: 'Información tributaria' }, ...tributaria]
            : []),
    ];
};

const camposReferenciasComerciales = (registro) => {
    if (!registro) return [];

    const tipo = String(registro.tipo_referencia ?? '').trim();
    const etiqueta = tipo.toLowerCase() === 'proveedor'
        ? 'Proveedor'
        : 'Cliente';

    const campos = [
    { etiqueta: 'Nombre o razón social', valor: registro.nombre_razon_social },
 { etiqueta: 'Tipo de identificación', valor: registro.tipo_doc_nombre || registro.tipo_doc },
{ etiqueta: 'Número de identificación', valor: registro.numero_doc },
{ etiqueta: 'Dirección', valor: registro.direccion },
{ etiqueta: 'País', valor: registro.pais_nombre || registro.pais || registro.id_pais },
{ etiqueta: 'Departamento', valor: registro.departamento_nombre },
{ etiqueta: 'Ciudad', valor: registro.ciudad_nombre },
    { etiqueta: 'Teléfono', valor: registro.telefono },
    { etiqueta: 'Celular', valor: registro.celular },
    { etiqueta: 'E-mail', valor: registro.email },
]

        .filter(campo =>
            campo.valor !== null &&
            campo.valor !== undefined &&
            String(campo.valor).trim() !== ''
        )
        .map(campo => ({ tipo: 'campo', ...campo }));

    return campos.length
        ? [{ tipo: 'subtitulo', etiqueta }, ...campos]
        : [];
};



const camposReferenciasBancarias = (registro) => {
    if (!registro) return [];

    const campos = [
        { etiqueta: 'Banco', valor: registro.nombre_banco ?? registro.Nombre_banco ?? registro['Nombre banco'] },
        { etiqueta: 'Sucursal', valor: registro.Sucursal ?? registro.sucursal },
        { etiqueta: 'Clase de cuenta', valor: registro['Clase Cuenta'] ?? registro.clase_cuenta },
        { etiqueta: 'Número de cuenta', valor: registro['Número de cuenta'] ?? registro.numero_cuenta },
    ];

    return campos
        .filter(campo =>
            campo.valor !== null &&
            campo.valor !== undefined &&
            String(campo.valor).trim() !== ''
        )
        .map(campo => ({ tipo: 'campo', ...campo }));
};



const camposAdjuntos = (registro) => {
    if (!registro) return [];

    const aceptacion = (valor) => {
        const normalizado = String(valor ?? '').trim().toLowerCase();

        if (valor === true || ['1', 'true', 'sí', 'si', 'yes'].includes(normalizado)) {
            return 'Sí';
        }

        if (valor === false || ['0', 'false', 'no'].includes(normalizado)) {
            return 'No';
        }

        return 'Sin registrar';
    };

    const documentos = [
        ['carta_instrucciones', 'Carta de instrucciones'],
        ['pagare', 'Pagaré'],
        ['certificado_existencia', 'Certificado de existencia y representación legal'],
        ['copia_rut', 'Copia del RUT'],
        ['estados_financieros', 'Estados financieros'],
        ['cedula_rl', 'Cédula del representante legal'],
        ['certificacion_bancaria', 'Certificación bancaria'],
        ['certificaciones', 'Certificaciones adicionales'],
    ];

    return [
        {
            tipo: 'subtitulo',
            etiqueta: 'Adjuntos',
        },
        ...documentos.map(([campo, etiqueta]) => ({
            tipo: 'adjunto',
            campo,
            etiqueta,
            idFrm: registro.IdFrm,
            disponible: registro[`${campo}_disponible`] === true,
            valor: registro[`${campo}_disponible`] === true
                ? 'Adjunto cargado'
                : 'Pendiente',
        })),

        {
    tipo: 'subtitulo',
    etiqueta: 'Declaraciones y autorizaciones',
},
{
    tipo: 'campo',
    etiqueta: '¿Acepta la política de tratamiento de datos?',
    valor: aceptacion(registro.acepta_autorizacion),
},

        {
            tipo: 'subtitulo',
            etiqueta: 'Firma del representante legal',
        },
        {
            tipo: 'firma',
            etiqueta: 'Firma registrada',
            valor: registro.rl_firma_url,
        },
    ];
};




const aplicarFiltros = () => {
    router.get(
        route('gestion-clientes.formularios-terceros.index'),
        { ...filtrosForm.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const limpiarFiltros = () => {
    filtrosForm.value = {
        fecha_formulario: '',
    };

    aplicarFiltros();
};
const ordenarPor = (campo) => {
    if (filtrosForm.value.ordenar === campo) {
        filtrosForm.value.direccion =
            filtrosForm.value.direccion === 'asc' ? 'desc' : 'asc';
    } else {
        filtrosForm.value.ordenar = campo;
        filtrosForm.value.direccion = 'asc';
    }

    aplicarFiltros();
};

const indicadorOrden = (campo) => {
    if (filtrosForm.value.ordenar !== campo) return '↕';

    return filtrosForm.value.direccion === 'asc' ? '↑' : '↓';
};



const camposSGC = (registro) => {
    if (!registro) return [];

    const esSi = valor =>
        valor === true || valor === 1 ||
        ['1', 'true', 'si', 'sí', 'yes'].includes(
            String(valor ?? '').trim().toLowerCase()
        );

    const esNo = valor =>
        valor === false || valor === 0 ||
        ['0', 'false', 'no'].includes(
            String(valor ?? '').trim().toLowerCase()
        );

    const respuesta = valor => {
        if (esSi(valor)) return 'Sí';
        if (esNo(valor)) return 'No';
        return valor;
    };

    const tieneValor = valor =>
        valor !== null &&
        valor !== undefined &&
        String(valor).trim() !== '';

    const grupos = [
        {
            titulo: 'Sistema de Autocontrol y Gestión del Riesgo LA/FT',
            campos: [
                ['¿Tiene conocimiento sobre el Sistema de Autocontrol y Gestión del Riesgo LA/FT?', registro.r_conoce_LA_FT, true],
                ['Entidad obligada a reportar', registro.r_obligado_reportar, true],
                ['¿Cuenta con Oficial de Cumplimiento?', registro.r_tiene_oficial_cumplimiento, true],
                ['Nombre(s) y apellidos del Oficial de Cumplimiento', registro.r_nombre_oficial_cumplimiento],
                ['Celular del Oficial de Cumplimiento', registro.r_celular_oficial_cumplimiento],
                ['E-mail del Oficial de Cumplimiento', registro.r_email_oficial_cumplimiento],
            ],
        },
        {
            titulo: 'Activos virtuales',
            campos: [
                ['¿Maneja activos virtuales?', registro.a_maneja_activos_viirtuales, true],
                ['¿Cuáles activos virtuales maneja?', registro.a_cuales_activos_virtuales],
                ['¿Participa, intercambia o realiza transferencia de activos virtuales?', registro.a_tranfiere_activos_virtuales, true],
            ],
        },
        {
            titulo: 'Certificación OEA y programas de seguridad',
            campos: [
                ['¿Cuenta con certificación OEA o cualquier otro programa de seguridad administrado por una aduana extranjera?', registro.o_tiene_oea, true],
                ['¿Cuál?', registro.o_cual_oea],
            ],
        },
        {
            titulo: 'Personas expuestas pública o políticamente (PEP)',
            campos: [
                ['¿Es una persona expuesta pública o políticamente (PEP)?', registro.p_persona_publicamente_expuesta, true],
                ['Cargo que desempeña', registro.p_cargo_desempena],
                ['Fecha de inicio del cargo', registro.p_fecha_inicio_cargo],
                ['Fecha de finalización del cargo', registro.p_finalizacion_cargo],
                ['¿Alguien de su familia es considerado PEP?', registro.p_familiar_pep, true],
            ],
        },
    ];

    return grupos.flatMap((grupo, indiceGrupo) => {
        const campos = grupo.campos.filter(([etiqueta, valor]) => {
            if (!tieneValor(valor)) return false;

            if ([
                'Nombre(s) y apellidos del Oficial de Cumplimiento',
                'Celular del Oficial de Cumplimiento',
                'E-mail del Oficial de Cumplimiento',
            ].includes(etiqueta)) {
                return esSi(registro.r_tiene_oficial_cumplimiento);
            }

            if (etiqueta === '¿Cuáles activos virtuales maneja?') {
                return esSi(registro.a_maneja_activos_viirtuales);
            }

            if (etiqueta === '¿Cuál?') {
                return esSi(registro.o_tiene_oea);
            }

            if ([
                'Cargo que desempeña',
                'Fecha de inicio del cargo',
                'Fecha de finalización del cargo',
            ].includes(etiqueta)) {
                return esSi(registro.p_persona_publicamente_expuesta);
            }

            return true;
        }).map(([etiqueta, valor, esBooleano]) => ({
            tipo: 'campo',
            etiqueta,
            valor: esBooleano ? respuesta(valor) : valor,
        }));

        return [
            ...(campos.length
                ? [{
                    tipo: 'subtitulo',
                    etiqueta: grupo.titulo,
                    id: `sgc-${indiceGrupo}`,
                }]
                : []),
            ...campos,
        ];
    });
};


const verFormulario = async (formulario) => {
    if (!formulario?.id) {
        console.error('El formulario seleccionado no tiene un ID válido.');
        return;
    }

    formularioSeleccionado.value = formulario;
    modoEdicion.value = false;
    detalleFormulario.value = null;
    detalleEdicion.value = null;
    errorGuardado.value = '';
    errorDetalle.value = '';
    cargandoDetalle.value = true;
    seccionAbierta.value = 'general';

    try {
        const respuesta = await fetch(
            route('gestion-clientes.formularios-terceros.show', {
                id: formulario.id
            }),
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }
        );

        if (!respuesta.ok) {
            throw new Error('No fue posible cargar los datos del formulario.');
        }

        detalleFormulario.value = await respuesta.json();
        detalleEdicion.value = JSON.parse(
            JSON.stringify(detalleFormulario.value)
        );
    } catch (error) {
        console.error('Error consultando el formulario:', error);
        errorDetalle.value = 'No se pudo cargar la información. Intenta nuevamente.';
    } finally {
        cargandoDetalle.value = false;
    }
};

const editarFormulario = async (formulario) => {
    if (
        !formulario?.id ||
        String(formulario.estado ?? '').trim() !== 'Ingresado'
    ) {
        return;
    }

    formularioSeleccionado.value = formulario;
    modoEdicion.value = true;
    detalleFormulario.value = null;
    detalleEdicion.value = null;
    errorGuardado.value = '';
    errorDetalle.value = '';
    cargandoDetalle.value = true;
    seccionAbierta.value = 'general';

    try {
        const respuesta = await fetch(
            route('gestion-clientes.formularios-terceros.show', {
                id: formulario.id
            }),
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }
        );

        if (!respuesta.ok) {
            throw new Error('No fue posible cargar los datos del formulario.');
        }

        detalleFormulario.value = await respuesta.json();
        detalleEdicion.value = JSON.parse(
            JSON.stringify(detalleFormulario.value)
        );

        await prepararEdicion();
    } catch (error) {
        console.error('Error preparando la edición:', error);
        errorDetalle.value = 'No se pudo cargar la información. Intenta nuevamente.';
        modoEdicion.value = false;
    } finally {
        cargandoDetalle.value = false;
    }
};

const cerrarDetalle = () => {
    formularioSeleccionado.value = null;
    detalleFormulario.value = null;
    errorDetalle.value = '';
    modoEdicion.value = false;
    detalleEdicion.value = null;
    errorGuardado.value = '';
    guardandoCambios.value = false;
};

const guardarCambios = async () => {
    const id = formularioSeleccionado.value?.id;
    const detalle = detalleEdicion.value;

    if (!id || !detalle?.general) return false;

    const errorValidacion = validarEdicion(detalle);

    if (errorValidacion) {
        errorGuardado.value = errorValidacion;
        return false;
    }

    guardandoCambios.value = true;
    errorGuardado.value = '';

    const seleccionar = (objeto, campos) =>
        Object.fromEntries(
            campos
                .filter(campo =>
                    Object.prototype.hasOwnProperty.call(objeto ?? {}, campo)
                )
                .map(campo => [campo, objeto[campo]])
        );

    const camposGenerales = [
        'Fecha_frm', 'IdTipo_frm', 'IdTipo_tercero',
        'tipo_persona', 'vendedor', 'tipo_doc', 'numero_doc',
        'razon_social', 'tiene_establ_comercio',
        'nombre_establecimiento', 'direccion', 'IdCiudad',
        'IdDepartamento', 'telefono', 'celular1', 'celular2',
        'email', 'email_facturacion', 'actividad_economica',
        'IdCodigo_ciiu', 'rl_nombre', 'rl_tipo_doc',
        'rl_numero_doc', 'rl_email', 'tesoreria_nombre',
        'tesoreria_telefono', 'tesoreria_email',
    ];

    const configuracion = {
        direcciones: {
            pk: 'IdDireccion',
            campos: [
                'IdDireccion', 'direccion_envio', 'Id_cod_pais',
                'Id_cod_dpto', 'Id_cod_mpio', 'barrio', 'observaciones',
            ],
        },
        socios: {
            pk: 'IdSocio',
            campos: [
                'IdSocio', 'nombres', 'apellidos', 'tipo_doc',
                'numero_doc', 'porcentaje_participacion',
            ],
        },
        referencias_comerciales: {
            pk: 'IdReferencia_comercial',
            campos: [
                'IdReferencia_comercial', 'tipo_referencia',
                'nombre_razon_social', 'tipo_doc', 'numero_doc',
                'direccion', 'IdCiudad', 'IdDepartamento',
                'telefono', 'celular', 'email',
            ],
        },
        referencias_bancarias: {
            pk: 'IdReferencia_bancaria',
            campos: [
                'IdReferencia_bancaria', 'nombre_banco', 'sucursal',
                'clase_cuenta', 'numero_cuenta',
            ],
        },
    };

    const camposFinancieros = [
        'fecha_corte', 'activos', 'pasivos', 'ingresos', 'egresos',
        'responsable_iva', 'declara_renta', 'gran_contribuyente',
        'autoretenedor',
    ];

    const camposSGC = [
        'r_conoce_LA_FT', 'r_obligado_reportar',
        'r_tiene_oficial_cumplimiento',
        'r_nombre_oficial_cumplimiento',
        'r_celular_oficial_cumplimiento',
        'r_email_oficial_cumplimiento',
        'a_maneja_activos_viirtuales',
        'a_cuales_activos_virtuales',
        'a_tranfiere_activos_virtuales',
        'o_tiene_oea', 'o_cual_oea',
        'p_persona_publicamente_expuesta',
        'p_cargo_desempeña', 'p_cargo_desempena',
        'p_fecha_inicio_cargo', 'p_finalizacion_cargo',
        'p_familiar_pep',
    ];

    try {
        const datos = {
            general: seleccionar(detalle.general, camposGenerales),
        };

        for (const [seccion, config] of Object.entries(configuracion)) {
            if (!Array.isArray(detalle[seccion])) continue;

            datos[seccion] = detalle[seccion].map((fila) => {
                if (!fila[config.pk]) {
                    throw new Error(
                        `No se encontró el identificador de un registro de ${seccion}. No se guardaron los cambios.`
                    );
                }

                return seleccionar(fila, config.campos);
            });
        }

        // El backend acepta un objeto o una lista con el registro existente.
        if (Array.isArray(detalle.financiera) && detalle.financiera.length) {
            datos.financiera = seleccionar(
                detalle.financiera[0],
                camposFinancieros
            );
        }

        if (Array.isArray(detalle.sgc) && detalle.sgc.length) {
            datos.sgc = seleccionar(detalle.sgc[0], camposSGC);
        }

        const respuesta = await fetch(
            `/gestion-clientes/formularios-terceros/${encodeURIComponent(id)}`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify(datos),
            }
        );

        const resultado = await respuesta.json().catch(() => ({}));

        if (!respuesta.ok) {
            throw new Error(
                resultado.message ||
                resultado.mensaje ||
                'No fue posible guardar los cambios.'
            );
        }

        const respuestaDetalle = await fetch(
            `/gestion-clientes/formularios-terceros/${encodeURIComponent(id)}/detalle`,
            {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            }
        );

        if (!respuestaDetalle.ok) {
            throw new Error(
                'Los cambios se guardaron, pero no fue posible recargar el formulario.'
            );
        }

        const detalleActualizado = await respuestaDetalle.json();

        detalleFormulario.value = detalleActualizado;
        detalleEdicion.value = JSON.parse(
            JSON.stringify(detalleActualizado)
        );

        modoEdicion.value = false;

        // Refresca el listado (razón social, etc.) sin cerrar la ventana.
        router.reload({ only: ['formularios'], preserveScroll: true });

        return true;
    } catch (error) {
        errorGuardado.value = error.message ||
            'Ocurrió un error al guardar los cambios.';

        return false;
    } finally {
        guardandoCambios.value = false;
    }
};


/* ===================================================================
 | EDICIÓN COMPLETA: todos los campos del formulario son editables
 =================================================================== */

const normalizarTexto = (texto) =>
    String(texto ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase();

const esSiValor = (v) =>
    v === true || v === 1 ||
    ['1', 'true', 'si', 'sí', 'yes'].includes(String(v ?? '').trim().toLowerCase());

const esNoValor = (v) =>
    v === false || v === 0 ||
    ['0', 'false', 'no'].includes(String(v ?? '').trim().toLowerCase());

// Conserva el formato con el que ya venía guardado (bit o texto SI/NO).
const escribirSiNo = (actual, si) => {
    if (typeof actual === 'string' && actual.trim() !== '' && !['0', '1'].includes(actual.trim())) {
        const conTilde = actual.trim().toLowerCase() === 'sí' || actual.trim() === 'No';
        if (conTilde) return si ? 'Sí' : 'No';
        return si ? 'SI' : 'NO';
    }
    return si ? 1 : 0;
};

const paisesCatalogo = ref([]);
const departamentosPorPais = ref({});
const municipiosPorDpto = ref({});

const codigoColombia = computed(() =>
    paisesCatalogo.value.find(p => normalizarTexto(p.nombre) === 'colombia')?.codigo ?? null
);

const paisEsExtranjero = (codigoPais) => {
    if (codigoPais === null || codigoPais === undefined || codigoPais === '') return false;

    const pais = paisesCatalogo.value.find(p => String(p.codigo) === String(codigoPais));

    return pais ? normalizarTexto(pais.nombre) !== 'colombia' : false;
};

const pedirJson = async (url) => {
    try {
        const respuesta = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!respuesta.ok) return [];

        const datos = await respuesta.json();

        return Array.isArray(datos) ? datos : [];
    } catch (error) {
        console.error('Error cargando catálogo:', error);
        return [];
    }
};

const cargarPaisesCatalogo = async () => {
    if (paisesCatalogo.value.length) return;
    paisesCatalogo.value = await pedirJson('/gestion-clientes/paises');
};

const cargarDepartamentosDe = async (pais) => {
    if (pais === null || pais === undefined || pais === '') return;
    if (departamentosPorPais.value[pais]) return;

    departamentosPorPais.value = {
        ...departamentosPorPais.value,
        [pais]: await pedirJson(`/gestion-clientes/departamentos?pais=${encodeURIComponent(pais)}`),
    };
};

const cargarMunicipiosDe = async (departamento) => {
    if (departamento === null || departamento === undefined || departamento === '') return;
    if (municipiosPorDpto.value[departamento]) return;

    municipiosPorDpto.value = {
        ...municipiosPorDpto.value,
        [departamento]: await pedirJson(`/gestion-clientes/municipios/${encodeURIComponent(departamento)}`),
    };
};

const FECHAS_EDICION = ['Fecha_frm', 'fecha_corte', 'p_fecha_inicio_cargo', 'p_finalizacion_cargo'];

// Limpia espacios (columnas CHAR) y deja las fechas como AAAA-MM-DD.
const normalizarRegistro = (registro) => {
    if (!registro || typeof registro !== 'object') return;

    Object.keys(registro).forEach((clave) => {
        const valor = registro[clave];

        if (typeof valor !== 'string') return;

        registro[clave] = FECHAS_EDICION.includes(clave)
            ? valor.substring(0, 10)
            : valor.trim();
    });
};

const prepararEdicion = async () => {
    const detalle = detalleEdicion.value;

    if (!detalle) return;

    [
        detalle.general,
        ...(detalle.direcciones || []),
        ...(detalle.socios || []),
        ...(detalle.financiera || []),
        ...(detalle.referencias_comerciales || []),
        ...(detalle.referencias_bancarias || []),
        ...(detalle.sgc || []),
    ].forEach(normalizarRegistro);

    await cargarPaisesCatalogo();

    const tareas = [];

    if (codigoColombia.value !== null) {
        tareas.push(cargarDepartamentosDe(codigoColombia.value));
    }

    const departamentoGeneral = detalle.general?.IdDepartamento;
    if (departamentoGeneral) tareas.push(cargarMunicipiosDe(departamentoGeneral));

    (detalle.direcciones || []).forEach((dir) => {
        if (dir.Id_cod_pais && !paisEsExtranjero(dir.Id_cod_pais)) {
            tareas.push(cargarDepartamentosDe(dir.Id_cod_pais));
        }
        if (dir.Id_cod_dpto) tareas.push(cargarMunicipiosDe(dir.Id_cod_dpto));
    });

    (detalle.referencias_comerciales || []).forEach((ref) => {
        if (ref.IdDepartamento) tareas.push(cargarMunicipiosDe(ref.IdDepartamento));
    });

    await Promise.all(tareas);
};

const opciones = {
    tipoFormulario: () => (props.tiposFormulario || []).map(t => ({
        valor: t.IdTipo_frm, etiqueta: String(t.Tipo_frm ?? '').trim(),
    })),
    tipoTercero: () => (props.tiposTercero || []).map(t => ({
        valor: t.IdTipo_tercero, etiqueta: String(t.tipo_tercero ?? '').trim(),
    })),
    tipoDoc: () => (props.tiposIdentificacion || []).map(t => ({
        valor: String(t.codigo ?? '').trim(), etiqueta: String(t.nombre ?? '').trim(),
    })),
    vendedor: () => (props.vendedores || []).map(v => ({
        valor: String(v.codigo ?? '').trim(),
        etiqueta: `${String(v.codigo ?? '').trim()} - ${String(v.nombre ?? '').trim()}`,
    })),
    ciiu: () => (props.ciiu || []).map(c => ({
        valor: c.IdCodigo_ciiu,
        etiqueta: `${String(c.codigo_ciiu ?? '').trim()} - ${String(c.actividad_economica ?? '').trim()}`.slice(0, 110),
    })),
    persona: () => [
        { valor: 'Natural', etiqueta: 'Persona Natural' },
        { valor: 'Jurídica', etiqueta: 'Persona Jurídica' },
    ],
    paises: () => paisesCatalogo.value.map(p => ({ valor: p.codigo, etiqueta: p.nombre })),
    departamentos: (pais) => (departamentosPorPais.value[pais] || []).map(d => ({ valor: d.codigo, etiqueta: d.nombre })),
    municipios: (dpto) => (municipiosPorDpto.value[dpto] || []).map(m => ({ valor: m.codigo, etiqueta: m.nombre })),
};

// Agrega el valor actual a la lista si no está (evita perder datos antiguos).
const conActual = (lista, actual) => {
    if (actual === null || actual === undefined || actual === '') return lista;
    if (lista.some(o => String(o.valor) === String(actual))) return lista;
    return [...lista, { valor: actual, etiqueta: String(actual) }];
};

const camposEdicion = (seccionId, r) => {
    if (!r) return [];

    const t = (clave, etiqueta, extra = {}) => ({ tipo: 'text', clave, etiqueta, ...extra });
    const sub = (etiqueta) => ({ tipo: 'subtitulo', etiqueta });
    const sino = (clave, etiqueta) => ({ tipo: 'sino', clave, etiqueta });

    if (seccionId === 'general') {
        const juridica = normalizarTexto(r.tipo_persona) === 'juridica';
        const paisEtiqueta = r.pais_nombre;
        const colombiano = !paisEtiqueta || normalizarTexto(paisEtiqueta) === 'colombia';
        const colombia = codigoColombia.value;

        return [
            t('Fecha_frm', 'Fecha del formulario *', { tipo: 'date' }),
            { tipo: 'select', clave: 'IdTipo_frm', etiqueta: 'Tipo de formulario', numero: true,
              opciones: conActual(opciones.tipoFormulario(), r.IdTipo_frm) },
            { tipo: 'select', clave: 'IdTipo_tercero', etiqueta: 'Tipo de tercero', numero: true,
              opciones: conActual(opciones.tipoTercero(), r.IdTipo_tercero) },
            props.vendedores.length
                ? { tipo: 'select', clave: 'vendedor', etiqueta: 'Vendedor',
                    opciones: conActual(opciones.vendedor(), r.vendedor) }
                : t('vendedor', 'Vendedor (código)'),
            { tipo: 'select', clave: 'tipo_persona', etiqueta: 'Tipo de persona *',
              opciones: conActual(opciones.persona(), r.tipo_persona) },
            props.tiposIdentificacion.length
                ? { tipo: 'select', clave: 'tipo_doc', etiqueta: 'Tipo de identificación *',
                    opciones: conActual(opciones.tipoDoc(), r.tipo_doc) }
                : t('tipo_doc', 'Tipo de identificación (código) *'),
            t('numero_doc', 'Número de identificación *', { maxlength: 30 }),
            t('razon_social', juridica ? 'Razón social *' : 'Nombres y apellidos *'),
            { tipo: 'sino', clave: 'tiene_establ_comercio', etiqueta: '¿Tiene establecimiento de comercio?' },
            ...(esSiValor(r.tiene_establ_comercio)
                ? [t('nombre_establecimiento', 'Nombre del establecimiento de comercio')]
                : []),
            t('direccion', 'Dirección *'),
            { tipo: 'lectura', etiqueta: 'País *', valor: paisEtiqueta || 'Sin información' },
            ...(colombiano && colombia !== null
                ? [
                    { tipo: 'select', clave: 'IdDepartamento', etiqueta: 'Departamento *',
                      alCambiar: 'dpto', hijo: 'IdCiudad',
                      opciones: conActual(opciones.departamentos(colombia), r.IdDepartamento) },
                    { tipo: 'select', clave: 'IdCiudad', etiqueta: 'Ciudad *',
                      opciones: conActual(opciones.municipios(r.IdDepartamento), r.IdCiudad) },
                ]
                : []),
            t('telefono', 'Teléfono', { tipo: 'tel' }),
            t('celular1', 'Celular', { tipo: 'tel' }),
            t('celular2', 'Celular alterno', { tipo: 'tel' }),
            t('email', 'E-mail', { tipo: 'email' }),
            t('email_facturacion', 'Correo autorizado para recepción de la factura', { tipo: 'email' }),
            props.ciiu.length
                ? { tipo: 'select', clave: 'IdCodigo_ciiu', etiqueta: 'Código CIIU', numero: true,
                    alCambiar: 'ciiu', opciones: conActual(opciones.ciiu(), r.IdCodigo_ciiu) }
                : { tipo: 'lectura', etiqueta: 'Código CIIU', valor: r.codigo_ciiu_nombre || 'Sin información' },
            t('actividad_economica', 'Actividad económica principal', { ancho: 'completo' }),

            sub('Información del representante legal'),
            t('rl_nombre', 'Nombre y apellidos'),
            props.tiposIdentificacion.length
                ? { tipo: 'select', clave: 'rl_tipo_doc', etiqueta: 'Tipo de identificación',
                    opciones: conActual(opciones.tipoDoc(), r.rl_tipo_doc) }
                : t('rl_tipo_doc', 'Tipo de identificación (código)'),
            t('rl_numero_doc', 'Número de identificación', { maxlength: 30 }),
            t('rl_email', 'E-mail', { tipo: 'email' }),

            sub('Información de tesorería'),
            t('tesoreria_nombre', 'Nombre y apellidos'),
            t('tesoreria_telefono', 'Teléfono', { tipo: 'tel' }),
            t('tesoreria_email', 'E-mail', { tipo: 'email' }),
        ];
    }

    if (seccionId === 'direcciones') {
        const extranjero = paisEsExtranjero(r.Id_cod_pais);

        return [
            t('direccion_envio', 'Dirección de envío'),
            { tipo: 'select', clave: 'Id_cod_pais', etiqueta: 'País', alCambiar: 'pais',
              hijos: ['Id_cod_dpto', 'Id_cod_mpio', 'barrio'],
              opciones: conActual(opciones.paises(), r.Id_cod_pais) },
            ...(extranjero
                ? []
                : [
                    { tipo: 'select', clave: 'Id_cod_dpto', etiqueta: 'Departamento',
                      alCambiar: 'dpto', hijo: 'Id_cod_mpio',
                      opciones: conActual(opciones.departamentos(r.Id_cod_pais), r.Id_cod_dpto) },
                    { tipo: 'select', clave: 'Id_cod_mpio', etiqueta: 'Ciudad',
                      opciones: conActual(opciones.municipios(r.Id_cod_dpto), r.Id_cod_mpio) },
                    t('barrio', 'Barrio'),
                ]),
            t('observaciones', 'Observaciones', { tipo: 'textarea', ancho: 'completo' }),
        ];
    }

    if (seccionId === 'socios') {
        return [
            t('nombres', 'Nombres'),
            t('apellidos', 'Apellidos'),
            props.tiposIdentificacion.length
                ? { tipo: 'select', clave: 'tipo_doc', etiqueta: 'Tipo de identificación',
                    opciones: conActual(opciones.tipoDoc(), r.tipo_doc) }
                : t('tipo_doc', 'Tipo de identificación (código)'),
            t('numero_doc', 'Número de identificación', { maxlength: 30 }),
            t('porcentaje_participacion', 'Porcentaje de participación (%)',
                { tipo: 'number', min: 0, max: 100, step: '0.01' }),
        ];
    }

    if (seccionId === 'financiera') {
        return [
            sub('Información financiera'),
            t('fecha_corte', 'Fecha de corte', { tipo: 'date' }),
            t('activos', 'Activos', { tipo: 'number', step: 'any' }),
            t('pasivos', 'Pasivos', { tipo: 'number', step: 'any' }),
            t('ingresos', 'Ingresos', { tipo: 'number', step: 'any' }),
            t('egresos', 'Egresos', { tipo: 'number', step: 'any' }),
            sub('Información tributaria'),
            sino('responsable_iva', '¿Es responsable de IVA?'),
            sino('declara_renta', '¿Declara renta?'),
            sino('gran_contribuyente', '¿Es gran contribuyente?'),
            sino('autoretenedor', '¿Es autoretenedor?'),
        ];
    }

    if (seccionId === 'referencias_comerciales') {
        const tipoRef = String(r.tipo_referencia ?? '').trim();
        const opcionesTipo = conActual(
            [{ valor: 'Cliente', etiqueta: 'Cliente' }, { valor: 'Proveedor', etiqueta: 'Proveedor' }],
            tipoRef
        );
        const colombia = codigoColombia.value;

        return [
            { tipo: 'select', clave: 'tipo_referencia', etiqueta: 'Tipo de referencia', opciones: opcionesTipo },
            t('nombre_razon_social', 'Nombre o razón social'),
            props.tiposIdentificacion.length
                ? { tipo: 'select', clave: 'tipo_doc', etiqueta: 'Tipo de identificación',
                    opciones: conActual(opciones.tipoDoc(), r.tipo_doc) }
                : t('tipo_doc', 'Tipo de identificación (código)'),
            t('numero_doc', 'Número de identificación', { maxlength: 30 }),
            t('direccion', 'Dirección'),
            { tipo: 'select', clave: 'IdDepartamento', etiqueta: 'Departamento',
              alCambiar: 'dpto', hijo: 'IdCiudad',
              opciones: conActual(colombia !== null ? opciones.departamentos(colombia) : [], r.IdDepartamento) },
            { tipo: 'select', clave: 'IdCiudad', etiqueta: 'Ciudad',
              opciones: conActual(opciones.municipios(r.IdDepartamento), r.IdCiudad) },
            t('telefono', 'Teléfono', { tipo: 'tel' }),
            t('celular', 'Celular', { tipo: 'tel' }),
            t('email', 'E-mail', { tipo: 'email' }),
        ];
    }

    if (seccionId === 'referencias_bancarias') {
        return [
            t('nombre_banco', 'Banco'),
            t('sucursal', 'Sucursal'),
            t('clase_cuenta', 'Clase de cuenta'),
            t('numero_cuenta', 'Número de cuenta'),
        ];
    }

    if (seccionId === 'sgc') {
        const claveCargo = Object.prototype.hasOwnProperty.call(r, 'p_cargo_desempeña')
            ? 'p_cargo_desempeña'
            : 'p_cargo_desempena';

        return [
            sub('Sistema de Autocontrol y Gestión del Riesgo LA/FT'),
            sino('r_conoce_LA_FT', '¿Tiene conocimiento sobre el Sistema de Autocontrol y Gestión del Riesgo LA/FT?'),
            sino('r_obligado_reportar', 'Entidad obligada a reportar'),
            sino('r_tiene_oficial_cumplimiento', '¿Cuenta con Oficial de Cumplimiento?'),
            ...(esSiValor(r.r_tiene_oficial_cumplimiento)
                ? [
                    t('r_nombre_oficial_cumplimiento', 'Nombre(s) y apellidos del Oficial de Cumplimiento'),
                    t('r_celular_oficial_cumplimiento', 'Celular del Oficial de Cumplimiento', { tipo: 'tel' }),
                    t('r_email_oficial_cumplimiento', 'E-mail del Oficial de Cumplimiento', { tipo: 'email' }),
                ]
                : []),

            sub('Activos virtuales'),
            sino('a_maneja_activos_viirtuales', '¿Maneja activos virtuales?'),
            ...(esSiValor(r.a_maneja_activos_viirtuales)
                ? [t('a_cuales_activos_virtuales', '¿Cuáles activos virtuales maneja?')]
                : []),
            sino('a_tranfiere_activos_virtuales', '¿Participa, intercambia o realiza transferencia de activos virtuales?'),

            sub('Certificación OEA y programas de seguridad'),
            sino('o_tiene_oea', '¿Cuenta con certificación OEA o cualquier otro programa de seguridad administrado por una aduana extranjera?'),
            ...(esSiValor(r.o_tiene_oea) ? [t('o_cual_oea', '¿Cuál?')] : []),

            sub('Personas expuestas pública o políticamente (PEP)'),
            sino('p_persona_publicamente_expuesta', '¿Es una persona expuesta pública o políticamente (PEP)?'),
            ...(esSiValor(r.p_persona_publicamente_expuesta)
                ? [
                    t(claveCargo, 'Cargo que desempeña'),
                    t('p_fecha_inicio_cargo', 'Fecha de inicio del cargo', { tipo: 'date' }),
                    t('p_finalizacion_cargo', 'Fecha de finalización del cargo', { tipo: 'date' }),
                ]
                : []),
            sino('p_familiar_pep', '¿Alguien de su familia es considerado PEP?'),
        ];
    }

    return [];
};

const valorSiNoSelect = (registro, clave) => {
    const v = registro[clave];

    if (esSiValor(v)) return 'si';
    if (esNoValor(v)) return 'no';

    return '';
};

const asignarCampo = (registro, campo, valor, elemento = null) => {
    if (campo.tipo === 'tel') {
        valor = String(valor ?? '').replace(/\D/g, '');
        if (elemento) elemento.value = valor;
    }

    if (campo.tipo === 'sino') {
        registro[campo.clave] = valor === '' ? null : escribirSiNo(registro[campo.clave], valor === 'si');
        return;
    }

    let nuevo = valor;

    if (campo.tipo === 'number') {
        nuevo = valor === '' ? null : Number(valor);
        if (nuevo !== null && !Number.isFinite(nuevo)) nuevo = null;
    } else if (campo.tipo === 'select' && campo.numero) {
        nuevo = valor === '' ? null : Number(valor);
    } else if (valor === '' && ['select', 'date', 'tel'].includes(campo.tipo)) {
        nuevo = null;
    }

    registro[campo.clave] = nuevo;

    if (campo.alCambiar === 'pais') {
        (campo.hijos || []).forEach((hijo) => { registro[hijo] = null; });

        if (nuevo !== null && !paisEsExtranjero(nuevo)) {
            cargarDepartamentosDe(nuevo);
        }
    }

    if (campo.alCambiar === 'dpto') {
        if (campo.hijo) registro[campo.hijo] = null;
        if (nuevo !== null) cargarMunicipiosDe(nuevo);
    }

    if (campo.alCambiar === 'ciiu') {
        const item = (props.ciiu || []).find(c => Number(c.IdCodigo_ciiu) === Number(nuevo));
        if (item) registro.actividad_economica = String(item.actividad_economica ?? '').trim();
    }
};

const validarEdicion = (detalle) => {
    const g = detalle.general || {};

    if (String(g.numero_doc ?? '').length > 30 || String(g.rl_numero_doc ?? '').length > 30) {
        return 'Los números de identificación no pueden superar 30 caracteres.';
    }

    for (const socio of detalle.socios || []) {
        const p = socio.porcentaje_participacion;

        if (p !== null && p !== undefined && p !== '' && (Number(p) < 0 || Number(p) > 100)) {
            return 'El porcentaje de participación de cada socio debe estar entre 0 y 100.';
        }
    }

    return '';
};

/*
|--------------------------------------------------------------------------
| ESTADOS: En borrador -> Ingresado -> Validado o Rechazado por vendedor
|--------------------------------------------------------------------------
*/
const ESTADOS_ASESOR = [
    'Validado por vendedor',
    'Rechazado por vendedor',
];

const modalEstado = ref(null);
const estadoElegido = ref('');
const observacionEstado = ref('');
const cambiandoEstado = ref(false);
const errorEstado = ref('');
const aviso = ref(null);
let temporizadorAviso = null;

const puedeCambiarEstado = (formulario) =>
    Boolean(props.permisos?.validar) &&
    String(formulario?.estado ?? '').trim() === 'Ingresado';

const mostrarAviso = (tipo, texto) => {
    aviso.value = { tipo, texto };
    clearTimeout(temporizadorAviso);
    temporizadorAviso = setTimeout(() => { aviso.value = null; }, 5000);
};

onBeforeUnmount(() => clearTimeout(temporizadorAviso));

const abrirCambioEstado = (formulario, estado = '') => {
    if (!puedeCambiarEstado(formulario)) return;

    modalEstado.value = formulario;
    estadoElegido.value = estado;
    observacionEstado.value = '';
    errorEstado.value = '';
};

const cerrarCambioEstado = () => {
    if (cambiandoEstado.value) return;
    modalEstado.value = null;
};

const confirmarCambioEstado = async () => {
    const formulario = modalEstado.value;

    if (!formulario?.id || !ESTADOS_ASESOR.includes(estadoElegido.value)) {
        errorEstado.value = 'Seleccione el nuevo estado.';
        return;
    }

    if (
        estadoElegido.value === 'Rechazado por vendedor' &&
        !observacionEstado.value.trim()
    ) {
        errorEstado.value = 'Indique el motivo del rechazo en las observaciones.';
        return;
    }

    cambiandoEstado.value = true;
    errorEstado.value = '';

    try {
        // Si el asesor está editando, primero se guardan los cambios.
        if (modoEdicion.value) {
            const guardado = await guardarCambios();

            if (!guardado) {
                modalEstado.value = null;
                return;
            }
        }

        const respuesta = await fetch(
            `/gestion-clientes/formularios-terceros/${encodeURIComponent(formulario.id)}/estado`,
            {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({
                    estado: estadoElegido.value,
                    observaciones: observacionEstado.value.trim() || null,
                }),
            }
        );

        const resultado = await respuesta.json().catch(() => ({}));

        if (!respuesta.ok) {
            throw new Error(
                resultado.message || resultado.mensaje ||
                'No fue posible cambiar el estado.'
            );
        }

        const idCambiado = formulario.id;

        modalEstado.value = null;
        cerrarDetalle();
        router.reload({ only: ['formularios'], preserveScroll: true });
        mostrarAviso('exito', `Formulario #${idCambiado}: ${resultado.estado}.`);
    } catch (error) {
        errorEstado.value = error.message || 'Ocurrió un error al cambiar el estado.';
    } finally {
        cambiandoEstado.value = false;
    }
};


const campoEditable = (etiqueta) => {
    const campos = {
        'Fecha del formulario *': 'Fecha_frm',
        'Número de identificación *': 'numero_doc',
        'Razón social *': 'razon_social',
        'Nombres y apellidos *': 'razon_social',
        'Nombre del establecimiento de comercio': 'nombre_establecimiento',
        'Dirección *': 'direccion',
        'Teléfono': 'telefono',
        'Celular': 'celular1',
        'Celular alterno': 'celular2',
        'E-mail': 'email',
        'Correo autorizado para recepción de la factura': 'email_facturacion',
        'Número de identificación': 'rl_numero_doc',
        'Nombre y apellidos': 'rl_nombre',
        'Teléfono ': 'tesoreria_telefono',
    };

    return campos[etiqueta] ?? null;
};


const campoDeSeccion = (seccion, item) => {
    const mapas = {
        direcciones: {
            'Dirección de envío': 'direccion_envio',
            'Barrio': 'barrio',
            'Observaciones': 'observaciones',
        },
        socios: {
            'Nombres': 'nombres',
            'Apellidos': 'apellidos',
            'Número de identificación': 'numero_doc',
            'Porcentaje de participación': 'porcentaje_participacion',
        },
        financiera: {
            'Fecha de corte': 'fecha_corte',
            'Activos': 'activos',
            'Pasivos': 'pasivos',
            'Ingresos': 'ingresos',
            'Egresos': 'egresos',
        },
        referencias_comerciales: {
            'Nombre o razón social': 'nombre_razon_social',
            'Número de identificación': 'numero_doc',
            'Dirección': 'direccion',
            'Teléfono': 'telefono',
            'Celular': 'celular',
            'E-mail': 'email',
        },
        referencias_bancarias: {
            'Banco': 'nombre_banco',
            'Sucursal': 'sucursal',
            'Clase de cuenta': 'clase_cuenta',
            'Número de cuenta': 'numero_cuenta',
        },
    };

    return mapas[seccion]?.[item.etiqueta] ?? null;
};

const formatearFecha = (fecha) => {
    if (!fecha) return '—';
    return String(fecha).substring(0, 10);
};

const claseEstado = (estado) => {
    const valor = (estado || '').toLowerCase();

    if (valor.includes('rechazado')) {
        return 'bg-red-100 text-red-800';
    }

    if (valor.includes('validado') || valor.includes('aceptado')) {
        return 'bg-green-100 text-green-800';
    }

    if (valor.includes('ingresado')) {
        return 'bg-green-100 text-green-800';
    }

    if (valor.includes('borrador') || valor.includes('proceso')) {
        return 'bg-amber-100 text-amber-800';
    }

    return 'bg-gray-100 text-gray-700';
};

const textoPagina = (label) => {
    return label
        .replace('&laquo; Previous', '<<')
        .replace('Previous', '<')
        .replace('Next &raquo;', '>>')
        .replace('Next', '>')
        .replace('&laquo;', '<<')
        .replace('&raquo;', '>>');
};
</script>

<template>
    <AppLayout title="Formularios de terceros">
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Formularios de terceros
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Consulta y seguimiento de los formularios registrados.
                    </p>
                </div>

                <button
                    v-if="permisos.crear"
                    type="button"
                    @click="router.visit(route('gestion-clientes.registro-tercero'))"
                    class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <svg
                        class="mr-2 h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    Nuevo formulario
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

                <!-- Filtros -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-800">
                            Filtros de búsqueda
                        </h3>
                    </div>

                    <div class="p-6">

<form @submit.prevent="aplicarFiltros">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div>
            <label
                for="fecha_formulario"
                class="mb-2 block text-sm font-medium text-gray-700"
            >
                Fecha de formulario
            </label>
            <input
                id="fecha_formulario"
                v-model="filtrosForm.fecha_formulario"
                type="date"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
        </div>
    </div>

    <div class="mt-5 flex flex-col justify-end gap-3 sm:flex-row">
        <button
            type="button"
            @click="limpiarFiltros"
            class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-200"
        >
            Limpiar
        </button>

        <button
            type="submit"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700"
        >
            Aplicar filtros
        </button>
    </div>
</form>

                    </div>
                </div>

                <!-- Tabla -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-gray-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-base font-semibold text-gray-800">
                            Formularios registrados
                        </h3>

<div class="flex flex-col gap-2 sm:flex-row sm:items-center">
    <input
        v-model="filtrosForm.buscar"
        type="text"
        placeholder="Buscar formulario..."
        @keyup.enter="aplicarFiltros"
        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
    />
    <span class="whitespace-nowrap text-sm text-gray-500">
        {{ formularios.total ?? 0 }} registros
    </span>
</div>

                    </div>


<!-- Listado moderno de formularios -->
<div class="space-y-4 bg-slate-50 p-4 sm:p-6">

    <!-- Controles de ordenamiento -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-600">
            Mostrando
            <span class="font-semibold text-[#17365D]">{{ formularios.total ?? 0 }}</span>
            formularios registrados
            <span v-if="borradores.length">
                y
                <span class="font-semibold text-amber-700">{{ borradores.length }}</span>
                borradores
            </span>
        </p>

        <div class="flex items-center gap-2">
            <label for="orden-formularios" class="text-sm text-slate-600">
                Ordenar por
            </label>

            <select
                id="orden-formularios"
                :value="filtrosForm.ordenar || 'fecha'"
                @change="ordenarPor($event.target.value)"
                class="rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
                <option value="fecha">Fecha</option>
                <option value="id">Número de formulario</option>
                <option value="vendedor">Vendedor</option>
                <option value="numero_doc">Identificación</option>
                <option value="razon_social">Razón social</option>
                <option value="tipo_formulario">Tipo de formulario</option>
                <option value="estado">Estado</option>
            </select>

            <button
                type="button"
                @click="ordenarPor(filtrosForm.ordenar || 'fecha')"
                :title="'Cambiar dirección del orden ' + indicadorOrden(filtrosForm.ordenar || 'fecha')"
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white text-[#17365D] transition hover:bg-blue-50"
                aria-label="Cambiar dirección del ordenamiento"
            >
                <svg xmlns="http://www.w3.org/2000/svg"
                     width="18" height="18" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="m7 15 5 5 5-5"/>
                    <path d="M12 20V4"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Tarjetas de formularios registrados -->
    <div
        v-for="formulario in formularios.data || []"
        :key="formulario.id"
        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition duration-200 hover:border-blue-200 hover:shadow-md"
    >
        <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-5">

            <!-- Número de formulario -->
            <div class="flex shrink-0 items-center gap-3 sm:w-24 sm:flex-col sm:items-center sm:justify-center sm:gap-1 sm:border-r sm:border-slate-100 sm:pr-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-[#E8EFF7] text-xl font-bold text-[#17365D]">
                    {{ formulario.id }}
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Formulario
                </span>
            </div>

            <!-- Información del formulario -->
            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h4 class="break-words text-base font-semibold text-slate-800">
                        {{ formulario.razon_social || 'Sin razón social registrada' }}
                    </h4>

                    <span
                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                        :class="claseEstado(formulario.estado)"
                    >
                        {{ formulario.estado || 'Sin estado' }}
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2 xl:grid-cols-3">
                    <div class="flex items-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             width="17" height="17" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round"
                             class="mt-0.5 shrink-0 text-[#17365D]">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M5 21v-2a7 7 0 0 1 14 0v2"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500">Vendedor</p>
                            <p class="break-words font-medium text-slate-700">
                                {{ formulario.vendedor_nombre
                                    ? `${formulario.vendedor || ''} - ${formulario.vendedor_nombre}`
                                    : formulario.vendedor || '—' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             width="17" height="17" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round"
                             class="mt-0.5 shrink-0 text-[#17365D]">
                            <rect x="3" y="4" width="18" height="16" rx="2"/>
                            <path d="M7 8h10M7 12h10M7 16h5"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500">Identificación</p>
                            <p class="break-words font-medium text-slate-700">
                                {{ formulario.numero_doc || '—' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             width="17" height="17" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round"
                             class="mt-0.5 shrink-0 text-[#17365D]">
                            <rect x="3" y="5" width="18" height="16" rx="2"/>
                            <path d="M16 3v4M8 3v4M3 11h18"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500">Fecha del formulario</p>
                            <p class="font-medium text-slate-700">
                                {{ formatearFecha(formulario.fecha) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-2 sm:col-span-2 xl:col-span-3">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             width="17" height="17" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round"
                             class="mt-0.5 shrink-0 text-[#17365D]">
                            <path d="M4 4h16v16H4z"/>
                            <path d="M8 8h8M8 12h8M8 16h5"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500">Tipo de formulario</p>
                            <p class="break-words font-medium text-slate-700">
                                {{ formulario.tipo_formulario || '—' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Acciones del formulario -->
            <div class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-100 pt-3 sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0">

                <template v-if="formulario.borrador_id">
                    <!-- Seguir editando -->
                    <button
                        type="button"
                        @click="router.visit(
                            route('gestion-clientes.registro-tercero')
                            + '?borrador_id='
                            + encodeURIComponent(formulario.borrador_id)
                        )"
                        title="Seguir editando"
                        aria-label="Seguir editando borrador"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-[#17365D] transition hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-300"
                    >
                       <svg xmlns="http://www.w3.org/2000/svg"
     width="19" height="19" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="1.8"
     stroke-linecap="round" stroke-linejoin="round">
    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h8"/>
    <path d="M14 2v6h6"/>
    <path d="M8 13h5"/>
    <path d="M8 17h4"/>
    <path d="m16 18 4-4 2 2-4 4-3 1z"/>
</svg>
                    </button>

                    <!-- Eliminar -->
                    <button
                        type="button"
                        @click="eliminarBorrador({
                            borrador_id: formulario.borrador_id,
                            razon_social: formulario.razon_social
                        })"
                        title="Eliminar borrador"
                        aria-label="Eliminar borrador"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-red-600 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg"
                             width="19" height="19" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"/>
                            <path d="M8 6V4h8v2"/>
                            <path d="m19 6-1 14H6L5 6"/>
                            <path d="M10 11v5M14 11v5"/>
                        </svg>
                    </button>
                </template>

                <template v-else>
                    <!-- Ver formulario -->
                    <button
                        type="button"
                        @click="verFormulario(formulario)"
                        title="Ver formulario"
                        aria-label="Ver formulario"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-[#17365D] transition hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg"
                             width="19" height="19" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>

                    <!-- Editar según permisos y estado -->
                    <button
                        v-if="permisos.editar && formulario.estado === 'Ingresado'"
                        type="button"
                        @click="editarFormulario(formulario)"
                        title="Editar formulario"
                        aria-label="Editar formulario"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-700 transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg"
                             width="19" height="19" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/>
                        </svg>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <!-- Tarjetas de borradores -->
    <div
        v-for="borrador in borradores"
        :key="`borrador-${borrador.borrador_id}`"
        class="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm transition duration-200 hover:border-amber-300 hover:shadow-md"
    >
        <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-5">

            <div class="flex shrink-0 items-center gap-3 sm:w-24 sm:flex-col sm:items-center sm:justify-center sm:gap-1 sm:border-r sm:border-slate-100 sm:pr-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-amber-50 text-xl font-bold text-amber-800">
                    {{ borrador.borrador_id || '—' }}
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Borrador
                </span>
            </div>

            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h4 class="break-words text-base font-semibold text-slate-800">
                        {{ borrador.razon_social || 'Cliente sin nombre' }}
                    </h4>
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                        En borrador
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <p class="text-xs text-slate-500">Vendedor</p>
                        <p class="font-medium text-slate-700">—</p>
                    </div>

                    <div>
                        <p class="text-xs text-slate-500">Identificación</p>
                        <p class="break-words font-medium text-slate-700">
                            {{ borrador.numero_doc || 'Sin registrar' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-slate-500">Fecha del borrador</p>
                        <p class="font-medium text-slate-700">
                            {{ formatearFecha(borrador.fecha) }}
                        </p>
                    </div>

                    <div class="sm:col-span-2 xl:col-span-3">
                        <p class="text-xs text-slate-500">Tipo de formulario</p>
                        <p class="font-medium text-slate-700">—</p>
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-100 pt-3 sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0">
                <button
                    type="button"
                    @click="router.visit(
                        route('gestion-clientes.registro-tercero')
                        + '?borrador_id='
                        + encodeURIComponent(borrador.borrador_id)
                    )"
                    title="Continuar editando"
                    aria-label="Continuar editando borrador"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-[#17365D] transition hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-300"
                >
                    <svg xmlns="http://www.w3.org/2000/svg"
                         width="19" height="19" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 20h9"/>
                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/>
                    </svg>
                </button>

                <button
                    type="button"
                    @click="eliminarBorrador(borrador)"
                    title="Eliminar borrador"
                    aria-label="Eliminar borrador"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-red-600 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-300"
                >
                    <svg xmlns="http://www.w3.org/2000/svg"
                         width="19" height="19" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"/>
                        <path d="M8 6V4h8v2"/>
                        <path d="m19 6-1 14H6L5 6"/>
                        <path d="M10 11v5M14 11v5"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Estado vacío -->
    <div
        v-if="(!formularios.data || formularios.data.length === 0) && borradores.length === 0"
        class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center"
    >
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100">
            <svg xmlns="http://www.w3.org/2000/svg"
                 width="26" height="26" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round"
                 class="text-slate-500">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
                <path d="M14 2v6h6M8 13h8M8 17h8"/>
            </svg>
        </div>

        <p class="font-semibold text-slate-800">
            No se encontraron formularios
        </p>
        <p class="mt-1 text-sm text-slate-500">
            Prueba cambiando los filtros o crea un formulario nuevo.
        </p>
    </div>

</div>


<!-- Paginación -->
<div
    v-if="formularios.links?.length"
    class="flex flex-col gap-3 border-t border-gray-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
>
    <p class="text-sm text-gray-500">
        Página {{ formularios.current_page }} de {{ formularios.last_page }}
    </p>

    <div class="flex flex-wrap items-center gap-1">
        <button
            v-for="(link, index) in formularios.links"
            :key="index"
            v-show="link.label !== '...'"
            type="button"
            :disabled="!link.url || link.active"
            @click="link.url && router.visit(link.url, {
                preserveState: true,
                preserveScroll: true
            })"
            class="min-w-9 rounded-md border px-3 py-2 text-sm transition"
            :class="link.active
                ? 'border-indigo-600 bg-indigo-600 text-white'
                : !link.url
                    ? 'cursor-not-allowed border-gray-200 text-gray-300'
                    : 'border-gray-300 text-gray-700 hover:bg-gray-100'"
        >
            {{ textoPagina(link.label) }}
        </button>
    </div>
</div>

                </div>
            </div>
        </div>

<!-- Consulta del formulario en ventana modal -->
<div
    v-if="formularioSeleccionado"
    class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/60 p-2 sm:p-4"
    @click.self="cerrarDetalle"
>
    <div
        class="flex h-[94vh] w-full max-w-[1600px] flex-col overflow-hidden rounded-xl bg-white shadow-2xl"
    >


<!-- Encabezado del modal -->
<div
    class="relative flex shrink-0 items-center justify-center border-b border-gray-200 bg-white px-5 py-4"
>
    <h3 class="text-xl font-bold tracking-tight text-gray-800">
        Formulario #{{ formularioSeleccionado.id }}
    </h3>

    <span
        class="ml-3 rounded-full px-3 py-1 text-xs font-semibold"
        :class="claseEstado(formularioSeleccionado.estado)"
    >
        {{ formularioSeleccionado.estado || 'Sin estado' }}
    </span>

    <!-- Botón cerrar -->
    <button
        type="button"
        @click="cerrarDetalle"
        class="absolute right-5 rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-800"
        aria-label="Cerrar formulario"
        title="Cerrar"
    >
        <svg
            class="h-6 w-6"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
            />
        </svg>
    </button>
</div>


<!-- Contenido de consulta: solo lectura -->
<div class="min-h-0 flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6">

    <!-- Cargando -->
    <div
        v-if="cargandoDetalle"
        class="flex min-h-64 items-center justify-center gap-3 text-gray-500"
    >
        <svg class="h-6 w-6 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            />
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
            />
        </svg>
        Cargando información del formulario...
    </div>

    <!-- Error -->
    <div
        v-else-if="errorDetalle"
        class="rounded-lg border border-red-200 bg-red-50 p-5 text-sm text-red-700"
    >
        {{ errorDetalle }}

        <button
            type="button"
            @click="verFormulario(formularioSeleccionado)"
            class="ml-2 font-semibold underline"
        >
            Reintentar
        </button>
    </div>

    <!-- Secciones del formulario -->
    <div v-else-if="detalleFormulario" class="mx-auto max-w-6xl space-y-3">

        <section
            v-for="seccion in seccionesDetalle"
            :key="seccion.id"
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
        >
            <button
                type="button"
                @click="seccionAbierta = seccionAbierta === seccion.id ? '' : seccion.id"
                class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-gray-50"
                :aria-expanded="seccionAbierta === seccion.id"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-sm font-bold text-indigo-700">
                        {{ seccionesDetalle.findIndex(item => item.id === seccion.id) + 1 }}
                    </span>

                    <div>
                        <h4 class="font-semibold text-gray-800">
                            {{ seccion.titulo }}
                        </h4>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ seccion.datos.length }}
                            {{ seccion.datos.length === 1 ? 'registro' : 'registros' }}
                        </p>
                    </div>
                </div>

                <svg
                    class="h-5 w-5 shrink-0 text-gray-500 transition-transform"
                    :class="{ 'rotate-180': seccionAbierta === seccion.id }"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                >
                    <path
                        fill-rule="evenodd"
                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.09 1.032l-4.25 4.51a.75.75 0 01-1.09 0l-4.25-4.51a.75.75 0 01.02-1.06z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>

            <div
                v-if="seccionAbierta === seccion.id"
                class="border-t border-gray-100 p-4 sm:p-5"
            >
                <div
                    v-if="seccion.datos.length"
                    class="space-y-5"
                >
                    <div
                        v-for="(registro, indice) in seccion.datos"
                        :key="registro.Id || registro.id || indice"
                        class="rounded-lg border border-gray-100 p-4"
                    >
                        <p
                            v-if="seccion.datos.length > 1"
                            class="mb-4 text-sm font-semibold text-indigo-700"
                        >
                            Registro {{ indice + 1 }}
                        </p>


                        <!-- MODO EDICIÓN: todos los campos editables -->
                        <div
                            v-if="modoEdicion && seccion.id !== 'adjuntos'"
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <template
                                v-for="(campo, posicion) in camposEdicion(seccion.id, registro)"
                                :key="`${seccion.id}-${campo.clave ?? campo.etiqueta}-${posicion}`"
                            >
                                <div
                                    v-if="campo.tipo === 'subtitulo'"
                                    class="col-span-full mt-2 border-t border-slate-200 pt-5"
                                >
                                    <h5 class="text-sm font-bold text-slate-800">
                                        {{ campo.etiqueta }}
                                    </h5>
                                </div>

                                <div
                                    v-else
                                    class="min-w-0"
                                    :class="campo.ancho === 'completo' ? 'col-span-full' : ''"
                                >
                                    <label class="mb-1 block text-xs font-medium text-gray-500">
                                        {{ campo.etiqueta }}
                                    </label>

                                    <div
                                        v-if="campo.tipo === 'lectura'"
                                        class="rounded-md border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-600"
                                    >
                                        {{ campo.valor }}
                                    </div>

                                    <select
                                        v-else-if="campo.tipo === 'select'"
                                        :value="registro[campo.clave] ?? ''"
                                        @change="asignarCampo(registro, campo, $event.target.value)"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    >
                                        <option value="">Seleccione una opción</option>
                                        <option
                                            v-for="opcion in campo.opciones"
                                            :key="opcion.valor"
                                            :value="opcion.valor"
                                        >
                                            {{ opcion.etiqueta }}
                                        </option>
                                    </select>

                                    <select
                                        v-else-if="campo.tipo === 'sino'"
                                        :value="valorSiNoSelect(registro, campo.clave)"
                                        @change="asignarCampo(registro, campo, $event.target.value)"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    >
                                        <option value="">Sin respuesta</option>
                                        <option value="si">Sí</option>
                                        <option value="no">No</option>
                                    </select>

                                    <textarea
                                        v-else-if="campo.tipo === 'textarea'"
                                        :value="registro[campo.clave] ?? ''"
                                        @input="asignarCampo(registro, campo, $event.target.value)"
                                        rows="3"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    ></textarea>

                                    <input
                                        v-else
                                        :type="campo.tipo"
                                        :value="registro[campo.clave] ?? ''"
                                        :maxlength="campo.maxlength"
                                        :min="campo.min"
                                        :max="campo.max"
                                        :step="campo.step"
                                        :inputmode="campo.tipo === 'tel' ? 'numeric' : undefined"
                                        @input="asignarCampo(registro, campo, $event.target.value, $event.target)"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    />
                                </div>
                            </template>
                        </div>

                        <dl
                            v-else
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <template
                                v-for="(item, indice) in (

seccion.id === 'general'
    ? camposGenerales(registro)
    : seccion.id === 'direcciones'
        ? camposDirecciones(registro)
       : seccion.id === 'socios'
    ? camposSocios(registro)
   : seccion.id === 'financiera'
    ? camposFinancieros(registro)
    : seccion.id === 'referencias_comerciales'
        ? camposReferenciasComerciales(registro)
     : seccion.id === 'referencias_bancarias'
    ? camposReferenciasBancarias(registro)
    : seccion.id === 'sgc'
        ? camposSGC(registro)
        : seccion.id === 'adjuntos'
    ? camposAdjuntos(registro)
        : camposVisibles(registro).map(([campo, valor]) => ({
            tipo: 'campo',
            etiqueta: campo,
            valor
        }))

                                )"
                                :key="item.tipo === 'subtitulo'
                                    ? item.etiqueta
                                    : `${item.etiqueta}-${indice}`"
                            >
                                <div
                                    v-if="item.tipo === 'subtitulo'"
                                    class="col-span-full mt-2 border-t border-slate-200 pt-5"
                                >
                                    <h5 class="text-sm font-bold text-slate-800">
                                        {{ item.etiqueta }}
                                    </h5>
                                </div>

<div v-else-if="item.tipo === 'firma'" class="col-span-full">
    <dt class="mb-2 text-xs font-medium text-gray-500">
        {{ item.etiqueta }}
    </dt>

    <dd>
        <img
            v-if="item.valor"
            :src="item.valor"
            alt="Firma del representante legal"
            class="max-h-40 max-w-full object-contain"
        />
        <span v-else class="text-gray-500">
            Sin firma registrada
        </span>
    </dd>
</div>

                                <div v-else class="min-w-0">
                                    <dt class="mb-1 text-xs font-medium text-gray-500">
                                        {{ item.etiqueta }}
                                    </dt>

                                    <dd
    v-if="item.tipo === 'adjunto'"
    class="rounded-md border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-800"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <span
            :class="item.disponible
                ? 'text-green-700'
                : 'text-gray-500'"
            class="font-medium"
        >
            {{ item.valor }}
        </span>

        <div v-if="item.disponible" class="flex items-center gap-3">
            <a
                :href="route(
                    'gestion-clientes.formularios-terceros.adjunto.ver',
                    { id: item.idFrm, campo: item.campo }
                )"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800"
            >
                <icon name="eye" size="xs" />
                Ver
            </a>

            <a
                :href="route(
                    'gestion-clientes.formularios-terceros.adjunto.descargar',
                    { id: item.idFrm, campo: item.campo }
                )"
                class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800"
            >
                <icon name="download" size="xs" />
                Descargar
            </a>
        </div>
    </div>
</dd>

<dd
    v-else
    class="break-words rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800"
>

<template
    v-if="modoEdicion && seccion.id !== 'adjuntos' && seccion.id !== 'general'
        && campoDeSeccion(seccion.id, item)"
>
    <input
        v-model="registro[campoDeSeccion(seccion.id, item)]"
        :type="['activos', 'pasivos', 'ingresos', 'egresos', 'porcentaje_participacion'].includes(campoDeSeccion(seccion.id, item)) ? 'number' : 'text'"
        :step="campoDeSeccion(seccion.id, item) === 'porcentaje_participacion' ? '0.01' : 'any'"
        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
    />
</template>

<template
    v-else-if="modoEdicion && seccion.id !== 'adjuntos' && seccion.id !== 'general'"
>
    <span class="text-gray-700">
        {{ valorCampo(item.valor) }}
    </span>
</template>

<template v-else-if="seccion.id === 'general' && modoEdicion && campoEditable(item.etiqueta)">
    <input
    v-model="detalleEdicion.general[campoEditable(item.etiqueta)]"
    :type="campoEditable(item.etiqueta) === 'Fecha_frm' ? 'date' : 'text'"
    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
/>
</template>

<template v-else>
    {{ valorCampo(item.valor) }}
</template>

</dd>
                                </div>
                            </template>
                        </dl>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center"
                >
                    <p class="text-sm font-medium text-gray-600">
                        No hay información registrada en esta sección.
                    </p>
                </div>
            </div>
        </section>
    </div>
</div>


<!-- Pie fijo del modal -->

<div class="flex shrink-0 flex-wrap items-center justify-end gap-3 border-t border-gray-200 bg-white px-5 py-3">
    <p
        v-if="errorGuardado"
        class="text-sm text-red-600"
    >
        {{ errorGuardado }}
    </p>

    <button
        v-if="modoEdicion"
        type="button"
        @click="guardarCambios"
        :disabled="guardandoCambios"
        class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
    >
        {{ guardandoCambios ? 'Guardando...' : 'Guardar cambios' }}
    </button>

    <button
        type="button"
        @click="cerrarDetalle"
        class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-100"
    >
        Cerrar
    </button>
</div>
</div>
</div>

<!-- Cambio de estado por el asesor -->
<div
    v-if="modalEstado"
    class="fixed inset-0 z-[120] flex items-center justify-center bg-gray-900/60 p-4"
    @click.self="cerrarCambioEstado"
>
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-gray-800">
            Formulario #{{ modalEstado.id }}
        </h3>
        <p class="mt-1 text-sm text-gray-500">
            {{ modalEstado.razon_social }}
        </p>

        <div class="mt-5 space-y-3">
            <label
                v-for="estado in ESTADOS_ASESOR"
                :key="estado"
                class="flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                :class="estadoElegido === estado ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200'"
            >
                <input
                    v-model="estadoElegido"
                    type="radio"
                    name="nuevo-estado"
                    :value="estado"
                    class="text-emerald-600 focus:ring-emerald-500"
                />
                {{ estado }}
            </label>
        </div>

        <p v-if="modoEdicion" class="mt-4 text-xs text-amber-700">
            Se guardarán primero los cambios que hiciste en el formulario.
        </p>

        <label class="mt-5 block text-xs font-medium text-gray-500">
            Observaciones
            {{ estadoElegido === 'Rechazado por vendedor' ? '(obligatorio al rechazar)' : '(opcional)' }}
        </label>
        <textarea
            v-model="observacionEstado"
            rows="3"
            maxlength="450"
            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
        ></textarea>

        <p v-if="errorEstado" class="mt-3 text-sm text-red-600">
            {{ errorEstado }}
        </p>

        <div class="mt-6 flex justify-end gap-3">
            <button
                type="button"
                @click="cerrarCambioEstado"
                :disabled="cambiandoEstado"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-50"
            >
                Cancelar
            </button>
            <button
                type="button"
                @click="confirmarCambioEstado"
                :disabled="cambiandoEstado || !estadoElegido"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ cambiandoEstado ? 'Guardando...' : 'Confirmar' }}
            </button>
        </div>
    </div>
</div>


<!-- Aviso -->
<div
    v-if="aviso"
    class="fixed right-5 top-5 z-[130] rounded-lg px-5 py-3 text-sm font-semibold text-white shadow-lg"
    :class="aviso.tipo === 'exito' ? 'bg-emerald-600' : 'bg-red-600'"
>
    {{ aviso.texto }}
</div>
</AppLayout>
</template>