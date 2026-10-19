<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

function volverAFormularios() {
    window.location.href = '/gestion-clientes/formularios-terceros'
}

const props = defineProps({
    tiposTercero: {
        type: Array,
        default: () => [],
    },

    tiposFormulario: {
        type: Array,
        default: () => [],
    },
     tiposIdentificacion: {
        type: Array,
        default: () => [],
    },

    ciiu: {
    type: Array,
    default: () => [],
},

    vendedores: {
        type: Array,
        default: () => [],
    },

        modoConsulta: {
        type: Boolean,
        default: false,
    },

    modoEdicion: {
    type: Boolean,
    default: false,
},

   idFormulario: {
        type: [Number, String],
        default: null,
    },

    borradorId: {
        type: String,
        default: null,
    },
})

const paises = ref([])
const departamentos = ref([])
const municipios = ref([])


const cargandoPaises = ref(false)
const cargandoDepartamentos = ref(false)
const cargandoMunicipios = ref(false)
/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN
|--------------------------------------------------------------------------
*/

const totalPasos = 9
const pasoActual = ref(1)
const mostrarFormulario = ref(false)
const cargandoConsulta = ref(false)
const errorConsulta = ref('')
const documentosExistentes = ref({})
const mostrarDeclaraciones = ref(false)
const firmaRepresentante = ref('')
const declaracionesAceptadas = ref(false)
const canvasFirma = ref(null)
const firmaGuardada = ref(false)
const adjuntosBorrador = ref({})



function abrirDeclaraciones() {
    mostrarDeclaraciones.value = true
}

function cerrarDeclaraciones() {
    mostrarDeclaraciones.value = false
}

function aceptarDeclaraciones() {
    form.acepta_politica_datos = true
    form.acepta_autorizacion = true
    mostrarDeclaraciones.value = false
}

function rechazarDeclaraciones() {
    form.acepta_politica_datos = false
    form.acepta_autorizacion = false
    mostrarDeclaraciones.value = false
}

// ── Firma ──────────────────────────────────────────────────

const firmaCanvasAlto = 180
let dibujando = false

const configurarContextoFirma = (ctx) => {
    ctx.lineWidth = 2.4
    ctx.lineCap = 'round'
    ctx.lineJoin = 'round'
    ctx.strokeStyle = '#1e293b'
}

const prepararCanvasFirma = () => {
    const canvas = canvasFirma.value

    if (!canvas) {
        return
    }

    const rect = canvas.getBoundingClientRect()
    const ratio = window.devicePixelRatio || 1

    const ancho = Math.max(
        Math.round(rect.width * ratio),
        1
    )

    const alto = Math.round(
        firmaCanvasAlto * ratio
    )

    canvas.style.height =
        `${firmaCanvasAlto}px`

    if (
        canvas.width !== ancho ||
        canvas.height !== alto
    ) {
        canvas.width = ancho
        canvas.height = alto
    }

    const ctx = canvas.getContext('2d')

    ctx.setTransform(
        ratio,
        0,
        0,
        ratio,
        0,
        0
    )

    configurarContextoFirma(ctx)
}

const puntoFirma = (e) => {
    const canvas = canvasFirma.value
    const rect = canvas.getBoundingClientRect()

    return {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top,
    }
}

const iniciarFirma = (e) => {
    if (
        e.button !== undefined &&
        e.button !== 0
    ) {
        return
    }

    e.preventDefault()

    prepararCanvasFirma()

    dibujando = true

    e.currentTarget?.setPointerCapture?.(
        e.pointerId
    )

    const ctx =
        canvasFirma.value.getContext('2d')

    const punto = puntoFirma(e)

    ctx.beginPath()

    ctx.moveTo(
        punto.x,
        punto.y
    )

    firmaGuardada.value = false
}

const dibujarFirma = (e) => {
    if (!dibujando) {
        return
    }

    e.preventDefault()

    const ctx =
        canvasFirma.value.getContext('2d')

    const punto = puntoFirma(e)

    ctx.lineTo(
        punto.x,
        punto.y
    )

    ctx.stroke()

    firmaGuardada.value = false
}

const terminarFirma = (e) => {
    if (!dibujando) {
        return
    }

    dibujando = false

    e?.currentTarget?.releasePointerCapture?.(
        e.pointerId
    )
}

const firmaTieneContenido = () => {
    const canvas = canvasFirma.value

    if (!canvas) {
        return false
    }

    const ctx = canvas.getContext('2d')

    const datos = ctx.getImageData(
        0,
        0,
        canvas.width,
        canvas.height
    ).data

    for (
        let i = 3;
        i < datos.length;
        i += 4
    ) {
        if (datos[i] !== 0) {
            return true
        }
    }

    return false
}

const limpiarFirma = () => {
    const canvas = canvasFirma.value

    if (!canvas) {
        return
    }

    prepararCanvasFirma()

    const ctx =
        canvas.getContext('2d')

    ctx.clearRect(
        0,
        0,
        canvas.width,
        canvas.height
    )

    configurarContextoFirma(ctx)

    firmaRepresentante.value = ''

    form.firma = null

    form.rl_firma_url = ''

    firmaGuardada.value = false

    dibujando = false
}

const guardarFirma = () => {
    const canvas = canvasFirma.value

    if (!canvas) {
        return
    }

    if (!firmaTieneContenido()) {
        alert(
            'Primero debe realizar o cargar una firma.'
        )

        return
    }

    firmaRepresentante.value =
        canvas.toDataURL('image/png')

    form.firma =
        firmaRepresentante.value

    firmaGuardada.value = true

    guardarBorrador()
}

const cargarFirma = (event) => {
    const archivo =
        event.target.files?.[0]

    if (!archivo) {
        return
    }

    if (
        ![
            'image/jpeg',
            'image/png',
        ].includes(archivo.type)
    ) {
        alert(
            'La firma debe estar en formato JPG o PNG.'
        )

        event.target.value = ''

        return
    }

    const lector = new FileReader()

    lector.onload = (e) => {
        const imagen = new Image()

        imagen.onload = () => {
            const canvas = canvasFirma.value

            if (!canvas) {
                return
            }

            prepararCanvasFirma()

            const margen = 40

            const anchoDisponible =
                canvas.width - margen * 2

            const altoDisponible =
                canvas.height - margen * 2

            const escala = Math.min(
                anchoDisponible / imagen.width,
                altoDisponible / imagen.height,
                1
            )

            const ancho =
                imagen.width * escala

            const alto =
                imagen.height * escala

            const x =
                (canvas.width - ancho) / 2

            const y =
                (canvas.height - alto) / 2

            const ctx =
                canvas.getContext('2d')

            ctx.drawImage(
                imagen,
                x,
                y,
                ancho,
                alto
            )

            firmaRepresentante.value =
                canvas.toDataURL('image/png')

            form.firma =
                firmaRepresentante.value

            firmaGuardada.value = false
        }

        imagen.src = e.target.result
    }

    lector.readAsDataURL(archivo)
}

function formatearMoneda(valor) {
    if (valor === null || valor === undefined || valor === '') {
        return ''
    }


console.log('FORMATEANDO:', valor)
    let texto = String(valor)

    // Dejar solamente números y coma
    texto = texto.replace(/[^\d,]/g, '')

    // Permitir solamente una coma decimal
    const partes = texto.split(',')

    let entero = partes[0] || ''
    let decimal = partes[1] || ''

    // Máximo 4 decimales
    decimal = decimal.slice(0, 4)

    // Quitar ceros innecesarios al inicio
    entero = entero.replace(/^0+(?=\d)/, '')

    // Agregar separador de miles automáticamente
    entero = entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.')

    return decimal !== ''
        ? `${entero},${decimal}`
        : entero
}

function convertirMonedaParaBD(valor) {
    if (
        valor === null ||
        valor === undefined ||
        valor === ''
    ) {
        return null
    }

    return String(valor)
        .replace(/\./g, '')
        .replace(',', '.')
}

function agregarReferenciaBancaria() {
    form.referencias_bancarias.push({
        nombre_banco: '',
        sucursal_banco: '',
        clase_cuenta: '',
        numero_cuenta: null,
    })
}

function eliminarReferenciaBancaria(index) {
    if (form.referencias_bancarias.length > 1) {
        form.referencias_bancarias.splice(index, 1)
    }
}

const seleccionarAdjunto = async (campo, event) => {
    const archivo = event.target.files?.[0] ?? null

    if (!archivo) {
        form.adjuntos[campo] = null
        delete adjuntosBorrador.value[campo]
        return
    }

    // Se conserva el File para el envío actual
    form.adjuntos[campo] = archivo

    try {
        const datos = new FormData()

        datos.append('campo', campo)
        datos.append('archivo', archivo)
        datos.append('borrador_id', borradorId.value)

        const respuesta = await axios.post(
            '/gestion-clientes/registro-tercero/borrador/adjunto',
            datos,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            }
        )

        if (respuesta.data?.ok && respuesta.data?.adjunto) {
            adjuntosBorrador.value[campo] =
                respuesta.data.adjunto

            console.log(
                'Adjunto guardado en borrador:',
                campo,
                respuesta.data.adjunto
            )

            guardarBorrador()
        }

    } catch (error) {
        console.error(
            'Error guardando adjunto en borrador:',
            error
        )

        form.adjuntos[campo] = null

        delete adjuntosBorrador.value[campo]

        if (error.response?.data?.message) {
            console.error(
                error.response.data.message
            )
        }
    }
}

const abrirDocumentoAdjunto = (url) => {
    window.open(url, '_blank', 'noopener,noreferrer')
}

const eliminarAdjunto = (campo) => {
    form.adjuntos[campo] = null
}

const actividadEconomicaSeleccionada = computed(() => {
    const codigo = String(form.codigo_ciiu ?? '').trim()

    const item = props.ciiu.find(
        ciiu => String(ciiu.codigo_ciiu ?? '').trim() === codigo
    )

    return String(item?.actividad_economica ?? '').trim()
})

const pasos = [
    {
        numero: 1,
        titulo: 'Información general',
        descripcion: 'Datos principales del tercero',
    },
    {
        numero: 2,
        titulo: 'Direcciones de envío',
        descripcion: 'Direcciones para entrega',
    },
    {
        numero: 3,
        titulo: 'Socios',
        descripcion: 'Información de los socios',
    },
    {
        numero: 4,
        titulo: 'Información financiera y tributaria',
        descripcion: 'Información financiera y tributaria',
    },
    {
        numero: 5,
        titulo: 'Referencias comerciales',
        descripcion: 'Proveedores y clientes',
    },
    {
        numero: 6,
        titulo: 'Referencias bancarias',
        descripcion: 'Información bancaria',
    },
    {
    numero: 7,
    titulo: 'Información SGC',
    descripcion: 'LA/FT, activos virtuales, OEA y PEP',
    },
    {
    numero: 8,
    titulo: 'Adjuntos y validaciones',
    descripcion: 'Aceptación de declaraciones y adjuntos',
},
   {
        numero: 9,
        titulo: 'Confirmación',
        descripcion: 'Revise la información antes de enviar',
    },
]



/*
|--------------------------------------------------------------------------
| FORMULARIO
|--------------------------------------------------------------------------
*/



const form = useForm({

    id_frm: null,

    // =========================================================
    // SELECCIÓN INICIAL
    // =========================================================

    id_tipo_tercero: null,
    id_tipo_frm: null,
    tipo_persona: '',

    // =========================================================
    // INFORMACIÓN GENERAL
    // TER_frm_tercero
    // =========================================================

    fecha_frm: new Date().toISOString().split('T')[0],
    vendedor: '',

    tipo_doc: '',
    numero_doc: '',
    razon_social: '',

    tiene_establecimiento: null,
    nombre_establecimiento: '',

    direccion: '',

    id_pais: null,
    id_ciudad: null,
    id_departamento: null,

    telefono: null,
    celular1: null,
    celular2: null,

    email: '',
    email_facturacion: '',

    codigo_ciiu: null,

    // =========================================================
    // REPRESENTANTE LEGAL
    // =========================================================

    rl_nombre: '',
    rl_tipo_doc: '',
    rl_numero_doc: '',
    rl_email: '',

    // Firma
    rl_firma_url: '',
    firma: null,
// =========================================================
    // ADJUNTOS
    // =========================================================

    adjuntos: {
    carta_instrucciones: null,
    pagare: null,
    certificado_existencia: null,
    copia_rut: null,
    estados_financieros: null,
    cedula_rl: null,
    certificacion_bancaria: null,
    certificaciones: null,
},

    // =========================================================
    // TESORERÍA
    // =========================================================

    tesoreria_nombre: '',
    tesoreria_telefono: null,
    tesoreria_email: '',

    // =========================================================
    // DIRECCIONES DE ENVÍO
    // TER_direccion_envio
    // =========================================================

    direcciones_envio: [
        {
            direccion: '',

            // País seleccionado desde Max/360
            id_pais: null,
            pais: '',

            // Departamento seleccionado desde Max/360
            id_departamento:null,

            // Municipio seleccionado desde Max/360
            id_municipio: null,

            barrio: '',
            observaciones: '',

             departamentos: [],
        cargandoDepartamentos: false,

            municipios: [],
            cargandoMunicipios: false,
        },
    ],

    // =========================================================
    // INFORMACIÓN FINANCIERA
    // TER_informacion_financiera
    // =========================================================

    fecha_corte: new Date().toISOString().split('T')[0],

    activos: null,
    pasivos: null,
    ingresos: null,
    egresos: null,

    // =========================================================
    // DECLARACIONES TRIBUTARIAS
    // =========================================================

    responsable_iva: null,
    declara_renta: null,
    gran_contribuyente: null,
    autoretenedor: null,

    // =========================================================
    // INFORMACIÓN SGC
    // TER_informacion_sgc
    // =========================================================

    r_conoce_LA_FT: null,
    r_obligado_reportar: null,
    r_tiene_oficial_cumplimiento: null,

    r_nombre_oficial_cumplimiento: '',
    r_celular_oficial_cumplimiento: null,
    r_email_oficial_cumplimiento: '',

    a_maneja_activos_viirtuales: null,
    a_cuales_activos_virtuales: '',
    a_tranfiere_activos_virtuales: null,

    o_tiene_oea: null,
    o_cual_oea: '',

    p_persona_publicamente_expuesta: null,
    p_cargo_desempeña: '',
    p_fecha_inicio_cargo: '',
    p_finalizacion_cargo: '',
    p_familiar_pep: null,

    // =========================================================
    // REFERENCIA BANCARIA
    // TER_referencia_bancaria
    // =========================================================
referencias_bancarias: [
    {
        nombre_banco: '',
        sucursal_banco: '',
        clase_cuenta: '',
        numero_cuenta: null,
    },
],
    // =========================================================
    // REFERENCIAS COMERCIALES
    // =========================================================

 proveedores: [
    {
        nombre_razon_social: '',
        tipo_identificacion: '',
        numero_identificacion: '',
        direccion: '',

        id_pais: '',
        id_departamento: '',
        id_ciudad: '',

        telefono: '',
        celular: '',
        email: '',

        departamentos: [],
        cargandoDepartamentos: false,

        municipios: [],
        cargandoMunicipios: false,
    },
],


    clientes: [
    {
        nombre_razon_social: '',
        tipo_identificacion: '',
        numero_identificacion: '',
        direccion: '',

        id_pais: '',
        id_departamento: '',
        id_ciudad: '',

        telefono: '',
        celular: '',
        email: '',

        departamentos: [],
        cargandoDepartamentos: false,

        municipios: [],
        cargandoMunicipios: false,
    },
],

    // =========================================================
    // SOCIOS
    // TER_socio
    // =========================================================

    socios: [
        {
            nombres: '',
            apellidos: '',
            tipo_identificacion: '',
            numero_identificacion: '',
            porcentaje_participacion: null,
        },
    ],

    // =========================================================
    // AUTORIZACIÓN
    // TER_frm_tercero
    // =========================================================

    acepta_autorizacion: false,
    acepta_politica_datos: null,
})

const agregarDireccion = () => {
    form.direcciones_envio.push({
        direccion: '',
        id_pais: '',
        pais: '',
        id_departamento: '',
        id_municipio: '',
        barrio: '',
        observaciones: '',
        departamentos: [],
        cargandoDepartamentos: false,
        municipios: [],
        cargandoMunicipios: false,
    })
}


const cargarDepartamentosDireccion = async (direccion) => {
    direccion.departamentos = []
    direccion.id_departamento = ''
    direccion.id_municipio = ''
    direccion.municipios = []

    if (paisEsExtranjero(direccion.id_pais)) {
        direccion.barrio = ''
        return
    }

    if (!direccion.id_pais) return

    direccion.cargandoDepartamentos = true

    try {
        const response = await fetch(
            `/gestion-clientes/departamentos?pais=${encodeURIComponent(direccion.id_pais)}`
        )

        if (!response.ok) {
            throw new Error('No fue posible cargar los departamentos.')
        }

        const data = await response.json()

        direccion.departamentos = Array.isArray(data) ? data : []

    } catch (error) {
        console.error(
            'Error cargando departamentos de la dirección:',
            error
        )

        direccion.departamentos = []

    } finally {
        direccion.cargandoDepartamentos = false
    }
}

const cargarMunicipiosDireccion = async (direccion) => {
    direccion.municipios = []
    direccion.id_municipio = ''

    if (!direccion.id_departamento) {
        return
    }

    direccion.cargandoMunicipios = true

    try {
        const response = await fetch(
            `/gestion-clientes/municipios/${encodeURIComponent(direccion.id_departamento)}`
        )

        if (!response.ok) {
            throw new Error('No fue posible cargar las ciudades.')
        }

        const data = await response.json()

        direccion.municipios = Array.isArray(data) ? data : []
    } catch (error) {
        console.error(
            'Error cargando ciudades de la dirección:',
            error
        )

        direccion.municipios = []
    } finally {
        direccion.cargandoMunicipios = false
    }
}
const eliminarDireccion = (index) => {
    if (form.direcciones_envio.length <= 1) {
        return
    }

    form.direcciones_envio.splice(index, 1)
}


const cargarDepartamentosProveedor = async (proveedor) => {
    proveedor.departamentos = []
    proveedor.id_departamento = ''
    proveedor.id_ciudad = ''
    proveedor.municipios = []

    if (!proveedor.id_pais || paisEsExtranjero(proveedor.id_pais)) {
        return
    }

    proveedor.cargandoDepartamentos = true

    try {
        const response = await fetch(
            `/gestion-clientes/departamentos?pais=${encodeURIComponent(proveedor.id_pais)}`
        )

        if (!response.ok) {
            throw new Error('No fue posible cargar los departamentos.')
        }

        const data = await response.json()
        proveedor.departamentos = Array.isArray(data) ? data : []

    } catch (error) {
        console.error(
            'Error cargando departamentos del proveedor:',
            error
        )

        proveedor.departamentos = []
    } finally {
        proveedor.cargandoDepartamentos = false
    }
}


const cargarMunicipiosProveedor = async (proveedor) => {
    proveedor.municipios = []
    proveedor.id_ciudad = ''

    if (!proveedor.id_departamento) {
        return
    }

    proveedor.cargandoMunicipios = true

    try {
        const response = await fetch(
            `/gestion-clientes/municipios/${encodeURIComponent(proveedor.id_departamento)}`
        )

        if (!response.ok) {
            throw new Error('No fue posible cargar las ciudades.')
        }

        const data = await response.json()

        proveedor.municipios = Array.isArray(data) ? data : []

    } catch (error) {
        console.error(
            'Error cargando ciudades del proveedor:',
            error
        )

        proveedor.municipios = []
    } finally {
        proveedor.cargandoMunicipios = false
    }
}


const cargarDepartamentosCliente = async (cliente) => {
    cliente.departamentos = []
    cliente.municipios = []
    cliente.id_departamento = ''
    cliente.id_ciudad = ''

    if (!cliente.id_pais || paisEsExtranjero(cliente.id_pais)) {
        return
    }

    cliente.cargandoDepartamentos = true

    try {
        const response = await fetch(
            `/gestion-clientes/departamentos?pais=${encodeURIComponent(cliente.id_pais)}`
        )

        if (!response.ok) {
            throw new Error('No fue posible cargar los departamentos.')
        }

        const data = await response.json()

        cliente.departamentos = Array.isArray(data) ? data : []

    } catch (error) {
        console.error(
            'Error cargando departamentos del cliente:',
            error
        )

        cliente.departamentos = []
    } finally {
        cliente.cargandoDepartamentos = false
    }
}


const cargarMunicipiosCliente = async (cliente) => {
    cliente.municipios = []
    cliente.id_ciudad = ''

    if (!cliente.id_departamento) {
        return
    }

    cliente.cargandoMunicipios = true

    try {
        const response = await fetch(
            `/gestion-clientes/municipios/${encodeURIComponent(cliente.id_departamento)}`
        )

        if (!response.ok) {
            throw new Error('No fue posible cargar las ciudades.')
        }

        const data = await response.json()

        cliente.municipios = Array.isArray(data) ? data : []

    } catch (error) {
        console.error(
            'Error cargando ciudades del cliente:',
            error
        )

        cliente.municipios = []
    } finally {
        cliente.cargandoMunicipios = false
    }
}
const agregarSocio = () => {
    form.socios.push({
        nombres: '',
        apellidos: '',
        tipo_identificacion: '',
        numero_identificacion: '',
        porcentaje_participacion: '',
    })
}

const eliminarSocio = (index) => {
    if (form.socios.length <= 1) {
        return
    }

    form.socios.splice(index, 1)
}

const agregarProveedor = () => {
    form.proveedores.push({
        nombre_razon_social: '',
        tipo_identificacion: '',
        numero_identificacion: '',
        direccion: '',

        id_pais: '',
        id_departamento: '',
        id_ciudad: '',

        telefono: '',
        celular: '',
        email: '',

        departamentos: [],
        cargandoDepartamentos: false,

        municipios: [],
        cargandoMunicipios: false,
    })
}

const eliminarProveedor = (index) => {
    if (form.proveedores.length <= 1) {
        return
    }

    form.proveedores.splice(index, 1)
}

const agregarCliente = () => {
    form.clientes.push({
        nombre_razon_social: '',
        tipo_identificacion: '',
        numero_identificacion: '',
        direccion: '',

        id_pais: '',
        id_departamento: '',
        id_ciudad: '',

        telefono: '',
        celular: '',
        email: '',

        departamentos: [],
        cargandoDepartamentos: false,

        municipios: [],
        cargandoMunicipios: false,
    })
}

const eliminarCliente = (index) => {
    if (form.clientes.length <= 1) {
        return
    }

    form.clientes.splice(index, 1)
}

/*
|--------------------------------------------------------------------------
| COMPUTADAS
|--------------------------------------------------------------------------
*/

const porcentajeProgreso = computed(() => {
    return Math.round((pasoActual.value / totalPasos) * 100)
})

const tipoTerceroSeleccionado = computed(() => {
    return props.tiposTercero.find(
        item =>
            Number(item.IdTipo_tercero) ===
            Number(form.id_tipo_tercero)
    )
})

const tipoFormularioSeleccionado = computed(() => {
    return props.tiposFormulario.find(
        item =>
            Number(item.IdTipo_frm) ===
            Number(form.id_tipo_frm)
    )
})

const esNatural = computed(() => {
    return form.tipo_persona === 'Natural'
})

const esJuridica = computed(() => {
    return form.tipo_persona === 'Jurídica'
})

const puedeIniciarFormulario = computed(() => {
    return (
        form.id_tipo_tercero &&
        form.id_tipo_frm
    )
})

const normalizarTexto = (texto) =>
    String(texto ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase()

// true SOLO cuando hay un país seleccionado y es distinto de Colombia
function paisEsExtranjero(idPais) {
    if (idPais === null || idPais === undefined || idPais === '') {
        return false
    }

    const pais = paises.value.find(
        (p) => String(p.codigo) === String(idPais)
    )

    if (!pais) {
        return false
    }

    return normalizarTexto(pais.nombre) !== 'colombia'
}

const puedeAvanzar = computed(() => {

    if (pasoActual.value === 1) {
        return (
            form.tipo_persona &&
            form.tipo_doc &&
            form.numero_doc &&
            form.razon_social &&
            form.direccion &&
            (
                paisEsExtranjero(form.id_pais) ||
                (form.id_ciudad && form.id_departamento)
            )
        )
    }

    return true
})

/*
|--------------------------------------------------------------------------
| NOMBRES
|--------------------------------------------------------------------------
*/

function nombreTipoTercero() {
    return (
        tipoTerceroSeleccionado.value?.tipo_tercero?.trim() || ''
    )
}

function nombreTipoFormulario() {
    return (
        tipoFormularioSeleccionado.value?.Tipo_frm?.trim() || ''
    )
}

function nombreTipoIdentificacion(codigo) {
    if (codigo === null || codigo === undefined || codigo === '') {
        return '—'
    }

    return (
        props.tiposIdentificacion.find(
            tipo => String(tipo.codigo).trim() === String(codigo).trim()
        )?.nombre?.trim() || codigo
    )
}

function mostrarValor(valor) {
    if (valor === null || valor === undefined || valor === '') {
        return '—'
    }

    return valor
}

function mostrarSiNo(valor) {
    if (valor === true || valor === 1 || valor === '1') {
        return 'Sí'
    }

    if (valor === false || valor === 0 || valor === '0') {
        return 'No'
    }

    return 'No diligenciado'
}

function nombreDepartamento(codigo) {
    return (
        departamentos.value.find(
            item => String(item.codigo) === String(codigo)
        )?.nombre ||
        codigo ||
        ''
    )
}

function nombreMunicipio(codigo, lista = municipios.value) {
    return (
        lista.find(
            item => String(item.codigo) === String(codigo)
        )?.nombre ||
        codigo ||
        ''
    )
}

function mostrarFirma() {
    if (form.firma || form.rl_firma_url) {
        return 'Firma adjunta'
    }

    return 'No adjunta'
}

/*
|--------------------------------------------------------------------------
| TIPO DE TERCERO
|--------------------------------------------------------------------------
*/

function seleccionarTipoTercero(id) {
    form.id_tipo_tercero = id

    // Al cambiar de tipo de tercero, el formulario debe volver a seleccionarse.
    form.id_tipo_frm = ''

    guardarBorrador()
}

function cambiarTipoTercero() {
    form.id_tipo_tercero = ''
    form.id_tipo_frm = ''

    guardarBorrador()
}

function soloNumeros(valor) {
    return String(valor ?? '').replace(/\D/g, '')
}

function soloDecimal(valor) {
    return String(valor ?? '')
        .replace(',', '.')
        .replace(/[^\d.]/g, '')
        .replace(/(\..*)\./g, '$1')
}


/*
|--------------------------------------------------------------------------
| PORCENTAJE DE PARTICIPACIÓN DE CADA SOCIO
| Cada socio maneja su porcentaje de forma individual.
| Puede tener entre 0 % y 100 %.
|--------------------------------------------------------------------------
*/

function limitarPorcentaje(valor) {
    let limpio = String(valor ?? '')
        .replace(',', '.')
        .replace(/[^\d.]/g, '')
        .replace(/(\..*)\./g, '$1')

    const [entero, decimales] = limpio.split('.')

    if (decimales !== undefined) {
        limpio = entero + '.' + decimales.slice(0, 2)
    }

    const numero = parseFloat(limpio)

    if (isNaN(numero)) {
        return limpio
    }

    // Cada socio puede tener máximo 100 %
    if (numero > 100) {
        return '100'
    }

    return limpio
}

function onPorcentajeInput(event, socio) {
    const valor = limitarPorcentaje(event.target.value)

    // El porcentaje pertenece únicamente a este socio
    socio.porcentaje_participacion = valor

    // Actualiza visualmente el campo
    event.target.value = valor
}

// =====================================================
// CONSULTAS A INTEGRACIÓN MAX
// =====================================================
async function cargarPaises() {
    cargandoPaises.value = true

    try {
        const response = await fetch('/gestion-clientes/paises')

        if (!response.ok) {
            throw new Error('No fue posible cargar los países.')
        }

        const data = await response.json()

        paises.value = Array.isArray(data) ? data : []

    } catch (error) {
        console.error('Error cargando países:', error)
        paises.value = []
    } finally {
        cargandoPaises.value = false
    }
}


async function cargarDepartamentos(conservarSeleccion = false) {
    const departamentoGuardado = form.id_departamento
    const ciudadGuardada = form.id_ciudad

    departamentos.value = []
    municipios.value = []

    if (!conservarSeleccion) {
        form.id_departamento = ''
        form.id_ciudad = ''
    }

    if (!form.id_pais || paisEsExtranjero(form.id_pais)) {
        return
    }

    cargandoDepartamentos.value = true

    try {
        const response = await fetch(
            `/gestion-clientes/departamentos?pais=${encodeURIComponent(form.id_pais)}`
        )

        if (!response.ok) {
            throw new Error('No fue posible cargar los departamentos.')
        }

        const data = await response.json()
        departamentos.value = Array.isArray(data) ? data : []

        if (
            conservarSeleccion &&
            departamentoGuardado != null &&
            departamentoGuardado !== ''
        ) {
            form.id_departamento = departamentoGuardado
        }

        if (conservarSeleccion && form.id_departamento) {
            await cargarMunicipios(form.id_departamento, true)

            if (ciudadGuardada != null && ciudadGuardada !== '') {
                form.id_ciudad = ciudadGuardada
            }
        }
    } catch (error) {
        console.error('Error cargando departamentos:', error)
        departamentos.value = []
    } finally {
        cargandoDepartamentos.value = false
    }
}

async function cargarMunicipios(codigoDepartamento, conservarSeleccion = false) {
    const ciudadGuardada = form.id_ciudad

    municipios.value = []

    if (!conservarSeleccion) {
        form.id_ciudad = ''
    }

    if (!codigoDepartamento) {
        return
    }

    cargandoMunicipios.value = true

    try {
        const response = await fetch(
            `/gestion-clientes/municipios/${encodeURIComponent(codigoDepartamento)}`
        )

        if (!response.ok) {
            throw new Error('No fue posible cargar las ciudades.')
        }

        const data = await response.json()
        municipios.value = Array.isArray(data) ? data : []

        if (conservarSeleccion && ciudadGuardada != null) {
            form.id_ciudad = ciudadGuardada
        }
    } catch (error) {
        console.error('Error cargando ciudades:', error)
        municipios.value = []
    } finally {
        cargandoMunicipios.value = false
    }
}



/*
|--------------------------------------------------------------------------
| TIPO DE FORMULARIO
|--------------------------------------------------------------------------
*/

function seleccionarTipoFormulario(event) {
    form.id_tipo_frm = event.target.value

    guardarBorrador()
}

/*
|--------------------------------------------------------------------------
| INICIAR
|--------------------------------------------------------------------------
*/

function iniciarFormulario() {

    if (!puedeIniciarFormulario.value) {
        return
    }

    form.clearErrors()

    mostrarFormulario.value = true
    pasoActual.value = 1

    guardarBorrador()
    window.scrollTo({
        top: 0,
        behavior: 'smooth',
    })
}

/*
|--------------------------------------------------------------------------
| PERSONA
|--------------------------------------------------------------------------
*/

function seleccionarTipoPersona(tipo) {

    form.tipo_persona = tipo

    if (tipo === 'Natural') {

        form.rl_nombre = ''
        form.rl_tipo_doc = ''
        form.rl_numero_doc = ''
        form.rl_email = ''
        form.rl_firma_url = ''

        form.tesoreria_nombre = ''
        form.tesoreria_telefono = ''
        form.tesoreria_email = ''
    }

    guardarBorrador()
}

/*
|--------------------------------------------------------------------------
| NAVEGACIÓN
|--------------------------------------------------------------------------
*/

function siguientePaso() {

    if (!puedeAvanzar.value) {
        return
    }

    if (pasoActual.value < totalPasos) {

        pasoActual.value++

        guardarBorrador()

        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        })
    }
}

function anteriorPaso() {

    if (pasoActual.value > 1) {

        pasoActual.value--

        guardarBorrador()

        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        })
    }
}

function irPaso(numero) {

    if (numero <= pasoActual.value) {

        pasoActual.value = numero

        guardarBorrador()

        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        })
    }
}

/*
|--------------------------------------------------------------------------
| SÍ / NO
|--------------------------------------------------------------------------
*/
function seleccionarEstablecimiento(valor) {
    form.tiene_establecimiento = valor

    if (!valor) {
        form.nombre_establecimiento = ''
    }
}
function respuestaSiNo(campo, valor) {


    form[campo] = valor

    if (valor === false) {

        if (campo === 'r_tiene_oficial_cumplimiento') {

            form.r_nombre_oficial_cumplimiento = ''
            form.r_celular_oficial_cumplimiento = ''
            form.r_email_oficial_cumplimiento = ''
        }

        if (campo === 'a_maneja_activos_viirtuales') {

            form.a_cuales_activos_virtuales = ''
        }

        if (campo === 'o_tiene_oea') {

            form.o_cual_oea = ''
        }

        if (campo === 'p_persona_publicamente_expuesta') {

            form.p_cargo_desempeña = ''
            form.p_fecha_inicio_cargo = ''
            form.p_finalizacion_cargo = ''
        }
    }

    guardarBorrador()
}

/*
|--------------------------------------------------------------------------
| ERRORES
|--------------------------------------------------------------------------
*/

function errorCampo(campo) {
    return form.errors[campo] || ''
}

/*
|--------------------------------------------------------------------------
| REGISTRAR
|--------------------------------------------------------------------------
*/

const mostrarConfirmacionEnvio = ref(false)

function abrirConfirmacionEnvio() {
    if (form.processing) {
        return
    }

    mostrarConfirmacionEnvio.value = true
}

function cerrarConfirmacionEnvio() {
    if (form.processing) {
        return
    }

    mostrarConfirmacionEnvio.value = false
}

function confirmarEnvio() {

    if (form.processing) {
        return
    }

    mostrarConfirmacionEnvio.value = false

    // Evita que un guardado automático pendiente recree el borrador.
    clearTimeout(temporizadorBorrador)
    enviandoFormulario.value = true

    form.transform((data) => {

        // Quita datos que solo sirven para la pantalla (listas de
        // departamentos/municipios): enviarlas multiplica los campos del
        // POST y PHP (max_input_vars=1000) termina descartando campos.
        const limpiarFila = ({
            departamentos,
            municipios,
            cargandoDepartamentos,
            cargandoMunicipios,
            ...resto
        }) => resto

        const sinUbicacion = (fila) =>
            paisEsExtranjero(fila.id_pais)
                ? {
                    ...fila,
                    id_departamento: '',
                    id_municipio: '',
                    barrio: '',
                }
                : fila
const normalizarPorcentaje = (valor) => {
    if (valor === null || valor === undefined || valor === '') {
        return null
    }

    const texto = String(valor)
        .trim()
        .replace(',', '.')

    const numero = Number(texto)

    return Number.isFinite(numero)
        ? numero
        : null
}

        const payload = {
    ...data,
    borrador_id: borradorId.value,

           ...(paisEsExtranjero(data.id_pais)
    ? { id_departamento: null, id_ciudad: null }
    : {}),

            activos: convertirMonedaParaBD(data.activos),
            pasivos: convertirMonedaParaBD(data.pasivos),
            ingresos: convertirMonedaParaBD(data.ingresos),
            egresos: convertirMonedaParaBD(data.egresos),

            direcciones_envio: data.direcciones_envio
                .map(limpiarFila)
                .map(sinUbicacion),
            proveedores: data.proveedores.map(limpiarFila),
            clientes: data.clientes.map(limpiarFila),

            referencias_bancarias: data.referencias_bancarias.filter(
                (r) => r.nombre_banco || r.numero_cuenta
            ),
        }

        // Números de identificación: siempre texto (VARCHAR 30 en la BD).
        const aTexto = (valor) => String(valor ?? '').trim().slice(0, 30)

        payload.numero_doc = aTexto(data.numero_doc)
        payload.rl_numero_doc = aTexto(data.rl_numero_doc)

        payload.socios = data.socios.map((socio) => ({
            ...socio,
            numero_identificacion: aTexto(socio.numero_identificacion),
            porcentaje_participacion: normalizarPorcentaje(
                socio.porcentaje_participacion
            ),
        }))

        // País diferente a Colombia: no se envía departamento ni ciudad.
        const normalizarComercial = (fila) => ({
            ...fila,
            numero_identificacion: aTexto(fila.numero_identificacion),
        ...(paisEsExtranjero(fila.id_pais)
    ? { id_departamento: null, id_ciudad: null }
    : {}),
        })

        payload.proveedores = payload.proveedores.map(normalizarComercial)
        payload.clientes = payload.clientes.map(normalizarComercial)

        // El backend espera los archivos como campos planos
        // (carta_instrucciones, pagare, ...), no dentro de "adjuntos".
        Object.entries(data.adjuntos || {}).forEach(([campo, archivo]) => {
            if (archivo) {
                payload[campo] = archivo
            }
        })

        delete payload.adjuntos
        delete payload.acepta_politica_datos

        return payload
    })

    form.post(
        route('gestion-clientes.registro-tercero.store'),
        {
            preserveScroll: true,
            // Con archivos se necesita multipart; sin archivos se envía JSON,
            // que no está sujeto al límite max_input_vars de PHP.
            forceFormData: Object.values(form.adjuntos || {}).some(Boolean),

          onSuccess: () => {

    console.log('========== FORMULARIO ENVIADO ==========')
    console.log('ON SUCCESS EJECUTADO')
    console.log('========================================')

    eliminarBorrador()

    pasoActual.value = 1
    mostrarFormulario.value = false
    mostrarDeclaraciones.value = false
    mostrarConfirmacionEnvio.value = false

    form.reset()
    form.clearErrors()

    erroresEnvio.value = []
    mostrarModalError.value = false
    mostrarModalExito.value = true
},

           onError: (errors) => {
    console.error('========== ERROR FORMULARIO ==========')
    console.error('Errores Laravel:', errors)
    console.error('JSON:', JSON.stringify(errors, null, 2))
    console.error('======================================')

                mostrarConfirmacionEnvio.value = false

                // Solo se listan los errores de campos; los errores
                // internos del servidor se informan con el mensaje general.
              erroresEnvio.value = Object.entries(errors)
    .map(([, mensaje]) => mensaje)
    .flat()
    .filter(Boolean)

                mostrarModalExito.value = false
                mostrarModalError.value = true
            },

            onFinish: () => {

                enviandoFormulario.value = false

            },
        }
    )
}


/*
|--------------------------------------------------------------------------
| PDF
|--------------------------------------------------------------------------
*/
const mostrarModalExito = ref(false)
const mostrarModalError = ref(false)
const erroresEnvio = ref([])

const mostrarVistaPdf = ref(false)
const urlVistaPdf = ref('')
const urlDescargaPdf = '/gestion-clientes/registro-tercero/pdf/descargar'

async function abrirVistaPdf() {
    try {
        const datos = JSON.parse(
            JSON.stringify(form.data(), (key, value) => {
                // Los archivos no se pueden enviar como JSON.
                // Para el PDF solo necesitamos saber si fueron seleccionados.
                if (value instanceof File) {
                    return true
                }

                return value
            })
        )

        const response = await axios.post(
            '/gestion-clientes/registro-tercero/pdf/previsualizar',
            {
                datos,
            },
            {
                responseType: 'blob',
            }
        )

        if (urlVistaPdf.value) {
            URL.revokeObjectURL(urlVistaPdf.value)
        }

        urlVistaPdf.value = URL.createObjectURL(
            new Blob([response.data], {
                type: 'application/pdf',
            })
        )

        mostrarModalExito.value = false
        mostrarVistaPdf.value = true

    } catch (error) {
        console.error(
            'Error generando la vista previa del PDF:',
            error
        )

        if (error.response?.data instanceof Blob) {
            const textoError = await error.response.data.text()

            console.error(
                'ERROR REAL DEL SERVIDOR:',
                textoError
            )
        } else {
            console.error(
                'Respuesta del servidor:',
                error.response?.data
            )
        }
    }
}

function cerrarVistaPdf() {
    mostrarVistaPdf.value = false

    if (urlVistaPdf.value) {
        URL.revokeObjectURL(urlVistaPdf.value)
    }

    urlVistaPdf.value = ''
}

function cerrarModalExito() {
    mostrarModalExito.value = false
}

function cerrarModalError() {
    mostrarModalError.value = false
}
/*
|--------------------------------------------------------------------------
| AUTOGUARDADO
|--------------------------------------------------------------------------
*/

const claveBorrador =
    'senco360_registro_tercero_borrador'

const ultimoGuardado = ref(null)
const guardando = ref(false)


function horaUltimoGuardado() {

    if (!ultimoGuardado.value) {
        return ''
    }

    return ultimoGuardado.value.toLocaleTimeString(
        'es-CO',
        {
            hour: '2-digit',
            minute: '2-digit'
        }
    )
}



const urlBorrador = '/gestion-clientes/registro-tercero/borrador'

const borradorId = ref(
    props.borradorId ||
    (
        typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : `${Date.now()}_${Math.random().toString(36).slice(2, 12)}`
    )
)

let temporizadorBorrador = null
let guardadoBorradorEnCurso = false
let guardadoBorradorPendiente = false
const enviandoFormulario = ref(false)

function guardarBorrador() {
    if (props.modoConsulta || props.modoEdicion) {
        return
    }

    if (
        form.processing ||
        enviandoFormulario.value
    ) {
        return
    }

    clearTimeout(temporizadorBorrador)

    temporizadorBorrador = setTimeout(
        guardarBorradorEnServidor,
        1500
    )
}


async function guardarBorradorEnServidor() {
   if (props.modoConsulta || props.modoEdicion) {
    return
}

    if (
        form.processing ||
        enviandoFormulario.value
    ) {
        return
    }

    // Serializar autoguardados: evitar que una petición antigua sobrescriba datos nuevos.
    if (guardadoBorradorEnCurso) {
        guardadoBorradorPendiente = true
        return
    }
    guardadoBorradorEnCurso = true

    try {

        /*
        |--------------------------------------------------------------------------
        | COPIA COMPLETA DEL FORMULARIO
        |--------------------------------------------------------------------------
        | Se guarda todo lo que actualmente exista dentro de form.
        */
        const datosForm = JSON.parse(
            JSON.stringify(form.data())
        )

        /*
        |--------------------------------------------------------------------------
        | DATOS COMPLETOS DEL BORRADOR
        |--------------------------------------------------------------------------
        */
        const datos = {
          borrador_id: borradorId.value,

            /*
            | Todo el formulario:
            | tipo de tercero, tipo de formulario, información general,
            | direcciones, socios, finanzas, proveedores, clientes,
            | referencias, SGC, declaraciones, etc.
            */
            form: datosForm,
             adjuntosBorrador:
        adjuntosBorrador.value,

            /*
            | Estado de navegación.
            */
            pasoActual: pasoActual.value,

            /*
            | Estado visual del formulario.
            */
            mostrarFormulario: mostrarFormulario.value,
            mostrarDeclaraciones: mostrarDeclaraciones.value,
            mostrarConfirmacionEnvio:
                mostrarConfirmacionEnvio.value,

            /*
            | Estado de firma.
            */
            firmaRepresentante:
                firmaRepresentante.value,

            firmaGuardada:
                firmaGuardada.value,

            /*
            | Fecha y hora del último guardado.
            */
            fecha: new Date().toISOString(),
        }

        /*
        |--------------------------------------------------------------------------
        | GUARDAR TODO EN EL SERVIDOR
        |--------------------------------------------------------------------------
        */
        const respuestaBorrador = await axios.post(urlBorrador, {
            datos: JSON.stringify(datos),
        })

        if (respuestaBorrador.data?.id_frm) {
            form.id_frm = respuestaBorrador.data.id_frm
        }

        ultimoGuardado.value = new Date()

        guardando.value = true

        setTimeout(() => {
            guardando.value = false
        }, 800)

    } catch (error) {

        console.error(
            'Error guardando borrador:',
            error
        )
    } finally {
        guardadoBorradorEnCurso = false
        if (guardadoBorradorPendiente) {
            guardadoBorradorPendiente = false
            guardarBorradorEnServidor()
        }
    }
}

async function recuperarBorrador() {

    try {

        /*
        |--------------------------------------------------------------------------
        | LIMPIEZA DE CACHÉ ANTIGUA DEL NAVEGADOR
        |--------------------------------------------------------------------------
        | El borrador oficial ahora está en el servidor.
        */
        localStorage.removeItem(claveBorrador)

        const { data } = await axios.get(urlBorrador, {
    params: {
        borrador_id: borradorId.value,
    },
})

        if (!data?.borrador) {
            return
        }

        const datos =
            typeof data.borrador === 'string'
                ? JSON.parse(data.borrador)
                : data.borrador

        if (!datos) {
            return
        }

        if (
    datos.adjuntosBorrador &&
    typeof datos.adjuntosBorrador === 'object'
) {
    adjuntosBorrador.value =
        datos.adjuntosBorrador
}

        /*
        |--------------------------------------------------------------------------
        | RESTAURAR TODO EL FORMULARIO
        |--------------------------------------------------------------------------
        */
        if (datos.form) {

            Object.keys(datos.form).forEach(campo => {

                if (
                    Object.prototype.hasOwnProperty.call(
                        form,
                        campo
                    )
                ) {
                    form[campo] = datos.form[campo]
                }

            })
        }

        if (datos.id_frm && !form.id_frm) {
            form.id_frm = datos.id_frm
        }

        /*
        |--------------------------------------------------------------------------
        | RESTAURAR PASO
        |--------------------------------------------------------------------------
        */
        if (
            datos.pasoActual !== undefined &&
            datos.pasoActual !== null
        ) {
            pasoActual.value =
                Number(datos.pasoActual) || 1
        }

        /*
        |--------------------------------------------------------------------------
        | RESTAURAR ESTADO DEL FORMULARIO
        |--------------------------------------------------------------------------
        */
        if (
            datos.mostrarFormulario !== undefined
        ) {
            mostrarFormulario.value =
                Boolean(datos.mostrarFormulario)
        }

        if (
            datos.mostrarDeclaraciones !== undefined
        ) {
            mostrarDeclaraciones.value =
                Boolean(datos.mostrarDeclaraciones)
        }

        if (
            datos.mostrarConfirmacionEnvio !== undefined
        ) {
            mostrarConfirmacionEnvio.value =
                Boolean(datos.mostrarConfirmacionEnvio)
        }

        /*
        |--------------------------------------------------------------------------
        | RESTAURAR FIRMA
        |--------------------------------------------------------------------------
        */
        if (datos.firmaRepresentante) {

            firmaRepresentante.value =
                datos.firmaRepresentante

        }

        if (datos.firmaGuardada !== undefined) {

            firmaGuardada.value =
                Boolean(datos.firmaGuardada)

        }

        /*
        |--------------------------------------------------------------------------
        | ÚLTIMA FECHA DE GUARDADO
        |--------------------------------------------------------------------------
        */
        if (datos.fecha) {

            ultimoGuardado.value =
                new Date(datos.fecha)

        }

    } catch (error) {

        console.error(
            'Error recuperando borrador:',
            error
        )
    }
}

async function eliminarBorrador() {

    clearTimeout(temporizadorBorrador)

    ultimoGuardado.value = null

    try {
        localStorage.removeItem(claveBorrador)

        await axios.delete(urlBorrador, {
    params: {
        borrador_id: borradorId.value,
    },
})

    } catch (error) {

        console.error(
            'Error eliminando borrador:',
            error
        )
    }
}

async function cargarFormularioConsulta() {
    if ((!props.modoConsulta && !props.modoEdicion) || !props.idFormulario) {
    return
}

    cargandoConsulta.value = true
    errorConsulta.value = ''
    restaurandoUbicacionPrincipal = true

    try {
        const respuesta = await axios.get(
            `/gestion-clientes/formularios-terceros/${props.idFormulario}/detalle`
        )

        const detalle = respuesta.data
        const general = detalle.general

        if (!general) {
            throw new Error('No se encontró la información del formulario.')
        }

        const financiera = detalle.financiera?.[0] ?? {}
        const sgc = detalle.sgc?.[0] ?? {}
        const adjuntos = detalle.adjuntos?.[0] ?? {}

        const codigoCiiu = props.ciiu.find(
            item => Number(item.IdCodigo_ciiu) === Number(general.IdCodigo_ciiu)
        )?.codigo_ciiu ?? ''

        Object.assign(form, {
            id_frm: general.IdFrm,
            id_tipo_tercero: general.IdTipo_tercero,
            id_tipo_frm: general.IdTipo_frm,
            tipo_persona: general.tipo_persona ?? '',
            fecha_frm: general.Fecha_frm?.substring(0, 10) ?? '',
            vendedor: general.vendedor ?? '',
            tipo_doc: general.tipo_doc ?? '',
            numero_doc: general.numero_doc ?? '',
            razon_social: general.razon_social ?? '',
            tiene_establecimiento: general.tiene_establ_comercio ?? null,
            nombre_establecimiento: general.nombre_establecimiento ?? '',
            direccion: general.direccion ?? '',
            id_ciudad: general.IdCiudad ?? null,
            id_departamento: general.IdDepartamento ?? null,
            telefono: general.telefono ?? null,
            celular1: general.celular1 ?? null,
            celular2: general.celular2 ?? null,
            email: general.email ?? '',
            email_facturacion: general.email_facturacion ?? '',
            codigo_ciiu: codigoCiiu,
            rl_nombre: general.rl_nombre ?? '',
            rl_tipo_doc: general.rl_tipo_doc ?? '',
            rl_numero_doc: general.rl_numero_doc ?? '',
            rl_email: general.rl_email ?? '',
            rl_firma_url: general.rl_firma_url ?? '',
            tesoreria_nombre: general.tesoreria_nombre ?? '',
            tesoreria_telefono: general.tesoreria_telefono ?? null,
            tesoreria_email: general.tesoreria_email ?? '',

            fecha_corte: financiera.fecha_corte?.substring(0, 10) ?? '',
            activos: financiera.activos ?? null,
            pasivos: financiera.pasivos ?? null,
            ingresos: financiera.ingresos ?? null,
            egresos: financiera.egresos ?? null,
            responsable_iva: financiera.responsable_iva ?? null,
            declara_renta: financiera.declara_renta ?? null,
            gran_contribuyente: financiera.gran_contribuyente ?? null,
            autoretenedor: financiera.autoretenedor ?? null,

            r_conoce_LA_FT: sgc.r_conoce_LA_FT ?? null,
            r_obligado_reportar: sgc.r_obligado_reportar ?? null,
            r_tiene_oficial_cumplimiento: sgc.r_tiene_oficial_cumplimiento ?? null,
            r_nombre_oficial_cumplimiento: sgc.r_nombre_oficial_cumplimiento ?? '',
            r_celular_oficial_cumplimiento: sgc.r_celular_oficial_cumplimiento ?? null,
            r_email_oficial_cumplimiento: sgc.r_email_oficial_cumplimiento ?? '',
            a_maneja_activos_viirtuales: sgc.a_maneja_activos_viirtuales ?? null,
            a_cuales_activos_virtuales: sgc.a_cuales_activos_virtuales ?? '',
            a_tranfiere_activos_virtuales: sgc.a_tranfiere_activos_virtuales ?? null,
            o_tiene_oea: sgc.o_tiene_oea ?? null,
            o_cual_oea: sgc.o_cual_oea ?? '',
            p_persona_publicamente_expuesta: sgc.p_persona_publicamente_expuesta ?? null,
            p_cargo_desempeña: sgc.p_cargo_desempeña ?? '',
            p_fecha_inicio_cargo: sgc.p_fecha_inicio_cargo?.substring(0, 10) ?? '',
            p_finalizacion_cargo: sgc.p_finalizacion_cargo?.substring(0, 10) ?? '',
            p_familiar_pep: sgc.p_familiar_pep ?? null,

            socios: (detalle.socios ?? []).map(socio => ({
                nombres: socio.nombres ?? '',
                apellidos: socio.apellidos ?? '',
                tipo_identificacion: socio.tipo_doc ?? '',
                numero_identificacion: socio.numero_doc ?? '',
                porcentaje_participacion: socio.porcentaje_participacion ?? null,
            })),

            referencias_bancarias: (detalle.referencias_bancarias ?? []).map(ref => ({
                nombre_banco: ref.nombre_banco ?? '',
                sucursal_banco: ref.sucursal ?? '',
                clase_cuenta: ref.clase_cuenta ?? '',
                numero_cuenta: ref.numero_cuenta ?? '',
            })),

            direcciones_envio: (detalle.direcciones ?? []).map(dir => ({
                direccion: dir.direccion_envio ?? '',
                id_pais: dir.Id_cod_pais ?? null,
                pais: '',
                id_departamento: dir.Id_cod_dpto ?? null,
                id_municipio: dir.Id_cod_mpio ?? null,
                barrio: dir.barrio ?? '',
                observaciones: dir.observaciones ?? '',
                departamentos: [],
                cargandoDepartamentos: false,
                municipios: [],
                cargandoMunicipios: false,
            })),
        })

        const referencias = detalle.referencias_comerciales ?? []

        form.clientes = referencias
            .filter(ref => String(ref.tipo_referencia ?? '').toLowerCase().includes('cliente'))
            .map(ref => ({
                nombre_razon_social: ref.nombre_razon_social ?? '',
                tipo_identificacion: ref.tipo_doc ?? '',
                numero_identificacion: ref.numero_doc ?? '',
                direccion: ref.direccion ?? '',
                id_pais: '',
                id_departamento: ref.IdDepartamento ?? '',
                id_ciudad: ref.IdCiudad ?? '',
                telefono: ref.telefono ?? '',
                celular: ref.celular ?? '',
                email: ref.email ?? '',
                departamentos: [],
                cargandoDepartamentos: false,
                municipios: [],
                cargandoMunicipios: false,
            }))

        form.proveedores = referencias
            .filter(ref => String(ref.tipo_referencia ?? '').toLowerCase().includes('proveedor'))
            .map(ref => ({
                nombre_razon_social: ref.nombre_razon_social ?? '',
                tipo_identificacion: ref.tipo_doc ?? '',
                numero_identificacion: ref.numero_doc ?? '',
                direccion: ref.direccion ?? '',
                id_pais: '',
                id_departamento: ref.IdDepartamento ?? '',
                id_ciudad: ref.IdCiudad ?? '',
                telefono: ref.telefono ?? '',
                celular: ref.celular ?? '',
                email: ref.email ?? '',
                departamentos: [],
                cargandoDepartamentos: false,
                municipios: [],
                cargandoMunicipios: false,
            }))

        form.acepta_autorizacion = Boolean(general.acepta_autorizacion)
        documentosExistentes.value = adjuntos

        mostrarFormulario.value = true
        pasoActual.value = 1
    } catch (error) {
        console.error('Error cargando formulario para consulta:', error)
        errorConsulta.value = 'No fue posible cargar el formulario solicitado.'
    } finally {
        restaurandoUbicacionPrincipal = false
        cargandoConsulta.value = false
    }
}


/*
|--------------------------------------------------------------------------
| MONTAR / DESMONTAR
|--------------------------------------------------------------------------
*/

let intervaloBorrador = null
let restaurandoUbicacionPrincipal = false

onMounted(async () => {
    restaurandoUbicacionPrincipal = true

    try {
        await cargarPaises()

        if (props.modoConsulta || props.modoEdicion) {
    await cargarFormularioConsulta()
} else {
            await recuperarBorrador()

            if (form.id_pais && !paisEsExtranjero(form.id_pais)) {
                await cargarDepartamentos(true)
            } else {
                departamentos.value = []
                municipios.value = []
            }
        }
    } catch (error) {
        console.error('Error inicializando RegistroTercero:', error)
        errorConsulta.value = 'Ocurrió un error al cargar la información.'
    } finally {
        restaurandoUbicacionPrincipal = false
    }

   if (!props.modoConsulta && !props.modoEdicion) {
        intervaloBorrador = setInterval(() => {
            guardarBorrador()
        }, 30000)
    }
})

onBeforeUnmount(() => {
    if (intervaloBorrador) {
        clearInterval(intervaloBorrador)
    }

    clearTimeout(temporizadorBorrador)
})

watch(
    () => form.id_departamento,
    async (nuevoDepartamento, antiguoDepartamento) => {
        // Evitar que la restauración borre la ciudad guardada
        if (restaurandoUbicacionPrincipal) {
            return
        }

        if (nuevoDepartamento !== antiguoDepartamento) {
            form.id_ciudad = ''
        }

        await cargarMunicipios(nuevoDepartamento)
    }
)


watch(
    () => form.data(),
    () => {

        guardarBorrador()

    },
    {
        deep: true,
    }
)

</script>

<template>

<AppLayout title="Registro de Tercero">

    <div class="min-h-screen bg-slate-50 py-8">

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <!-- ===================================================== -->
            <!-- ENCABEZADO -->
            <!-- ===================================================== -->

            <div class="mb-8">

                <div class="flex items-center justify-between">

                    <div>

                        <h1 class="text-3xl font-bold text-slate-800">
                            Registro de Tercero
                        </h1>

                        <p class="mt-1 text-sm text-slate-500">
                            Complete la información requerida para registrar
                            un nuevo tercero.
                        </p>

                    </div>

                    <div
                        v-if="mostrarFormulario"
                        class="flex items-center gap-3"
                    >

                   <button
    type="button"
    @click="volverAFormularios"
    class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
>
    ← Volver
</button>
                        <div
                            class="hidden rounded-xl bg-white px-5 py-3 shadow-sm sm:block"
                        >

                            <div class="text-right">

                                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                                    Progreso
                                </p>

                                <p class="text-2xl font-bold text-blue-600">
                                    {{ porcentajeProgreso }}%
                                </p>

                            </div>

                        </div>

                    </div>

                </div>

                <div
                    v-if="mostrarFormulario"
                    class="mt-6 h-2 overflow-hidden rounded-full bg-slate-200"
                >

                    <div
                        class="h-full rounded-full bg-blue-600 transition-all duration-500"
                        :style="{
                            width: `${porcentajeProgreso}%`
                        }"
                    ></div>

                </div>

            </div>


            <!-- ===================================================== -->
            <!-- PANTALLA INICIAL -->
            <!-- ===================================================== -->

            <div
                v-if="!mostrarFormulario"
                class="mx-auto max-w-4xl"
            >

                <div class="rounded-2xl bg-white shadow-sm">

                    <div class="p-6 sm:p-8">

                        <div class="mb-8">

                            <h2 class="text-2xl font-bold text-slate-800">
                                Antes de comenzar
                            </h2>

                            <p class="mt-1 text-sm text-slate-500">
                                Seleccione el tipo de tercero y el tipo de
                                formulario que desea realizar.
                            </p>

                        </div>


                        <!-- TIPO DE TERCERO -->

                        <div class="mb-8">

                            <label class="mb-3 block text-sm font-semibold text-slate-700">
                                1. Tipo de tercero
                            </label>

                            <!--
                                Si todavía no se ha seleccionado un tipo,
                                mostramos todas las opciones. Una vez seleccionado,
                                solo queda visible la opción elegida.
                            -->
                            <div
                                v-if="!form.id_tipo_tercero"
                                class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                            >

                                <button
                                    v-for="tipo in tiposTercero"
                                    :key="tipo.IdTipo_tercero"
                                    type="button"
                                    class="rounded-2xl border-2 bg-white p-6 text-left transition hover:border-blue-300 hover:bg-slate-50"
                                    @click="seleccionarTipoTercero(tipo.IdTipo_tercero)"
                                >
                                    <div class="mb-3 text-3xl">
                                        {{
                                            Number(tipo.IdTipo_tercero) === 1
                                                ? '👤'
                                                : '🏢'
                                        }}
                                    </div>

                                    <div class="font-bold text-slate-800">
                                        {{ tipo.tipo_tercero?.trim() }}
                                    </div>

                                    <div class="mt-1 text-sm text-slate-500">
                                        Seleccionar este tipo de tercero
                                    </div>
                                </button>

                            </div>

                            <!-- Tipo de tercero seleccionado -->
                            <div v-else>
                                <div
                                    v-for="tipo in tiposTercero.filter(
                                        item =>
                                            Number(item.IdTipo_tercero) ===
                                            Number(form.id_tipo_tercero)
                                    )"
                                    :key="tipo.IdTipo_tercero"
                                    class="rounded-2xl border-2 border-blue-600 bg-blue-50 p-6 shadow-md"
                                >
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="flex items-center gap-4">
                                            <div class="text-3xl">
                                                {{
                                                    Number(tipo.IdTipo_tercero) === 1
                                                        ? '👤'
                                                        : '🏢'
                                                }}
                                            </div>

                                            <div>
                                                <div class="font-bold text-slate-800">
                                                    {{ tipo.tipo_tercero?.trim() }}
                                                </div>

                                                <div class="mt-1 text-sm text-blue-600">
                                                    Tipo de tercero seleccionado
                                                </div>
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            class="shrink-0 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                            @click="cambiarTipoTercero"
                                        >
                                            Cambiar
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>


                        <!-- TIPO FORMULARIO -->

                        <div
                            v-if="form.id_tipo_tercero"
                            class="mb-8"
                        >

                            <label class="mb-3 block text-sm font-semibold text-slate-700">
                                2. Tipo de formulario
                            </label>

                            <select
                                v-model="form.id_tipo_frm"
                                class="input-form"
                            >

                                <option value="">
                                    Seleccione una opción
                                </option>

                                <option
                                    v-for="tipo in tiposFormulario"
                                    :key="tipo.IdTipo_frm"
                                    :value="tipo.IdTipo_frm"
                                >
                                    {{ tipo.Tipo_frm?.trim() }}
                                </option>

                            </select>

                        </div>


                        <!-- RESUMEN -->

                        <div
                            v-if="puedeIniciarFormulario"
                            class="mb-8 rounded-xl bg-blue-50 p-5"
                        >

                            <p class="text-xs font-medium uppercase tracking-wide text-blue-500">
                                Registro seleccionado
                            </p>

                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">

                                <div>

                                    <p class="text-xs text-slate-400">
                                        Tipo de tercero
                                    </p>

                                    <p class="font-semibold text-slate-700">
                                        {{ nombreTipoTercero() }}
                                    </p>

                                </div>

                                <div>

                                    <p class="text-xs text-slate-400">
                                        Tipo de formulario
                                    </p>

                                    <p class="font-semibold text-slate-700">
                                        {{ nombreTipoFormulario() }}
                                    </p>

                                </div>

                            </div>

                        </div>


                        <button
                            type="button"
                            :disabled="!puedeIniciarFormulario"
                            @click="iniciarFormulario"
                            class="w-full rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Comenzar formulario →
                        </button>

                    </div>

                </div>

            </div>


            <!-- ===================================================== -->
            <!-- FORMULARIO -->
            <!-- ===================================================== -->

            <div
                v-else
                class="grid grid-cols-1 gap-6 lg:grid-cols-4"
            >

                <!-- ================================================= -->
                <!-- SIDEBAR -->
                <!-- ================================================= -->

                <div class="lg:col-span-1">

                    <div class="sticky top-6 rounded-2xl bg-white p-4 shadow-sm">

                        <div
                            v-for="paso in pasos"
                            :key="paso.numero"
                            class="mb-2 last:mb-0"
                        >

                            <button
                                type="button"
                                class="flex w-full items-center rounded-xl p-3 text-left transition"
                                :class="
                                    pasoActual === paso.numero
                                        ? 'bg-blue-50 text-blue-700'
                                        : pasoActual > paso.numero
                                            ? 'text-green-600'
                                            : 'text-slate-400'
                                "
                                @click="irPaso(paso.numero)"
                            >

                                <span
                                    class="mr-3 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                    :class="
                                        pasoActual === paso.numero
                                            ? 'bg-blue-600 text-white'
                                            : pasoActual > paso.numero
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-slate-100 text-slate-400'
                                    "
                                >

                                    <span v-if="pasoActual > paso.numero">
                                        ✓
                                    </span>

                                    <span v-else>
                                        {{ paso.numero }}
                                    </span>

                                </span>

                                <span>

                                    <span class="block text-sm font-semibold">
                                        {{ paso.titulo }}
                                    </span>

                                    <span class="block text-xs text-slate-400">
                                        {{ paso.descripcion }}
                                    </span>

                                </span>

                            </button>

                        </div>


                      <!-- ESTADO AUTOGUARDADO -->

<div
    class="mt-5 rounded-xl bg-slate-50 p-3"
>

    <div class="flex items-center justify-between gap-3">

        <div class="flex items-center gap-2">

            <span
                class="h-2 w-2 rounded-full"
                :class="
                    guardando
                        ? 'bg-blue-500'
                        : 'bg-green-500'
                "
            ></span>

            <span class="text-xs text-slate-500">

                {{
                    guardando
                        ? 'Guardando...'
                        : 'Avance guardado'
                }}

            </span>

        </div>

        <button
            type="button"
            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
            :disabled="guardando"
            @click="guardarBorrador"
        >
            💾 Guardar
        </button>

    </div>

    <div
        v-if="ultimoGuardado"
        class="mt-2 text-xs text-slate-400"
    >
        Último guardado:
        {{
            ultimoGuardado.toLocaleTimeString(
                'es-CO',
                {
                    hour: '2-digit',
                    minute: '2-digit'
                }
            )
        }}
    </div>

</div>

                    </div>

                </div>


                <!-- ================================================= -->
                <!-- CONTENIDO -->
                <!-- ================================================= -->

                <div class="lg:col-span-3">

                    <div class="rounded-2xl bg-white shadow-sm">




<!-- ================================================= -->
<!-- PASO 1 - INFORMACIÓN GENERAL -->
<!-- ================================================= -->

<div
    v-if="pasoActual === 1"
    class="p-6 sm:p-8"
>

    <!-- ENCABEZADO -->
    <div class="mb-8">

        <h2 class="text-2xl font-bold text-slate-800">
            Información general
        </h2>
    </div>


    <!-- ================================================= -->
<!-- FECHA, VENDEDOR Y TIPO DE PERSONA -->
<!-- ================================================= -->

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">

    <!-- FECHA DEL FORMULARIO -->
    <div>
        <label class="label-form">
            Fecha del formulario *
        </label>

        <input
            v-model="form.fecha_frm"
            type="date"
            class="input-form"
        />
    </div>

    <!-- VENDEDOR -->
    <div>
        <label class="label-form">
            Vendedor
        </label>

        <select
            v-model="form.vendedor"
            class="input-form"
        >
            <option value="">
                Seleccione un vendedor
            </option>

            <option
                v-for="vendedor in props.vendedores"
                :key="vendedor.codigo"
                :value="vendedor.codigo"
            >
                {{ vendedor.codigo }} - {{ vendedor.nombre }}
            </option>
        </select>
    </div>

    <!-- TIPO DE PERSONA -->
    <div>
        <label class="label-form">
            Tipo de persona *
        </label>

        <select
            v-model="form.tipo_persona"
            class="input-form"
            @change="seleccionarTipoPersona(form.tipo_persona)"
        >
            <option value="">
                Seleccione una opción
            </option>

            <option value="Natural">
                Persona Natural
            </option>

            <option value="Jurídica">
                Persona Jurídica
            </option>
        </select>
    </div>

    <!-- TIPO DE IDENTIFICACIÓN -->
    <div v-if="form.tipo_persona">
        <label class="label-form">
            Tipo de identificación *
        </label>

        <select
            v-model="form.tipo_doc"
            class="input-form"
        >
            <option value="">
                Seleccione un tipo de identificación
            </option>

            <option
                v-for="tipo in tiposIdentificacion"
                :key="tipo.codigo"
                :value="tipo.codigo"
            >
                {{ tipo.nombre }}
            </option>
        </select>
    </div>

    <!-- NÚMERO DE IDENTIFICACIÓN -->
    <div v-if="form.tipo_persona">
        <label class="label-form">
            Número de identificación *
        </label>

        <input
            v-model="form.numero_doc"
            type="text"
            maxlength="30"
            class="input-form"
            placeholder="Ingrese el número de identificación"
        />
    </div>

    <!-- NOMBRES Y APELLIDOS / RAZÓN SOCIAL -->
    <div
        v-if="form.tipo_persona"
        class="md:col-span-1"
    >
        <label class="label-form">
            {{
                esJuridica
                    ? 'Razón social *'
                    : 'Nombres y apellidos *'
            }}
        </label>

        <input
            v-model="form.razon_social"
            type="text"
            maxlength="100"
            class="input-form"
            :placeholder="
                esJuridica
                    ? 'Ej. Senco S.A.S.'
                    : 'Ingrese nombres y apellidos'
            "
        />
    </div>

</div>


    <!-- ================================================= -->
    <!-- INFORMACIÓN QUE DEPENDE DEL TIPO DE PERSONA -->
    <!-- ================================================= -->

    <div
        v-if="form.tipo_persona"
        class="space-y-8"
    >

<!-- ================================================= -->
<!-- IDENTIFICACIÓN -->
<!-- ================================================= -->

<div>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">




        <!-- ================================================= -->
        <!-- ESTABLECIMIENTO DE COMERCIO -->
        <!-- ================================================= -->

        <div>

            <label class="label-form">
                ¿Tiene establecimiento de comercio?
            </label>

            <div class="flex gap-3">

                <!-- SÍ -->
                <button
                    type="button"
                    class="flex-1 rounded-xl border px-4 py-3 text-sm font-semibold transition"
                    :class="
                        form.tiene_establecimiento === true
                            ? 'border-blue-600 bg-blue-600 text-white'
                            : 'border-slate-300 bg-white text-slate-700'
                    "
                    @click="seleccionarEstablecimiento(true)"
                >
                    Sí
                </button>


                <!-- NO -->
                <button
                    type="button"
                    class="flex-1 rounded-xl border px-4 py-3 text-sm font-semibold transition"
                    :class="
                        form.tiene_establecimiento === false
                            ? 'border-blue-600 bg-blue-600 text-white'
                            : 'border-slate-300 bg-white text-slate-700'
                    "
                    @click="seleccionarEstablecimiento(false)"
                >
                    No
                </button>

            </div>

        </div>


        <!-- NOMBRE DEL ESTABLECIMIENTO -->
        <div v-if="form.tiene_establecimiento === true">

            <label class="label-form">
                Nombre del establecimiento de comercio
            </label>

            <input
                v-model="form.nombre_establecimiento"
                type="text"
                maxlength="50"
                class="input-form"
                placeholder="Ingrese el nombre del establecimiento"
            />

        </div>

    </div>

</div>


<!-- ================================================= -->
<!-- CONTACTO -->
<!-- ================================================= -->

<div class="border-t border-slate-200 pt-8">

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

        <!-- DIRECCIÓN -->
        <div>

            <label class="label-form">
                Dirección *
            </label>

            <input
                v-model="form.direccion"
                type="text"
                maxlength="200"
                class="input-form"
                placeholder="Ingrese la dirección"
            />

        </div>

<!-- PAÍS -->
<div>

    <label class="label-form">
        País *
    </label>

  <select
    v-model="form.id_pais"
    class="input-form"
    :disabled="paises.length === 0"
    @change="cargarDepartamentos()"
>
        <option value="">
            {{
                paises.length === 0
                    ? 'No hay países disponibles'
                    : 'Seleccione un país'
            }}
        </option>

        <option
            v-for="pais in paises"
            :key="pais.codigo"
            :value="pais.codigo"
        >
            {{ pais.nombre }}
        </option>
    </select>

</div>

 <template v-if="!paisEsExtranjero(form.id_pais)">
 <!-- DEPARTAMENTO -->
        <div>

            <label class="label-form">
                Departamento *
            </label>

            <select
                v-model="form.id_departamento"
                class="input-form"
                :disabled="cargandoDepartamentos"
            >
                <option value="">
                    {{
                        cargandoDepartamentos
                            ? 'Cargando departamentos...'
                            : 'Seleccione un departamento'
                    }}
                </option>

                <option
                    v-for="departamento in departamentos"
                    :key="departamento.codigo"
                    :value="departamento.codigo"
                >
                    {{ departamento.nombre }}
                </option>
            </select>

        </div>

         <!-- CIUDAD -->
        <div>

            <label class="label-form">
                Ciudad *
            </label>

            <select
                v-model="form.id_ciudad"
                class="input-form"
                :disabled="!form.id_departamento || cargandoMunicipios"
            >
                <option value="">
                    {{
                        cargandoMunicipios
                            ? 'Cargando ciudades...'
                            : !form.id_departamento
                                ? 'Seleccione primero un departamento'
                                : 'Seleccione una ciudad'
                    }}
                </option>

                <option
                    v-for="municipio in municipios"
                    :key="municipio.codigo"
                    :value="municipio.codigo"
                >
                    {{ municipio.nombre }}
                </option>
            </select>

        </div>



</template>

        <!-- TELÉFONO -->
        <div>

            <label class="label-form">
                Teléfono
            </label>

            <input
                v-model="form.telefono" @keydown="bloquearTextoNumerico"
                type="text"
                inputmode="numeric"
                maxlength="10"
                class="input-form"
                @input="form.telefono = soloNumeros(form.telefono)"
                placeholder="Ingrese el teléfono"

            />

        </div>


        <!-- CELULAR -->
        <div>

            <label class="label-form">
                Celular
            </label>

            <input
                v-model="form.celular1" @keydown="bloquearTextoNumerico" @input="form.celular1 = soloNumeros(form.celular1)"
                type="text"
                inputmode="numeric"
                maxlength="10"
                class="input-form"
                placeholder="Ingrese el celular"
            />

        </div>


        <!-- CELULAR ALTERNO -->
        <div>

            <label class="label-form">
                Celular alterno
            </label>

            <input
                v-model="form.celular2" @keydown="bloquearTextoNumerico" @input="form.celular2 = soloNumeros(form.celular2)"
                type="text"
                inputmode="numeric"
                maxlength="10"
                class="input-form"
                placeholder="Ingrese el celular alterno"
            />

        </div>


        <!-- E-MAIL -->
        <div>

            <label class="label-form">
                E-mail
            </label>

            <input
                v-model="form.email"
                type="email"
                maxlength="50"
                class="input-form"
                placeholder="correo@ejemplo.com"
            />

        </div>


        <!-- CORREO FACTURACIÓN -->
        <div>

            <label class="label-form">
                Correo autorizado para recepción de la factura
            </label>

            <input
                v-model="form.email_facturacion"
                type="email"
                maxlength="50"
                class="input-form"
                placeholder="correo@ejemplo.com"
            />

        </div>


         <!-- CÓDIGO CIIU -->
        <div>

            <label class="label-form">
                Código CIIU *
            </label>

           <select
    v-model="form.codigo_ciiu"
    class="input-form"
>
    <option value="">
        Seleccione un código CIIU
    </option>

    <option
        v-for="item in props.ciiu"
        :key="item.IdCodigo_ciiu"
        :value="item.codigo_ciiu?.trim()"
    >
        {{ item.codigo_ciiu?.trim() }}
    </option>
</select>

        </div>

    </div>

</div>

     <!-- ACTIVIDAD ECONÓMICA -->
<div>

    <label class="label-form">
        Actividad económica principal
    </label>

    <input
        :value="actividadEconomicaSeleccionada"
        type="text"
        class="input-form"
        readonly
        placeholder="Se mostrará según el código CIIU seleccionado"
    />

</div>





<!-- ================================================= -->
<!-- INFORMACIÓN DEL REPRESENTANTE LEGAL -->
<!-- ================================================= -->

<div class="border-t border-slate-200 pt-8">

    <div class="mb-5">

        <h3 class="text-lg font-bold text-slate-800">
            Información del representante legal
        </h3>

    </div>


    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

        <!-- NOMBRE DEL REPRESENTANTE -->
        <div>

            <label class="label-form">
                Nombre y apellidos
            </label>

            <input
                v-model="form.rl_nombre"
                type="text"
                maxlength="50"
                class="input-form"
                placeholder="Ingrese nombres y apellidos"
            />

        </div>


        <!-- TIPO DE DOCUMENTO -->
<div>

    <label class="label-form">
        Tipo de identificación
    </label>

    <select
        v-model="form.rl_tipo_doc"
        class="input-form"
    >
        <option value="">
            Seleccione un tipo de identificación
        </option>

        <option
            v-for="tipo in tiposIdentificacion"
            :key="tipo.codigo"
            :value="tipo.codigo"
        >
            {{ tipo.nombre }}
        </option>
    </select>

</div>

        <!-- NÚMERO DE DOCUMENTO -->
        <div>

            <label class="label-form">
                Número de identificación
            </label>

           <input
    v-model="form.rl_numero_doc"
    type="text"
    maxlength="30"
    class="input-form"
    placeholder="Ingrese el número de identificación"
/>

        </div>


        <!-- E-mail -->
        <div>

            <label class="label-form">
                E-mail
            </label>

            <input
                v-model="form.rl_email"
                type="email"
                maxlength="50"
                class="input-form"
                placeholder="correo@ejemplo.com"
            />

        </div>

    </div>

</div>


<!-- ================================================= -->
<!-- INFORMACIÓN DE TESORERÍA -->
<!-- ================================================= -->

<div class="border-t border-slate-200 pt-8">

    <div class="mb-5">

        <h3 class="text-lg font-bold text-slate-800">
            Información de tesorería
        </h3>

    </div>


    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

        <!-- NOMBRE / CONTACTO -->
        <div>

            <label class="label-form">
                Nombre  y apellidos
            </label>

            <input
                v-model="form.tesoreria_nombre"
                type="text"
                maxlength="50"
                class="input-form"
                placeholder="Ingrese nombres y apellidos"
            />

        </div>


        <!-- TELÉFONO -->
        <div>

            <label class="label-form">
                Teléfono
            </label>

            <input
                v-model="form.tesoreria_telefono" @keydown="bloquearTextoNumerico" @input="form.tesoreria_telefono = soloNumeros(form.tesoreria_telefono)"
                type="text"
                inputmode="numeric"
                maxlength="10"
                class="input-form"
                placeholder="Ingrese el teléfono"
            />

        </div>


        <!-- E-mail -->
        <div>

            <label class="label-form">
                E-mail
            </label>

            <input
                v-model="form.tesoreria_email"
                type="email"
                maxlength="50"
                class="input-form"
                placeholder="correo@ejemplo.com"
            />

        </div>

    </div>

</div>

</div>

</div>


<!-- ================================================= -->

<!-- PASO 2 - DIRECCIONES DE ENVÍO -->

<!-- ================================================= -->

<div
    v-if="pasoActual === 2"
    class="p-6 sm:p-8"
>

<!-- ENCABEZADO -->
<div class="mb-8">

    <h2 class="text-2xl font-bold text-slate-800">
        Direcciones de envío
    </h2>

</div>


<div class="space-y-6">

    <!-- DIRECCIONES -->
    <div
        v-for="(direccion, index) in form.direcciones_envio"
        :key="index"
        class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6"
    >

        <!-- ENCABEZADO -->
        <div class="mb-6 flex items-center justify-between">

            <div class="flex items-center gap-3">

                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-800 text-white"
                >
                    <span class="text-lg">
                        🚚
                    </span>
                </div>

                <div>

                    <h3 class="font-semibold text-slate-800">
                        Dirección de envío {{ index + 1 }}
                    </h3>

                </div>

            </div>


            <!-- ELIMINAR -->
            <button
                v-if="form.direcciones_envio.length > 1"
                type="button"
                class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
                @click="eliminarDireccion(index)"
            >
                🗑 Eliminar
            </button>

        </div>


        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

            <!-- DIRECCIÓN -->
            <div class="md:col-span-2">

                <label class="label-form">
                    Dirección de envío *
                </label>

                <input
                    v-model="direccion.direccion"
                    type="text"
                    class="input-form"
                    maxlength="500"
                    placeholder="Ingrese la dirección de envío"
                />

            </div>

<!-- PAÍS -->
<div>

    <label class="label-form">
        País *
    </label>

    <select
        v-model="direccion.id_pais"
        class="input-form"
        :disabled="paises.length === 0"
        @change="cargarDepartamentosDireccion(direccion)"
    >
        <option value="">
            {{
                paises.length === 0
                    ? 'No hay países disponibles'
                    : 'Seleccione un país'
            }}
        </option>

        <option
            v-for="pais in paises"
            :key="pais.codigo"
            :value="pais.codigo"
        >
            {{ pais.nombre }}
        </option>
    </select>

</div>


<template v-if="!paisEsExtranjero(direccion.id_pais)">

<!-- DEPARTAMENTO -->
<div>

    <label class="label-form">
        Departamento *
    </label>

    <select
        v-model="direccion.id_departamento"
        class="input-form"
        :disabled="
            !direccion.id_pais ||
            direccion.cargandoDepartamentos
        "
        @change="cargarMunicipiosDireccion(direccion)"
    >
        <option value="">
            {{
                direccion.cargandoDepartamentos
                    ? 'Cargando departamentos...'
                    : !direccion.id_pais
                        ? 'Seleccione primero un país'
                        : 'Seleccione un departamento'
            }}
        </option>

        <option
            v-for="departamento in direccion.departamentos || []"
            :key="departamento.codigo"
            :value="departamento.codigo"
        >
            {{ departamento.nombre }}
        </option>
    </select>

</div>


<!-- CIUDAD -->
<div>

    <label class="label-form">
        Ciudad *
    </label>

    <select
        v-model="direccion.id_municipio"
        class="input-form"
        :disabled="
            !direccion.id_departamento ||
            direccion.cargandoMunicipios
        "
    >
        <option value="">
            {{
                direccion.cargandoMunicipios
                    ? 'Cargando ciudades...'
                    : !direccion.id_departamento
                        ? 'Seleccione primero un departamento'
                        : 'Seleccione una ciudad'
            }}
        </option>

        <option
            v-for="municipio in direccion.municipios || []"
            :key="municipio.codigo"
            :value="municipio.codigo"
        >
            {{ municipio.nombre }}
        </option>
    </select>

</div>
            <!-- BARRIO -->
            <div>

                <label class="label-form">
                    Barrio *
                </label>

                <input
                    v-model="direccion.barrio"
                    type="text"
                    class="input-form"
                    maxlength="10"
                    placeholder="Ingrese el barrio"
                />

            </div>


</template>

            <!-- OBSERVACIONES -->
            <div class="md:col-span-2">

                <label class="label-form">
                    Observaciones
                </label>

                <textarea
                    v-model="direccion.observaciones"
                    rows="3"
                    class="input-form resize-none"
                    maxlength="100"
                    placeholder="Ingrese alguna observación"
                ></textarea>

            </div>

        </div>

    </div>


      <!-- AGREGAR DIRECCIÓN -->

            <div class="flex justify-end">

                <button
                    type="button"
                    @click="agregarDireccion"
                    class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                >
                    + Agregar dirección
                </button>

        </div>

    </div>

</div>

<!-- ================================================= -->
<!-- PASO 3 - SOCIOS -->
<!-- ================================================= -->

<div
    v-if="pasoActual === 3"
    class="p-6 sm:p-8"
>

    <!-- ENCABEZADO -->
    <div class="mb-8">

        <h2 class="text-2xl font-bold text-slate-800">
            Socios
        </h2>

    </div>


    <!-- SOCIOS -->
    <div class="space-y-6">

        <div
            v-for="(socio, index) in form.socios"
            :key="index"
            class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6"
        >

            <!-- ENCABEZADO SOCIO -->
            <div class="mb-6 flex items-center justify-between">

                <div class="flex items-center gap-3">

                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-800 text-white"
                    >
                        <span class="text-lg">
                         🧑‍💼
                        </span>
                    </div>

                    <div>

                        <h3 class="font-semibold text-slate-800">
                            Socio {{ index + 1 }}
                        </h3>

                    </div>

                </div>


                <!-- ELIMINAR -->
                <button
                    v-if="form.socios.length > 1"
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
                    @click="eliminarSocio(index)"
                >
                    🗑 Eliminar
                </button>

            </div>


            <!-- CAMPOS -->
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

<!-- NOMBRES -->
<div>

    <label class="label-form">
        Nombres *
    </label>

    <input
        v-model="socio.nombres"
        type="text"
        class="input-form"
        maxlength="30"
        placeholder="Ingrese los nombres"
    />

</div>


<!-- APELLIDOS -->
<div>

    <label class="label-form">
        Apellidos *
    </label>

    <input
        v-model="socio.apellidos"
        type="text"
        class="input-form"
        maxlength="30"
        placeholder="Ingrese los apellidos"
    />

</div>


<!-- TIPO IDENTIFICACIÓN -->
<div>

    <label class="label-form">
        Tipo de identificación *
    </label>

    <select
    v-model="socio.tipo_identificacion"
    class="input-form"
>
    <option value="">
        Seleccione un tipo de identificación
    </option>

    <option
        v-for="tipo in tiposIdentificacion"
        :key="tipo.codigo"
        :value="tipo.codigo"
    >
        {{ tipo.nombre }}
    </option>
</select>

</div>


<!-- NÚMERO IDENTIFICACIÓN -->
<div>

    <label class="label-form">
        Número de identificación *
    </label>

 <input
    v-model="socio.numero_identificacion"
    type="text"
    maxlength="30"
    class="input-form"
    placeholder="Ingrese el número de identificación"
/>

</div>


<!-- PARTICIPACIÓN -->
<div>

    <label class="label-form">
        % de participación social *
    </label>

<input
    v-model="socio.porcentaje_participacion"
    @input="onPorcentajeInput($event, socio, index)"
    type="text"
    inputmode="decimal"
    maxlength="6"
    class="input-form"
    placeholder="Ej. 25.50"
/>

<p
    class="mt-1 text-xs"
    :class="totalParticipacion > 100 ? 'text-red-600' : 'text-slate-500'"
>
    Total de participación: {{ totalParticipacion }}% (máximo 100%)
</p>

</div>

</div>
</div>


          <!-- AGREGAR SOCIO-->

            <div class="flex justify-end">

                <button
                    type="button"
                    @click="agregarSocio"
                    class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                >
                    + Agregar socio
                </button>

        </div>

    </div>

</div>

<!-- ================================================= -->
<!-- PASO 4 - INFORMACIÓN FINANCIERA Y TRIBUTARIA -->
<!-- ================================================= -->

<div
    v-if="pasoActual === 4"
    class="p-6 sm:p-8"
>
    <!-- ================================================= -->
    <!-- ENCABEZADO -->
    <!-- ================================================= -->

    <div class="mb-8">
        <h2 class="text-2xl font-bold text-slate-800">
            Información financiera y tributaria
        </h2>
    </div>


    <!-- ================================================= -->
    <!-- INFORMACIÓN FINANCIERA -->
    <!-- ================================================= -->

    <div class="mb-10">

        <div class="mb-5 flex items-center gap-3">

            <div
                class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-800 text-white"
            >
                <span class="text-lg">📊 </span>
            </div>

            <div>
                <h3 class="font-semibold text-slate-800">
                    Información financiera
                </h3>
            </div>

        </div>


        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

            <!-- ================================================= -->
            <!-- FECHA DE CORTE -->
            <!-- ================================================= -->

            <div>

                <label class="label-form flex items-center gap-2">

                    Fecha de corte *

                    <span class="group relative inline-flex cursor-help">

                        <span
                            class="flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 text-[11px] font-semibold text-slate-500 transition group-hover:border-slate-500 group-hover:bg-slate-100 group-hover:text-slate-700"
                        >
                            ?
                        </span>

                        <span
                            class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-72 -translate-x-1/2 rounded-xl bg-slate-800 px-3 py-2 text-xs font-normal leading-5 text-white opacity-0 shadow-lg transition-opacity duration-200 group-hover:opacity-100"
                        >
                            Indicar la fecha de corte de la información financiera.
                        </span>

                    </span>

                </label>

                <input
                    v-model="form.fecha_corte"
                    type="date"
                    class="input-form"
                />

            </div>


            <!-- ================================================= -->
            <!-- ACTIVOS -->
            <!-- ================================================= -->

            <div>

                <label class="label-form flex items-center gap-2">

                    Activos *

                    <span class="group relative inline-flex cursor-help">

                        <span
                            class="flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 text-[11px] font-semibold text-slate-500 transition group-hover:border-slate-500 group-hover:bg-slate-100 group-hover:text-slate-700"
                        >
                            ?
                        </span>

                        <span
                            class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-64 -translate-x-1/2 rounded-xl bg-slate-800 px-3 py-2 text-xs font-normal leading-5 text-white opacity-0 shadow-lg transition-opacity duration-200 group-hover:opacity-100"
                        >
                            Registre el valor total de los activos en pesos colombianos (COP).
                        </span>

                    </span>

                </label>

            <input
    :value="form.activos"
    type="text"
    inputmode="decimal"
    @input="form.activos = formatearMoneda($event.target.value)"
    class="input-form"
    placeholder="Ingrese el valor de los activos"
/>
            </div>


            <!-- ================================================= -->
            <!-- PASIVOS -->
            <!-- ================================================= -->

            <div>

                <label class="label-form flex items-center gap-2">

                    Pasivos *

                    <span class="group relative inline-flex cursor-help">

                        <span
                            class="flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 text-[11px] font-semibold text-slate-500 transition group-hover:border-slate-500 group-hover:bg-slate-100 group-hover:text-slate-700"
                        >
                            ?
                        </span>

                        <span
                            class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-64 -translate-x-1/2 rounded-xl bg-slate-800 px-3 py-2 text-xs font-normal leading-5 text-white opacity-0 shadow-lg transition-opacity duration-200 group-hover:opacity-100"
                        >
                            Registre el valor total de los pasivos en pesos colombianos (COP).
                        </span>

                    </span>

                </label>

            <!-- PASIVOS --> <input
   :value="form.pasivos"
    type="text"
    inputmode="decimal"
    @input="form.pasivos = formatearMoneda($event.target.value)"
    class="input-form"
    placeholder="Ingrese el valor de los pasivos"
/>
            </div>


            <!-- ================================================= -->
            <!-- INGRESOS -->
            <!-- ================================================= -->

            <div>

                <label class="label-form flex items-center gap-2">

                    Ingresos *

                    <span class="group relative inline-flex cursor-help">

                        <span
                            class="flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 text-[11px] font-semibold text-slate-500 transition group-hover:border-slate-500 group-hover:bg-slate-100 group-hover:text-slate-700"
                        >
                            ?
                        </span>

                        <span
                            class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-64 -translate-x-1/2 rounded-xl bg-slate-800 px-3 py-2 text-xs font-normal leading-5 text-white opacity-0 shadow-lg transition-opacity duration-200 group-hover:opacity-100"
                        >
                            Registre el valor total de los ingresos en pesos colombianos (COP).
                        </span>

                    </span>

                </label>

          <input
   :value="form.ingresos"
    type="text"
    inputmode="decimal"
    @input="form.ingresos = formatearMoneda($event.target.value)"
    class="input-form"
    placeholder="Ingrese el valor de los ingresos"
/>

            </div>


            <!-- ================================================= -->
            <!-- EGRESOS -->
            <!-- ================================================= -->

            <div>

                <label class="label-form flex items-center gap-2">

                    Egresos *

                    <span class="group relative inline-flex cursor-help">

                        <span class="flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 text-[11px] font-semibold text-slate-500 transition group-hover:border-slate-500 group-hover:bg-slate-100 group-hover:text-slate-700">
                            ?
                        </span>

                        <span
                            class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-64 -translate-x-1/2 rounded-xl bg-slate-800 px-3 py-2 text-xs font-normal leading-5 text-white opacity-0 shadow-lg transition-opacity duration-200 group-hover:opacity-100"
                        >
                            Registre el valor total de los egresos en pesos colombianos (COP).
                        </span>

                    </span>

                </label>

                <input
    v-model="form.egresos"
    type="text"
    inputmode="decimal"
    @input="form.egresos = formatearMoneda($event.target.value)"
    class="input-form"
    placeholder="Ingrese el valor de los egresos"
/>

            </div>

        </div>

    </div>


    <!-- ================================================= -->
    <!-- INFORMACIÓN TRIBUTARIA -->
    <!-- ================================================= -->

    <div>

        <div class="mb-5 flex items-center gap-3">

            <div
                class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-800 text-white"
            >
                <span class="text-lg">🧾</span>
            </div>

            <div>
                <h3 class="font-semibold text-slate-800">
                    Información tributaria
                </h3>
            </div>

        </div>


        <div class="space-y-4">

            <!-- ================================================= -->
            <!-- RESPONSABLE DE IVA -->
            <!-- ================================================= -->

            <div
                class="rounded-2xl border border-slate-200 bg-white p-5"
            >

                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >

                    <div>

                        <p class="font-medium text-slate-800">
                            ¿Es responsable de IVA?
                        </p>
                    </div>


                    <div class="flex gap-2">

                        <button
                            type="button"
                            class="rounded-xl border px-5 py-2 text-sm font-medium transition"
                            :class="
                                form.responsable_iva === true
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'
                            "
                            @click="form.responsable_iva = true"
                        >
                            Sí
                        </button>

                        <button
                            type="button"
                            class="rounded-xl border px-5 py-2 text-sm font-medium transition"
                            :class="
                                form.responsable_iva === false
                                    ? 'border-red-500 bg-red-50 text-red-700'
                                    : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'
                            "
                            @click="form.responsable_iva = false"
                        >
                            No
                        </button>

                    </div>

                </div>

            </div>


            <!-- ================================================= -->
            <!-- DECLARA RENTA -->
            <!-- ================================================= -->

            <div
                class="rounded-2xl border border-slate-200 bg-white p-5"
            >

                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >

                    <div>

                        <p class="font-medium text-slate-800">
                            ¿Declara renta?
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            Seleccione Sí o No según corresponda.
                        </p>

                    </div>


                    <div class="flex gap-2">

                        <button
                            type="button"
                            class="rounded-xl border px-5 py-2 text-sm font-medium transition"
                            :class="
                                form.declara_renta === true
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'
                            "
                            @click="form.declara_renta = true"
                        >
                            Sí
                        </button>

                        <button
                            type="button"
                            class="rounded-xl border px-5 py-2 text-sm font-medium transition"
                            :class="
                                form.declara_renta === false
                                    ? 'border-red-500 bg-red-50 text-red-700'
                                    : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'
                            "
                            @click="form.declara_renta = false"
                        >
                            No
                        </button>

                    </div>

                </div>

            </div>


            <!-- ================================================= -->
            <!-- GRAN CONTRIBUYENTE -->
            <!-- ================================================= -->

            <div
                class="rounded-2xl border border-slate-200 bg-white p-5"
            >

                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >

                    <div>

                        <p class="font-medium text-slate-800">
                            ¿Es gran contribuyente?
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            Seleccione Sí o No según corresponda.
                        </p>

                    </div>


                    <div class="flex gap-2">

                        <button
                            type="button"
                            class="rounded-xl border px-5 py-2 text-sm font-medium transition"
                            :class="
                                form.gran_contribuyente === true
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'
                            "
                            @click="form.gran_contribuyente = true"
                        >
                            Sí
                        </button>

                        <button
                            type="button"
                            class="rounded-xl border px-5 py-2 text-sm font-medium transition"
                            :class="
                                form.gran_contribuyente === false
                                    ? 'border-red-500 bg-red-50 text-red-700'
                                    : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'
                            "
                            @click="form.gran_contribuyente = false"
                        >
                            No
                        </button>

                    </div>

                </div>

            </div>


            <!-- ================================================= -->
            <!-- AUTORETENEDOR -->
            <!-- ================================================= -->

            <div
                class="rounded-2xl border border-slate-200 bg-white p-5"
            >

                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >

                    <div>

                        <p class="font-medium text-slate-800">
                            ¿Es autoretenedor?
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            Seleccione Sí o No según corresponda.
                        </p>

                    </div>


                    <div class="flex gap-2">

                        <button
                            type="button"
                            class="rounded-xl border px-5 py-2 text-sm font-medium transition"
                            :class="
                                form.autoretenedor === true
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'
                            "
                            @click="form.autoretenedor = true"
                        >
                            Sí
                        </button>

                        <button
                            type="button"
                            class="rounded-xl border px-5 py-2 text-sm font-medium transition"
                            :class="
                                form.autoretenedor === false
                                    ? 'border-red-500 bg-red-50 text-red-700'
                                    : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'
                            "
                            @click="form.autoretenedor = false"
                        >
                            No
                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- ================================================= -->
<!-- PASO 5 - REFERENCIAS COMERCIALES -->
<!-- ================================================= -->

<div
    v-if="pasoActual === 5"
    class="p-6 sm:p-8"
>

    <div class="mb-8">

        <h2 class="text-2xl font-bold text-slate-800">
            Referencias comerciales
        </h2>

    </div>


    <!-- ================================================= -->
    <!-- PROVEEDORES -->
    <!-- ================================================= -->

    <div class="mb-10">

        <div class="mb-5 flex items-center gap-3">

            <div
                class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-800 text-white"
            >
                <span class="text-lg">
                    🏭
                </span>
            </div>

            <div>

                <h3 class="font-semibold text-slate-800">
                    Proveedores
                </h3>

                <p class="text-xs text-slate-500">
                    Registre las referencias de sus proveedores.
                </p>

            </div>

        </div>


        <div class="space-y-6">

            <div
                v-for="(proveedor, index) in form.proveedores"
                :key="index"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6"
            >

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h4 class="font-semibold text-slate-800">
                            Proveedor {{ index + 1 }}
                        </h4>

                        <p class="text-xs text-slate-500">
                            Información de la referencia
                        </p>

                    </div>

                    <button
                        v-if="form.proveedores.length > 1"
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
                        @click="eliminarProveedor(index)"
                    >
                        🗑 Eliminar
                    </button>

                </div>


                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">


                    <!-- NOMBRE -->

                    <div class="md:col-span-2">

                        <label class="label-form">
                            Nombre o razón social *
                        </label>

                        <input
                            v-model="proveedor.nombre_razon_social"
                            type="text"
                            class="input-form"
                            placeholder="Ingrese el nombre o razón social"
                        />

                    </div>


                    <!-- TIPO IDENTIFICACIÓN -->

                    <div>

                        <label class="label-form">
                            Tipo de identificación *
                        </label>

                        <select
                            v-model="proveedor.tipo_identificacion"
                            class="input-form"
                        >

                            <option value="">
                                Seleccione un tipo de identificación
                            </option>

                            <option
                                v-for="tipo in tiposIdentificacion"
                                :key="tipo.codigo"
                                :value="tipo.codigo"
                            >
                                {{ tipo.nombre }}
                            </option>

                        </select>

                    </div>


                    <!-- NUMERO -->

                    <div>

                        <label class="label-form">
                            Número de identificación *
                        </label>

                        <input
    v-model="proveedor.numero_identificacion"
    type="text"
    maxlength="30"
    class="input-form"
    placeholder="Ingrese el número de identificación"
/>

                    </div>


                    <!-- DIRECCIÓN -->

                    <div class="md:col-span-2">

                        <label class="label-form">
                            Dirección *
                        </label>

                        <input
                            v-model="proveedor.direccion"
                            type="text"
                            class="input-form"
                            placeholder="Ingrese la dirección"
                        />

                    </div>


                    <!-- PAÍS -->

                    <div>

                        <label class="label-form">
                            País *
                        </label>

                        <select
                            v-model= "proveedor.id_pais"
                            class="input-form"
                            :disabled="paises.length === 0"
                            @change="cargarDepartamentosProveedor(proveedor)"
                        >

                            <option value="">
                                {{
                                    paises.length === 0
                                        ? 'No hay países disponibles'
                                        : 'Seleccione un país'
                                }}
                            </option>

                            <option
                                v-for="pais in paises"
                                :key="pais.codigo"
                                :value="pais.codigo"
                            >
                                {{ pais.nombre }}
                            </option>

                        </select>

                    </div>


                    <template v-if="!paisEsExtranjero(proveedor.id_pais)">

<!-- DEPARTAMENTO -->

                    <div>

                        <label class="label-form">
                            Departamento *
                        </label>

                        <select
                            v-model="proveedor.id_departamento"
                            class="input-form"
                            :disabled="proveedor.cargandoDepartamentos"
                            @change="cargarMunicipiosProveedor(proveedor)"
                        >

                            <option value="">
                                {{
                                    proveedor.cargandoDepartamentos
                                        ? 'Cargando departamentos...'
                                        : 'Seleccione un departamento'
                                }}
                            </option>

                            <option
                                v-for="departamento in proveedor.departamentos || []"
                                :key="departamento.codigo"
                                :value="departamento.codigo"
                            >
                                {{ departamento.nombre }}
                            </option>

                        </select>

                    </div>


                    <!-- CIUDAD -->

                    <div>

                        <label class="label-form">
                            Ciudad *
                        </label>

                        <select
                            v-model="proveedor.id_ciudad"
                            class="input-form"
                            :disabled="!proveedor.id_departamento || proveedor.cargandoMunicipios"

                        >

                            <option value="">
                                {{
                                   proveedor.cargandoMunicipios
                                        ? 'Cargando ciudades...'
                                        : !proveedor.id_departamento
                                            ? 'Seleccione primero un departamento'
                                            : 'Seleccione una ciudad'

                                }}
                            </option>

                            <option
                                v-for="municipio in proveedor.municipios || []"
                                :key="municipio.codigo"
                                :value="municipio.codigo"
                            >
                                {{ municipio.nombre }}
                            </option>

                        </select>

                    </div>

</template>



                    <!-- TELÉFONO -->

                    <div>

                        <label class="label-form">
                            Teléfono *
                        </label>

                        <input
                            v-model="proveedor.telefono"
                            @keydown="bloquearTextoNumerico"
                            @input="proveedor.telefono = soloNumeros(proveedor.telefono)"
                            type="text"
                            inputmode="numeric"
                            class="input-form"
                            placeholder="Ingrese el teléfono"
                        />

                    </div>


                    <!-- CELULAR -->

                    <div>

                        <label class="label-form">
                            Celular *
                        </label>

                        <input
                            v-model="proveedor.celular"
                            @keydown="bloquearTextoNumerico"
                            @input="proveedor.celular = soloNumeros(proveedor.celular)"
                            type="text"
                            inputmode="numeric"
                            class="input-form"
                            placeholder="Ingrese el celular"
                        />

                    </div>


                    <!-- E-MAIL -->

                    <div class="md:col-span-2">

                        <label class="label-form">
                            E-mail*
                        </label>

                        <input
                            v-model="proveedor.email"
                            type="email"
                            class="input-form"
                            placeholder="correo@ejemplo.com"
                        />

                    </div>

                </div>

            </div>


            <!-- AGREGAR PROVEEDOR -->

            <div class="flex justify-end">

                <button
                    type="button"
                    @click="agregarProveedor"
                    class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                >
                    + Agregar proveedor
                </button>

            </div>

        </div>

    </div>


    <!-- ================================================= -->
    <!-- CLIENTES -->
    <!-- ================================================= -->

    <div>

        <div class="mb-5 flex items-center gap-3">

            <div
                class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-800 text-white"
            >
                <span class="text-lg">
                    🤝
                </span>
            </div>

            <div>

                <h3 class="font-semibold text-slate-800">
                    Clientes
                </h3>

            </div>

        </div>


        <div class="space-y-6">

            <div
                v-for="(cliente, index) in form.clientes"
                :key="index"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6"
            >

                <div class="mb-6 flex items-center justify-between">

                    <div>

                        <h4 class="font-semibold text-slate-800">
                            Cliente {{ index + 1 }}
                        </h4>

                    </div>

                    <button
                        v-if="form.clientes.length > 1"
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
                        @click="eliminarCliente(index)"
                    >
                        🗑 Eliminar
                    </button>

                </div>


                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">


                    <!-- NOMBRE -->

                    <div class="md:col-span-2">

                        <label class="label-form">
                            Nombre o razón social *
                        </label>

                        <input
                            v-model="cliente.nombre_razon_social"
                            type="text"
                            class="input-form"
                            placeholder="Ingrese el nombre o razón social"
                        />

                    </div>


                    <!-- TIPO IDENTIFICACIÓN -->

                    <div>

                        <label class="label-form">
                            Tipo de identificación *
                        </label>

                        <select
                            v-model="cliente.tipo_identificacion"
                            class="input-form"
                        >

                            <option value="">
                                Seleccione un tipo de identificación
                            </option>

                            <option
                                v-for="tipo in tiposIdentificacion"
                                :key="tipo.codigo"
                                :value="tipo.codigo"
                            >
                                {{ tipo.nombre }}
                            </option>

                        </select>

                    </div>


                    <!-- NUMERO -->

                    <div>

                        <label class="label-form">
                            Número de identificación *
                        </label>

                       <input
    v-model="cliente.numero_identificacion"
    type="text"
    maxlength="30"
    class="input-form"
    placeholder="Ingrese el número de identificación"
/>

                    </div>


                    <!-- DIRECCIÓN -->

                    <div class="md:col-span-2">

                        <label class="label-form">
                            Dirección *
                        </label>

                        <input
                            v-model="cliente.direccion"
                            type="text"
                            class="input-form"
                            placeholder="Ingrese la dirección"
                        />

                    </div>


                    <!-- PAÍS -->

                    <div>

                        <label class="label-form">
                            País *
                        </label>

                        <select
                            v-model="cliente.id_pais"
                            class="input-form"
                            :disabled="paises.length === 0"
                            @change="cargarDepartamentosCliente(cliente)"
                        >

                            <option value="">
                                {{
                                    paises.length === 0
                                        ? 'No hay países disponibles'
                                        : 'Seleccione un país'
                                }}
                            </option>

                            <option
                                v-for="pais in paises"
                                :key="pais.codigo"
                                :value="pais.codigo"
                            >
                                {{ pais.nombre }}
                            </option>

                        </select>

                    </div>


                    <template v-if="!paisEsExtranjero(cliente.id_pais)">

<!-- DEPARTAMENTO -->

                    <div>

                        <label class="label-form">
                            Departamento *
                        </label>

                        <select
                            v-model="cliente.id_departamento"
                            class="input-form"
                            :disabled="cliente.cargandoDepartamentos"
                            @change="cargarMunicipiosCliente(cliente)"
                        >

                            <option value="">
                                {{
                                    cliente.cargandoDepartamentos
                                        ? 'Cargando departamentos...'
                                        : 'Seleccione un departamento'
                                }}
                            </option>

                            <option
                                v-for="departamento in cliente.departamentos || []"
                                :key="departamento.codigo"
                                :value="departamento.codigo"
                            >
                                {{ departamento.nombre }}
                            </option>

                        </select>

                    </div>


                    <!-- CIUDAD -->

                    <div>

                        <label class="label-form">
                            Ciudad *
                        </label>

                        <select
                            v-model="cliente.id_ciudad"
                            class="input-form"
                            :disabled="!cliente.id_departamento || cliente.cargandoMunicipios"
                        >

                            <option value="">
                                {{
                                    cliente.cargandoMunicipios
                                        ? 'Cargando ciudades...'
                                        : !cliente.id_departamento
                                            ? 'Seleccione primero un departamento'
                                            : 'Seleccione una ciudad'
                                }}
                            </option>

                            <option
                                v-for="municipio in cliente.municipios || []"
                                :key="municipio.codigo"
                                :value="municipio.codigo"
                            >
                                {{ municipio.nombre }}
                            </option>

                        </select>

                    </div>

</template>


                    <!-- TELÉFONO -->

                    <div>

                        <label class="label-form">
                            Teléfono *
                        </label>

                        <input
                            v-model="cliente.telefono"
                            @keydown="bloquearTextoNumerico"
                            @input="cliente.telefono = soloNumeros(cliente.telefono)"
                            type="text"
                            inputmode="numeric"
                            class="input-form"
                            placeholder="Ingrese el teléfono"
                        />

                    </div>


                    <!-- CELULAR -->

                    <div>

                        <label class="label-form">
                            Celular *
                        </label>

                        <input
                            v-model="cliente.celular"
                            @keydown="bloquearTextoNumerico"
                            @input="cliente.celular = soloNumeros(cliente.celular)"
                            type="text"
                            inputmode="numeric"
                            class="input-form"
                            placeholder="Ingrese el celular"
                        />

                    </div>


                    <!-- E-MAIL -->

                    <div class="md:col-span-2">

                        <label class="label-form">
                            E-mail*
                        </label>

                        <input
                            v-model="cliente.email"
                            type="email"
                            class="input-form"
                            placeholder="correo@ejemplo.com"
                        />

                    </div>

                </div>

            </div>


            <!-- AGREGAR CLIENTE -->

            <div class="flex justify-end">

                <button
                    type="button"
                    @click="agregarCliente"
                    class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                >
                    + Agregar cliente
                </button>

            </div>

        </div>

    </div>

</div>


<!-- ================================================= -->
<!-- PASO 6 - REFERENCIAS BANCARIAS -->
<!-- ================================================= -->

<div
    v-if="pasoActual === 6"
    class="p-6 sm:p-8"
>
    <!-- ================================================= -->
    <!-- ENCABEZADO -->
    <!-- ================================================= -->

    <div class="mb-8">
        <h2 class="text-2xl font-bold text-slate-800">
            Referencias bancarias
        </h2>
    </div>


    <!-- ================================================= -->
    <!-- INFORMACIÓN BANCARIA -->
    <!-- ================================================= -->

    <div>

        <div class="mb-5 flex items-center gap-3">

            <div
                class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-800 text-white"
            >
                <span class="text-lg">💵 </span>
            </div>

            <div>
                <h3 class="font-semibold text-slate-800">
                    Información bancaria
                </h3>
            </div>

        </div>


<!-- ================================================= -->
<!-- TARJETAS DE REFERENCIAS BANCARIAS -->
<!-- ================================================= -->

<div
    v-for="(referencia, index) in form.referencias_bancarias"
    :key="index"
    class="mb-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"
>

    <!-- ENCABEZADO DE LA REFERENCIA -->

    <div class="mb-5 flex items-center justify-between">

        <div>
            <h4 class="font-semibold text-slate-800">
                Referencia bancaria {{ index + 1 }}
            </h4>
        </div>

        <button
            v-if="form.referencias_bancarias.length > 1"
            type="button"
            @click="eliminarReferenciaBancaria(index)"
            class="text-sm font-medium text-red-600 hover:text-red-700"
        >
     🗑 Eliminar
        </button>

    </div>


    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

        <!-- ================================================= -->
        <!-- NOMBRE DEL BANCO -->
        <!-- ================================================= -->

        <div>
            <label class="label-form flex items-center gap-2">

                Nombre del banco *

                <span class="group relative inline-flex cursor-help">
                </span>

            </label>

            <input
                v-model="referencia.nombre_banco"
                type="text"
                maxlength="100"
                class="input-form"
                placeholder="Ingrese el nombre del banco"
            />
        </div>


        <!-- ================================================= -->
        <!-- SUCURSAL -->
        <!-- ================================================= -->

        <div>
            <label class="label-form flex items-center gap-2">

                Sucursal *

                <span class="group relative inline-flex cursor-help">
                </span>

            </label>

            <input
                v-model="referencia.sucursal_banco"
                type="text"
                maxlength="100"
                class="input-form"
                placeholder="Ingrese la sucursal"
            />
        </div>


        <!-- ================================================= -->
        <!-- CLASE DE CUENTA -->
        <!-- ================================================= -->

        <div>

            <label class="label-form flex items-center gap-2">

                Clase de cuenta *

                <span class="group relative inline-flex cursor-help">
                </span>

            </label>

            <input
                v-model="referencia.clase_cuenta"
                type="text"
                maxlength="40"
                class="input-form"
                placeholder="Ingrese la clase de cuenta"
            />

        </div>


        <!-- ================================================= -->
        <!-- NÚMERO DE CUENTA -->
        <!-- ================================================= -->

        <div>

            <label class="label-form flex items-center gap-2">

                Número de cuenta *

                <span class="group relative inline-flex cursor-help">
                </span>

            </label>

            <input
                v-model="referencia.numero_cuenta"
                @keydown="bloquearTextoNumerico"
                type="text"
                inputmode="numeric"
                class="input-form"
                @input="referencia.numero_cuenta = soloNumeros(referencia.numero_cuenta)"
                placeholder="Ingrese el número de cuenta"
            />

        </div>

    </div>

</div>


<!-- ================================================= -->
<!-- AGREGAR REFERENCIA -->
<!-- ================================================= -->

<div class="flex justify-end">

    <button
        type="button"
        @click="agregarReferenciaBancaria"
        class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
    >
        + Agregar referencia bancaria
    </button>

</div>
</div>
</div>
</div>


<!-- ================================================= -->
<!-- PASO 7 - INFORMACIÓN SGC -->
<!-- ================================================= -->

<div
    v-if="pasoActual === 7"
    class="p-6 sm:p-8"
>

    <!-- ================================================= -->
    <!-- 1. LA/FT -->
    <!-- ================================================= -->

    <div
        class="mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >

        <!-- TITULO -->

        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">

            <div class="flex items-start gap-4">

                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-800 text-sm font-bold text-white"
                >
                    01
                </div>

                <div>
                    <h3 class="font-bold text-slate-800">
                        Sistema de Autocontrol y Gestión del Riesgo LA/FT
                    </h3>

                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Información relacionada con lavado de activos,
                        financiación del terrorismo y obligaciones de reporte.
                    </p>
                </div>

            </div>

        </div>


        <!-- CONTENIDO -->

        <div class="space-y-4 p-6">

            <!-- CONOCIMIENTO LA/FT -->

            <div class="question-box">

                <div class="question-title">

                    <div class="pr-4">

                        <p class="question-text">
                            ¿Tiene conocimiento sobre el Sistema de Autocontrol
                            y Gestión del Riesgo LA/FT?
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            Seleccione Sí o No según corresponda.
                        </p>

                    </div>

                    <div class="flex shrink-0 gap-2">

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.r_conoce_LA_FT === true
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.r_conoce_LA_FT = true"
                        >
                            Sí
                        </button>

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.r_conoce_LA_FT === false
                                    ? 'border-red-500 bg-red-50 text-red-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.r_conoce_LA_FT = false"
                        >
                            No
                        </button>

                    </div>

                </div>

            </div>


            <!-- OBLIGADO A REPORTAR -->

            <div class="question-box">

                <div class="question-title">

                    <div class="pr-4">

                        <p class="question-text">
                            Entidad obligada a reportar
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            Indique si la entidad está obligada a realizar
                            reportes ante las autoridades correspondientes.
                        </p>

                    </div>

                    <div class="flex shrink-0 gap-2">

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.r_obligado_reportar === true
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.r_obligado_reportar = true"
                        >
                            Sí
                        </button>

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.r_obligado_reportar === false
                                    ? 'border-red-500 bg-red-50 text-red-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.r_obligado_reportar = false"
                        >
                            No
                        </button>

                    </div>

                </div>

            </div>


            <!-- OFICIAL DE CUMPLIMIENTO -->

            <div class="question-box">

                <div class="question-title">

                    <div class="pr-4">

                        <p class="question-text">
                            ¿Cuenta con Oficial de Cumplimiento?
                        </p>

                    </div>

                    <div class="flex shrink-0 gap-2">

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.r_tiene_oficial_cumplimiento === true
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.r_tiene_oficial_cumplimiento = true"
                        >
                            Sí
                        </button>

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.r_tiene_oficial_cumplimiento === false
                                    ? 'border-red-500 bg-red-50 text-red-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.r_tiene_oficial_cumplimiento = false"
                        >
                            No
                        </button>

                    </div>

                </div>


                <!-- DATOS OFICIAL -->

                <div
                    v-if="form.r_tiene_oficial_cumplimiento === true"
                    class="mt-5 rounded-xl border border-slate-200 bg-white p-5"
                >

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

                        <div>

                            <label class="label-form">
                                Nombre(s) y apellidos
                            </label>

                            <input
                                v-model="form.r_nombre_oficial_cumplimiento"
                                type="text"
                                class="input-form"
                                placeholder="Ingrese el nombre completo"
                            />

                        </div>


                        <div>

                            <label class="label-form">
                                Celular
                            </label>

                          <input
    v-model="form.r_celular_oficial_cumplimiento"
    type="text"
    inputmode="numeric"
    maxlength="10"
    @input="form.r_celular_oficial_cumplimiento = soloNumeros(form.r_celular_oficial_cumplimiento)"
    class="input-form"
/>

                        </div>


                        <div class="md:col-span-2">

                            <label class="label-form">
                                E-mail
                            </label>

                            <input
                                v-model="form.r_email_oficial_cumplimiento"
                                type="email"
                                class="input-form"
                                placeholder="Ingrese el correo electrónico"
                            />

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ================================================= -->
    <!-- 2. ACTIVOS VIRTUALES -->
    <!-- ================================================= -->

    <div
        class="mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >

        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">

            <div class="flex items-start gap-4">

                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-800 text-sm font-bold text-white"
                >
                    02
                </div>

                <div>
                    <h3 class="font-bold text-slate-800">
                        Activos virtuales
                    </h3>

                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Información relacionada con el manejo, intercambio
                        o transferencia de activos virtuales.
                    </p>
                </div>

            </div>

        </div>


        <div class="space-y-4 p-6">

            <!-- MANEJA ACTIVOS -->

            <div class="question-box">

                <div class="question-title">

                    <div class="pr-4">

                        <p class="question-text">
                            ¿Maneja activos virtuales?
                        </p>
                    </div>

                    <div class="flex shrink-0 gap-2">

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.a_maneja_activos_viirtuales === true
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.a_maneja_activos_viirtuales = true"
                        >
                            Sí
                        </button>

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.a_maneja_activos_viirtuales === false
                                    ? 'border-red-500 bg-red-50 text-red-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.a_maneja_activos_viirtuales = false"
                        >
                            No
                        </button>

                    </div>

                </div>


                <div
                    v-if="form.a_maneja_activos_viirtuales === true"
                    class="mt-5 border-t border-slate-200 pt-5"
                >

                    <label class="label-form">
                        ¿Cuáles activos virtuales maneja?
                    </label>

                    <input
                        v-model="form.a_cuales_activos_virtuales"
                        type="text"
                        class="input-form"
                        placeholder="Indique cuáles activos virtuales maneja"
                    />

                </div>

            </div>


            <!-- TRANSFIERE ACTIVOS -->

            <div class="question-box">

                <div class="question-title">

                    <div class="pr-4">

                        <p class="question-text">
                            ¿Participa, intercambia o realiza transferencia
                            de activos virtuales?
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            Seleccione Sí o No según corresponda.
                        </p>

                    </div>

                    <div class="flex shrink-0 gap-2">

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.a_tranfiere_activos_virtuales === true
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.a_tranfiere_activos_virtuales = true"
                        >
                            Sí
                        </button>

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.a_tranfiere_activos_virtuales === false
                                    ? 'border-red-500 bg-red-50 text-red-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.a_tranfiere_activos_virtuales = false"
                        >
                            No
                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ================================================= -->
    <!-- 3. OEA -->
    <!-- ================================================= -->

    <div
        class="mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >

        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">

            <div class="flex items-start gap-4">

                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-800 text-sm font-bold text-white"
                >
                    03
                </div>

                <div>
                    <h3 class="font-bold text-slate-800">
                        Certificación OEA y programas de seguridad
                    </h3>
                </div>

            </div>

        </div>


        <div class="p-6">

            <div class="question-box">

                <div class="question-title">

                    <div class="pr-4">

                        <p class="question-text">
                            ¿Cuenta con certificación OEA o cualquier otro
                            programa de seguridad administrado por una
                            aduana extranjera?
                        </p>
                    </div>

                    <div class="flex shrink-0 gap-2">

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.o_tiene_oea === true
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.o_tiene_oea = true"
                        >
                            Sí
                        </button>

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.o_tiene_oea === false
                                    ? 'border-red-500 bg-red-50 text-red-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.o_tiene_oea = false"
                        >
                            No
                        </button>

                    </div>

                </div>


                <div
                    v-if="form.o_tiene_oea === true"
                    class="mt-5 border-t border-slate-200 pt-5"
                >

                    <label class="label-form">
                        ¿Cuál?
                    </label>

                    <input
                        v-model="form.o_cual_oea"
                        type="text"
                        class="input-form"
                        placeholder="Indique la certificación o programa"
                    />

                </div>

            </div>

        </div>

    </div>


    <!-- ================================================= -->
    <!-- 4. PEP -->
    <!-- ================================================= -->

    <div
        class="mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >

        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">

            <div class="flex items-start gap-4">

                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-800 text-sm font-bold text-white"
                >
                    04
                </div>

                <div>
                    <h3 class="font-bold text-slate-800">
                        Personas expuestas pública o políticamente (PEP)
                    </h3>
                </div>

            </div>

        </div>


        <div class="space-y-4 p-6">

            <!-- PERSONA PEP -->

            <div class="question-box">

                <div class="question-title">

                    <div class="pr-4">

                        <p class="question-text">
                            ¿Es una persona expuesta pública o políticamente
                            (PEP)?
                        </p>
                    </div>

                    <div class="flex shrink-0 gap-2">

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.p_persona_publicamente_expuesta === true
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.p_persona_publicamente_expuesta = true"
                        >
                            Sí
                        </button>

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.p_persona_publicamente_expuesta === false
                                    ? 'border-red-500 bg-red-50 text-red-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.p_persona_publicamente_expuesta = false"
                        >
                            No
                        </button>

                    </div>

                </div>


                <!-- DATOS PEP -->

                <div
                    v-if="form.p_persona_publicamente_expuesta === true"
                    class="mt-5 rounded-xl border border-slate-200 bg-white p-5"
                >

                    <p class="mb-4 text-sm font-semibold text-slate-700">
                        Información de la persona públicamente expuesta
                    </p>

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

                        <div class="md:col-span-2">

                            <label class="label-form">
                                Cargo que desempeña
                            </label>

                            <input
                                v-model="form.p_cargo_desempeña"
                                type="text"
                                class="input-form"
                                placeholder="Ingrese el cargo que desempeña"
                            />

                        </div>


                        <div>

                            <label class="label-form">
                                Fecha de inicio del cargo
                            </label>

                            <input
                                v-model="form.p_fecha_inicio_cargo"
                                type="date"
                                class="input-form"
                            />

                        </div>


                        <div>

                            <label class="label-form">
                                Fecha de finalización del cargo
                            </label>

                            <input
                                v-model="form.p_finalizacion_cargo"
                                type="date"
                                class="input-form"
                            />

                        </div>

                    </div>

                </div>

            </div>


            <!-- FAMILIAR PEP -->

            <div class="question-box">

                <div class="question-title">

                    <div class="pr-4">

                        <p class="question-text">
                            ¿Alguien de su familia es considerado PEP?
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            Seleccione Sí o No según corresponda.
                        </p>

                    </div>

                    <div class="flex shrink-0 gap-2">

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.p_familiar_pep === true
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.p_familiar_pep = true"
                        >
                            Sí
                        </button>

                        <button
                            type="button"
                            class="yes-no-button"
                            :class="
                                form.p_familiar_pep === false
                                    ? 'border-red-500 bg-red-50 text-red-700'
                                    : 'border-slate-200 bg-white text-slate-500'
                            "
                            @click="form.p_familiar_pep = false"
                        >
                            No
                        </button>

                    </div>

                </div>

            </div>

        </div>
    </div>
</div>


<!-- ========================================================= -->
<!-- PASO 8 - DECLARACIÓN, AUTORIZACIÓN, FIRMA Y ADJUNTOS    -->
<!-- ========================================================= -->

<div
    v-if="pasoActual === 8"
    class="p-6 sm:p-8"
>

    <!-- ===================================================== -->
    <!-- ADJUNTOS -->
    <!-- ===================================================== -->

    <div class="mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">

            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">
                Adjuntos
            </h3>

            <p class="mt-1 text-sm text-slate-500">
                Adjunte los documentos requeridos para completar el registro.
            </p>

        </div>

        <div class="px-6 py-6">

            <!-- ================================================= -->
            <!-- DOCUMENTOS PARA DILIGENCIAR, FIRMAR Y ADJUNTAR -->
            <!-- ================================================= -->

            <div class="mb-8">

                <h4 class="mb-1 text-sm font-semibold text-slate-800">
                    Documentos para diligenciar y firmar
                </h4>

                <p class="mb-5 text-xs text-slate-500">
                    Consulte y descargue cada documento, diligéncielo, fírmelo y posteriormente adjunte la versión firmada.
                </p>

                <!-- ============================================= -->
                <!-- LISTA DE DOCUMENTOS -->
                <!-- ============================================= -->

                <div class="space-y-4">

<!-- ========================================= -->
<!-- CARTA DE INSTRUCCIONES -->
<!-- ========================================= -->

<div class="rounded-xl border border-slate-200 p-4">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <label class="label-form">
                Carta de instrucciones
            </label>

            <p class="mt-1 text-xs text-slate-500">
                Descargue, diligencie, firme y adjunte la carta de instrucciones.
            </p>
        </div>

        <a
            href="/documentos/Carta%20de%20Instrucciones.pdf"
            target="_blank"
            rel="noopener noreferrer"
            class="shrink-0 text-sm font-semibold text-blue-600 underline underline-offset-2 transition hover:text-blue-800"
        >
            Ver documento
        </a>

    </div>

    <div class="mt-4">

        <input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png"
            class="input-form"
            @change="seleccionarAdjunto('carta_instrucciones', $event)"
        />

        <div
            v-if="form.adjuntos.carta_instrucciones"
            class="mt-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
        >

            <span class="truncate text-xs text-slate-600">
                {{ form.adjuntos.carta_instrucciones.name }}
            </span>

            <button
                type="button"
                class="ml-3 text-xs font-semibold text-red-600 hover:text-red-800"
                @click="eliminarAdjunto('carta_instrucciones')"
            >
                Quitar
            </button>

        </div>

    </div>

</div>

<!-- ========================================= -->
<!-- PAGARÉ -->
<!-- ========================================= -->

<div class="rounded-xl border border-slate-200 p-4">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <label class="label-form">
                Pagaré
            </label>

            <p class="mt-1 text-xs text-slate-500">
                Descargue, diligencie, firme y adjunte el pagaré.
            </p>
        </div>

        <a
            href="/documentos/pagare.pdf"
            target="_blank"
            rel="noopener noreferrer"
            class="shrink-0 text-sm font-semibold text-blue-600 underline underline-offset-2 transition hover:text-blue-800"
        >
            Ver documento
        </a>

    </div>

    <div class="mt-4">

        <input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png"
            class="input-form"
            @change="seleccionarAdjunto('pagare', $event)"
        />

        <div
            v-if="form.adjuntos.pagare"
            class="mt-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
        >

            <span class="truncate text-xs text-slate-600">
                {{ form.adjuntos.pagare.name }}
            </span>

            <button
                type="button"
                class="ml-3 text-xs font-semibold text-red-600 hover:text-red-800"
                @click="eliminarAdjunto('pagare')"
            >
                Quitar
            </button>

        </div>

    </div>

</div>




                    <!-- ========================================= -->
                    <!-- CERTIFICADO DE EXISTENCIA -->
                    <!-- ========================================= -->

                    <div class="rounded-xl border border-slate-200 p-4">

                        <label class="label-form">
                            Certificado de existencia
                        </label>

                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="input-form mt-4"
                            @change="seleccionarAdjunto('certificado_existencia', $event)"
                        />

                        <div
                            v-if="form.adjuntos.certificado_existencia"
                            class="mt-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
                        >

                            <span class="truncate text-xs text-slate-600">
                                {{ form.adjuntos.certificado_existencia.name }}
                            </span>

                            <button
                                type="button"
                                class="ml-3 text-xs font-semibold text-red-600 hover:text-red-800"
                                @click="eliminarAdjunto('certificado_existencia')"
                            >
                                Quitar
                            </button>

                        </div>

                    </div>


                    <!-- ========================================= -->
                    <!-- COPIA RUT -->
                    <!-- ========================================= -->

                    <div class="rounded-xl border border-slate-200 p-4">

                        <label class="label-form">
                            Copia RUT
                        </label>

                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="input-form mt-4"
                            @change="seleccionarAdjunto('copia_rut', $event)"
                        />

                        <div
                            v-if="form.adjuntos.copia_rut"
                            class="mt-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
                        >

                            <span class="truncate text-xs text-slate-600">
                                {{ form.adjuntos.copia_rut.name }}
                            </span>

                            <button
                                type="button"
                                class="ml-3 text-xs font-semibold text-red-600 hover:text-red-800"
                                @click="eliminarAdjunto('copia_rut')"
                            >
                                Quitar
                            </button>

                        </div>

                    </div>

<!-- ========================================= -->
<!-- MANIFESTACIÓN DE ESTADOS FINANCIEROS -->
<!-- ========================================= -->

<div class="rounded-xl border border-slate-200 p-4">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <label class="label-form">
                Manifestación de estados financieros
            </label>

            <p class="mt-1 text-xs text-slate-500">
                Descargue, diligencie y firme el documento antes de adjuntarlo.
            </p>
        </div>

        <a
            href="/documentos/Manifestacion%20Estados%20Financieros.pdf"
            target="_blank"
            rel="noopener noreferrer"
            class="shrink-0 text-sm font-semibold text-blue-600 underline underline-offset-2 transition hover:text-blue-800"
        >
            Ver documento
        </a>

    </div>

    <div class="mt-4">

        <input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png"
            class="input-form"
            @change="seleccionarAdjunto('estados_financieros', $event)"
        />

        <div
            v-if="form.adjuntos.estados_financieros"
            class="mt-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
        >

            <span class="truncate text-xs text-slate-600">
                {{ form.adjuntos.estados_financieros.name }}
            </span>

            <button
                type="button"
                class="ml-3 text-xs font-semibold text-red-600 hover:text-red-800"
                @click="eliminarAdjunto('estados_financieros')"
            >
                Quitar
            </button>

        </div>

    </div>

</div>

                    <!-- ========================================= -->
                    <!-- CÉDULA REPRESENTANTE LEGAL -->
                    <!-- ========================================= -->

                    <div class="rounded-xl border border-slate-200 p-4">

                        <label class="label-form">
                            Cédula representante legal
                        </label>

                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="input-form mt-4"
                            @change="seleccionarAdjunto('cedula_rl', $event)"
                        />

                        <div
                            v-if="form.adjuntos.cedula_rl"
                            class="mt-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
                        >

                            <span class="truncate text-xs text-slate-600">
                                {{ form.adjuntos.cedula_rl.name }}
                            </span>

                            <button
                                type="button"
                                class="ml-3 text-xs font-semibold text-red-600 hover:text-red-800"
                                @click="eliminarAdjunto('cedula_rl')"
                            >
                                Quitar
                            </button>

                        </div>

                    </div>


                    <!-- ========================================= -->
                    <!-- CERTIFICACIÓN BANCARIA -->
                    <!-- ========================================= -->

                    <div class="rounded-xl border border-slate-200 p-4">

                        <label class="label-form">
                            Certificación bancaria
                        </label>

                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="input-form mt-4"
                            @change="seleccionarAdjunto('certificacion_bancaria', $event)"
                        />

                        <div
                            v-if="form.adjuntos.certificacion_bancaria"
                            class="mt-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
                        >

                            <span class="truncate text-xs text-slate-600">
                                {{ form.adjuntos.certificacion_bancaria.name }}
                            </span>

                            <button
                                type="button"
                                class="ml-3 text-xs font-semibold text-red-600 hover:text-red-800"
                                @click="eliminarAdjunto('certificacion_bancaria')"
                            >
                                Quitar
                            </button>

                        </div>

                    </div>


                    <!-- ========================================= -->
                    <!-- CERTIFICACIONES -->
                    <!-- ========================================= -->

                    <div class="rounded-xl border border-slate-200 p-4">

                        <label class="label-form">
                            Certificaciones
                        </label>

                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="input-form mt-4"
                            @change="seleccionarAdjunto('certificaciones', $event)"
                        />

                        <div
                            v-if="form.adjuntos.certificaciones"
                            class="mt-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
                        >

                            <span class="truncate text-xs text-slate-600">
                                {{ form.adjuntos.certificaciones.name }}
                            </span>

                            <button
                                type="button"
                                class="ml-3 text-xs font-semibold text-red-600 hover:text-red-800"
                                @click="eliminarAdjunto('certificaciones')"
                            >
                                Quitar
                            </button>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>




<!-- ===================================================== -->
<!-- DECLARACIONES Y AUTORIZACIONES -->
<!-- ===================================================== -->

<div class="mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

    <!-- ENCABEZADO -->
    <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">

        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">
            Declaraciones y autorizaciones
        </h3>

    </div>

    <!-- CONTENIDO -->
    <div class="px-6 py-6">

        <div class="rounded-xl border border-slate-200 bg-white p-5">

            <!-- PREGUNTA -->
            <div>
                <p class="text-sm font-semibold text-slate-800">
                    ¿Acepta la política de tratamiento de datos?
                </p>

                <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">

                    <p class="text-sm text-slate-500">
                        Puede consultar el contenido completo de las declaraciones antes de seleccionar una opción.
                    </p>

                    <!-- ENLACE / VENTANA DE DECLARACIONES -->
                    <button
                        type="button"
                        class="shrink-0 text-sm font-semibold text-blue-600 underline underline-offset-2 transition hover:text-blue-800"
                        @click="abrirDeclaraciones"
                    >
                        Ver declaraciones y autorizaciones
                    </button>

                </div>
            </div>

            <!-- SEPARADOR -->
            <div class="my-5 border-t border-slate-200"></div>

            <!-- SIN RESPUESTA -->
            <div
                v-if="form.acepta_politica_datos === null"
                class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3"
            >
                <p class="text-sm font-medium text-slate-700">
                    Pendiente de respuesta
                </p>

                <p class="mt-1 text-xs text-slate-500">
                    Consulte las declaraciones y seleccione si acepta o no continuar.
                </p>
            </div>

            <!-- ACEPTA -->
<div
    v-else-if="form.acepta_politica_datos === true"
    class="mt-4 flex items-center justify-end"
>
    <span
        class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700"
    >
        Aceptado
    </span>
</div>

<!-- NO ACEPTA -->
<div
    v-else
    class="mt-4 flex items-center justify-end"
>
    <span
        class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700"
    >
        No aceptado
    </span>
</div>
</div>
</div>
</div>

    <!-- ================================================= -->
<!-- FIRMA -->
<!-- ================================================= -->

<div
    class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
>
    <!-- ENCABEZADO -->

    <div
        class="border-b border-slate-200 bg-slate-50 px-6 py-5"
    >
        <div class="flex items-center gap-3">

            <div
                class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M16.862 3.487a2.25 2.25 0 013.182 3.182L8.25 18.463 4 19.5l1.037-4.25L16.862 3.487z"
                    />
                </svg>
            </div>

            <div>
                <h3 class="font-bold text-slate-800">
                    Firma
                </h3>

                <p class="mt-1 text-xs text-slate-500">
                    Firme directamente o cargue una imagen de su firma.
                </p>
            </div>

        </div>
    </div>


    <!-- CONTENIDO -->

    <div class="p-6">

        <!-- CANVAS -->

        <div
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
        >

            <canvas
                ref="canvasFirma"
                class="block w-full rounded-lg border border-gray-300 bg-white shadow-inner touch-none cursor-crosshair"
                @pointerdown="iniciarFirma"
                @pointermove="dibujarFirma"
                @pointerup="terminarFirma"
                @pointercancel="terminarFirma"
                @pointerleave="terminarFirma"
            ></canvas>

        </div>


        <!-- INDICACIÓN -->

        <p class="mt-3 text-xs text-slate-500">
            Dibuje su firma dentro del recuadro utilizando el mouse,
            trackpad o pantalla táctil.
        </p>


        <!-- BOTONES -->

        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">

            <!-- LIMPIAR -->

            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-md"
                @click="limpiarFirma"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-8 0h10"
                    />
                </svg>

                Limpiar
            </button>


            <!-- GUARDAR -->

            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
                @click="guardarFirma"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M5 4h11l3 3v13H5V4zm3 0v5h8V4M8 20v-6h8v6"
                    />
                </svg>

                Guardar
            </button>


            <!-- CARGAR IMAGEN -->

            <label
                class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-5 py-2.5 text-sm font-semibold text-blue-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-100 hover:shadow-md"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M4 16l4-4a2 2 0 012.828 0L16 17m-2-2l1.586-1.586a2 2 0 012.828 0L20 16M14 8h.01M5 20h14a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v14a1 1 0 001 1z"
                    />
                </svg>

                Cargar imagen

                <input
                    type="file"
                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                    class="hidden"
                    @change="cargarFirma"
                />
            </label>

        </div>


        <!-- ESTADO -->

        <div
            v-if="firmaGuardada"
            class="mt-5 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3"
        >

            <div
                class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-green-600"
            >
                ✓
            </div>

            <div>
                <p class="text-sm font-semibold text-green-800">
                    Firma guardada
                </p>

                <p class="text-xs text-green-700">
                    La firma quedó lista para enviar con el formulario.
                </p>
            </div>

        </div>

        </div>

    </div>

</div>



<!-- ========================================================= -->
<!-- PASO 9 - CONFIRMACIÓN / VISTA PREVIA                     -->
<!-- ========================================================= -->

<div
    v-if="pasoActual === 9"
    class="bg-slate-100 px-2 py-6 sm:px-4 lg:px-6"
>
    <div
        id="formulario-preview"
        class="mx-auto max-w-6xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
    >

        <!-- ================================================= -->
        <!-- ENCABEZADO INSTITUCIONAL -->
        <!-- ================================================= -->

        <div class="border-b-4 border-slate-800 bg-white px-6 py-7 sm:px-10">

            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">

                <div>
                    <div class="text-3xl font-extrabold tracking-wide text-slate-900">
                        SENCO
                    </div>

                    <div class="mt-2 text-sm font-bold uppercase tracking-[0.12em] text-slate-700">
                        Formulario para el conocimiento del cliente
                    </div>

                    <div class="mt-1 text-xs text-slate-500">
                        Registro de información de terceros
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 px-5 py-4 sm:min-w-[220px]">
                    <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">
                        Fecha del formulario
                    </div>

                    <div class="mt-1 text-sm font-bold text-slate-800">
                        {{ form.fecha_frm || '—' }}
                    </div>
                </div>

            </div>

            <!-- IDENTIFICACIÓN PRINCIPAL -->

            <div class="mt-7 grid grid-cols-1 gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-3">

                <div class="bg-white px-4 py-4">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Tipo de tercero
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{
                            tipoTerceroSeleccionado?.Tipo_tercero?.trim?.()
                            || tipoTerceroSeleccionado?.TipoTercero?.trim?.()
                            || tipoTerceroSeleccionado?.Descripcion?.trim?.()
                            || tipoTerceroSeleccionado?.Nombre?.trim?.()
                            || '—'
                        }}
                    </div>
                </div>

                <div class="bg-white px-4 py-4">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Tipo de formulario
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{ tipoFormularioSeleccionado?.Tipo_frm?.trim?.() || '—' }}
                    </div>
                </div>

                <div class="bg-white px-4 py-4">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Tipo de persona
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{ form.tipo_persona || '—' }}
                    </div>
                </div>

            </div>

        </div>


        <!-- ================================================= -->
        <!-- BLOQUE 01 - INFORMACIÓN GENERAL -->
        <!-- ================================================= -->

        <section class="border-b border-slate-200">

            <div class="flex items-center gap-3 bg-slate-800 px-6 py-3 sm:px-10">
                <span class="text-xs font-bold text-slate-300">01</span>

                <h3 class="text-sm font-bold uppercase tracking-wider text-white">
                    Información general
                </h3>
            </div>

            <div class="grid grid-cols-1 gap-px bg-slate-200 md:grid-cols-2">

                <div class="bg-white px-6 py-5">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Tipo de documento
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{ nombreTipoIdentificacion(form.tipo_doc) }}
                    </div>
                </div>

                <div class="bg-white px-6 py-5">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Número de documento
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{ form.numero_doc || '—' }}
                    </div>
                </div>

                <div class="bg-white px-6 py-5 md:col-span-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Razón social / Nombres y apellidos
                    </div>

                    <div class="mt-1 text-base font-bold text-slate-900">
                        {{ form.razon_social || '—' }}
                    </div>
                </div>

                <div class="bg-white px-6 py-5">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        ¿Tiene establecimiento de comercio?
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{ mostrarSiNo(form.tiene_establecimiento) }}
                    </div>
                </div>

                <div class="bg-white px-6 py-5">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Nombre del establecimiento
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{ form.nombre_establecimiento || '—' }}
                    </div>
                </div>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- BLOQUE 02 - CONTACTO Y UBICACIÓN -->
        <!-- ================================================= -->

        <section class="border-b border-slate-200">

            <div class="flex items-center gap-3 bg-slate-800 px-6 py-3 sm:px-10">
                <span class="text-xs font-bold text-slate-300">02</span>

                <h3 class="text-sm font-bold uppercase tracking-wider text-white">
                    Información de contacto y ubicación
                </h3>
            </div>

            <div class="grid grid-cols-1 gap-px bg-slate-200 md:grid-cols-2">

                <div class="bg-white px-6 py-5 md:col-span-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Dirección
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{ form.direccion || '—' }}
                    </div>
                </div>

                <div class="bg-white px-6 py-5">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Departamento
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{
                            departamentos.find(
                                departamento =>
                                    String(
                                        departamento.Equivalencia ??
                                        departamento['Código DIAN'] ??
                                        departamento.codigo ??
                                        departamento.id
                                    ) === String(form.id_departamento)
                            )?.Departamento
                            ||
                            departamentos.find(
                                departamento =>
                                    String(departamento.codigo ?? departamento.id) ===
                                    String(form.id_departamento)
                            )?.nombre
                            ||
                            '—'
                        }}
                    </div>
                </div>

                <div class="bg-white px-6 py-5">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Ciudad / Municipio
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{
                            municipios.find(
                                municipio =>
                                    String(
                                        municipio.Equivalencia ??
                                        municipio['Código'] ??
                                        municipio.codigo ??
                                        municipio.IdMunicipio ??
                                        municipio.id
                                    ) === String(form.id_ciudad)
                            )?.Municipio
                            ||
                            municipios.find(
                                municipio =>
                                    String(municipio.IdMunicipio ?? municipio.id) ===
                                    String(form.id_ciudad)
                            )?.Nombre
                            ||
                            municipios.find(
                                municipio =>
                                    String(municipio.codigo) === String(form.id_ciudad)
                            )?.nombre
                            ||
                            '—'
                        }}
                    </div>
                </div>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- REPRESENTANTE LEGAL -->
        <!-- ================================================= -->

        <section class="border-b border-slate-200">

            <div class="flex items-center gap-3 bg-slate-800 px-6 py-3 sm:px-10">
                <h3 class="text-sm font-bold uppercase tracking-wider text-white">
                    Representante legal
                </h3>
            </div>

            <div class="grid grid-cols-1 gap-px bg-slate-200 md:grid-cols-2">

                <div class="bg-white px-6 py-5 md:col-span-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Nombre completo
                    </div>

                    <div class="mt-1 text-base font-bold text-slate-900">
                        {{ form.rl_nombre || '—' }}
                    </div>
                </div>

                <div class="bg-white px-6 py-5">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Tipo de documento
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{ nombreTipoIdentificacion(form.rl_tipo_doc) }}
                    </div>
                </div>

                <div class="bg-white px-6 py-5">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Número de documento
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{ form.rl_numero_doc || '—' }}
                    </div>
                </div>

                <div class="bg-white px-6 py-5 md:col-span-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Correo electrónico
                    </div>

                    <div class="mt-1 break-words text-sm font-semibold text-slate-800">
                        {{ form.rl_email || '—' }}
                    </div>
                </div>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- TESORERÍA -->
        <!-- ================================================= -->

        <section class="border-b border-slate-200">

            <div class="bg-slate-50 px-6 py-4 sm:px-10">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800">
                    Información de tesorería
                </h3>
            </div>

            <div class="grid grid-cols-1 gap-px bg-slate-200 md:grid-cols-3">

                <div class="bg-white px-6 py-5">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Nombre
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{ form.tesoreria_nombre || '—' }}
                    </div>
                </div>

                <div class="bg-white px-6 py-5">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Teléfono
                    </div>

                    <div class="mt-1 text-sm font-semibold text-slate-800">
                        {{ form.tesoreria_telefono || '—' }}
                    </div>
                </div>

                <div class="bg-white px-6 py-5">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Correo electrónico
                    </div>

                    <div class="mt-1 break-words text-sm font-semibold text-slate-800">
                        {{ form.tesoreria_email || '—' }}
                    </div>
                </div>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- BLOQUE 03 - DIRECCIONES DE ENVÍO -->
        <!-- ================================================= -->

        <section class="border-b border-slate-200">

            <div class="flex items-center gap-3 bg-slate-800 px-6 py-3 sm:px-10">
                <span class="text-xs font-bold text-slate-300">03</span>

                <h3 class="text-sm font-bold uppercase tracking-wider text-white">
                    Direcciones de envío
                </h3>
            </div>

            <div class="px-6 py-6 sm:px-10">

                <div class="overflow-hidden rounded-lg border border-slate-200">

                    <div class="overflow-x-auto">

                        <table class="min-w-full text-left text-sm">

                            <thead class="bg-slate-50">
                                <tr class="border-b border-slate-200 text-[10px] uppercase tracking-wider text-slate-500">
                                    <th class="px-4 py-3 font-bold">Dirección</th>
                                    <th class="px-4 py-3 font-bold">Barrio</th>
                                    <th class="px-4 py-3 font-bold">Departamento</th>
                                    <th class="px-4 py-3 font-bold">Ciudad / Municipio</th>
                                    <th class="px-4 py-3 font-bold">Observaciones</th>
                                </tr>
                            </thead>

                            <tbody>

                                <tr
                                    v-if="!form.direcciones_envio || form.direcciones_envio.length === 0"
                                >
                                    <td
                                        colspan="5"
                                        class="px-4 py-7 text-center text-sm text-slate-400"
                                    >
                                        No se registraron direcciones de envío.
                                    </td>
                                </tr>

                                <tr
                                    v-for="(direccion, index) in (form.direcciones_envio || [])"
                                    :key="index"
                                    class="border-b border-slate-100 last:border-0"
                                >
                                    <td class="px-4 py-4 font-semibold text-slate-800">
                                        {{ direccion.direccion || '—' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ direccion.barrio || '—' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{
                                            departamentos.find(
                                                departamento =>
                                                    String(
                                                        departamento.Equivalencia ??
                                                        departamento['Código DIAN'] ??
                                                        departamento.codigo ??
                                                        departamento.id
                                                    ) === String(direccion.id_departamento)
                                            )?.Departamento
                                            ||
                                            departamentos.find(
                                                departamento =>
                                                    String(departamento.codigo ?? departamento.id) ===
                                                    String(direccion.id_departamento)
                                            )?.nombre
                                            ||
                                            '—'
                                        }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{
                                            direccion.municipios?.find(
                                                municipio =>
                                                    String(
                                                        municipio.Equivalencia ??
                                                        municipio['Código'] ??
                                                        municipio.codigo ??
                                                        municipio.IdMunicipio ??
                                                        municipio.id
                                                    ) === String(direccion.id_municipio)
                                            )?.Municipio
                                            ||
                                            direccion.municipios?.find(
                                                municipio =>
                                                    String(municipio.IdMunicipio ?? municipio.id) ===
                                                    String(direccion.id_municipio)
                                            )?.Nombre
                                            ||
                                            '—'
                                        }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ direccion.observaciones || '—' }}
                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- BLOQUE 04 - SOCIOS -->
        <!-- ================================================= -->

        <section class="border-b border-slate-200">

            <div class="flex items-center gap-3 bg-slate-800 px-6 py-3 sm:px-10">
                <span class="text-xs font-bold text-slate-300">04</span>

                <h3 class="text-sm font-bold uppercase tracking-wider text-white">
                    Información de socios
                </h3>
            </div>

            <div class="px-6 py-6 sm:px-10">

                <div class="overflow-hidden rounded-lg border border-slate-200">

                    <div class="overflow-x-auto">

                        <table class="min-w-full text-left text-sm">

                            <thead class="bg-slate-50">
                                <tr class="border-b border-slate-200 text-[10px] uppercase tracking-wider text-slate-500">
                                    <th class="px-4 py-3 font-bold">Nombres y apellidos</th>
                                    <th class="px-4 py-3 font-bold">Tipo de identificación</th>
                                    <th class="px-4 py-3 font-bold">Número de identificación</th>
                                    <th class="px-4 py-3 text-right font-bold">Participación</th>
                                </tr>
                            </thead>

                            <tbody>

                                <tr
                                    v-if="!form.socios || form.socios.length === 0"
                                >
                                    <td
                                        colspan="4"
                                        class="px-4 py-7 text-center text-sm text-slate-400"
                                    >
                                        No se registraron socios.
                                    </td>
                                </tr>

                                <tr
                                    v-for="(socio, index) in (form.socios || [])"
                                    :key="index"
                                    class="border-b border-slate-100 last:border-0"
                                >
                                   <td class="px-4 py-4 font-semibold text-slate-800">
    {{ [socio.nombres, socio.apellidos].filter(Boolean).join(' ') || '—' }}
</td>

<td class="px-4 py-4 text-slate-700">
    {{ nombreTipoIdentificacion(socio.tipo_identificacion) }}
</td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ socio.numero_identificacion || '—' }}
                                    </td>

                                    <td class="px-4 py-4 text-right font-semibold text-slate-800">
                                        {{
                                            socio.porcentaje_participacion !== '' &&
                                            socio.porcentaje_participacion !== null &&
                                            socio.porcentaje_participacion !== undefined
                                                ? socio.porcentaje_participacion + ' %'
                                                : '—'
                                        }}
                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- BLOQUE 05 - INFORMACIÓN FINANCIERA -->
        <!-- ================================================= -->

        <section class="border-b border-slate-200">

            <div class="flex items-center gap-3 bg-slate-800 px-6 py-3 sm:px-10">
                <span class="text-xs font-bold text-slate-300">05</span>

                <h3 class="text-sm font-bold uppercase tracking-wider text-white">
                    Información financiera y tributaria
                </h3>
            </div>

            <div class="px-6 py-6 sm:px-10">

                <div class="mb-5 rounded-lg border border-slate-200 bg-slate-50 px-5 py-4">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Fecha de corte
                    </div>

                    <div class="mt-1 text-sm font-bold text-slate-800">
                        {{ form.fecha_corte || '—' }}
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

                    <div class="rounded-lg border border-slate-200 bg-white p-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Activos
                        </div>

                        <div class="mt-2 text-base font-bold text-slate-900">
                           {{ mostrarValor(form.activos) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Pasivos
                        </div>

                        <div class="mt-2 text-base font-bold text-slate-900">
                            {{ mostrarValor(form.pasivos) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Ingresos
                        </div>

                        <div class="mt-2 text-base font-bold text-slate-900">
                            {{ mostrarValor(form.ingresos) }}
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Egresos
                        </div>

                        <div class="mt-2 text-base font-bold text-slate-900">
                            {{ mostrarValor(form.egresos) }}
                        </div>
                    </div>

                </div>

                <div class="mt-5 grid grid-cols-1 gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-2 lg:grid-cols-4">

                    <div class="bg-white px-5 py-4">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Responsable de IVA
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ mostrarSiNo(form.responsable_iva) }}
                        </div>
                    </div>

                    <div class="bg-white px-5 py-4">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Declara renta
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ mostrarSiNo(form.declara_renta) }}
                        </div>
                    </div>

                    <div class="bg-white px-5 py-4">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Gran contribuyente
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ mostrarSiNo(form.gran_contribuyente) }}
                        </div>
                    </div>

                    <div class="bg-white px-5 py-4">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Autorretenedor
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ mostrarSiNo(form.autoretenedor) }}
                        </div>
                    </div>

                </div>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- BLOQUE 06 - REFERENCIAS COMERCIALES -->
        <!-- ================================================= -->

        <section class="border-b border-slate-200">

            <div class="flex items-center gap-3 bg-slate-800 px-6 py-3 sm:px-10">
                <span class="text-xs font-bold text-slate-300">06</span>

                <h3 class="text-sm font-bold uppercase tracking-wider text-white">
                    Referencias comerciales
                </h3>
            </div>


            <!-- PROVEEDORES -->

            <div class="px-6 pt-6 sm:px-10">

                <div class="mb-4 flex items-center gap-3">
                    <div class="h-6 w-1 rounded-full bg-slate-800"></div>

                    <h4 class="text-sm font-bold uppercase tracking-wider text-slate-800">
                        Proveedores
                    </h4>
                </div>

                <div class="overflow-hidden rounded-lg border border-slate-200">

                    <div class="overflow-x-auto">

                        <table class="min-w-full text-left text-sm">

                            <thead class="bg-slate-50">
                                <tr class="border-b border-slate-200 text-[10px] uppercase tracking-wider text-slate-500">
                                    <th class="px-4 py-3 font-bold">Nombre / Razón social</th>
                                    <th class="px-4 py-3 font-bold">Identificación</th>
                                    <th class="px-4 py-3 font-bold">Dirección</th>
                                    <th class="px-4 py-3 font-bold">Teléfono</th>
                                    <th class="px-4 py-3 font-bold">Celular</th>
                                    <th class="px-4 py-3 font-bold">Correo</th>
                                </tr>
                            </thead>

                            <tbody>

                                <tr
                                    v-if="!form.proveedores || form.proveedores.length === 0"
                                >
                                    <td
                                        colspan="6"
                                        class="px-4 py-7 text-center text-sm text-slate-400"
                                    >
                                        No se registraron proveedores.
                                    </td>
                                </tr>

                                <tr
                                    v-for="(proveedor, index) in (form.proveedores || [])"
                                    :key="index"
                                    class="border-b border-slate-100 last:border-0"
                                >
                                    <td class="px-4 py-4 font-semibold text-slate-800">
                                        {{ proveedor.nombre_razon_social || '—' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ nombreTipoIdentificacion(proveedor.tipo_identificacion) }}

                                        <span v-if="proveedor.numero_identificacion">
                                            - {{ proveedor.numero_identificacion }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ proveedor.direccion || '—' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ proveedor.telefono || '—' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ proveedor.celular || '—' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ proveedor.email || '—' }}
                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>


            <!-- CLIENTES -->

            <div class="px-6 py-7 sm:px-10">

                <div class="mb-4 flex items-center gap-3">
                    <div class="h-6 w-1 rounded-full bg-slate-800"></div>

                    <h4 class="text-sm font-bold uppercase tracking-wider text-slate-800">
                        Clientes
                    </h4>
                </div>

                <div class="overflow-hidden rounded-lg border border-slate-200">

                    <div class="overflow-x-auto">

                        <table class="min-w-full text-left text-sm">

                            <thead class="bg-slate-50">
                                <tr class="border-b border-slate-200 text-[10px] uppercase tracking-wider text-slate-500">
                                    <th class="px-4 py-3 font-bold">Nombre / Razón social</th>
                                    <th class="px-4 py-3 font-bold">Identificación</th>
                                    <th class="px-4 py-3 font-bold">Dirección</th>
                                    <th class="px-4 py-3 font-bold">Teléfono</th>
                                    <th class="px-4 py-3 font-bold">Celular</th>
                                    <th class="px-4 py-3 font-bold">Correo</th>
                                </tr>
                            </thead>

                            <tbody>

                                <tr
                                    v-if="!form.clientes || form.clientes.length === 0"
                                >
                                    <td
                                        colspan="6"
                                        class="px-4 py-7 text-center text-sm text-slate-400"
                                    >
                                        No se registraron clientes.
                                    </td>
                                </tr>

                                <tr
                                    v-for="(cliente, index) in (form.clientes || [])"
                                    :key="index"
                                    class="border-b border-slate-100 last:border-0"
                                >
                                    <td class="px-4 py-4 font-semibold text-slate-800">
                                        {{ cliente.nombre_razon_social || '—' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ nombreTipoIdentificacion(cliente.tipo_identificacion) }}

                                        <span v-if="cliente.numero_identificacion">
                                            - {{ cliente.numero_identificacion }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ cliente.direccion || '—' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ cliente.telefono || '—' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ cliente.celular || '—' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ cliente.email || '—' }}
                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- REFERENCIA BANCARIA -->
        <!-- ================================================= -->

        <section class="border-b border-slate-200">

            <div class="bg-slate-200">
    <div
        v-if="!form.referencias_bancarias?.length"
        class="bg-white px-6 py-6 text-sm text-slate-500 sm:px-10"
    >
        No se registraron referencias bancarias.
    </div>

    <div
        v-for="(referencia, index) in (form.referencias_bancarias || [])"
        :key="index"
        class="mb-px bg-white last:mb-0"
    >
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-3 sm:px-10">
            <h4 class="text-sm font-semibold text-slate-800">
                Referencia bancaria {{ index + 1 }}
            </h4>
        </div>

        <div class="grid grid-cols-1 gap-px bg-slate-200 md:grid-cols-2">
            <div class="bg-white px-6 py-5">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                    Entidad financiera
                </div>
                <div class="mt-1 text-sm font-semibold text-slate-800">
                    {{ referencia.nombre_banco || '—' }}
                </div>
            </div>

            <div class="bg-white px-6 py-5">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                    Sucursal
                </div>
                <div class="mt-1 text-sm font-semibold text-slate-800">
                    {{ referencia.sucursal_banco || '—' }}
                </div>
            </div>

            <div class="bg-white px-6 py-5">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                    Clase de cuenta
                </div>
                <div class="mt-1 text-sm font-semibold text-slate-800">
                    {{ referencia.clase_cuenta || '—' }}
                </div>
            </div>

            <div class="bg-white px-6 py-5">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                    Número de cuenta
                </div>
                <div class="mt-1 text-sm font-semibold text-slate-800">
                    {{ referencia.numero_cuenta || '—' }}
                </div>
            </div>
        </div>
    </div>
</div>

        </section>


        <!-- ================================================= -->
        <!-- BLOQUE 07 - INFORMACIÓN SGC -->
        <!-- ================================================= -->

        <section class="border-b border-slate-200">

            <div class="flex items-center gap-3 bg-slate-800 px-6 py-3 sm:px-10">
                <span class="text-xs font-bold text-slate-300">07</span>

                <h3 class="text-sm font-bold uppercase tracking-wider text-white">
                    Información SGC
                </h3>
            </div>


            <!-- LA / FT -->

            <div class="border-b border-slate-200">

                <div class="bg-slate-50 px-6 py-4 sm:px-10">
                    <h4 class="text-sm font-bold text-slate-800">
                        Sistema de Autocontrol y Gestión del Riesgo LA/FT
                    </h4>
                </div>

                <div class="grid grid-cols-1 gap-px bg-slate-200 md:grid-cols-2">

                    <div class="bg-white px-6 py-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Conoce el sistema de riesgo LA/FT
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ mostrarSiNo(form.r_conoce_LA_FT) }}
                        </div>
                    </div>

                    <div class="bg-white px-6 py-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Entidad obligada a reportar
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ mostrarSiNo(form.r_obligado_reportar) }}
                        </div>
                    </div>

                    <div class="bg-white px-6 py-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Cuenta con Oficial de Cumplimiento
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ mostrarSiNo(form.r_tiene_oficial_cumplimiento) }}
                        </div>
                    </div>

                    <div class="bg-white px-6 py-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Nombre del Oficial de Cumplimiento
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ form.r_nombre_oficial_cumplimiento || '—' }}
                        </div>
                    </div>

                    <div class="bg-white px-6 py-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Celular del Oficial de Cumplimiento
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ form.r_celular_oficial_cumplimiento || '—' }}
                        </div>
                    </div>

                    <div class="bg-white px-6 py-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Correo del Oficial de Cumplimiento
                        </div>

                        <div class="mt-1 break-words text-sm font-semibold text-slate-800">
                            {{ form.r_email_oficial_cumplimiento || '—' }}
                        </div>
                    </div>

                </div>

            </div>


            <!-- ACTIVOS VIRTUALES -->

            <div class="border-b border-slate-200">

                <div class="bg-slate-50 px-6 py-4 sm:px-10">
                    <h4 class="text-sm font-bold text-slate-800">
                        Activos virtuales
                    </h4>
                </div>

                <div class="grid grid-cols-1 gap-px bg-slate-200 md:grid-cols-2">

                    <div class="bg-white px-6 py-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Maneja activos virtuales
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ mostrarSiNo(form.a_maneja_activos_viirtuales) }}
                        </div>
                    </div>

                    <div class="bg-white px-6 py-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            ¿Cuáles activos virtuales?
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ form.a_cuales_activos_virtuales || '—' }}
                        </div>
                    </div>

                    <div class="bg-white px-6 py-5 md:col-span-2">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Transfiere activos virtuales
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ mostrarSiNo(form.a_tranfiere_activos_virtuales) }}
                        </div>
                    </div>

                </div>

            </div>


            <!-- OEA -->

            <div class="border-b border-slate-200">

                <div class="bg-slate-50 px-6 py-4 sm:px-10">
                    <h4 class="text-sm font-bold text-slate-800">
                        OEA / Otro
                    </h4>
                </div>

                <div class="grid grid-cols-1 gap-px bg-slate-200 md:grid-cols-2">

                    <div class="bg-white px-6 py-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            ¿Tiene OEA?
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ mostrarSiNo(form.o_tiene_oea) }}
                        </div>
                    </div>

                    <div class="bg-white px-6 py-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            ¿Cuál OEA?
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ form.o_cual_oea || '—' }}
                        </div>
                    </div>

                </div>

            </div>


            <!-- PEP -->

            <div>

                <div class="bg-slate-50 px-6 py-4 sm:px-10">
                    <h4 class="text-sm font-bold text-slate-800">
                        Persona Expuesta Políticamente (PEP)
                    </h4>
                </div>

                <div class="grid grid-cols-1 gap-px bg-slate-200 md:grid-cols-2">

                    <div class="bg-white px-6 py-5 md:col-span-2">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Persona públicamente expuesta
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ mostrarSiNo(form.p_persona_publicamente_expuesta) }}
                        </div>
                    </div>

                    <div class="bg-white px-6 py-5 md:col-span-2">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Cargo que desempeña
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ form.p_cargo_desempeña || '—' }}
                        </div>
                    </div>

                    <div class="bg-white px-6 py-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Fecha de inicio del cargo
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ form.p_fecha_inicio_cargo || '—' }}
                        </div>
                    </div>

                    <div class="bg-white px-6 py-5">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Finalización del cargo
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ form.p_finalizacion_cargo || '—' }}
                        </div>
                    </div>

                    <div class="bg-white px-6 py-5 md:col-span-2">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Familiar PEP
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-800">
                            {{ mostrarSiNo(form.p_familiar_pep) }}
                        </div>
                    </div>

                </div>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- BLOQUE 08 - DECLARACIÓN Y AUTORIZACIÓN -->
        <!-- ================================================= -->

        <section class="border-b border-slate-200">

            <div class="flex items-center gap-3 bg-slate-800 px-6 py-3 sm:px-10">

                <span class="text-xs font-bold text-slate-300">
                    08
                </span>

                <h3 class="text-sm font-bold uppercase tracking-wider text-white">
                    Declaración y autorización
                </h3>

            </div>

            <div class="px-6 py-7 sm:px-10">

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-6">

                    <div class="flex items-start gap-4">

                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-800 text-white">

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>

                        </div>

                        <div>

                            <h4 class="text-sm font-bold text-slate-800">
                                Declaración del tercero
                            </h4>

                            <p class="mt-2 text-sm leading-7 text-slate-600">
                                Declaro que la información suministrada en este formulario es
                                verdadera, completa y corresponde a la realidad. Así mismo,
                                autorizo a SENCO para realizar el tratamiento de los datos
                                personales suministrados de acuerdo con las políticas y
                                disposiciones aplicables.
                            </p>

                        </div>

                    </div>

                </div>


                <!-- ESTADOS -->

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">

                    <!-- POLÍTICA -->

                    <div
                        class="rounded-xl border p-5 transition"
                        :class="form.acepta_politica_datos === true
                            ? 'border-green-200 bg-green-50/60'
                            : 'border-slate-200 bg-white'"
                    >

                        <div class="flex items-start gap-4">

                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                                :class="form.acepta_politica_datos === true
                                    ? 'bg-green-100 text-green-600'
                                    : 'bg-slate-100 text-slate-400'"
                            >

                                <svg
                                    v-if="form.acepta_politica_datos === true"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8.25 8.25a1 1 0 01-1.414 0l-4.25-4.25a1 1 0 011.414-1.414l3.543 3.543 7.543-7.543a1 1 0 011.414 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>

                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v2m0 4h.01M12 3a9 9 0 100 18 9 9 0 000-18z"
                                    />
                                </svg>

                            </div>

                            <div>

                                <div class="text-sm font-bold text-slate-800">
                                    Política de tratamiento de datos
                                </div>

                                <div class="mt-1 text-xs text-slate-500">
                                    <span v-if="form.acepta_politica_datos === true">
                                        Acepto la política de tratamiento de datos.
                                    </span>

                                    <span v-else-if="form.acepta_politica_datos === false">
                                        No acepto la política de tratamiento de datos.
                                    </span>

                                    <span v-else>
                                        No se ha registrado una respuesta.
                                    </span>
                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- FIRMA -->

                    <div
                        class="rounded-xl border p-5 transition"
                        :class="firmaRepresentante
                            ? 'border-green-200 bg-green-50/60'
                            : 'border-slate-200 bg-white'"
                    >

                        <div class="flex items-start gap-4">

                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                                :class="firmaRepresentante
                                    ? 'bg-green-100 text-green-600'
                                    : 'bg-slate-100 text-slate-400'"
                            >

                                <svg
                                    v-if="firmaRepresentante"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M16.707 5.293 8.457 13.543a1 1 0 0 1-1.414 0l-4.25-4.25a1 1 0 011.414-1.414l3.543 3.543 7.543-7.543a1 1 0 011.414 0Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>

                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v2m0 4h.01M12 3a9 9 0 100 18 9 9 0 000-18z"
                                    />
                                </svg>

                            </div>

                            <div class="w-full">

                                <div class="text-sm font-bold text-slate-800">
                                    Firma del representante
                                </div>

                                <div
                                    v-if="firmaRepresentante"
                                    class="mt-4 rounded-lg border border-slate-200 bg-white p-4"
                                >
                                    <img
                                        :src="firmaRepresentante"
                                        alt="Firma del representante"
                                        class="mx-auto max-h-28 max-w-full object-contain"
                                    />
                                </div>

                                <div
                                    v-else
                                    class="mt-4 rounded-lg border border-slate-200 bg-white p-4"
                                >
                                    <div class="text-center text-xs text-slate-500">
                                        Firma no registrada
                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- CIERRE DE LA VISTA PREVIA -->
        <!-- ================================================= -->

        <div class="bg-slate-50 px-6 py-7 sm:px-10">

            <div class="rounded-xl border border-slate-200 bg-white p-6">

                <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">

                    <div class="flex items-start gap-4">

                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-800 text-white">

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>

                        </div>

                        <div>

                            <h4 class="text-sm font-bold text-slate-800">
                                Revisión de la información
                            </h4>

                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                                Verifique que la información registrada sea correcta antes
                                de finalizar el proceso. Una vez enviado el formulario,
                                la información será registrada en el sistema.
                            </p>

                        </div>

                    </div>

                    <div class="shrink-0 text-left sm:text-right">

                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            Estado
                        </div>

                        <div class="mt-1 text-sm font-bold text-slate-800">
                            Información lista para envío
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>
</div>

<!-- ================================================= -->
<!-- VER PDF - SOLO PASO 9 -->
<!-- ================================================= -->

<div
    v-if="pasoActual === 9"
    class="mx-auto flex max-w-6xl justify-center px-6 py-5 sm:px-8"
>
    <button
        type="button"
        class="rounded-xl border border-blue-300 bg-white px-6 py-3 text-sm font-semibold text-blue-600 transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="form.processing"
        @click="abrirVistaPdf"
    >
        Descargar pdf
    </button>
</div>

<!-- ================================================= -->
<!-- BOTONES -->
<!-- ================================================= -->

<div
    class="flex items-center justify-between border-t border-slate-200 px-6 py-5 sm:px-8"
>

    <button
        v-if="pasoActual > 1"
        type="button"
        class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        @click="anteriorPaso"
    >
        ← Atrás
    </button>

    <div v-else></div>

    <button
        v-if="pasoActual < totalPasos"
        type="button"
        class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="!puedeAvanzar"
        @click="siguientePaso"
    >
        Continuar →
    </button>

    <button
        v-else-if="!props.modoConsulta"
        type="button"
        class="rounded-xl bg-green-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="form.processing"
        @click="abrirConfirmacionEnvio"
    >
        {{
            form.processing
                ? 'Enviando...'
                : '✓ Enviar formulario'
        }}
    </button>

</div>

</div>

</div>

</div>

</div>

<!-- ================================================= -->
<!-- MODAL DE DECLARACIONES -->
<!-- ================================================= -->

<div
    v-if="mostrarDeclaraciones"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
>
    <div
        class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
    >

        <!-- ENCABEZADO -->

        <div
            class="flex items-center justify-between border-b border-slate-200 px-6 py-5 sm:px-8"
        >
            <div>
                <h2 class="text-xl font-bold text-slate-800">
                    Declaraciones y autorizaciones
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Lea atentamente la siguiente información.
                </p>
            </div>

            <button
                type="button"
                class="rounded-xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                @click="cerrarDeclaraciones"
            >
                ✕
            </button>
        </div>


        <!-- CONTENIDO -->

        <div class="overflow-y-auto px-6 py-6 sm:px-8">

            <!-- ORIGEN DE FONDOS -->

            <div class="mb-8">
                <h3 class="text-base font-bold text-slate-800">
                    1. Declaraciones de origen de fondos
                </h3>

                <div class="mt-3 space-y-3 text-sm leading-6 text-slate-600">

                    <p>
                    Los recursos que poseo y los de la sociedad que represento
                    y que permiten realizar la presente transacción o vinculación
                    comercial con Senco Latin America S.A.S., provienen exclusivamente
                    del ejercicio de actividades lícitas y no están relacionados con
                    operaciones de lavado de activos, financiación del terrorismo o
                    proliferación de armas de destrucción masiva.
                    </p>

                    <p>
                    Tanto mi actividad, profesión u oficio es lícita y la ejerzo dentro
                    del marco legal y los recursos que poseo no provienen de actividades ilícitas,
                    por lo tanto no admitiré que terceros efectúen depósitos en mis cuentas con
                    fondos provenientes de las actividades ilícitas contempladas en el
                    Código Penal colombiano o en cualquier otra norma que lo adicione;
                    ni efectuaré transacciones destinadas a tales actividades o a favor de las
                    personas relacionadas con las mismas.
                    </p>

                    <p>
                    Además declaro que ni la sociedad que represento, ni yo mismo nos encontramos
                    en algún proceso en instancias nacionales o internacionales
                    por aspecto de lavado de activos o Financiación del Terrorismo.
                    </p>

                </div>
            </div>


            <!-- TRATAMIENTO DE DATOS -->

            <div class="mb-8">
                <h3 class="text-base font-bold text-slate-800">
                    2. Tratamiento de datos personales
                </h3>

                <div class="mt-3 space-y-3 text-sm leading-6 text-slate-600">

                    <p>
                    Con el diligenciamiento del presente documento, Autorizo de manera libre,
                    previa, expresa, voluntaria e informada a Senco Latin America S.A.S. para
                    que trate, almacene, use, circule, transmita y transfiera los datos personales
                    y corporativos suministrados o que llegaren a suministrarse, con las siguientes
                    finalidades: (i) la evaluación y aprobación de productos y/o servicios; (ii) el
                    desarrollo y ejecución de la relación contractual correspondiente a los productos
                    y/o servicios contratados; (iii) el envío de información relacionada con novedades
                    o cambios en los productos y/o servicios; (iv) el envío de información sobre eventos,
                    campañas promocionales y publicidad; (v) el envío de información legal o administrativa;
                    y (vi) la realización de estudios estadísticos, de comportamiento, necesidades, consumo
                    y preferencias.
                    </p>

                    <p>
                    Así mismo, autorizo que dicha información pueda ser compartida con empresas y/o entidades
                    en Colombia o en el exterior, con el fin de ofrecer productos y/o servicios relacionados.
                    Para las finalidades anteriormente descritas, autorizo el uso de los datos de contacto
                    suministrados, tales como correo electrónico, teléfono, celular, SMS, redes sociales y
                    demás medios físicos o electrónicos conocidos o por conocerse.
                    </p>

                    <p>
                    Declaro que he sido informado(a) de que Senco Latin America S.A.S., directamente
                    o a través de terceros, actuará como Responsable del Tratamiento de los datos personales,
                    de conformidad con su Política de Tratamiento de Datos Personales, disponible en
                    https://sencolatinamerica.co/politica-de-tratamiento-de-datos-personales/, y que ha puesto
                    a disposición los canales de atención correspondientes para el ejercicio de los derechos
                    de conocimiento, actualización, rectificación, supresión y revocatoria de la autorización
                    otorgada, a través de la línea de atención en Marinilla, Colombia 448-15-70 ext. 135 y
                    el correo electrónico protecciondedatos@senco.co.
                    Igualmente, autorizo a Senco Latin America S.A.S. para consultar y verificar información
                    en listas restrictivas y vinculantes nacionales e internacionales, incluyendo listas OFAC,
                    ONU y demás listas de prevención de lavado de activos y financiación del terrorismo.
                    </p>

                    <p>
                    Finalmente, certifico que la información suministrada en la solicitud y en el presente
                    documento es veraz, completa y verificable, y me comprometo, en representación de la sociedad,
                    a informar cualquier modificación relevante de la misma cuando sea requerido por Senco Latin
                    America S.A.S. Declaro igualmente haber leído, entendido y aceptado el contenido
                    del presente documento.
                    </p>

                </div>
            </div>


            <!-- CENTRALES DE RIESGO -->

            <div>
                <h3 class="text-base font-bold text-slate-800">
                    3. Autorización de consulta y reporte ante centrales de riesgo
                </h3>

                <div class="mt-3 space-y-3 text-sm leading-6 text-slate-600">

                    <p>
                    Con la firma del presente documento, Autorizo de manera expresa
                    e irrevocables a Senco Latin America S.A.S. y/o a quien en el
                    futuro ostente la facultad de acreedor de las obligaciones por mi
                    contraídas con Senco Latin America S.A.S., para que consulte, solicite,
                    suministre, reporte, procese o divulgue, en cualquier tiempo, y en
                    cualquier central de información de riesgo legalmente autorizada,
                    toda la información que se refiera a su comportamiento crediticio,
                    financiero, comercial y de servicios, relevante para conocer su desempeño
                    como deudor, su capacidad de pago o para valorar el riesgo futuro de
                    conceder un crédito; así como reportar a cualquier central de
                    información de riesgo legalmente autorizada, datos sobre el cumplimiento
                    oportuno, como sobre el incumplimiento, si lo hubiere, de sus
                    obligaciones crediticias, o de sus deberes legales de contenido patrimonial,
                    de tal forma que estas presenten una información veraz, pertinente, completa
                    actualizada y exacta de su desempeño como deudor
                    </p>

                </div>
            </div>

        </div>


        <!-- PIE -->

<div
    class="flex flex-col-reverse gap-3 border-t border-slate-200 px-6 py-5 sm:flex-row sm:justify-end sm:px-8"
>
    <button
    type="button"
    class="rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
    @click="cerrarDeclaraciones"
>
    Cerrar
</button>

<button
    type="button"
    class="rounded-xl border border-red-300 bg-white px-6 py-3 text-sm font-semibold text-red-600 transition hover:bg-red-50"
    @click="rechazarDeclaraciones"
>
    No acepto
</button>

<button
    type="button"
    class="rounded-xl bg-green-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-green-700"
    @click="aceptarDeclaraciones"
>
    ✓ Acepto
</button>

</div>
</div>
</div>

</AppLayout>



<!-- ================================================= -->
<!-- MODAL CONFIRMAR REGISTRO -->
<!-- ================================================= -->

<div
    v-if="mostrarConfirmacionEnvio"
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 p-4"
>
    <div
        class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl"
    >

        <!-- ENCABEZADO -->

        <div class="border-b border-slate-200 px-6 py-5 sm:px-7">

            <div class="flex items-start gap-4">

                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-6 w-6"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM10.29 3.86l-8.2 14.25A1.5 1.5 0 003.39 20.5h17.22a1.5 1.5 0 001.3-2.25l-8.2-14.25a1.5 1.5 0 00-2.6 0z"
                        />
                    </svg>
                </div>

                <div class="min-w-0">
                    <h2 class="text-lg font-bold text-slate-800">
                        Confirmar registro
                    </h2>

                    <p class="mt-1 text-sm leading-5 text-slate-500">
                        Está a punto de enviar la información diligenciada.
                    </p>
                </div>

            </div>

        </div>


        <!-- CONTENIDO -->

        <div class="px-6 py-7 sm:px-7">

            <p class="text-center text-base font-semibold text-slate-700">
                ¿Está seguro de enviar la información a SENCO?
            </p>

            <p class="mt-3 text-center text-sm leading-6 text-slate-500">
                Al confirmar, la información registrada será enviada y
                almacenada en el sistema.
            </p>

        </div>


        <!-- ACCIONES -->

        <div
            class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end sm:px-7"
        >

            <button
                type="button"
                class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="form.processing"
                @click="cerrarConfirmacionEnvio"
            >
                Cancelar
            </button>

            <button
                type="button"
                class="rounded-xl bg-green-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="form.processing"
                @click="confirmarEnvio"
            >
                {{
                    form.processing
                        ? 'Registrando...'
                        : 'Sí, enviar información'
                }}
            </button>

        </div>

    </div>
</div>

<!-- ================================================= -->
<!-- MODAL: FORMULARIO ENVIADO CORRECTAMENTE -->
<!-- ================================================= -->

<div
    v-if="mostrarModalExito"
    class="fixed inset-0 z-[10000] flex items-center justify-center bg-slate-900/60 p-4"
>
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">

        <div class="px-6 py-8 text-center sm:px-8">

            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-green-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <h2 class="mt-5 text-xl font-bold text-slate-800">
                Formulario enviado correctamente
            </h2>

            <p class="mt-2 text-sm leading-6 text-slate-500">
                La información fue registrada con éxito
            </p>

        </div>

        <div class="flex flex-col gap-3 border-t border-slate-200 px-6 py-4 sm:flex-row sm:justify-end sm:px-8">

            <button
                type="button"
                class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                @click="cerrarModalExito"
            >
                Cerrar
            </button>
        </div>

    </div>
</div>


<!-- ================================================= -->
<!-- MODAL: ERROR AL ENVIAR -->
<!-- ================================================= -->

<div
    v-if="mostrarModalError"
    class="fixed inset-0 z-[10000] flex items-center justify-center bg-slate-900/60 p-4"
>
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">

        <div class="px-6 py-8 text-center sm:px-8">

            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>

            <h2 class="mt-5 text-xl font-bold text-slate-800">
                No ha sido posible enviar el formulario
            </h2>

            <p class="mt-2 text-sm leading-6 text-slate-500">
                Se presentó un error al enviar el formulario. Revise la información e intente nuevamente.
            </p>

            <ul
                v-if="erroresEnvio.length"
                class="mt-4 max-h-40 space-y-1 overflow-y-auto rounded-lg bg-red-50 p-3 text-left text-xs text-red-700"
            >
                <li v-for="(mensaje, i) in erroresEnvio" :key="i">
                    • {{ mensaje }}
                </li>
            </ul>

        </div>

        <div class="flex justify-end border-t border-slate-200 px-6 py-4 sm:px-8">
            <button
                type="button"
                class="rounded-xl bg-slate-800 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                @click="cerrarModalError"
            >
                Cerrar
            </button>
        </div>

    </div>
</div>


<!-- ================================================= -->
<!-- MODAL: VISTA PREVIA DEL PDF -->
<!-- ================================================= -->

<div
    v-if="mostrarVistaPdf"
    class="fixed inset-0 z-[10000] flex items-center justify-center bg-slate-900/70 p-4"
>
    <div class="flex h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">

        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-bold text-slate-800">
                Vista previa del PDF
            </h2>

            <button
                type="button"
                class="rounded-lg px-3 py-1 text-slate-500 transition hover:bg-slate-100"
                @click="cerrarVistaPdf"
            >
                ✕
            </button>
        </div>

        <iframe
            :src="urlVistaPdf"
            title="Vista previa del PDF"
            class="w-full flex-1 bg-slate-100"
        ></iframe>

        <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:justify-end">

            <a
                :href="urlVistaPdf"
                target="_blank"
                rel="noopener"
                class="rounded-xl border border-slate-300 px-5 py-3 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                Abrir en otra pestaña
            </a>

            <button
                type="button"
                class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                @click="cerrarVistaPdf"
            >
                Cerrar
            </button>

            <a
                :href="urlDescargaPdf"
                class="rounded-xl bg-slate-800 px-5 py-3 text-center text-sm font-semibold text-white transition hover:bg-slate-700"
            >
                Descargar PDF
            </a>

        </div>

    </div>
</div>


</template>



<style scoped>

.label-form {
    @apply mb-2 block text-sm font-medium text-slate-700;
}

.input-form {
    @apply w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100;
}

.check-form {
    @apply flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-700 transition hover:bg-slate-50;
}

.question-box {
    @apply rounded-xl border border-slate-200 bg-slate-50 p-5;
}

.question-title {
    @apply flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between;
}

.yes-no-button {
    @apply rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-bold transition hover:shadow-sm;
}

.error-form {
    @apply mt-1 text-xs text-red-600;
}
.preview-label {
    font-size: 0.68rem;
    line-height: 1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #94a3b8;
}

.preview-value {
    margin-top: 0.25rem;
    min-height: 1.25rem;
    font-size: 0.875rem;
    line-height: 1.35rem;
    font-weight: 600;
    color: #1e293b;
    word-break: break-word;
}

.preview-section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-bottom: 1px solid rgb(203 213 225);
    background: rgb(30 41 59);
    padding: 1rem 1.25rem;
}

.preview-number {
    display: flex;
    height: 2rem;
    width: 2rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    background: white;
    font-size: 0.75rem;
    font-weight: 900;
    color: rgb(30 41 59);
}

.preview-section-title {
    font-size: 0.875rem;
    font-weight: 700;
    text-transform: uppercase;
    color: white;
}

.preview-section-subtitle {
    margin-top: 0.125rem;
    font-size: 0.75rem;
    color: rgb(203 213 225);
}

.preview-field {
    display: flex;
    min-height: 4.5rem;
    flex-direction: column;
    justify-content: center;
    gap: 0.35rem;
    border-bottom: 1px solid rgb(226 232 240);
    padding: 0.9rem 1.1rem;
}

.preview-field:nth-child(even) {
    background: rgb(248 250 252);
}

.preview-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: rgb(100 116 139);
}

.preview-value {
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.4;
    color: rgb(30 41 59);
}

.preview-badge {
    display: inline-flex;
    width: fit-content;
    border-radius: 9999px;
    background: rgb(241 245 249);
    padding: 0.35rem 0.75rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: rgb(71 85 105);
}


/*
|--------------------------------------------------------------------------
| PDF
|--------------------------------------------------------------------------
*/

@media print {

    :global(body) {
        background: white !important;
    }

    :global(nav),
    :global(header),
    button {
        display: none !important;
    }

    .lg\:col-span-1 {
        display: none !important;
    }

    .lg\:col-span-3 {
        width: 100% !important;
    }

}

</style>