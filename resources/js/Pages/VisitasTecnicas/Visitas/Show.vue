<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    visita:           { type: Object, required: true },
    equipos:          { type: Array,  default: () => [] },
    tipos_mant:       { type: Array,  default: () => [] },
    tipos_falla:      { type: Array,  default: () => [] },
    tipos_solucion:   { type: Array,  default: () => [] },
    historial:        { type: Array,  default: () => [] },
})

// Cargar descripciones para equipos y repuestos
const equiposConDescripcion = ref({})
const repuestosConDescripcion = ref({})

const obtenerDescripcionRepuesto = async (codigo, bodegaParam) => {
    if (!codigo) {
        return {
            descripcion: null,
            codigo_proveedor: null,
            inventario: null,
        }
    }

    if (repuestosConDescripcion.value[codigo]) {
        return repuestosConDescripcion.value[codigo]
    }

    const response = await fetch(
        `/visitas-tecnicas/senco360/repuestos/descripcion?codigo=${encodeURIComponent(codigo)}&bodega=${encodeURIComponent(bodegaParam)}`,
        { credentials: 'include' }
    )
    const data = await response.json()

    const detalle = {
        descripcion: data.descripcion,
        codigo_proveedor: data.codigo_proveedor,
        inventario: data.inventario,
    }

    repuestosConDescripcion.value[codigo] = detalle

    return detalle
}

const cargarDescripciones = async () => {
    try {
        // Cargar descripciones de equipos
        const codigosEquipos = props.equipos.map(e => e.id_cod_max).filter(Boolean)
        for (const codigo of codigosEquipos) {
            // Skip if already loaded
            if (equiposConDescripcion.value[codigo]) continue
            
            const response = await fetch(
                `/visitas-tecnicas/senco360/herramientas/descripcion?codigo=${encodeURIComponent(codigo)}`,
                { credentials: 'include' }
            )
            const data = await response.json()
            equiposConDescripcion.value[codigo] = {
                descripcion: data.descripcion,
                codigo_proveedor: data.codigo_proveedor,
            }
        }

        // Cargar descripciones de repuestos
        const codigosRepuestos = props.equipos.flatMap(e => 
            (e.repuestos || []).map(r => r.id_cod_max)
        ).filter(Boolean)
        const codigosUnicos = [...new Set(codigosRepuestos)]
        
        for (const codigo of codigosUnicos) {
            // Skip if already loaded
            if (repuestosConDescripcion.value[codigo]) continue
            
            await obtenerDescripcionRepuesto(codigo, bodega.value)
        }
    } catch (error) {
    }
}

onMounted(() => {
    cargarDescripciones()
})

// Recargar descripciones cuando cambian los equipos
watch(() => props.equipos, () => {
    cargarDescripciones()
}, { deep: true })

// Constantes - estados
const ESTADO_PENDIENTE_REPUESTOS = 6
const ESTADO_REPUESTO_RECHAZADO = 15
const ESTADO_REPUESTO_INSTALADO = 19
const ID_SOLUCION_CAMBIO_REPUESTOS = 4

const bodega = computed(() => {
    const direccion = (props.visita?.direccion || '').toUpperCase()
    const palabrasBodega67 = ['URABA', 'CHIGORODO', 'CAREPA', 'APARTADO', 'TURBO', 'RIOGRANDE', 'NECOCLI', 'ARBOLETES', 'MUTATA']

    const usaBodega67 = palabrasBodega67.some(palabra => direccion.includes(palabra.toUpperCase()))

    return usaBodega67 ? '67' : '03'
})

const completado = computed(() => {
    return props.visita?.estado === 'Completado'
})

const esPendienteRepuestos = computed(() => {
    return Number(props.visita?.estado_id) === ESTADO_PENDIENTE_REPUESTOS
})

const puedeEditar = computed(() => !!props.visita?.puede_editar_detalle)
const puedeGuardarBorrador = computed(() => !!props.visita?.puede_guardar_borrador)
const puedeGestionarEvidenciaFinal = computed(() => !!props.visita?.puede_gestionar_evidencia_final)

// Verificar si hay repuestos pendientes (no instalados ni rechazados)
const tieneRepuestosPendientes = computed(() => {
    for (const equipo of props.equipos) {
        for (const repuesto of (equipo.repuestos || [])) {
            // Si el repuesto no está instalado (19) ni rechazado (15), está pendiente
            const estadoId = Number(repuesto.estado_id)
            if (estadoId !== 19 && estadoId !== 15) {
                return true
            }
        }
    }
    return false
})

const tieneEquipos = computed(() => {
    return (props.equipos || []).length > 0
})

const puedeFinalizar = computed(() => {
    return props.visita?.puede_finalizar && !tieneRepuestosPendientes.value && tieneEquipos.value
})

const puedeSolicitarCotizacion = computed(() => {
    return !!props.visita?.puede_solicitar_cotizacion && tieneRepuestosPendientes.value
})

const buttonBaseClass = 'inline-flex items-center justify-center gap-1.5 rounded-lg font-semibold transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50'

const buttonSizeClass = {
    xs: 'px-2.5 py-1.5 text-[11px]',
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2.5 text-sm',
    lg: 'px-4 py-3 text-sm',
}

const buttonVariantClass = {
    primary: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-300',
    neutral: 'bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-300',
    success: 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-300',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-300',
    warning: 'bg-orange-600 text-white hover:bg-orange-700 focus:ring-orange-300',
    emerald: 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-300',
}

const buttonClass = (variant = 'primary', size = 'md', extra = '') => {
    return [buttonBaseClass, buttonSizeClass[size] ?? buttonSizeClass.md, buttonVariantClass[variant] ?? buttonVariantClass.primary, extra]
}

// ── Estado local ───────────────────────────────────────────
const equipoExpandido    = ref(null)
const seccionesExpandidas = ref({})
const modalEquipo        = ref(false)
const modalRepuesto      = ref(false)
const modalFinalizar     = ref(false)
const modalCotizacion    = ref(false)
const modalCotizacionEnviada = ref(false)
const modalReenviarInforme = ref(false)
const modalInformeReenviado = ref(false)
const modalFinalizacionExitosa = ref(false)
const modalInstalacion   = ref(false)
const modalSolucionesComplementarias = ref(false)
const equipoEditandoId   = ref(null)
const equipoSeleccionado = ref(null)
const repuestoInstalacion = ref(null)
const repuestoEditandoId = ref(null)
const equipoObservacionesTemporal = ref('')
const repuestosTemporal  = ref([])
const repuestoTemporalEditandoIndex = ref(null)
const repuestoEditorRef = ref(null)
const fotosDespuesInstalacion = ref([])
const equipoTieneRepuestosPrevios = ref(false)
const modalGuardadoBorrador = ref(false)
const fallasDropdownAbierto = ref(false)
const fallasBusqueda = ref('')
const descripcionOtros = ref('')
const solucionesDropdownAbierto = ref(false)
const solucionesBusqueda = ref('')
const historialColapsado = ref(true)
const fotoAmpliada = ref(null)

const beforeExpandEnter = (el) => {
    el.style.height = '0'
    el.style.opacity = '0'
    el.style.overflow = 'hidden'
}

const expandEnter = (el) => {
    el.style.willChange = 'height, opacity'

    requestAnimationFrame(() => {
        el.style.height = `${el.scrollHeight}px`
        el.style.opacity = '1'
    })
}

const afterExpandEnter = (el) => {
    el.style.height = 'auto'
    el.style.overflow = ''
    el.style.willChange = ''
}

const beforeExpandLeave = (el) => {
    el.style.height = `${el.scrollHeight}px`
    el.style.opacity = '1'
    el.style.overflow = 'hidden'
    el.style.willChange = 'height, opacity'
}

const expandLeave = (el) => {
    requestAnimationFrame(() => {
        el.style.height = '0'
        el.style.opacity = '0'
    })
}

const afterExpandLeave = (el) => {
    el.style.height = ''
    el.style.opacity = ''
    el.style.overflow = ''
    el.style.willChange = ''
}

const abrirFotoAmpliada = (url) => {
    if (!url) return
    fotoAmpliada.value = url
}

const cerrarFotoAmpliada = () => {
    if (!fotoAmpliada.value) return
    fotoAmpliada.value = null
}

const cerrarFotoAmpliadaConTecla = (event) => {
    if (event.key === 'Escape' && fotoAmpliada.value) {
        cerrarFotoAmpliada()
    }
}

onMounted(() => {
    window.addEventListener('keydown', cerrarFotoAmpliadaConTecla)
})

onUnmounted(() => {
    window.removeEventListener('keydown', cerrarFotoAmpliadaConTecla)
})

// Helper para toggle secciones internas
const toggleSeccion = (equipoId, seccion) => {
    const clave = `${equipoId}-${seccion}`
    seccionesExpandidas.value[clave] = !seccionesExpandidas.value[clave]
}

const esSeccionExpandida = (equipoId, seccion) => {
    return seccionesExpandidas.value[`${equipoId}-${seccion}`] ?? false
}

// ── Búsqueda de herramientas y repuestos ───────────────────
const herramientasBusqueda = ref('')
const herramientasResultados = ref([])
const herramientasCargando = ref(false)
const herramientaSeleccionada = ref(null)

const serialesSugeridos = ref([])
const serialesCargando = ref(false)
const serialesSugeridosAbierto = ref(false)

const repuestosBusqueda = ref('')
const repuestosResultados = ref([])
const repuestosCargando = ref(false)
const repuestoSeleccionado = ref(null)
const herramientasBuscada = ref(false)
const repuestosBuscada = ref(false)

let herramientaTimeout = null
let repuestoTimeout = null

const limpiarBusquedaHerramientas = () => {
    herramientasBusqueda.value = ''
    herramientasResultados.value = []
    herramientasCargando.value = false
    herramientasBuscada.value = false
}

const limpiarBusquedaRepuestos = () => {
    repuestosBusqueda.value = ''
    repuestosResultados.value = []
    repuestosCargando.value = false
    repuestosBuscada.value = false
}

const cargarSerialesSugeridos = async () => {
    if (!props.visita?.id) return

    serialesSugeridos.value = []
    serialesCargando.value = true

    try {
        const { data } = await axios.get(route('visitastecnicas.equipos.seriales-sugeridos', props.visita.id), {
            params: {
                direccion: props.visita?.direccion || '',
            },
        })

        serialesSugeridos.value = Array.isArray(data) ? data.filter(Boolean) : []
    } catch (error) {
        serialesSugeridos.value = []
    } finally {
        serialesCargando.value = false
    }
}

const seleccionarSerialSugerido = (serial) => {
    formEquipo.serial = serial
    serialesSugeridosAbierto.value = false
}

const manejarFocusSerial = () => {
    serialesSugeridosAbierto.value = true
    if (!serialesSugeridos.value.length && !serialesCargando.value) {
        cargarSerialesSugeridos()
    }
}

const manejarBlurSerial = () => {
    window.setTimeout(() => {
        serialesSugeridosAbierto.value = false
    }, 150)
}

// Canvas firma
const canvasFirma = ref(null)
const firmaCanvasAlto = 180
let dibujando = false

// ── Forms ──────────────────────────────────────────────────
const formEquipo = useForm({
    visita_id: props.visita?.id ?? null,
    id_cod_max:        '',
    serial:            '',
    id_tipo_mant:      '',
    id_tipo_falla:     [],
    descripcion_falla: '',
    id_solucion:       [],
    observaciones:     '',
    repuestos:         [],
})

const formRepuesto = useForm({
    equipo_id:   '',
    id_cod_max:  '',
    cantidad:    1,
    observacion: '',
    resolver_en_campo: false,
    es_urgente: false,
})

// Referencias para fotos del equipo
const fotosAntesEquipo = ref([])
const comprimiendoFotosAntes = ref(false)
const subiendoFotosEvidencia = ref(false)

const formServicio = useForm({
    correo_cliente: props.visita?.correo_cliente ?? '',
    fecha_inicio: props.visita?.fecha_inicio ?? '',
    hora_inicio: props.visita?.hora_inicio ? String(props.visita.hora_inicio).slice(0, 5) : '',
})

// --- Autoguardado temporal para correo_cliente y hora_inicio ---
const SERVICE_STORAGE_KEY = `visita_servicio_${props.visita?.id ?? 'global'}`
let serviceSaveTimeout = null

const saveServiceFieldsNow = () => {
    try {
        const payload = {
            correo_cliente: formServicio.correo_cliente || '',
            hora_inicio: formServicio.hora_inicio || '',
            _ts: Date.now(),
        }
        localStorage.setItem(SERVICE_STORAGE_KEY, JSON.stringify(payload))
    } catch (e) {
        // ignore
    }
}

const saveServiceFieldsDebounced = () => {
    clearTimeout(serviceSaveTimeout)
    serviceSaveTimeout = setTimeout(saveServiceFieldsNow, 700)
}

const loadServiceFields = () => {
    try {
        const raw = localStorage.getItem(SERVICE_STORAGE_KEY)
        if (!raw) return
        const data = JSON.parse(raw)
        if (data && typeof data === 'object') {
            // Solo restaurar si los campos están vacíos para no sobreescribir datos del servidor
            if (!formServicio.correo_cliente && data.correo_cliente) {
                formServicio.correo_cliente = data.correo_cliente
            }
            if (!formServicio.hora_inicio && data.hora_inicio) {
                formServicio.hora_inicio = data.hora_inicio
            }
        }
    } catch (e) {
        // ignore
    }
}

const clearServiceFields = () => {
    try { localStorage.removeItem(SERVICE_STORAGE_KEY) } catch (e) {}
}

// Guardar on change (debounced)
watch(() => [formServicio.correo_cliente, formServicio.hora_inicio], () => {
    saveServiceFieldsDebounced()
}, { deep: true })

// Guardar al cerrar la pestaña
const onBeforeUnload = () => saveServiceFieldsNow()
onMounted(() => {
    loadServiceFields()
    window.addEventListener('beforeunload', onBeforeUnload)
})
onUnmounted(() => {
    window.removeEventListener('beforeunload', onBeforeUnload)
})

const formFinalizar = useForm({
    fecha_fin: props.visita?.fecha_fin ?? '',
    hora_fin: props.visita?.hora_fin ? String(props.visita.hora_fin).slice(0, 5) : '',
    firma: '',
    observaciones: '',
})

const formReenviarInforme = useForm({
    correo: '',
})

const formInstalacion = useForm({
    fotos_despues: [],
    soluciones_adicionales: [],
    observacion_instalacion: '',
})

const mostrarAlertaFinalizacion = ref(false)
const mostrarAlertaCotizacion = ref(false)
const enviandoCotizacion = ref(false)

const descargarInforme = () => {
    window.location.href = route('visitastecnicas.visitas.informe-tecnico.descargar', props.visita.id)
}

const abrirModalReenviarInforme = () => {
    formReenviarInforme.reset()
    formReenviarInforme.clearErrors()
    modalReenviarInforme.value = true
}

const reenviarInforme = async () => {
    formReenviarInforme.clearErrors()
    formReenviarInforme.processing = true

    try {
        await axios.post(route('visitastecnicas.visitas.informe-tecnico.reenviar', props.visita.id), {
            correo: formReenviarInforme.correo,
        }, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        })

        modalReenviarInforme.value = false
        modalInformeReenviado.value = true
        formReenviarInforme.reset()
    } catch (error) {
        if (error.response?.status === 422 && error.response.data?.errors) {
            Object.entries(error.response.data.errors).forEach(([key, messages]) => {
                formReenviarInforme.setError(key, Array.isArray(messages) ? messages[0] : messages)
            })
            return
        }

        alert(error.response?.data?.message || 'No fue posible reenviar el informe técnico.')
    } finally {
        formReenviarInforme.processing = false
    }
}

// ── Helpers ────────────────────────────────────────────────
const estadoColor = computed(() => {
    const colores = {
        'En proceso':          'bg-blue-100 text-blue-700',
        'Completado':          'bg-green-100 text-green-700',
        'Reprogramado':        'bg-yellow-100 text-yellow-700',
        'Cancelado':           'bg-red-100 text-red-700',
        'Pendiente Repuestos': 'bg-orange-100 text-orange-700',
    }
    return colores[props.visita?.estado] ?? 'bg-stone-100 text-stone-600'
})

const estadoRepuestoColor = (estado) => {
    if (!estado) return 'bg-stone-100 text-stone-600'
    const colores = {
        'Solicitud Cotización': 'bg-stone-100 text-stone-600',
        'Repuesto Solicitado':  'bg-blue-100 text-blue-700',
        'Rechazo Cotización':   'bg-red-100 text-red-700',
        'Repuesto Facturado':   'bg-purple-100 text-purple-700',
        'Repuesto En Espera':   'bg-yellow-100 text-yellow-700',
        'Repuesto Despachado':  'bg-orange-100 text-orange-700',
        'Repuesto Instalado':   'bg-green-100 text-green-700',
    }
    return colores[estado] ?? 'bg-stone-100 text-stone-600'
}

const historialEstadoColor = (estado) => {
    const colores = {
        'En proceso':          'bg-blue-500',
        'Completado':          'bg-green-500',
        'Reprogramado':        'bg-yellow-500',
        'Cancelado':           'bg-red-500',
        'Pendiente Repuestos': 'bg-orange-500',
    }
    return colores[estado] ?? 'bg-stone-400'
}

function requiereRepuestos(idsSolucion) {
    const ids = Array.isArray(idsSolucion)
        ? idsSolucion
        : (idsSolucion ? [idsSolucion] : [])

    return ids.map(id => Number(id)).includes(ID_SOLUCION_CAMBIO_REPUESTOS)
}

const esSolucionCambioRepuestos = (solucionId) => {
    return Number(solucionId) === ID_SOLUCION_CAMBIO_REPUESTOS
}

const cumpleRepuestoObligatorio = computed(() => {
    if (!requiereRepuestos(formEquipo.id_solucion)) {
        return true
    }

    if (!puedeGestionarRepuestosEnModalEquipo.value) {
        return true
    }

    return repuestosTemporal.value.length > 0 || equipoTieneRepuestosPrevios.value
})

const tieneFallaSeleccionada = computed(() => {
    return formEquipo.id_tipo_falla.length > 0
})
const totalFallasSeleccionadas = computed(() => {
    return formEquipo.id_tipo_falla.length
})

const textoResumenFallas = computed(() => {
    if (totalFallasSeleccionadas.value === 0) return 'Seleccionar fallas...'

    return String(totalFallasSeleccionadas.value) + ' seleccionada' + (totalFallasSeleccionadas.value === 1 ? '' : 's')
})

const puedeGuardarEquipo = computed(() => {
    return Boolean(
        formEquipo.id_cod_max &&
        formEquipo.id_tipo_mant &&
        String(formEquipo.serial || '').trim() &&
        tieneFallaSeleccionada.value &&
        formEquipo.id_solucion.length > 0 &&
        cumpleRepuestoObligatorio.value
    )
})

const mensajeGuardarEquipo = computed(() => {
    if (formEquipo.processing) return 'Guardando cambios...'
    if (!formEquipo.id_cod_max) return 'Selecciona un equipo para continuar.'
    if (!String(formEquipo.serial || '').trim()) return 'Falta el serial del equipo.'
    if (!formEquipo.id_tipo_mant) return 'Selecciona el tipo de mantenimiento.'
    if (!tieneFallaSeleccionada.value) return 'Selecciona al menos una falla.'
    if (formEquipo.id_solucion.length === 0) return 'Selecciona al menos un tipo de solucion.'
    if (!cumpleRepuestoObligatorio.value) return 'Agrega al menos un repuesto para habilitar el guardado.'
    return ''
})

const pasoEquipoActual = ref(1)

const pasoEquipo1Completo = computed(() => {
    return Boolean(
        formEquipo.id_cod_max &&
        String(formEquipo.serial || '').trim() &&
        formEquipo.id_tipo_mant
    )
})

const pasoEquipo2Completo = computed(() => {
    if (!tieneFallaSeleccionada.value || formEquipo.id_solucion.length === 0) {
        return false
    }

    if (fallaOtrosSeleccionada.value && !descripcionOtros.value.trim()) {
        return false
    }

    return true
})

const puedeGestionarRepuestosEnModalEquipo = computed(() => !equipoEditandoId.value)
const requierePasoRepuestos = computed(() => {
    return puedeGestionarRepuestosEnModalEquipo.value && requiereRepuestos(formEquipo.id_solucion)
})

const pasosModalEquipo = computed(() => {
    const pasos = [
        { id: 1, titulo: 'Equipo', completo: pasoEquipo1Completo.value },
        { id: 2, titulo: 'Diagnóstico', completo: pasoEquipo2Completo.value },
    ]

    if (requierePasoRepuestos.value) {
        pasos.push({ id: 3, titulo: 'Repuestos', completo: cumpleRepuestoObligatorio.value })
    }

    return pasos
})

const pasosStepperEquipo = computed(() => {
    const pasos = [
        { id: 1, titulo: 'Equipo', completo: pasoEquipo1Completo.value, condicional: false, habilitado: true },
        { id: 2, titulo: 'Diagnóstico', completo: pasoEquipo2Completo.value, condicional: false, habilitado: true },
    ]

    if (requierePasoRepuestos.value) {
        pasos.push({
            id: 3,
            titulo: 'Repuestos',
            completo: cumpleRepuestoObligatorio.value,
            condicional: false,
            habilitado: true,
        })
    }

    return pasos
})

const totalPasosEquipo = computed(() => pasosModalEquipo.value.length)
const esUltimoPasoEquipo = computed(() => pasoEquipoActual.value === totalPasosEquipo.value)
const puedeAvanzarPasoEquipo = computed(() => {
    if (pasoEquipoActual.value === 1) return pasoEquipo1Completo.value
    if (pasoEquipoActual.value === 2) return pasoEquipo2Completo.value
    return cumpleRepuestoObligatorio.value
})

watch(modalEquipo, (abierto) => {
    if (abierto) {
        pasoEquipoActual.value = 1
    }
})

watch(requierePasoRepuestos, (requiere) => {
    if (!requiere && pasoEquipoActual.value > 2) {
        pasoEquipoActual.value = 2
    }
})

const irPasoEquipoSiguiente = () => {
    if (!puedeAvanzarPasoEquipo.value || esUltimoPasoEquipo.value) return
    pasoEquipoActual.value += 1
}

const irPasoEquipoAnterior = () => {
    if (pasoEquipoActual.value <= 1) return
    pasoEquipoActual.value -= 1
}

const solucionSeleccionada = (solucionId) => {
    return formEquipo.id_solucion.includes(solucionId)
}

const solucionBloqueada = (solucionId) => {
    const hayCambioRepuestosSeleccionado = formEquipo.id_solucion.some(id => esSolucionCambioRepuestos(id))
    const hayOtraSolucionSeleccionada = formEquipo.id_solucion.some(id => !esSolucionCambioRepuestos(id))

    if (solucionSeleccionada(solucionId)) {
        return false
    }

    if (esSolucionCambioRepuestos(solucionId)) {
        return hayOtraSolucionSeleccionada
    }

    return hayCambioRepuestosSeleccionado
}

const toggleSolucion = (solucionId) => {
    if (solucionBloqueada(solucionId)) {
        return
    }

    if (solucionSeleccionada(solucionId)) {
        formEquipo.id_solucion = formEquipo.id_solucion.filter(id => id !== solucionId)
        return
    }

    if (esSolucionCambioRepuestos(solucionId)) {
        formEquipo.id_solucion = [solucionId]
        return
    }

    formEquipo.id_solucion = [...formEquipo.id_solucion, solucionId]
}

const tiposSolucionOrdenados = computed(() => {
    return [...props.tipos_solucion].sort((a, b) => {
        const aSeleccionada = solucionSeleccionada(a.ID) ? 1 : 0
        const bSeleccionada = solucionSeleccionada(b.ID) ? 1 : 0

        if (aSeleccionada !== bSeleccionada) {
            return bSeleccionada - aSeleccionada
        }

        return String(a.TIPO_SOLUCION).localeCompare(String(b.TIPO_SOLUCION))
    })
})

const tiposSolucionFiltrados = computed(() => {
    const busqueda = solucionesBusqueda.value.trim().toLocaleLowerCase()

    if (!busqueda) {
        return tiposSolucionOrdenados.value
    }

    return tiposSolucionOrdenados.value.filter((solucion) =>
        String(solucion.TIPO_SOLUCION || '').toLocaleLowerCase().includes(busqueda)
    )
})

const solucionesSeleccionadasDetalle = computed(() => {
    return formEquipo.id_solucion
        .map((solucionId) => tiposSolucionOrdenados.value.find((solucion) => Number(solucion.ID) === Number(solucionId)))
        .filter(Boolean)
})

const textoResumenSoluciones = computed(() => {
    if (formEquipo.id_solucion.length === 0) return 'Seleccionar soluciones...'

    return String(formEquipo.id_solucion.length) + ' seleccionada' + (formEquipo.id_solucion.length === 1 ? '' : 's')
})

const quitarSolucion = (solucionId) => {
    formEquipo.id_solucion = formEquipo.id_solucion.filter(id => Number(id) !== Number(solucionId))
}

const tiposMantOrdenados = computed(() => {
    return [...props.tipos_mant].sort((a, b) => {
        return String(a.TIPO_MANT).localeCompare(String(b.TIPO_MANT))
    })
})

const tiposFallaOrdenadas = computed(() => {
    return [...props.tipos_falla].sort((a, b) => {
        return String(a.DESCRIPCION).localeCompare(String(b.DESCRIPCION))
    })
})

const tiposFallaFiltradas = computed(() => {
    const busqueda = fallasBusqueda.value.trim().toLocaleLowerCase()

    if (!busqueda) {
        return tiposFallaOrdenadas.value
    }

    return tiposFallaOrdenadas.value.filter((falla) =>
        String(falla.DESCRIPCION || '').toLocaleLowerCase().includes(busqueda)
    )
})

const fallasSeleccionadasDetalle = computed(() => {
    return formEquipo.id_tipo_falla
        .map((fallaId) => tiposFallaOrdenadas.value.find((falla) => Number(falla.ID) === Number(fallaId)))
        .filter(Boolean)
})

const fallaSeleccionada = (fallaId) => {
    return formEquipo.id_tipo_falla.map(id => Number(id)).includes(Number(fallaId))
}

const ID_FALLA_OTROS = 34
const fallaOtrosSeleccionada = computed(() => {
    return formEquipo.id_tipo_falla.map(id => Number(id)).includes(ID_FALLA_OTROS)
})

const toggleFalla = (fallaId) => {
    if (fallaSeleccionada(fallaId)) {
        formEquipo.id_tipo_falla = formEquipo.id_tipo_falla.filter(id => Number(id) !== Number(fallaId))

    // Si se quita la falla "Otros" (ID 34), limpiar descripcion
    if (Number(fallaId) === ID_FALLA_OTROS) {
        descripcionOtros.value = ''
    }
        return
    }

    formEquipo.id_tipo_falla = [...formEquipo.id_tipo_falla, fallaId]
}

const quitarFalla = (fallaId) => {
    formEquipo.id_tipo_falla = formEquipo.id_tipo_falla.filter(id => Number(id) !== Number(fallaId))

    if (Number(fallaId) === ID_FALLA_OTROS) {
        descripcionOtros.value = ''
    }
}

const repuestoEstaInstalado = (repuesto) => Number(repuesto?.estado_id) === ESTADO_REPUESTO_INSTALADO
const repuestoEstaRechazado = (repuesto) => Number(repuesto?.estado_id) === ESTADO_REPUESTO_RECHAZADO

const puedeAgregarSolucionComplementariaEquipo = (equipo) => {
    if (!equipo || !requiereRepuestos(equipo.soluciones_ids)) {
        return false
    }

    const repuestosEquipo = Array.isArray(equipo.repuestos) ? equipo.repuestos : []

    if (repuestosEquipo.length === 0) {
        return false
    }

    return repuestosEquipo.every((repuesto) =>
        repuestoEstaInstalado(repuesto) || repuestoEstaRechazado(repuesto)
    )
}

const equipoQuedariaListoParaSolucionesComplementarias = (equipo, repuestoObjetivo) => {
    if (!equipo || !repuestoObjetivo) {
        return false
    }

    const repuestosActualizados = (Array.isArray(equipo.repuestos) ? equipo.repuestos : []).map((repuesto) => {
        if (Number(repuesto.id) !== Number(repuestoObjetivo.id)) {
            return repuesto
        }

        return {
            ...repuesto,
            estado_id: ESTADO_REPUESTO_INSTALADO,
        }
    })

    return puedeAgregarSolucionComplementariaEquipo({
        ...equipo,
        repuestos: repuestosActualizados,
    })
}

const solucionComplementariaSeleccionada = (solucionId) => {
    return formInstalacion.soluciones_adicionales.includes(solucionId)
}

const toggleSolucionComplementaria = (solucionId) => {
    if (solucionComplementariaSeleccionada(solucionId)) {
        formInstalacion.soluciones_adicionales = formInstalacion.soluciones_adicionales.filter(id => id !== solucionId)
        return
    }

    formInstalacion.soluciones_adicionales = [...formInstalacion.soluciones_adicionales, solucionId]
}

const tiposSolucionComplementarios = computed(() => {
    const solucionesActuales = Array.isArray(equipoSeleccionado.value?.soluciones_ids)
        ? equipoSeleccionado.value.soluciones_ids.map(id => Number(id))
        : []

    return props.tipos_solucion
        .filter(solucion => !esSolucionCambioRepuestos(solucion.ID) && !solucionesActuales.includes(Number(solucion.ID)))
        .sort((a, b) => {
            const aSeleccionada = solucionComplementariaSeleccionada(a.ID) ? 1 : 0
            const bSeleccionada = solucionComplementariaSeleccionada(b.ID) ? 1 : 0

            if (aSeleccionada !== bSeleccionada) {
                return bSeleccionada - aSeleccionada
            }

            return String(a.TIPO_SOLUCION).localeCompare(String(b.TIPO_SOLUCION))
        })
})

// ── Firma ──────────────────────────────────────────────────
const configurarContextoFirma = (ctx) => {
    ctx.lineWidth = 2.4
    ctx.lineCap = "round"
    ctx.lineJoin = "round"
    ctx.strokeStyle = "#1e293b"
}

const prepararCanvasFirma = () => {
    const canvas = canvasFirma.value
    if (!canvas) return

    const rect = canvas.getBoundingClientRect()
    const ratio = window.devicePixelRatio || 1
    const ancho = Math.max(Math.round(rect.width * ratio), 1)
    const alto = Math.round(firmaCanvasAlto * ratio)

    canvas.style.height = firmaCanvasAlto + "px"

    if (canvas.width !== ancho || canvas.height !== alto) {
        canvas.width = ancho
        canvas.height = alto
    }

    const ctx = canvas.getContext("2d")
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0)
    configurarContextoFirma(ctx)
}

const puntoFirma = (e) => {
    const rect = canvasFirma.value.getBoundingClientRect()
    return {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top,
    }
}

const iniciarFirma = (e) => {
    if (e.button !== undefined && e.button !== 0) return

    e.preventDefault()
    prepararCanvasFirma()
    dibujando = true
    e.currentTarget?.setPointerCapture?.(e.pointerId)

    const ctx = canvasFirma.value.getContext("2d")
    const punto = puntoFirma(e)
    ctx.beginPath()
    ctx.moveTo(punto.x, punto.y)
}

const dibujarFirma = (e) => {
    if (!dibujando) return

    e.preventDefault()
    const ctx = canvasFirma.value.getContext("2d")
    const punto = puntoFirma(e)
    ctx.lineTo(punto.x, punto.y)
    ctx.stroke()
}

const terminarFirma = (e) => {
    if (!dibujando) return

    dibujando = false
    e?.currentTarget?.releasePointerCapture?.(e.pointerId)
}

const limpiarFirma = () => {
    const canvas = canvasFirma.value
    if (!canvas) return

    const ctx = canvas.getContext("2d")
    ctx.save()
    ctx.setTransform(1, 0, 0, 1, 0, 0)
    ctx.clearRect(0, 0, canvas.width, canvas.height)
    ctx.restore()
    configurarContextoFirma(ctx)
}

const firmaTieneContenido = () => {
    const canvas = canvasFirma.value
    if (!canvas) return false

    const canvasVacio = document.createElement("canvas")
    canvasVacio.width = canvas.width
    canvasVacio.height = canvas.height

    return canvas.toDataURL() !== canvasVacio.toDataURL()
}

onMounted(() => window.addEventListener('resize', prepararCanvasFirma))
onUnmounted(() => window.removeEventListener('resize', prepararCanvasFirma))

const camposFaltantesFinalizacion = computed(() => {
    if (props.visita?.es_capacitacion) {
        const faltantes = []

        if (!formServicio.fecha_inicio) {
            faltantes.push('Fecha inicio')
        }

        if (!formFinalizar.fecha_fin) {
            faltantes.push('Fecha fin')
        }

        return faltantes
    }

    const faltantes = []

    if (!formServicio.fecha_inicio) {
        faltantes.push('Fecha inicio')
    }

    if (!formServicio.hora_inicio) {
        faltantes.push('Hora inicio')
    }

    if (!formFinalizar.fecha_fin) {
        faltantes.push('Fecha fin')
    }

    if (!formFinalizar.hora_fin) {
        faltantes.push('Hora fin')
    }

    if (!tieneEquipos.value) {
        faltantes.push('Agregar al menos un equipo')
    }

    return faltantes
})

// ── Equipos ────────────────────────────────────────────────
const abrirModalEquipo = () => {
    equipoEditandoId.value = null
    formEquipo.id_cod_max = ''
    formEquipo.serial = ''
    formEquipo.id_tipo_mant = ''
    formEquipo.id_tipo_falla = []
    formEquipo.descripcion_falla = ''
    descripcionOtros.value = ''
    fallasBusqueda.value = ''
    fallasDropdownAbierto.value = false
    solucionesBusqueda.value = ''
    solucionesDropdownAbierto.value = false
    formEquipo.id_solucion = []
    formEquipo.observaciones = ''
    formEquipo.repuestos = []
    formEquipo.visita_id = props.visita.id
    herramientaSeleccionada.value = null
    limpiarBusquedaHerramientas()
    limpiarFormularioRepuestoTemporal()
    repuestosTemporal.value = []
    equipoTieneRepuestosPrevios.value = false
    formEquipo.clearErrors()
    serialesSugeridos.value = []
    serialesSugeridosAbierto.value = false
    void cargarSerialesSugeridos()
    modalEquipo.value = true
}

const abrirModalEditarEquipo = (equipo) => {
    equipoEditandoId.value = equipo.id
    formEquipo.visita_id = props.visita.id
    formEquipo.id_cod_max = equipo.id_cod_max ?? ''
    formEquipo.serial = equipo.serial ?? ''
    formEquipo.id_tipo_mant = equipo.id_tipo_mant ?? ''
    formEquipo.id_tipo_falla = Array.isArray(equipo.fallas_ids)
        ? equipo.fallas_ids.map(id => Number(id))
        : []
    formEquipo.descripcion_falla = equipo.descripcion_falla ?? ''
    descripcionOtros.value = ''
    fallasBusqueda.value = ''
    fallasDropdownAbierto.value = false
    solucionesBusqueda.value = ''
    solucionesDropdownAbierto.value = false
    formEquipo.id_solucion = Array.isArray(equipo.soluciones_ids)
        ? equipo.soluciones_ids.map(id => Number(id))
        : []
    formEquipo.observaciones = equipo.observaciones ?? ''
    formEquipo.repuestos = []

    const descripcion = equiposConDescripcion.value[equipo.id_cod_max]?.descripcion
    const codigoProveedor = equiposConDescripcion.value[equipo.id_cod_max]?.codigo_proveedor

    herramientaSeleccionada.value = {
        codigo: equipo.id_cod_max,
        descripcion: descripcion ?? equipo.id_cod_max,
        proveedor: codigoProveedor ?? null,
    }
    herramientasBusqueda.value = descripcion ?? equipo.id_cod_max ?? ''
    herramientasResultados.value = []
    herramientasBuscada.value = false
    repuestosTemporal.value = []
    equipoTieneRepuestosPrevios.value = Array.isArray(equipo.repuestos) && equipo.repuestos.length > 0
    fotosAntesEquipo.value = []
    formEquipo.clearErrors()
    serialesSugeridos.value = []
    serialesSugeridosAbierto.value = false
    void cargarSerialesSugeridos()
    modalEquipo.value = true
}

const buscarHerramientas = () => {
    clearTimeout(herramientaTimeout)

    const busqueda = String(herramientasBusqueda.value || '').trim()

    if (herramientaSeleccionada.value && busqueda !== String(herramientaSeleccionada.value.descripcion || '').trim()) {
        herramientaSeleccionada.value = null
        formEquipo.id_cod_max = ''
    }

    if (busqueda.length < 2) {
        herramientasResultados.value = []
        herramientasCargando.value = false
        herramientasBuscada.value = false
        return
    }

    herramientasBuscada.value = true
    herramientaTimeout = setTimeout(async () => {
        herramientasCargando.value = true
        try {
            const response = await fetch(
                `/visitas-tecnicas/senco360/herramientas/buscar?q=${encodeURIComponent(busqueda)}`,
                { credentials: 'include' }
            )
            const resultados = await response.json()

            if (busqueda === String(herramientasBusqueda.value || '').trim()) {
                herramientasResultados.value = resultados
            }
        } catch (error) {
            herramientasResultados.value = []
        } finally {
            if (busqueda === String(herramientasBusqueda.value || '').trim()) {
                herramientasCargando.value = false
            }
        }
    }, 300)
}

const seleccionarHerramienta = (item) => {
    herramientaSeleccionada.value = item
    formEquipo.id_cod_max = item.codigo
    herramientasBusqueda.value = item.descripcion
    herramientasResultados.value = []
    herramientasBuscada.value = false
}

const guardarEquipo = async () => {
    if (requierePasoRepuestos.value && !cumpleRepuestoObligatorio.value) {
        formEquipo.setError('repuestos', 'Debes agregar al menos un repuesto para esta solucion.')
        return
    }

    formEquipo.repuestos = repuestosTemporal.value.map(repuesto => ({
        id_cod_max: repuesto.id_cod_max,
        cantidad: repuesto.cantidad,
        observacion: repuesto.observacion,
        resolver_en_campo: !!repuesto.resolver_en_campo,
        es_urgente: !!repuesto.es_urgente,
    }))

    try {
        formEquipo.processing = true
        
        // Crear FormData para enviar equipo + foto
        const formData = new FormData()
        
        Object.keys(formEquipo.data()).forEach(key => {
            const value = formEquipo[key]
            
            // id_solucion e id_tipo_falla se envían como array[] para Laravel
            if (['id_solucion', 'id_tipo_falla'].includes(key) && Array.isArray(value)) {
                value.forEach(id => {
                    formData.append(`${key}[]`, id)
                })
            } 
            // repuestos se envía como JSON
            else if (key === 'repuestos' && Array.isArray(value)) {
                formData.append(key, JSON.stringify(value))
            } 
            // Otros campos normales
            else {
                formData.append(key, value || '')
            }
        })
        
        // Agregar descripcion_otros si la falla "Otros" está seleccionada
        if (fallaOtrosSeleccionada.value && descripcionOtros.value.trim()) {
            formData.append('descripcion_otros', descripcionOtros.value.trim())
        }

        const esEdicion = !!equipoEditandoId.value
        const url = esEdicion
            ? route('visitastecnicas.equipos.update', equipoEditandoId.value)
            : route('visitastecnicas.equipos.store')

        if (esEdicion) {
            formData.append('_method', 'PUT')
        }

        const response = await axios.post(url, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            }
        })

        const fotosPendientes = [...fotosAntesEquipo.value]
        const equipoIdPendiente = response.data?.id ?? equipoEditandoId.value

        modalEquipo.value = false
        equipoEditandoId.value = null
        repuestosTemporal.value = []
        fotosAntesEquipo.value = []
        formEquipo.reset()

        router.reload({ only: ['visita', 'equipos'] })

        if (fotosPendientes.length > 0 && equipoIdPendiente) {
            subirFotosAntesSilencioso(fotosPendientes, equipoIdPendiente, 'ANTES', props.visita.id)
        }

    } catch (error) {
        if (error.response?.data?.errors) {
            Object.keys(error.response.data.errors).forEach(key => {
                formEquipo.setError(key, error.response.data.errors[key])
            })
        }
    } finally {
        formEquipo.processing = false
    }
}

const subirFotosAntesSilencioso = async (fotos, equipoId, tipo, visitaId) => {
    try {
        const formData = new FormData()
        formData.append('equipo_id', equipoId)
        formData.append('tipo', tipo)
        if (visitaId) formData.append('visita_id', visitaId)
        fotos.forEach(foto => formData.append('fotos[]', foto.file))

        await axios.post(route('visitastecnicas.fotos.store'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })

        router.reload({ only: ['equipos'] })
    } catch (error) {
        console.error('Error subiendo evidencia inicial del equipo:', error)
    }
}

const agregarRepuestoTemporal = () => {
    if (!repuestoSeleccionado.value || !formRepuesto.id_cod_max) return
    const cantidad = Number(formRepuesto.cantidad)

    if (!Number.isFinite(cantidad) || cantidad <= 0) {
        formRepuesto.setError('cantidad', 'La cantidad debe ser mayor a 0.')
        return
    }

    if (formRepuesto.resolver_en_campo && !String(formRepuesto.observacion || '').trim()) {
        formRepuesto.setError('observacion', 'La observación es obligatoria cuando se marca como resuelto en campo.')
        return
    }

    formRepuesto.clearErrors(['cantidad', 'observacion'])

    const repuestoTemporalPayload = {
        id_cod_max: formRepuesto.id_cod_max,
        cantidad,
        observacion: formRepuesto.observacion,
        resolver_en_campo: !!formRepuesto.resolver_en_campo,
        es_urgente: !!formRepuesto.es_urgente,
        descripcion: repuestoSeleccionado.value.descripcion,
        codigo: repuestoSeleccionado.value.codigo,
        inventario: repuestoSeleccionado.value.inventario,
    }

    if (repuestoTemporalEditandoIndex.value !== null) {
        repuestosTemporal.value.splice(repuestoTemporalEditandoIndex.value, 1, repuestoTemporalPayload)
    } else {
        repuestosTemporal.value.push(repuestoTemporalPayload)
    }

    limpiarFormularioRepuestoTemporal()
    formEquipo.clearErrors('repuestos')
    formRepuesto.clearErrors('cantidad')
}

const resetearFormularioRepuesto = () => {
    formRepuesto.reset()
    formRepuesto.equipo_id = ''
    formRepuesto.id_cod_max = ''
    formRepuesto.cantidad = 1
    formRepuesto.observacion = ''
    formRepuesto.resolver_en_campo = false
    formRepuesto.es_urgente = false
}

const limpiarFormularioRepuestoTemporal = () => {
    repuestoTemporalEditandoIndex.value = null
    repuestoSeleccionado.value = null
    limpiarBusquedaRepuestos()
    resetearFormularioRepuesto()
    formRepuesto.clearErrors(['cantidad', 'observacion'])
}

const editarRepuestoTemporal = async (index) => {
    const repuesto = repuestosTemporal.value[index]

    if (!repuesto) return

    repuestoTemporalEditandoIndex.value = index
    repuestoSeleccionado.value = {
        codigo: repuesto.codigo,
        descripcion: repuesto.descripcion,
        inventario: repuesto.inventario,
    }
    repuestosBusqueda.value = repuesto.descripcion ?? repuesto.codigo ?? ''
    repuestosResultados.value = []
    formRepuesto.id_cod_max = repuesto.id_cod_max
    formRepuesto.cantidad = repuesto.cantidad
    formRepuesto.observacion = repuesto.observacion ?? ''
    formRepuesto.resolver_en_campo = !!repuesto.resolver_en_campo
    formRepuesto.es_urgente = !!repuesto.es_urgente
    formRepuesto.clearErrors(['cantidad', 'observacion'])

    await nextTick()
    repuestoEditorRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const seleccionarFotoAntes = async (event) => {
    const archivos = Array.from(event.target.files || [])
    if (archivos.length === 0) return

    comprimiendoFotosAntes.value = true
    try {
        const resultados = await Promise.all(
            archivos.map(async (archivo) => {
                const comprimido = await compressImageFile(archivo)
                return {
                    file: comprimido,
                    url: URL.createObjectURL(comprimido),
                    nombre: comprimido.name,
                }
            })
        )

        fotosAntesEquipo.value.push(...resultados)
    } finally {
        comprimiendoFotosAntes.value = false
    }
}

const eliminarFotoAntes = (index) => {
    fotosAntesEquipo.value.splice(index, 1)
}

const eliminarRepuestoTemporal = (index) => {
    if (repuestoTemporalEditandoIndex.value === index) {
        limpiarFormularioRepuestoTemporal()
    } else if (repuestoTemporalEditandoIndex.value !== null && repuestoTemporalEditandoIndex.value > index) {
        repuestoTemporalEditandoIndex.value -= 1
    }

    repuestosTemporal.value.splice(index, 1)

    if (cumpleRepuestoObligatorio.value) {
        formEquipo.clearErrors('repuestos')
    }
}

const compressImageFile = async (file) => {
    if (!file || !file.type.startsWith('image/')) {
        return file
    }

    const maxDimension = 1920
    const quality = 0.85

    try {
        const imageBitmap = await createImageBitmap(file)
        let { width, height } = imageBitmap

        if (width <= maxDimension && height <= maxDimension && file.type === 'image/jpeg') {
            imageBitmap.close?.()
            return file
        }

        const ratio = width / height
        if (width > maxDimension || height > maxDimension) {
            if (width >= height) {
                width = maxDimension
                height = Math.round(maxDimension / ratio)
            } else {
                height = maxDimension
                width = Math.round(maxDimension * ratio)
            }
        }

        const canvas = document.createElement('canvas')
        canvas.width = width
        canvas.height = height
        const ctx = canvas.getContext('2d')
        ctx.drawImage(imageBitmap, 0, 0, width, height)
        imageBitmap.close?.()

        const blob = await new Promise((resolve) => {
            canvas.toBlob(resolve, 'image/jpeg', quality)
        })

        if (!blob) {
            return file
        }

        const name = file.name.replace(/\.[^.]+$/, '.jpg')
        return new File([blob], name, { type: 'image/jpeg' })
    } catch (error) {
        return file
    }
}

const eliminarEquipo = (id) => {
    if (!confirm('¿Eliminar este equipo y sus repuestos?')) return
    router.delete(route('visitastecnicas.equipos.destroy', id), {
        onSuccess: () => router.reload({ only: ['equipos', 'visita'] }),
    })
}

// ── Repuestos ──────────────────────────────────────────────
const abrirModalRepuesto = (equipo) => {
    repuestoEditandoId.value = null
    equipoSeleccionado.value = equipo
    resetearFormularioRepuesto()
    formRepuesto.equipo_id = equipo.id
    repuestoSeleccionado.value = null
    limpiarBusquedaRepuestos()
    modalRepuesto.value = true
}

const abrirModalEditarRepuesto = async (equipo, repuesto) => {
    repuestoEditandoId.value = repuesto.id
    equipoSeleccionado.value = equipo
    resetearFormularioRepuesto()
    formRepuesto.equipo_id = equipo.id
    formRepuesto.id_cod_max = repuesto.id_cod_max ?? ''
    formRepuesto.cantidad = Number(repuesto.cantidad) || 1
    formRepuesto.observacion = repuesto.observacion ?? ''
    formRepuesto.resolver_en_campo = Number(repuesto.estado_id) === ESTADO_REPUESTO_INSTALADO
    formRepuesto.es_urgente = !!repuesto.es_urgente
    let detalleRepuesto = {
        descripcion: repuestosConDescripcion.value[repuesto.id_cod_max]?.descripcion ?? repuesto.descripcion ?? null,
        codigo_proveedor: repuestosConDescripcion.value[repuesto.id_cod_max]?.codigo_proveedor ?? repuesto.codigo_proveedor ?? null,
        inventario: repuestosConDescripcion.value[repuesto.id_cod_max]?.inventario ?? repuesto.inventario ?? null,
    }

        try {
            const detalleConsultado = await obtenerDescripcionRepuesto(repuesto.id_cod_max, bodega.value)
            detalleRepuesto = {
                descripcion: detalleConsultado.descripcion ?? detalleRepuesto.descripcion,
                codigo_proveedor: detalleConsultado.codigo_proveedor ?? detalleRepuesto.codigo_proveedor,
                inventario: detalleConsultado.inventario ?? detalleRepuesto.inventario,
            }
        } catch (error) {
    }

    repuestoSeleccionado.value = {
        codigo: repuesto.id_cod_max,
        descripcion: detalleRepuesto.descripcion ?? repuesto.id_cod_max,
        proveedor: detalleRepuesto.codigo_proveedor ?? null,
        inventario: detalleRepuesto.inventario ?? null,
    }
    repuestosBusqueda.value = detalleRepuesto.descripcion ?? repuesto.id_cod_max ?? ''
    repuestosResultados.value = []
    modalRepuesto.value = true
}

    const buscarRepuestos = () => {
        clearTimeout(repuestoTimeout)

        const busqueda = String(repuestosBusqueda.value || '').trim()

        if (repuestoSeleccionado.value && busqueda !== String(repuestoSeleccionado.value.descripcion || '').trim()) {
            repuestoSeleccionado.value = null
            formRepuesto.id_cod_max = ''
        }

        if (busqueda.length < 2) {
            repuestosResultados.value = []
            repuestosCargando.value = false
            repuestosBuscada.value = false
            return
        }

        repuestosBuscada.value = true
        repuestoTimeout = setTimeout(async () => {
            repuestosCargando.value = true
            try {
                const response = await fetch(
                    `/visitas-tecnicas/senco360/repuestos/buscar?q=${encodeURIComponent(busqueda)}&bodega=${encodeURIComponent(bodega.value)}`,
                    { credentials: 'include' }
                )
                const resultados = await response.json()

                if (busqueda === String(repuestosBusqueda.value || '').trim()) {
                    repuestosResultados.value = resultados
                }
            } catch (error) {
                repuestosResultados.value = []
            } finally {
                if (busqueda === String(repuestosBusqueda.value || '').trim()) {
                    repuestosCargando.value = false
                }
            }
        }, 300)
    }

const seleccionarRepuesto = (item) => {
    repuestoSeleccionado.value = item
    formRepuesto.id_cod_max = item.codigo
    repuestosBusqueda.value = item.descripcion
    repuestosResultados.value = []
    repuestosBuscada.value = false
}

const activarResueltoEnCampo = () => {
    if (formRepuesto.resolver_en_campo) {
        formRepuesto.es_urgente = false
    }
}

const activarUrgente = () => {
    if (formRepuesto.es_urgente) {
        formRepuesto.resolver_en_campo = false
        formRepuesto.clearErrors('observacion')
    }
}

const formatearInventario = (inventario) => {
    if (inventario === null || inventario === undefined || inventario === '') return 'N/D'

    const valor = Number(inventario)
    if (Number.isNaN(valor)) return inventario

    return new Intl.NumberFormat('es-CO', {
        maximumFractionDigits: 0,
    }).format(valor)
}

const textoStock = (inventario) => {
    const valor = Number(inventario)
    if (inventario === null || inventario === undefined || inventario === '' || Number.isNaN(valor)) {
        return 'Stock N/D'
    }

    if (valor <= 0) return 'Sin stock'
    return `Stock: ${formatearInventario(valor)}`
}

const claseStock = (inventario) => {
    const valor = Number(inventario)

    if (inventario === null || inventario === undefined || inventario === '' || Number.isNaN(valor)) {
        return 'bg-stone-100 text-stone-500'
    }

    if (valor <= 0) return 'bg-stone-100 text-stone-600'
    return 'bg-emerald-100 text-emerald-800'
}

const guardarRepuesto = () => {
    if (formRepuesto.resolver_en_campo && !String(formRepuesto.observacion || '').trim()) {
        formRepuesto.setError('observacion', 'La observación es obligatoria cuando se marca como resuelto en campo.')
        return
    }

    if (repuestoEditandoId.value) {
        formRepuesto.transform((data) => ({
            cantidad: data.cantidad,
            observacion: data.observacion,
            id_estado: data.resolver_en_campo ? 19 : 13,
            resolver_en_campo: !!data.resolver_en_campo,
            es_urgente: !!data.es_urgente,
        })).put(route('visitastecnicas.solicitudes-partes.update', repuestoEditandoId.value), {
            onSuccess: () => {
                modalRepuesto.value = false
                repuestoEditandoId.value = null
                router.reload({ only: ['equipos'] })
            },
            onFinish: () => formRepuesto.transform((data) => data),
        })
        return
    }

    formRepuesto.post(route('visitastecnicas.solicitudes-partes.store'), {
        onSuccess: () => {
            modalRepuesto.value = false
            repuestoEditandoId.value = null
            router.reload({ only: ['equipos'] })
        },
    })
}

const eliminarRepuesto = (id) => {
    if (!confirm('¿Eliminar este repuesto?')) return
    router.delete(route('visitastecnicas.solicitudes-partes.destroy', id), {
        preserveState: false,
        onSuccess: () => router.reload(),
    })
}

const abrirModalInstalacion = (equipo, repuesto) => {
    equipoSeleccionado.value = equipo
    repuestoInstalacion.value = repuesto
    fotosDespuesInstalacion.value = []
    formInstalacion.fotos_despues = []
    formInstalacion.soluciones_adicionales = []
    formInstalacion.observacion_instalacion = repuesto?.observacion_instalacion ?? ''
    modalInstalacion.value = true
}

const abrirModalSolucionesComplementarias = (equipo) => {
    equipoSeleccionado.value = equipo
    repuestoInstalacion.value = null
    formInstalacion.soluciones_adicionales = []
    equipoObservacionesTemporal.value = equipo.observaciones ?? ''
    modalSolucionesComplementarias.value = true
}

const cerrarModalSolucionesComplementarias = () => {
    modalSolucionesComplementarias.value = false
    formInstalacion.soluciones_adicionales = []
    equipoObservacionesTemporal.value = ''
}

const agregarFotosDespuesInstalacion = (event) => {
    const archivos = Array.from(event.target.files || [])
    archivos.forEach((archivo) => {
        fotosDespuesInstalacion.value.push({
            file: archivo,
            url: URL.createObjectURL(archivo),
        })
    })
    formInstalacion.fotos_despues = fotosDespuesInstalacion.value.map(foto => foto.file)
}

const eliminarFotoDespuesInstalacion = (index) => {
    fotosDespuesInstalacion.value.splice(index, 1)
    formInstalacion.fotos_despues = fotosDespuesInstalacion.value.map(foto => foto.file)
}

const confirmarInstalacion = async () => {
    if (!repuestoInstalacion.value || !equipoSeleccionado.value) return

    const debeAbrirSolucionesComplementarias = equipoQuedariaListoParaSolucionesComplementarias(
        equipoSeleccionado.value,
        repuestoInstalacion.value
    )

    try {
        formInstalacion.processing = true

        // Paso 1: Subir fotos si existen
        if (fotosDespuesInstalacion.value.length > 0) {
            const formData = new FormData()
            formData.append('equipo_id', equipoSeleccionado.value.id)
            formData.append('tipo', 'DESPUES')
            
            // Agregar cada archivo al formData
            fotosDespuesInstalacion.value.forEach((foto) => {
                formData.append('fotos[]', foto.file)
            })

            await axios.post(route('visitastecnicas.fotos.store'), formData, {
                headers: { 
                    'Content-Type': 'multipart/form-data',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
            })
        }

// Paso 2: Actualizar estado del repuesto a instalado
         await axios.put(route('visitastecnicas.repuestos.estado', repuestoInstalacion.value.id), {
             estado_id: 19,
             observacion: formInstalacion.observacion_instalacion,
             soluciones_adicionales: formInstalacion.soluciones_adicionales,
         }, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
        })

        // Paso 3: Limpiar y cerrar modal
        modalInstalacion.value = false
        fotosDespuesInstalacion.value = []
        formInstalacion.fotos_despues = []
        formInstalacion.soluciones_adicionales = []
        formInstalacion.observacion_instalacion = ''
        
        // Paso 4: Recargar datos necesarios sin navegar manualmente
        router.reload({
            only: ['visita', 'equipos', 'historial'],
            onSuccess: async (page) => {
                if (!debeAbrirSolucionesComplementarias) {
                    return
                }

                const equiposActualizados = page?.props?.equipos ?? props.equipos
                const equipoActualizado = Array.isArray(equiposActualizados)
                    ? equiposActualizados.find((equipo) => Number(equipo.id) === Number(equipoSeleccionado.value?.id))
                    : null

                if (!equipoActualizado || !puedeAgregarSolucionComplementariaEquipo(equipoActualizado)) {
                    return
                }

                await nextTick()
                abrirModalSolucionesComplementarias(equipoActualizado)
            },
        })

    } catch (error) {
        let mensaje = error.response?.data?.message || error.message
        if (error.response?.status === 422) {
            mensaje = 'Error de validación: ' + mensaje
        } else if (error.response?.status === 403) {
            mensaje = 'No permitido: ' + mensaje
        }
        
        alert('❌ ' + mensaje)
    }
    finally {
        formInstalacion.processing = false
    }
}

const guardarSolucionesComplementarias = async () => {
    if (!equipoSeleccionado.value || formInstalacion.soluciones_adicionales.length === 0) {
        return
    }

    try {
        formInstalacion.processing = true

        await axios.put(route('visitastecnicas.equipos.soluciones-complementarias', equipoSeleccionado.value.id), {
            soluciones_adicionales: formInstalacion.soluciones_adicionales.map(id => Number(id)),
            observaciones: equipoObservacionesTemporal.value,
        }, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
        })

        cerrarModalSolucionesComplementarias()
        router.reload({ only: ['visita', 'equipos', 'historial'] })
    } catch (error) {
        const mensaje = error.response?.data?.message || 'No fue posible guardar las soluciones complementarias.'
        alert(mensaje)
    } finally {
        formInstalacion.processing = false
    }
}

// ── Finalizar ────────────────────────────────────────────
const abrirModalFinalizar = () => {
    modalFinalizar.value = true
    mostrarAlertaFinalizacion.value = false
    formFinalizar.firma = ''
    nextTick(() => {
        prepararCanvasFirma()
        limpiarFirma()
    })
}

const abrirModalCotizacion = () => {
    modalCotizacion.value = true
    mostrarAlertaCotizacion.value = false
}

const finalizar = () => {
    mostrarAlertaFinalizacion.value = camposFaltantesFinalizacion.value.length > 0

    if (mostrarAlertaFinalizacion.value) {
        return
    }

    if (!props.visita?.es_capacitacion && firmaTieneContenido()) {
        formFinalizar.firma = canvasFirma.value.toDataURL('image/png')
    } else {
        formFinalizar.firma = ''
    }

    formFinalizar.transform((data) => ({
        ...data,
        correo_cliente: formServicio.correo_cliente,
        fecha_inicio: formServicio.fecha_inicio,
        hora_inicio: formServicio.hora_inicio,
    })).post(route('visitastecnicas.visitas.finalizar', props.visita.id), {
        onSuccess: () => {
            clearServiceFields()
            modalFinalizar.value = false
            modalFinalizacionExitosa.value = true
        },
        onFinish: () => formFinalizar.transform((payload) => payload),
    })
}

const solicitarCotizacion = async () => {
    enviandoCotizacion.value = true
    mostrarAlertaCotizacion.value = false
    formServicio.clearErrors()

    try {
        // Usar directamente la URL en lugar de route()
        const url = `/visitas-tecnicas/visitas/${props.visita.id}/solicitar-cotizacion`
        
        await axios.post(url, {
            correo_cliente: formServicio.correo_cliente,
            fecha_inicio: formServicio.fecha_inicio,
            hora_inicio: formServicio.hora_inicio,
        }, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            }
        })

        modalCotizacion.value = false
        modalCotizacionEnviada.value = true
        clearServiceFields()
        router.reload({ only: ['visita', 'equipos', 'historial'] })
    } catch (error) {
        if (error.response) {
            if (error.response.status === 422 && error.response.data?.errors) {
                formServicio.errors = error.response.data.errors
                mostrarAlertaCotizacion.value = true
                return
            }
            
            if (error.response.status === 403) {
                mostrarAlertaCotizacion.value = true
                return
            }
        }

        alert('Error al enviar a cotización: ' + (error.response?.data?.message || error.message))
    } finally {
        enviandoCotizacion.value = false
    }
}

// ── Capacitación ───────────────────────────────────────────
const formCapacitacion   = ref({ titulo: '', temas: '' })
const guardandoCapacitacion = ref(false)

// Inicializar form con datos existentes
if (props.equipos.length > 0 && props.visita?.es_capacitacion) {
    formCapacitacion.value.titulo = props.equipos[0].titulo ?? ''
    formCapacitacion.value.temas  = props.equipos[0].descripcion_falla ?? ''
}

const crearCapacitacion = () => {
    guardandoCapacitacion.value = true
    router.post(route('visitastecnicas.equipos.store'), {
        visita_id:         props.visita.id,
        id_cod_max:        'CAP',
        titulo:            formCapacitacion.value.titulo,
        descripcion_falla: formCapacitacion.value.temas,
        id_solucion:       null,
    }, {
        onSuccess: () => { guardandoCapacitacion.value = false },
        onError:   () => { guardandoCapacitacion.value = false },
    })
}

const guardarCapacitacion = (equipoId) => {
    guardandoCapacitacion.value = true
    router.put(route('visitastecnicas.equipos.update', equipoId), {
        id_cod_max:        props.equipos[0]?.id_cod_max || 'CAP',
        titulo:            formCapacitacion.value.titulo,
        descripcion_falla: formCapacitacion.value.temas,
    }, {
        onSuccess: () => { guardandoCapacitacion.value = false },
        onError:   () => { guardandoCapacitacion.value = false },
    })
}

const subirFotos = async (event, equipoId, tipo, visitaId = null) => {
    const archivos = Array.from(event.target.files || [])
    if (!archivos.length) return

    const input = event.target
    if (input) {
        input.value = ''
    }

    subiendoFotosEvidencia.value = true

    try {
        const archivosComprimidos = await Promise.all(
            archivos.map(async (archivo) => compressImageFile(archivo))
        )

        const formData = new FormData()
        if (equipoId) {
            formData.append('equipo_id', equipoId)
        }
        if (visitaId) {
            formData.append('visita_id', visitaId)
        }
        formData.append('tipo', tipo)
        archivosComprimidos.forEach((archivo) => {
            formData.append('fotos[]', archivo)
        })

        await axios.post(route('visitastecnicas.fotos.store'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })

        router.reload({ only: ['equipos', 'visita'] })
    } catch (error) {
    } finally {
        subiendoFotosEvidencia.value = false
    }
}

const eliminarFoto = (fotoId) => {
    if (!confirm('¿Eliminar esta foto?')) return

    axios.delete(route('visitastecnicas.fotos.destroy', fotoId))
    .then(() => {
        router.reload({ only: ['equipos', 'visita'] })
    })
    .catch(() => {
    })
}

const guardarBorrador = () => {
    formServicio.clearErrors()

    axios.post(route('visitastecnicas.visitas.guardar-borrador', props.visita.id), {
        correo_cliente: formServicio.correo_cliente,
        fecha_inicio: formServicio.fecha_inicio,
        hora_inicio: formServicio.hora_inicio,
    }, {
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
    }).then(() => {
        modalGuardadoBorrador.value = true
        clearServiceFields()
        router.reload({ only: ['visita'] })
    }).catch((error) => {
        if (error.response?.status === 422 && error.response.data?.errors) {
            formServicio.errors = error.response.data.errors
            return
        }

        alert(error.response?.data?.message || 'No fue posible guardar los cambios.')
    })
}
</script>

<template>
    <AppLayout v-if="visita" :title="`Visita #${visita.id}`">
        <Head :title="`Visita #${visita.id}`" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-stone-900">Visita #{{ visita.id }}</h2>
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="estadoColor">
                    {{ visita.estado }}
                </span>
            </div>
        </template>

        <div class="py-4 sm:py-6">
            <div class="mx-auto max-w-3xl space-y-4 px-3 sm:px-6">

                <!-- Banner completado -->
                <div v-if="completado"
                    class="flex items-center gap-3 rounded-xl bg-green-50 border border-green-200 px-4 py-3">
                    <svg class="h-5 w-5 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm font-medium text-green-700">Visita completada — solo lectura</p>
                </div>

                <!-- ── Sección 1: Cliente ── -->
                <div class="rounded-xl bg-white shadow-sm border border-stone-200 overflow-hidden">
                    <div class="bg-stone-50 border-b border-stone-200 px-4 py-2.5">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-stone-500">Cliente</h3>
                    </div>
                    <div class="px-4 py-3 grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <p class="text-xs text-stone-500">Empresa</p>
                            <p class="text-sm font-semibold text-stone-900">{{ visita.cliente?.nombre ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-stone-500">NIT</p>
                            <p class="text-sm text-stone-700">{{ visita.cliente?.nit ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-stone-500">N° Ruta</p>
                            <p class="text-sm text-stone-700">{{ visita.numero_ruta ?? '—' }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-stone-500">Dirección</p>
                            <p class="text-sm text-stone-700">{{ visita.direccion ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-stone-500">Fecha programada</p>
                            <p class="text-sm text-stone-700">{{ visita.fecha_visita ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-stone-500">Nombre Contacto</p>
                            <p class="text-sm text-stone-700">{{ visita.nom_contacto ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-stone-500">Teléfono Contacto</p>
                            <p class="text-sm text-stone-700">{{ visita.tel_contacto ?? '—' }}</p>
                        </div>
                        <div v-if="visita.observaciones_ruta" class="col-span-2">
                            <p class="text-xs text-stone-500">Observaciones</p>
                            <p class="text-sm text-stone-700">{{ visita.observaciones_ruta }}</p>
                        </div>
                    </div>
                </div>

                <!-- ── Sección 2: Servicio ── -->
                <div class="rounded-xl bg-white shadow-sm border border-stone-200 overflow-hidden">
                    <div class="bg-stone-50 border-b border-stone-200 px-4 py-2.5">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-stone-500">Servicio</h3>
                    </div>
                    <div class="px-4 py-3 grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-stone-500">Tipo</p>
                            <p class="text-sm font-medium text-stone-900">{{ visita.tipo_servicio ?? '—' }}</p>
                        </div>
                        <div v-if="visita.es_capacitacion || completado">
                            <p class="text-xs text-stone-500">Correo para Informe técnico de visita</p>
                            <p class="text-sm text-stone-700">{{ visita.correo_cliente ?? '—' }}</p>
                        </div>
                        <div v-else>
                            <label class="block text-xs text-stone-500 mb-1">Correo para informe técnico de la visita</label>
                            <input v-model="formServicio.correo_cliente" type="email"
                                placeholder="cliente@empresa.com"
                                class="block w-full rounded-lg border-stone-300 text-sm py-2.5 focus:border-[#C8102E] focus:ring-red-100" />
                            <p v-if="formServicio.errors.correo_cliente" class="mt-1 text-xs text-red-600">
                                {{ formServicio.errors.correo_cliente }}
                            </p>
                        </div>
                        <div v-if="visita.es_capacitacion || completado">
                            <p class="text-xs text-stone-500">Fecha inicio</p>
                            <p class="text-sm text-stone-700">{{ visita.fecha_inicio ?? '—' }}</p>
                        </div>
                        <div v-else>
                            <label class="block text-xs text-stone-500 mb-1">Fecha inicio <span class="text-red-500">*</span></label>
                            <input v-model="formServicio.fecha_inicio" type="date" required
                                :disabled="esPendienteRepuestos"
                                class="block w-full rounded-lg border-stone-300 text-sm py-2.5 focus:border-[#C8102E] focus:ring-red-100 disabled:bg-stone-100 disabled:cursor-not-allowed disabled:text-stone-500" />
                            <p v-if="esPendienteRepuestos" class="mt-1 text-xs text-stone-500">
                                No se puede modificar después de enviar a cotización
                            </p>
                            <p v-if="formServicio.errors.fecha_inicio" class="mt-1 text-xs text-red-600">
                                {{ formServicio.errors.fecha_inicio }}
                            </p>
                        </div>
                        <div v-if="visita.es_capacitacion || completado">
                            <p class="text-xs text-stone-500">Hora inicio</p>
                            <p class="text-sm text-stone-700">{{ visita.hora_inicio ?? '—' }}</p>
                        </div>
                        <div v-else>
                            <label class="block text-xs text-stone-500 mb-1">Hora inicio <span class="text-red-500">*</span></label>
                            <input v-model="formServicio.hora_inicio" type="time" required
                                :disabled="esPendienteRepuestos"
                                class="block w-full rounded-lg border-stone-300 text-sm py-2.5 focus:border-[#C8102E] focus:ring-red-100 disabled:bg-stone-100 disabled:cursor-not-allowed disabled:text-stone-500" />
                            <p v-if="esPendienteRepuestos" class="mt-1 text-xs text-stone-500">
                                No se puede modificar después de enviar a cotización
                            </p>
                            <p v-if="formServicio.errors.hora_inicio" class="mt-1 text-xs text-red-600">
                                {{ formServicio.errors.hora_inicio }}
                            </p>
                        </div>
                        <div v-if="visita.es_capacitacion || completado">
                            <p class="text-xs text-stone-500">Fecha fin</p>
                            <p class="text-sm text-stone-700">{{ visita.fecha_fin ?? '—' }}</p>
                        </div>
                        <div v-if="visita.es_capacitacion || completado">
                            <p class="text-xs text-stone-500">Hora fin</p>
                            <p class="text-sm text-stone-700">{{ visita.hora_fin ?? '—' }}</p>
                        </div>
                        <div v-if="visita.observaciones" class="col-span-2">
                            <p class="text-xs text-stone-500">Observaciones</p>
                            <p class="text-sm text-stone-700">{{ visita.observaciones }}</p>
                        </div>
                    </div>
                </div>

                <!-- ── Sección 3: Capacitación ── -->
<div v-if="visita?.es_capacitacion"
    class="rounded-xl bg-white shadow-sm border border-stone-200 overflow-hidden">
    <div class="bg-stone-50 border-b border-stone-200 px-4 py-2.5">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-stone-500">Capacitación</h3>
    </div>
    <div class="px-4 py-3 space-y-3">

        <!-- Formulario (crear o editar) -->
        <div>
            <label class="block text-xs font-medium text-stone-700 mb-1">
                Título <span class="text-red-500">*</span>
            </label>
            <p v-if="completado" class="text-sm text-stone-900 font-medium">
                {{ equipos[0]?.titulo ?? '—' }}
            </p>
            <input v-else v-model="formCapacitacion.titulo" type="text"
                placeholder="Título de la capacitación"
                class="block w-full rounded-lg border-stone-300 text-sm py-2.5 focus:border-[#C8102E] focus:ring-red-100" />
        </div>

        <div>
            <label class="block text-xs font-medium text-stone-700 mb-1">Temas tratados</label>
            <p v-if="completado" class="text-sm text-stone-700 whitespace-pre-wrap">
                {{ equipos[0]?.descripcion_falla ?? '—' }}
            </p>
            <textarea v-else v-model="formCapacitacion.temas" rows="5"
                placeholder="Describe los temas tratados en la capacitación..."
                class="block w-full rounded-lg border-stone-300 text-sm focus:border-[#C8102E] focus:ring-red-100"></textarea>
        </div>

        <!-- Fotos -->
        <div>
            <label class="block text-xs font-medium text-stone-700 mb-2">Evidencia Capacitación</label>
            <!-- Fotos existentes -->
            <div v-if="equipos[0]?.fotos_despues?.length > 0"
                class="grid grid-cols-3 gap-2 mb-3">
                <div v-for="foto in equipos[0].fotos_despues" :key="foto.id"
                    class="relative group">
                    <img :src="foto.url"
                        class="w-full h-24 object-cover rounded-lg border border-stone-200 cursor-pointer"
                        @click="abrirFotoAmpliada(foto.url)" />
                    <button v-if="!completado"
                        @click="eliminarFoto(foto.id)"
                        :class="buttonClass('danger', 'xs', 'absolute top-1 right-1 hidden h-6 w-6 px-0 py-0 group-hover:flex shadow')">
                        ✕
                    </button>
                </div>
            </div>
            <!-- Subir fotos -->
            <label v-if="!completado"
                class="flex items-center justify-center gap-2 w-full rounded-lg border-2 border-dashed border-stone-200 bg-stone-50 py-4 cursor-pointer hover:bg-stone-100 transition">
                <svg class="h-5 w-5 text-stone-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span class="text-sm text-stone-500">Agregar fotos</span>
                <input type="file" accept="image/*" multiple class="hidden"
                    @change="subirFotos($event, equipos[0]?.id, 'DESPUES')" />
            </label>
        </div>

        <!-- Botón guardar -->
        <div v-if="!completado">
            <button
                @click="equipos.length > 0 ? guardarCapacitacion(equipos[0].id) : crearCapacitacion()"
                :disabled="!formCapacitacion.titulo || guardandoCapacitacion"
                :class="buttonClass('primary', 'md', 'w-full')">
                {{ guardandoCapacitacion ? 'Guardando...' : (equipos.length > 0 ? 'Guardar cambios' : 'Guardar') }}
            </button>
        </div>

    </div>
</div>

                <!-- ── Sección 3: Equipos (Mantenimiento) ── -->
                <div v-else-if="visita && !visita.es_capacitacion" class="rounded-xl bg-white shadow-sm border border-stone-200 overflow-hidden">
                    <div class="flex items-center justify-between bg-stone-50 border-b border-stone-200 px-4 py-2.5">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-stone-500">Equipos atendidos</h3>
                        <button v-if="puedeEditar"
                            @click="abrirModalEquipo"
                            :class="buttonClass('primary', 'sm')">
                            + Agregar Equipo
                        </button>
                    </div>

                    <div v-if="equipos.length === 0" class="px-4 py-8 text-center text-sm text-stone-500">
                        No hay equipos registrados.
                    </div>

                    <div v-else class="space-y-2 bg-stone-50/60 px-3 py-3">
                        <div v-for="(equipo, indice) in equipos" :key="equipo.id" class="rounded-md border border-stone-200 bg-white overflow-hidden shadow-sm">
                            <!-- ENCABEZADO DESPLEGABLE DEL EQUIPO -->
                            <button @click="equipoExpandido = equipoExpandido === equipo.id ? null : equipo.id"
                                class="group w-full px-3 py-2.5 sm:px-3 sm:py-2.5 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3 hover:bg-stone-50 transition text-left border-b border-stone-200 bg-stone-50">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <span
                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border shadow-sm transition group-hover:scale-105 group-hover:shadow-md group-focus-visible:ring-2 group-focus-visible:ring-red-200"
                                        :class="equipoExpandido === equipo.id
                                            ? 'border-red-200 bg-red-50 text-[#C8102E]'
                                            : 'border-stone-300 bg-white text-stone-700'"
                                        aria-hidden="true"
                                    >
                                        <svg v-if="equipoExpandido === equipo.id" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" d="M5 12h14" />
                                        </svg>
                                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <p class="text-[13px] font-semibold text-stone-900 break-words">
                                            Equipo {{ indice + 1 }}
                                            <span v-if="equiposConDescripcion[equipo.id_cod_max]?.descripcion"> - {{ equiposConDescripcion[equipo.id_cod_max].descripcion }}</span>
                                            <span v-if="equipo.id_cod_max"> - {{ equipo.id_cod_max }}</span>
                                        </p>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-stone-600">
                                            <span v-if="equipo.serial"><span class="font-medium text-stone-700">Serial:</span> {{ equipo.serial }}</span>
                                            <span v-if="equiposConDescripcion[equipo.id_cod_max]?.codigo_proveedor"><span class="font-medium text-stone-700">Código Comodidad:</span> {{ equiposConDescripcion[equipo.id_cod_max].codigo_proveedor }}</span>
                                        </div>
                                        <p v-if="(Array.isArray(equipo.fallas) && equipo.fallas.length) || equipo.descripcion_falla" class="text-[11px] text-stone-600 line-clamp-2">
                                            <span class="font-medium text-stone-700">Fallas:</span> {{ Array.isArray(equipo.fallas) && equipo.fallas.length ? equipo.fallas.map(f => f.descripcion_otros && Number(f.id) === 34 ? f.descripcion_otros : f.descripcion).join(', ') : equipo.descripcion_falla }}
                                        </p>
                                        <p v-if="equipo.tipo_mant" class="text-[11px] text-stone-600">
                                            <span class="font-medium text-stone-700">Tipo mantenimiento:</span> {{ equipo.tipo_mant }}
                                        </p>
                                        <p v-if="equipo.observaciones" class="text-[11px] text-stone-600">
                                            <span class="font-medium text-stone-700">Observaciones:</span> {{ equipo.observaciones }}
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-1 flex w-full flex-wrap justify-end gap-1.5 sm:ml-2 sm:mt-0 sm:w-auto sm:shrink-0 sm:flex-row">
                                    <button v-if="puedeEditar && requiereRepuestos(equipo.soluciones_ids)"
                                        @click.stop="abrirModalRepuesto(equipo)"
                                        :class="buttonClass('success', 'xs')">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Repuesto
                                    </button>
                                    <button v-if="puedeEditar"
                                        @click.stop="abrirModalEditarEquipo(equipo)"
                                        :class="buttonClass('primary', 'xs')">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L12 15l-4 1 1-4 8.586-8.586z" />
                                        </svg>
                                        Editar
                                    </button>
                                    <button v-if="puedeEditar"
                                        @click.stop="eliminarEquipo(equipo.id)"
                                        :class="buttonClass('danger', 'xs')">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Eliminar
                                    </button>
                                </div>
                            </button>

                            <!-- CONTENIDO DEL EQUIPO (expandible) -->
                            <Transition
                                name="slide-fade"
                                @before-enter="beforeExpandEnter"
                                @enter="expandEnter"
                                @after-enter="afterExpandEnter"
                                @before-leave="beforeExpandLeave"
                                @leave="expandLeave"
                                @after-leave="afterExpandLeave">
                                <div v-if="equipoExpandido === equipo.id" class="overflow-hidden">
                                <div class="max-h-[70vh] overflow-y-auto bg-stone-50/70 px-2 py-2 space-y-1.5 sm:max-h-none sm:px-2.5 sm:py-2 sm:space-y-2">

                                <!-- SECCIÓN 2: Repuestos requeridos -->
                                <div v-if="requiereRepuestos(equipo.soluciones_ids)" class="ml-0 rounded-md border border-stone-200 bg-white overflow-hidden sm:ml-3">
                                    <button @click="toggleSeccion(equipo.id, 'repuestos')"
                                        class="w-full px-2.5 py-1.5 sm:px-3 sm:py-2 flex items-center justify-between hover:bg-stone-50 transition text-left border-l-2 border-l-amber-300 bg-amber-50/40">
                                        <div class="flex items-center gap-3">
                                            <span class="w-3.5 text-center text-lg leading-none font-bold text-amber-700">
                                                {{ esSeccionExpandida(equipo.id, 'repuestos') ? '-' : '+' }}
                                            </span>
                                            <p class="text-[11px] font-semibold text-amber-800 uppercase tracking-wide">Repuestos requeridos</p>
                                        </div>
                                        <span v-if="equipo.repuestos?.some(r => r.es_urgente)" class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">
                                            URGENTE
                                        </span>
                                        <span v-if="equipo.repuestos?.length" class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">{{ equipo.repuestos.length }}</span>
                                    </button>
                                    <Transition
                                        name="slide-fade"
                                        @before-enter="beforeExpandEnter"
                                        @enter="expandEnter"
                                        @after-enter="afterExpandEnter"
                                        @before-leave="beforeExpandLeave"
                                        @leave="expandLeave"
                                        @after-leave="afterExpandLeave">
                                        <div v-if="esSeccionExpandida(equipo.id, 'repuestos')">
                                            <div class="px-3 py-2 bg-white space-y-1.5 border-t border-stone-200">
                                                <div v-if="!equipo.repuestos?.length" class="text-xs text-stone-500 py-1.5 text-center">
                                                    No hay repuestos agregados
                                                </div>
                                                <div v-else class="space-y-1.5">
                                                    <div v-for="repuesto in equipo.repuestos" :key="repuesto.id"
                                                        class="flex flex-col sm:flex-row sm:items-start justify-between rounded border bg-stone-50/60 px-3 py-2 text-xs hover:bg-stone-50 transition gap-2" :class="repuesto.es_urgente ? 'border-red-300 bg-red-50/60' : 'border-stone-200'">
                                                        <div class="flex-1 min-w-0">
                                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mb-1">
                                                                <p class="font-semibold text-stone-900">Código Max: {{ repuesto.id_cod_max }}</p>
                                                                <span v-if="repuesto.es_urgente" class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">
                                                                    URGENTE
                                                                </span>
                                                                <span v-if="repuestosConDescripcion[repuesto.id_cod_max]?.codigo_proveedor || repuesto.codigo_proveedor" class="text-stone-500 hidden sm:inline">|</span>
                                                                <span v-if="repuestosConDescripcion[repuesto.id_cod_max]?.codigo_proveedor || repuesto.codigo_proveedor" class="text-stone-700 font-medium">Código Comodidad: {{ repuestosConDescripcion[repuesto.id_cod_max]?.codigo_proveedor ?? repuesto.codigo_proveedor }}</span>
                                                            </div>
                                                            <p v-if="repuestosConDescripcion[repuesto.id_cod_max]?.descripcion || repuesto.descripcion" class="text-stone-600 mb-1">{{ repuestosConDescripcion[repuesto.id_cod_max]?.descripcion ?? repuesto.descripcion }}</p>
                                                            <p class="text-stone-700">Cantidad: <span class="font-semibold">{{ repuesto.cantidad }}</span></p>
                                                            <p v-if="repuesto.observacion" class="mt-1 text-stone-600">
                                                                <span class="font-medium text-stone-700">Observación:</span> {{ repuesto.observacion }}
                                                            </p>
                                                        </div>
                                                        <div class="flex items-center gap-2 flex-shrink-0 self-end sm:self-start sm:ml-2">
                                                            <span class="text-xs font-semibold px-2 py-1 rounded border"
                                                                :class="estadoRepuestoColor(repuesto.estado ?? '')">
                                                                {{ repuesto.estado ?? 'Sin estado' }}
                                                            </span>
                                                            <button v-if="!completado && Number(repuesto.estado_id) === 18"
                                                                @click="abrirModalInstalacion(equipo, repuesto)"
                                                                :class="buttonClass('success', 'xs')">
                                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                                </svg>
                                                                Instalar
                                                            </button>
                                                            <button v-if="puedeEditar && [13, 19].includes(Number(repuesto.estado_id))"
                                                                @click="abrirModalEditarRepuesto(equipo, repuesto)"
                                                                :class="buttonClass('primary', 'xs', 'h-8 w-8 px-0 py-0 shadow-sm ring-1 ring-blue-200')"
                                                                title="Editar repuesto"
                                                                aria-label="Editar repuesto">
                                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L12 15l-4 1 1-4 8.586-8.586z" />
                                                                </svg>
                                                            </button>
                                                            <button v-if="puedeEditar && [13, 19].includes(Number(repuesto.estado_id))"
                                                                @click="eliminarRepuesto(repuesto.id)"
                                                                :class="buttonClass('danger', 'xs')">X</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>

                                <!-- SECCIÓN: Evidencia Inicial -->
                                <div class="ml-0 rounded-md border border-stone-200 bg-white overflow-hidden sm:ml-3">
                                    <button @click="toggleSeccion(equipo.id, 'fotos-antes')"
                                        class="w-full px-2.5 py-1.5 sm:px-3 sm:py-2 flex items-center justify-between hover:bg-stone-50 transition text-left border-l-2 border-l-sky-300 bg-sky-50/40">
                                        <div class="flex items-center gap-3">
                                            <span class="w-3.5 text-center text-lg leading-none font-bold text-sky-700">
                                                {{ esSeccionExpandida(equipo.id, 'fotos-antes') ? '-' : '+' }}
                                            </span>
                                            <p class="text-[11px] font-semibold text-sky-800 uppercase tracking-wide">Evidencia inicial</p>
                                        </div>
                                        <span v-if="equipo.fotos_antes?.length" class="rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-800">{{ equipo.fotos_antes.length }}</span>
                                    </button>
                                    <Transition
                                        name="slide-fade"
                                        @before-enter="beforeExpandEnter"
                                        @enter="expandEnter"
                                        @after-enter="afterExpandEnter"
                                        @before-leave="beforeExpandLeave"
                                        @leave="expandLeave"
                                        @after-leave="afterExpandLeave">
                                        <div v-if="esSeccionExpandida(equipo.id, 'fotos-antes')">
                                            <div class="px-3 py-2 bg-white space-y-1.5 border-t border-stone-200">
                                                <div v-if="equipo.fotos_antes?.length > 0" class="space-y-1.5">
                                                    <div v-for="(foto, idx) in equipo.fotos_antes" :key="'antes-' + idx"
                                                        class="flex items-center justify-between text-xs bg-stone-50/60 px-3 py-2 rounded border border-stone-200 hover:bg-stone-50 transition">
                                                        <div class="flex items-center gap-2">
                                                            <svg class="w-4 h-4 text-stone-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                            <span class="text-stone-700 font-medium">Foto {{ idx + 1 }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <button @click="abrirFotoAmpliada(foto.url)"
                                                                :class="buttonClass('primary', 'xs')">
                                                                Ver
                                                            </button>
                                                            <button v-if="!completado && puedeEditar"
                                                                @click="eliminarFoto(foto.id)"
                                                                :class="buttonClass('danger', 'xs')">
                                                                Eliminar
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div v-else class="text-xs text-stone-500 py-1.5 text-center">
                                                    Sin evidencia inicial registrada
                                                </div>
                                                <label v-if="!completado && puedeEditar"
                                                    class="flex items-center justify-center gap-2 w-full rounded border border-dashed border-stone-300 bg-stone-50 py-2 px-3 cursor-pointer hover:bg-stone-100 transition mt-1">
                                                    <svg class="h-4 w-4 text-stone-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    <span class="text-xs font-medium text-stone-700">Agregar evidencia</span>
                                                    <input type="file" accept="image/*" multiple class="hidden"
                                                        @change="subirFotos($event, equipo.id, 'ANTES')" />
                                                </label>
                                                <div v-if="subiendoFotosEvidencia" class="mt-2 flex items-center gap-2 rounded border border-stone-200 bg-stone-50 px-3 py-2 text-[11px] text-stone-600">
                                                    <svg class="h-4 w-4 animate-spin text-stone-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    <span>Comprimiendo y subiendo evidencias...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>

                                <!-- SECCIÓN: Evidencia Final -->
                                <div class="ml-0 rounded-md border border-stone-200 bg-white overflow-hidden sm:ml-3">
                                    <button @click="toggleSeccion(equipo.id, 'fotos-despues')"
                                        class="w-full px-2.5 py-1.5 sm:px-3 sm:py-2 flex items-center justify-between hover:bg-stone-50 transition text-left border-l-2 border-l-emerald-300 bg-emerald-50/40">
                                        <div class="flex items-center gap-3">
                                            <span class="w-3.5 text-center text-lg leading-none font-bold text-emerald-700">
                                                {{ esSeccionExpandida(equipo.id, 'fotos-despues') ? '-' : '+' }}
                                            </span>
                                            <p class="text-[11px] font-semibold text-emerald-800 uppercase tracking-wide">Evidencia final</p>
                                        </div>
                                        <span v-if="equipo.fotos_despues?.length" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">{{ equipo.fotos_despues.length }}</span>
                                    </button>
                                    <Transition
                                        name="slide-fade"
                                        @before-enter="beforeExpandEnter"
                                        @enter="expandEnter"
                                        @after-enter="afterExpandEnter"
                                        @before-leave="beforeExpandLeave"
                                        @leave="expandLeave"
                                        @after-leave="afterExpandLeave">
                                        <div v-if="esSeccionExpandida(equipo.id, 'fotos-despues')">
                                            <div class="px-3 py-2 bg-white space-y-1.5 border-t border-stone-200">
                                                <div v-if="equipo.fotos_despues?.length > 0" class="space-y-1">
                                                    <div v-for="(foto, idx) in equipo.fotos_despues" :key="'despues-' + idx"
                                                        class="flex items-center justify-between text-xs bg-stone-50/60 px-3 py-1.5 rounded border border-stone-200 hover:bg-stone-50 transition">
                                                        <div class="flex items-center gap-2">
                                                            <svg class="w-4 h-4 text-stone-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                            <span class="text-stone-700 font-medium">Foto {{ idx + 1 }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <button @click="abrirFotoAmpliada(foto.url)"
                                                                :class="buttonClass('primary', 'xs')">
                                                                Ver
                                                            </button>
                                                            <button v-if="!completado && puedeGestionarEvidenciaFinal"
                                                                @click="eliminarFoto(foto.id)"
                                                                :class="buttonClass('danger', 'xs')">
                                                                Eliminar
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div v-else class="text-xs text-stone-500 py-1.5 text-center">
                                                    Sin evidencia final registrada
                                                </div>
                                                <label v-if="!completado && puedeGestionarEvidenciaFinal"
                                                    class="flex items-center justify-center gap-2 w-full rounded border border-dashed border-stone-300 bg-stone-50 py-2 px-3 cursor-pointer hover:bg-stone-100 transition mt-1">
                                                    <svg class="h-4 w-4 text-stone-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    <span class="text-xs font-medium text-stone-700">Agregar evidencia final</span>
                                                    <input type="file" accept="image/*" multiple class="hidden"
                                                        @change="subirFotos($event, equipo.id, 'DESPUES')" />
                                                </label>
                                                <div v-if="subiendoFotosEvidencia" class="mt-2 flex items-center gap-2 rounded border border-stone-200 bg-stone-50 px-3 py-2 text-[11px] text-stone-600">
                                                    <svg class="h-4 w-4 animate-spin text-stone-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    <span>Comprimiendo y subiendo evidencias...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>

                                <!-- SECCIÓN: Soluciones aplicadas -->
                                <div class="rounded-md border border-stone-200 bg-white px-2.5 py-2 sm:px-3 sm:py-2">
                                    <div class="space-y-2">
                                        <div v-if="equipo.soluciones?.length || (puedeGestionarEvidenciaFinal && puedeAgregarSolucionComplementariaEquipo(equipo))">
                                            <div class="mb-1 flex items-center justify-between gap-2">
                                                <p class="text-[11px] text-stone-500 font-semibold uppercase tracking-wide">Soluciones aplicadas</p>
                                                <button
                                                    v-if="puedeGestionarEvidenciaFinal && puedeAgregarSolucionComplementariaEquipo(equipo)"
                                                    @click.stop="abrirModalSolucionesComplementarias(equipo)"
                                                    :class="buttonClass('emerald', 'xs', 'w-auto self-start px-2 py-1 text-[10px] leading-tight sm:px-2.5 sm:py-1.5 sm:text-[11px]')"
                                                >
                                                    <span class="sm:hidden"><b>+</b> Solución complementaria</span>
                                                    <span class="hidden sm:inline">Agregar solución complementaria</span>
                                                </button>
                                            </div>
                                            <ul v-if="equipo.soluciones?.length" class="space-y-1.5">
                                                <li v-for="sol in equipo.soluciones" :key="sol" class="flex items-start gap-2 text-xs font-medium text-stone-800">
                                                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <span class="break-words">{{ sol }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                </div>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>
                <div v-if="historial.length > 0"
                    class="rounded-xl bg-white shadow-sm border border-stone-200 overflow-hidden">
                    <button type="button"
                        @click="historialColapsado = !historialColapsado"
                        class="w-full bg-stone-50 border-b border-stone-200 px-4 py-2.5 flex items-center justify-between hover:bg-stone-100 transition">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-stone-500">Historial</h3>
                        <svg class="h-4 w-4 text-stone-500 transition-transform"
                            :class="historialColapsado ? '' : 'rotate-180'"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div v-show="!historialColapsado" class="px-4 py-3">
                        <div class="relative">
                            <!-- Línea vertical -->
                            <div class="absolute left-2 top-2 bottom-2 w-0.5 bg-stone-100"></div>
                            <div class="space-y-4">
                                <div v-for="(item, index) in historial" :key="index"
                                    class="relative flex gap-3 pl-7">
                                    <!-- Punto -->
                                    <div class="absolute left-0 mt-1 h-4 w-4 rounded-full border-2 border-white shadow-sm"
                                        :class="historialEstadoColor(item.estado)"></div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-sm font-medium text-stone-900">
                                                {{ item.estado }}
                                                <span v-if="item.repuesto" class="text-xs text-stone-500 font-normal">
                                                    — {{ item.repuesto_descripcion || item.repuesto }}
                                                </span>
                                            </p>
                                            <p class="text-xs text-stone-500 shrink-0">{{ item.fecha }}</p>
                                        </div>
                                        <p v-if="item.observaciones" class="text-xs text-stone-500 mt-0.5">
                                            {{ item.observaciones }}
                                        </p>
                                        <p v-if="item.usuario" class="text-xs text-stone-500">
                                            {{ item.usuario }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Sección: Firma del cliente ── -->
<div v-if="completado" class="rounded-xl bg-white shadow-sm border border-stone-200 overflow-hidden">
    <div class="bg-stone-50 border-b border-stone-200 px-4 py-2.5 flex items-center justify-between">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-stone-500">Firma del cliente</h3>
        <span v-if="completado && visita.tiene_firma"
            class="inline-flex items-center gap-1 text-xs text-green-600 font-medium">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Firmado
        </span>
    </div>
    <div class="px-4 py-4">

        <!-- Visita completada — mostrar firma -->
        <div v-if="completado && visita.firma_url">
            <div class="rounded-xl border border-stone-200 bg-stone-50 p-3 flex items-center justify-center">
                <img :src="visita.firma_url"
                    class="max-h-32 w-auto rounded-lg cursor-pointer"
                    @click="abrirFotoAmpliada(visita.firma_url)" />
            </div>
            <p class="mt-2 text-xs text-stone-500 text-center">Firma registrada el {{ visita.fecha_fin ?? visita.fecha_inicio }}</p>
        </div>

        <!-- Visita completada sin firma -->
        <div v-else-if="completado && !visita.firma_url"
            class="rounded-xl border border-dashed border-stone-200 bg-stone-50 py-6 text-center">
            <p class="text-sm text-stone-500">Sin firma registrada</p>
        </div>
    </div>
</div>

                <!-- ── Sección 6: Acciones ── -->
                <div v-if="!completado" class="flex gap-3">
                    <a :href="route('visitastecnicas.visitas.index')"
                        :class="buttonClass('neutral', 'lg', 'flex-1 text-center')">
                        Volver
                    </a>
                    <button v-if="puedeGuardarBorrador"
                        @click="guardarBorrador"
                        :class="buttonClass('primary', 'lg', 'flex-1')">
                        Guardar
                    </button>
                    <button v-if="puedeSolicitarCotizacion"
                        @click="abrirModalCotizacion"
                        :class="buttonClass('warning', 'lg', 'flex-1')">
                        Solicitar Cotización al Asesor
                    </button>
                    <button v-if="puedeFinalizar"
                        @click="abrirModalFinalizar"
                        :class="buttonClass('success', 'lg', 'flex-1')">
                        Finalizar
                    </button>
                </div>
                <div v-else class="flex flex-col gap-3 sm:flex-row">
                    <a :href="route('visitastecnicas.visitas.index')"
                        :class="buttonClass('neutral', 'lg', 'flex-1 text-center')">
                        Volver al listado
                    </a>
                    <button
                        @click="descargarInforme"
                        :class="buttonClass('primary', 'lg', 'flex-1')">
                        Descargar informe
                    </button>
                    <button
                        @click="abrirModalReenviarInforme"
                        :class="buttonClass('emerald', 'lg', 'flex-1')">
                        Reenviar informe
                    </button>
                </div>

            </div>
        </div>

        <!-- Modal Agregar Equipo con Repuestos integrado -->
        <div v-if="modalEquipo" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-2 pt-[calc(0.5rem+env(safe-area-inset-top))] pb-[calc(0.5rem+env(safe-area-inset-bottom))] backdrop-blur-[2px] sm:p-4">
            <div class="flex h-[95dvh] max-h-[95dvh] w-full max-w-2xl flex-col overflow-hidden rounded-[24px] border border-slate-200 bg-slate-50 shadow-[0_24px_80px_-24px_rgba(15,23,42,0.45)] sm:h-auto sm:max-h-[96vh] sm:rounded-[30px]">
                <div class="shrink-0 border-b border-slate-200 bg-white px-3 py-3 sm:px-5 sm:py-3.5">
                    <h3 class="text-base font-semibold text-slate-900 sm:text-lg">
                        {{ equipoEditandoId ? 'Editar Equipo' : 'Agregar Equipo' }}
                    </h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500 sm:text-sm">Completa los datos principales y agrega repuestos solo si la solución lo requiere.</p>
                    <div class="mt-2.5 sm:mt-3">
                        <div class="flex items-center gap-1.5 sm:gap-3">
                            <div
                                v-for="(paso, index) in pasosStepperEquipo"
                                :key="paso.id"
                                class="flex min-w-0 items-center gap-2"
                                :class="pasoEquipoActual === paso.id ? 'flex-[1.35]' : 'shrink-0'"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <div
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold transition sm:h-8 sm:w-8 sm:text-xs"
                                        :class="pasoEquipoActual === paso.id
                                            ? 'bg-blue-600 text-white shadow-sm'
                                            : paso.completo
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : paso.condicional
                                                    ? 'border border-dashed border-slate-300 bg-white text-slate-400'
                                                    : 'bg-slate-100 text-slate-500'"
                                    >
                                        {{ paso.id }}
                                    </div>
                                    <div
                                        class="min-w-0"
                                        :class="pasoEquipoActual === paso.id ? 'block' : 'hidden sm:block'"
                                    >
                                        <p
                                            class="truncate text-[10px] font-semibold uppercase tracking-[0.14em] sm:text-xs"
                                            :class="pasoEquipoActual === paso.id ? 'text-blue-700' : paso.completo ? 'text-emerald-700' : 'text-slate-400'"
                                        >
                                            {{ paso.titulo }}
                                        </p>
                                        <p v-if="paso.condicional" class="truncate text-[9px] text-slate-400 sm:text-[10px]">
                                            Si aplica
                                        </p>
                                    </div>
                                </div>
                                <div
                                    v-if="index < pasosStepperEquipo.length - 1"
                                    class="h-px min-w-3 flex-1 rounded-full sm:min-w-6"
                                    :class="paso.completo ? 'bg-emerald-300' : paso.condicional ? 'border-t border-dashed border-slate-200 bg-transparent' : 'bg-slate-200'"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-3 py-3 sm:px-5 sm:py-4">
                    <div class="space-y-2.5 sm:space-y-3">
                        <!-- EQUIPO FORM -->
                        <section v-if="pasoEquipoActual === 1" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm shadow-slate-200/50 sm:p-3.5">
                            <div class="space-y-1">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Equipo</p>
                                <label class="block text-xs font-medium text-slate-700">Buscar Equipo <span class="text-red-500">*</span></label>
                            </div>
                            <div class="relative">
                                <input v-model="herramientasBusqueda" type="text" placeholder="Buscar por CodMax, Nombre, Ref o Cod Comodidad"
                                    @input="buscarHerramientas"
                                    @keydown.enter.prevent="buscarHerramientas"
                                    class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 pr-14 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#C8102E] focus:ring-4 focus:ring-red-100" />
                                    <button type="button" @click="buscarHerramientas" aria-label="Buscar equipo"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 shadow-sm z-20">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                                        </svg>
                                    </button>
                                </div>
                            <div v-if="herramientasCargando" class="mt-2 flex items-center justify-center rounded-2xl border border-slate-200 bg-white py-6 shadow-sm">
                                <svg class="h-5 w-5 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </div>
                            <div v-else-if="herramientasResultados.length > 0" class="max-h-48 overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                                <div v-for="item in herramientasResultados" :key="item.codigo"
                                    @click="seleccionarHerramienta(item)"
                                    class="cursor-pointer border-b border-slate-100 px-4 py-2.5 transition hover:bg-slate-50 last:border-b-0">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ item.descripcion }}</p>
                                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500">
                                                <span>Cod. {{ item.codigo }}</span>
                                                <span v-if="item.referencia">Ref. {{ item.referencia }}</span>
                                                <span v-if="item.proveedor">Comod. {{ item.proveedor }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else-if="herramientasBuscada && herramientasResultados.length === 0" class="mt-2 rounded-2xl border border-slate-200 bg-white py-3 px-4 text-sm text-slate-500">
                                No hay resultados de tu búsqueda
                            </div>
                            <div v-if="herramientaSeleccionada" class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-2.5">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-blue-600">Seleccionado</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ herramientaSeleccionada.descripcion }}</p>
                                <p class="mt-1 text-xs text-slate-600">Cod. {{ herramientaSeleccionada.codigo }}</p>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-slate-700">Serial <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input
                                            v-model="formEquipo.serial"
                                            type="text"
                                            placeholder="Serial del equipo"
                                            @focus="manejarFocusSerial"
                                            @input="serialesSugeridosAbierto = true"
                                            @blur="manejarBlurSerial"
                                            class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#C8102E] focus:ring-4 focus:ring-red-100" />
                                        <div v-if="serialesSugeridosAbierto && (serialesCargando || serialesSugeridos.length > 0)" class="absolute z-20 mt-1 max-h-44 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                                            <div v-if="serialesCargando" class="px-3 py-2 text-xs text-slate-500">Buscando seriales previos...</div>
                                            <button v-else v-for="serial in serialesSugeridos" :key="serial" type="button" @mousedown.prevent="seleccionarSerialSugerido(serial)" class="block w-full border-b border-slate-100 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50 last:border-b-0">
                                                {{ serial }}
                                            </button>
                                        </div>
                                    </div>
                                    <p v-if="formEquipo.errors.serial" class="mt-1.5 text-xs text-red-600">
                                        {{ formEquipo.errors.serial }}
                                    </p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-slate-700">Tipo de mantenimiento <span class="text-red-500">*</span></label>
                                    <select
                                        v-model="formEquipo.id_tipo_mant"
                                        class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-[#C8102E] focus:ring-4 focus:ring-red-100">
                                        <option value="">Selecciona una opcion</option>
                                        <option v-for="tipo in tiposMantOrdenados" :key="tipo.ID" :value="tipo.ID">
                                            {{ tipo.TIPO_MANT }}
                                        </option>
                                    </select>
                                    <p v-if="formEquipo.errors.id_tipo_mant" class="mt-1.5 text-xs text-red-600">
                                        {{ formEquipo.errors.id_tipo_mant }}
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-2 rounded-2xl border border-slate-200 bg-slate-50 p-3.5">
                                <div class="flex items-center justify-between">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">
                                        Evidencia inicial <span class="font-normal lowercase tracking-normal text-slate-400">(opcional)</span>
                                    </p>
                                    <span v-if="fotosAntesEquipo.length" class="rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-800">
                                        {{ fotosAntesEquipo.length }}
                                    </span>
                                </div>
                                <div v-if="fotosAntesEquipo.length" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    <div v-for="(foto, idx) in fotosAntesEquipo" :key="'antes-modal-' + idx"
                                        class="group relative rounded-xl border border-slate-200 bg-white p-1.5">
                                        <img :src="foto.url" :alt="'Evidencia ' + (idx + 1)" class="h-20 w-full rounded-lg object-cover" />
                                        <button type="button" @click="eliminarFotoAntes(idx)"
                                            class="absolute right-1 top-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-red-600/90 text-white shadow hover:bg-red-600"
                                            aria-label="Eliminar foto">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <label class="flex items-center justify-center gap-2 w-full rounded-xl border border-dashed border-slate-300 bg-white py-2.5 px-3 cursor-pointer hover:bg-slate-50 transition">
                                    <svg class="h-4 w-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span class="text-xs font-medium text-slate-700">Seleccionar fotos de evidencia</span>
                                    <input type="file" accept="image/*" multiple class="hidden" @change="seleccionarFotoAntes" />
                                </label>
                                <div v-if="comprimiendoFotosAntes" class="mt-2 flex items-center gap-2 rounded border border-slate-200 bg-white px-3 py-2 text-[11px] text-slate-600">
                                    <svg class="h-4 w-4 animate-spin text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <span>Comprimiendo imagenes...</span>
                                </div>
                            </div>
                        </section>

                        <section v-if="pasoEquipoActual === 2" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm shadow-slate-200/50 sm:p-3.5">
                            <div class="space-y-2.5 rounded-2xl border border-slate-200 bg-slate-50 p-3.5">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Fallas identificadas</p>
                                    <p class="mt-1 text-xs text-slate-500">Selecciona una o varias fallas.</p>
                                </div>
                                <div class="relative">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-left text-sm text-slate-700 shadow-sm transition hover:border-slate-400 focus:border-[#C8102E] focus:outline-none focus:ring-4 focus:ring-red-100"
                                        @click="fallasDropdownAbierto = !fallasDropdownAbierto"
                                        @keydown.escape="fallasDropdownAbierto = false"
                                    >
                                        <span>{{ textoResumenFallas }}</span>
                                        <svg class="h-4 w-4 text-slate-400 transition" :class="fallasDropdownAbierto ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>

                                    <div
                                        v-if="fallasDropdownAbierto"
                                        class="absolute left-0 right-0 z-30 mt-1 overflow-hidden rounded-xl border border-slate-300 bg-white shadow-xl shadow-slate-900/10"
                                    >
                                        <div class="border-b border-slate-200 p-2">
                                            <input
                                                v-model="fallasBusqueda"
                                                type="text"
                                                placeholder="Buscar..."
                                                class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#C8102E] focus:ring-2 focus:ring-red-100"
                                            />
                                        </div>
                                        <div class="max-h-56 overflow-y-auto py-1">
                                            <label
                                                v-for="falla in tiposFallaFiltradas"
                                                :key="falla.ID"
                                                class="flex cursor-pointer items-center gap-2 border-b border-slate-100 px-3 py-2 text-sm font-medium text-slate-800 transition last:border-b-0 hover:bg-slate-50"
                                            >
                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                                    :checked="fallaSeleccionada(falla.ID)"
                                                    @change="toggleFalla(falla.ID)"
                                                />
                                                <span>{{ falla.DESCRIPCION }}</span>
                                            </label>

                                            <p v-if="tiposFallaFiltradas.length === 0" class="px-3 py-2 text-xs text-slate-500">
                                                No hay fallas configuradas para esta busqueda.
                                            </p>

                                        </div>
                                    </div>
                                </div>

                                <div v-if="totalFallasSeleccionadas > 0" class="flex flex-wrap gap-2">
                                    <span
                                        v-for="falla in fallasSeleccionadasDetalle"
                                        :key="`falla-${falla.ID}`"
                                        class="inline-flex max-w-full items-center gap-2 rounded-full border border-blue-200 bg-blue-50 py-1 pl-3 pr-1 text-xs font-medium text-blue-700"
                                    >
                                        <span class="truncate">{{ falla.DESCRIPCION }}</span>
                                        <button
                                            type="button"
                                            class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-blue-200 bg-blue-100 text-blue-800 transition hover:bg-blue-200"
                                            @click="quitarFalla(falla.ID)"
                                            aria-label="Quitar falla"
                                        >
                                            x
                                        </button>
                                    </span>
                                </div>

                                <!-- Campo de texto para "Otros" (ID 34) -->
                                <div v-if="fallaOtrosSeleccionada" class="mt-2">
                                    <label class="mb-1.5 block text-xs font-medium text-slate-700">
                                        Describe la falla... <span class="text-red-500">*</span>
                                    </label>
                                    <textarea
                                        v-model="descripcionOtros"
                                        placeholder="Describe la falla..."
                                        rows="3"
                                        class="block w-full resize-none rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#C8102E] focus:ring-4 focus:ring-red-100"
                                    ></textarea>
                                    <p v-if="formEquipo.errors.descripcion_otros" class="mt-1.5 text-xs text-red-600">
                                        {{ formEquipo.errors.descripcion_otros }}
                                    </p>
                                </div>

                                <p v-if="formEquipo.errors.id_tipo_falla" class="mt-1.5 text-xs text-red-600">
                                    {{ formEquipo.errors.id_tipo_falla }}
                                </p>
                            </div>
                            <div class="space-y-2.5 rounded-2xl border border-slate-200 bg-slate-50 p-3.5">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Tipo de solucion</p>
                                    <p class="mt-1 text-xs text-slate-500">Selecciona la accion realizada sobre el equipo.</p>
                                </div>
                                <div class="relative">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-left text-sm text-slate-700 shadow-sm transition hover:border-slate-400 focus:border-[#C8102E] focus:outline-none focus:ring-4 focus:ring-red-100"
                                        @click="solucionesDropdownAbierto = !solucionesDropdownAbierto"
                                        @keydown.escape="solucionesDropdownAbierto = false"
                                    >
                                        <span>{{ textoResumenSoluciones }}</span>
                                        <svg class="h-4 w-4 text-slate-400 transition" :class="solucionesDropdownAbierto ? `rotate-180` : ``" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>

                                    <div
                                        v-if="solucionesDropdownAbierto"
                                        class="absolute left-0 right-0 z-30 mt-1 overflow-hidden rounded-xl border border-slate-300 bg-white shadow-xl shadow-slate-900/10"
                                    >
                                        <div class="border-b border-slate-200 p-2">
                                            <input
                                                v-model="solucionesBusqueda"
                                                type="text"
                                                placeholder="Buscar..."
                                                class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#C8102E] focus:ring-2 focus:ring-red-100"
                                            />
                                        </div>
                                        <div class="max-h-56 overflow-y-auto py-1">
                                            <label
                                                v-for="s in tiposSolucionFiltrados"
                                                :key="s.ID"
                                                class="flex cursor-pointer items-center gap-2 border-b border-slate-100 px-3 py-2 text-sm font-medium text-slate-800 transition last:border-b-0 hover:bg-slate-50"
                                                :class="solucionBloqueada(s.ID) ? `opacity-45 cursor-not-allowed` : ``"
                                            >
                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 disabled:cursor-not-allowed"
                                                    :checked="solucionSeleccionada(s.ID)"
                                                    :disabled="solucionBloqueada(s.ID)"
                                                    @change="toggleSolucion(s.ID)"
                                                />
                                                <span>{{ s.TIPO_SOLUCION }}</span>
                                            </label>

                                            <p v-if="tiposSolucionFiltrados.length === 0" class="px-3 py-2 text-xs text-slate-500">
                                                No hay soluciones configuradas para esta busqueda.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="formEquipo.id_solucion.length > 0" class="flex flex-wrap gap-2">
                                    <span
                                        v-for="solucion in solucionesSeleccionadasDetalle"
                                        :key="`solucion-chip-${solucion.ID}`"
                                        class="inline-flex max-w-full items-center gap-2 rounded-full border border-blue-200 bg-blue-50 py-1 pl-3 pr-1 text-xs font-medium text-blue-700"
                                    >
                                        <span class="truncate">{{ solucion.TIPO_SOLUCION }}</span>
                                        <button
                                            type="button"
                                            class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-blue-200 bg-blue-100 text-blue-800 transition hover:bg-blue-200"
                                            @click="quitarSolucion(solucion.ID)"
                                            aria-label="Quitar solucion"
                                        >
                                            x
                                        </button>
                                    </span>
                                </div>

                                <p v-if="formEquipo.errors.id_solucion" class="mt-1.5 text-xs text-red-600">
                                    {{ formEquipo.errors.id_solucion }}
                                </p>
                            </div>

                            <div
                                v-if="equipoEditandoId && requiereRepuestos(formEquipo.id_solucion)"
                                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800"
                            >
                                Los repuestos de este equipo se gestionan desde el boton <span class="font-semibold">Repuesto</span>.
                            </div>

                            <div class="space-y-1.5 rounded-2xl border border-slate-200 bg-slate-50 p-3.5">
                                <label class="block text-xs font-medium text-slate-700">Observaciones</label>
                                <textarea v-model="formEquipo.observaciones" rows="2"
                                    class="block w-full resize-none rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-[#C8102E] focus:ring-4 focus:ring-red-100"></textarea>
                            </div>
                        </section>

                        <!-- Repuestos requeridos -->
                        <section v-if="pasoEquipoActual === 3 && requiereRepuestos(formEquipo.id_solucion)" class="space-y-3 rounded-2xl border border-blue-200 bg-blue-50/60 p-3 shadow-sm shadow-blue-100/50 sm:p-3.5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-900">Repuestos requeridos</h4>
                                    <p class="mt-1 text-xs text-slate-600">
                                        Debes agregar al menos un repuesto para guardar este equipo.
                                    </p>
                                </div>
                                <span class="rounded-full border border-blue-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-blue-700">
                                    {{ repuestosTemporal.length }} agregado<span v-if="repuestosTemporal.length !== 1">s</span>
                                </span>
                            </div>

                            <p v-if="formEquipo.errors.repuestos" class="text-xs text-red-600">
                                {{ formEquipo.errors.repuestos }}
                            </p>

                            <div class="space-y-3">
                                <div ref="repuestoEditorRef">
                                    <label class="mb-1.5 block text-xs font-medium text-slate-700">Buscar repuesto</label>
                                    <div class="relative">
                                        <input v-model="repuestosBusqueda" @input="buscarRepuestos" type="text" placeholder="Codigo, descripcion, referencia o codigo de comodidad..."
                                            class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 pr-14 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#C8102E] focus:ring-4 focus:ring-red-100" />
                                        <button type="button" @click="buscarRepuestos" aria-label="Buscar repuesto"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 shadow-sm z-20">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                                            </svg>
                                        </button>
                                        </div>
                                    <div v-if="repuestosCargando" class="mt-2 flex items-center justify-center rounded-2xl border border-slate-200 bg-white py-6 shadow-sm">
                                        <svg class="h-5 w-5 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                    </div>
                                    <div v-else-if="repuestosResultados.length > 0" class="mt-2 max-h-56 overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                                        <div v-for="item in repuestosResultados" :key="item.codigo"
                                            @click="seleccionarRepuesto(item)"
                                            class="cursor-pointer border-b border-slate-100 px-4 py-2.5 transition hover:bg-slate-50 last:border-b-0">
                                            <div class="flex items-start justify-between gap-3">
                                         <div class="min-w-0 flex-1">
                                             <p class="truncate text-sm font-semibold text-slate-900">{{ item.descripcion }}</p>
                                             <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500">
                                                 <span>Cod. {{ item.codigo }}</span>
                                                 <span v-if="item.referencia">Ref. {{ item.referencia }}</span>
                                                 <span v-if="item.proveedor">Comod. {{ item.proveedor }}</span>
                                                 <span v-if="item.bodega">Bodega {{ item.bodega }}</span>
                                             </div>
                                         </div>
                                                
                                                <div class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="claseStock(item.inventario)">
                                                    {{ textoStock(item.inventario) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else-if="repuestosBuscada && repuestosResultados.length === 0" class="mt-2 rounded-2xl border border-slate-200 bg-white py-3 px-4 text-sm text-slate-500">
                                        No hay resultados de tu búsqueda
                                    </div>
                                </div>
                                <div v-if="repuestoSeleccionado" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs text-emerald-800">
                                    Inventario disponible: <span class="font-semibold">{{ formatearInventario(repuestoSeleccionado.inventario) }}</span>
                                </div>

                                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-[120px_minmax(0,1fr)]">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-medium text-slate-700">Cantidad</label>
                                        <input v-model.number="formRepuesto.cantidad" type="number" min="1"
                                            class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-[#C8102E] focus:ring-4 focus:ring-red-100" />
                                        <p v-if="formRepuesto.errors.cantidad" class="mt-1.5 text-xs text-red-600">
                                            {{ formRepuesto.errors.cantidad }}
                                        </p>
                                    </div>
                                </div>

                                <label class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-900">
                                    <input
                                        v-model="formRepuesto.resolver_en_campo"
                                        @change="activarResueltoEnCampo"
                                        :disabled="formRepuesto.es_urgente"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:bg-emerald-100"
                                    />
                                    <span>
                                        Marcar como resuelto en campo.
                                        <span class="block text-emerald-700">El repuesto quedará instalado y no pasará por cotización.</span>
                                    </span>
                                </label>

                                <label class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-900">
                                    <input
                                        v-model="formRepuesto.es_urgente"
                                        @change="activarUrgente"
                                        :disabled="formRepuesto.resolver_en_campo"
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 rounded border-red-300 text-red-600 focus:ring-red-500 disabled:cursor-not-allowed disabled:bg-red-100"
                                    />
                                    <span>
                                        Marcar como urgente.
                                        <span class="block text-red-700">Este repuesto será prioritario.</span>
                                    </span>
                                </label>

                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-slate-700">
                                        Observacion
                                        <span v-if="formRepuesto.resolver_en_campo" class="text-red-500">*</span>
                                        <span v-else>(opcional)</span>
                                    </label>
                                    <textarea v-model="formRepuesto.observacion" rows="1" placeholder="Nota adicional..."
                                        class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-[#C8102E] focus:ring-4 focus:ring-red-100"></textarea>
                                    <p v-if="formRepuesto.errors.observacion" class="mt-1.5 text-xs text-red-600">
                                        {{ formRepuesto.errors.observacion }}
                                    </p>
                                </div>

                                <button @click="agregarRepuestoTemporal" :disabled="!repuestoSeleccionado || Number(formRepuesto.cantidad) <= 0 || (formRepuesto.resolver_en_campo && !String(formRepuesto.observacion || '').trim())"
                                    :class="buttonClass('success', 'lg', 'w-full')">
                                    {{ repuestoTemporalEditandoIndex !== null ? 'Actualizar repuesto' : '+ Agregar Repuesto' }}
                                </button>

                                <button
                                    v-if="repuestoTemporalEditandoIndex !== null"
                                    @click="limpiarFormularioRepuestoTemporal"
                                    :class="buttonClass('neutral', 'md', 'w-full')"
                                >
                                    Cancelar edición
                                </button>
                            </div>

                            <div v-if="repuestosTemporal.length > 0" class="rounded-2xl border border-slate-200 bg-white p-2.5 shadow-sm">
                                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Repuestos agregados</p>
                                <div class="max-h-56 space-y-2 overflow-y-auto pr-1">
                                            <div v-for="(r, idx) in repuestosTemporal" :key="idx" class="flex items-center justify-between rounded-xl border bg-slate-50 p-3 text-xs" :class="r.es_urgente ? 'border-red-300 bg-red-50' : 'border-slate-200'">
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <p class="font-medium text-slate-900">{{ r.descripcion }}</p>
                                                        <span v-if="r.es_urgente" class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">URGENTE</span>
                                                    </div>
                                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500">
                                                        <span>Cod. {{ r.codigo }}</span>
                                                        <span>Cantidad: {{ r.cantidad }}</span>
                                                    </div>
                                                    <p class="mt-1 text-emerald-700">Stock: {{ formatearInventario(r.inventario) }}</p>
                                                    <p v-if="r.resolver_en_campo" class="mt-1 text-emerald-700">Resuelto en campo</p>
                                                </div>
                                        <div class="ml-2 flex shrink-0 items-center gap-2">
                                            <button
                                                @click="editarRepuestoTemporal(idx)"
                                                :class="buttonClass('primary', 'xs', 'h-8 w-8 px-0 py-0 shadow-sm ring-1 ring-blue-200')"
                                                title="Editar repuesto"
                                                aria-label="Editar repuesto"
                                            >
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L12 15l-4 1 1-4 8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="eliminarRepuestoTemporal(idx)"
                                                :class="buttonClass('danger', 'xs', 'h-8 w-8 px-0 py-0 shadow-sm')"
                                                title="Eliminar repuesto"
                                                aria-label="Eliminar repuesto"
                                            >
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                    </div>
                </div>

                <div class="shrink-0 border-t border-slate-200 bg-white px-3 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] sm:px-5 sm:py-3.5">
                    <p v-if="mensajeGuardarEquipo" class="mb-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600 sm:mb-2.5 sm:px-4 sm:py-2.5">
                        {{ mensajeGuardarEquipo }}
                    </p>
                    <div class="flex flex-wrap gap-2 sm:flex-row sm:gap-3">
                        <button @click="modalEquipo = false"
                            :class="buttonClass('neutral', 'lg', 'flex-1 basis-[calc(50%-0.25rem)] sm:flex-1')">
                            Cancelar
                        </button>
                        <button
                            v-if="pasoEquipoActual > 1"
                            @click="irPasoEquipoAnterior"
                            :class="buttonClass('neutral', 'lg', 'flex-1 basis-[calc(50%-0.25rem)] sm:flex-1')"
                        >
                            Atrás
                        </button>
                        <button
                            v-if="!esUltimoPasoEquipo"
                            @click="irPasoEquipoSiguiente"
                            :disabled="!puedeAvanzarPasoEquipo"
                            :class="buttonClass('primary', 'lg', 'flex-1 basis-[calc(50%-0.25rem)] sm:flex-1')"
                        >
                            Continuar
                        </button>
                        <button
                            v-else
                            @click="guardarEquipo"
                            :disabled="formEquipo.processing || !puedeGuardarEquipo"
                            :class="buttonClass('primary', 'lg', 'flex-1 basis-[calc(50%-0.25rem)] sm:flex-1')"
                        >
                            {{ formEquipo.processing ? (equipoEditandoId ? 'Actualizando...' : 'Guardando...') : (equipoEditandoId ? 'Actualizar' : 'Guardar') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Agregar Repuesto a Equipo existente -->
        <div v-if="modalRepuesto" class="fixed inset-0 z-50 flex items-start justify-center bg-slate-950/45 p-3 pt-5 backdrop-blur-[2px] sm:items-center sm:p-4">
            <div class="flex max-h-[78svh] w-full max-w-md flex-col overflow-hidden rounded-[30px] border border-slate-200 bg-slate-50 shadow-[0_24px_80px_-24px_rgba(15,23,42,0.45)] sm:max-h-[85vh]">
                <div class="border-b border-slate-200 bg-white px-4 py-4 sm:px-5">
                    <h3 class="text-base font-semibold text-slate-900">{{ repuestoEditandoId ? 'Editar Repuesto' : 'Agregar Repuesto' }}</h3>
                </div>

                <div class="overflow-y-auto px-4 py-4 sm:px-5">
                <div class="mb-3 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Equipo</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ equipoSeleccionado?.id_cod_max }}</p>
                    <p v-if="equiposConDescripcion[equipoSeleccionado?.id_cod_max]?.descripcion" class="mt-1 text-xs text-slate-500">{{ equiposConDescripcion[equipoSeleccionado?.id_cod_max].descripcion }}</p>
                    <p v-if="equiposConDescripcion[equipoSeleccionado?.id_cod_max]?.codigo_proveedor" class="mt-1 text-xs text-blue-600">Cod. Comodidad: {{ equiposConDescripcion[equipoSeleccionado?.id_cod_max].codigo_proveedor }}</p>
                </div>

                <div class="space-y-3">
                    <div class="relative">
                        <label class="mb-1.5 block text-xs font-medium text-slate-700">{{ repuestoEditandoId ? 'Repuesto' : 'Buscar repuesto' }}</label>
                        <div class="relative">
                            <input v-model="repuestosBusqueda" @input="buscarRepuestos" type="text" placeholder="Codigo, descripcion, referencia o codigo de comodidad..."
                                :disabled="!!repuestoEditandoId"
                                class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 pr-14 text-sm text-slate-800 placeholder:text-slate-400 focus:border-[#C8102E] focus:ring-4 focus:ring-red-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500" />
                            <button v-if="!repuestoEditandoId" type="button" @click="buscarRepuestos" aria-label="Buscar repuesto"
                                class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 shadow-sm z-20">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                                </svg>
                            </button>
                            </div>
                            <div v-if="repuestoEditandoId">
                                <!-- when editing, don't show results -->
                            </div>
                            <div v-else-if="repuestosCargando" class="mt-2 flex items-center justify-center rounded-2xl border border-slate-200 bg-white py-6 shadow-sm">
                                <svg class="h-5 w-5 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </div>
                            <div v-else-if="!repuestoEditandoId && repuestosResultados.length > 0" class="mt-2 max-h-56 overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                                <div v-for="item in repuestosResultados" :key="item.codigo"
                                    @click="seleccionarRepuesto(item)"
                                    class="cursor-pointer border-b border-slate-100 px-4 py-2.5 transition hover:bg-slate-50 last:border-b-0">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ item.descripcion }}</p>
                                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500">
                                                <span>Cod. {{ item.codigo }}</span>
                                                <span v-if="item.referencia">Ref. {{ item.referencia }}</span>
                                                <span v-if="item.proveedor">Comod. {{ item.proveedor }}</span>
                                                <span v-if="item.bodega">Bodega {{ item.bodega }}</span>
                                            </div>
                                        </div>
                                        <div class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="claseStock(item.inventario)">
                                            {{ textoStock(item.inventario) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else-if="repuestosBuscada && repuestosResultados.length === 0" class="mt-2 rounded-2xl border border-slate-200 bg-white py-3 px-4 text-sm text-slate-500">
                                No hay resultados de tu búsqueda
                            </div>
                    </div>
                    <div v-if="repuestoSeleccionado" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs text-emerald-800">
                        Inventario disponible: <span class="font-semibold">{{ formatearInventario(repuestoSeleccionado.inventario) }}</span>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-slate-700">Cantidad</label>
                        <input v-model.number="formRepuesto.cantidad" type="number" min="1"
                            class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-[#C8102E] focus:ring-4 focus:ring-red-100" />
                    </div>
                    
                    <label class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-900">
                        <input
                            v-model="formRepuesto.resolver_en_campo"
                            @change="activarResueltoEnCampo"
                            :disabled="formRepuesto.es_urgente"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:bg-emerald-100"
                        />
                        <span>
                            Marcar como resuelto en campo.
                            <span class="block text-emerald-700">El repuesto quedará instalado y no pasará por cotización.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-900">
                        <input
                            v-model="formRepuesto.es_urgente"
                            @change="activarUrgente"
                            :disabled="formRepuesto.resolver_en_campo"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-red-300 text-red-600 focus:ring-red-500 disabled:cursor-not-allowed disabled:bg-red-100"
                        />
                        <span>
                            Marcar como urgente.
                            <span class="block text-red-700">Este repuesto será prioritario.</span>
                        </span>
                    </label>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-slate-700">
                            Observacion
                            <span v-if="formRepuesto.resolver_en_campo" class="text-red-500">*</span>
                            <span v-else>(opcional)</span>
                        </label>
                        <textarea v-model="formRepuesto.observacion" rows="2" placeholder="Nota adicional..."
                            class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-[#C8102E] focus:ring-4 focus:ring-red-100"></textarea>
                        <p v-if="formRepuesto.errors.observacion" class="mt-1.5 text-xs text-red-600">
                            {{ formRepuesto.errors.observacion }}
                        </p>
                    </div>
                </div>
                </div>

                <div class="border-t border-slate-200 bg-white px-4 py-4 sm:px-5">
                <div class="flex gap-3">
                    <button @click="modalRepuesto = false"
                        :class="buttonClass('neutral', 'md', 'flex-1')">
                        Cancelar
                    </button>
                    <button @click="guardarRepuesto" :disabled="formRepuesto.processing || !repuestoSeleccionado || (formRepuesto.resolver_en_campo && !String(formRepuesto.observacion || '').trim())"
                        :class="buttonClass('success', 'md', 'flex-1')">
                        {{ formRepuesto.processing ? 'Guardando...' : (repuestoEditandoId ? 'Actualizar' : 'Agregar') }}
                    </button>
                </div>
                </div>
            </div>
        </div>

        <!-- ── Modal: Finalizar ── -->
        <div v-if="modalFinalizar" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
                <h3 class="mb-2 text-base font-semibold text-stone-900">Finalizar visita</h3>
                <p class="mb-4 text-sm text-stone-500">¿Confirmas que el servicio fue realizado?</p>
                <div v-if="mostrarAlertaFinalizacion && camposFaltantesFinalizacion.length"
                    class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3">
                    <p class="text-sm font-semibold text-red-700">Faltan campos obligatorios para finalizar la visita:</p>
                    <ul class="mt-2 list-disc pl-5 text-sm text-red-600">
                        <li v-for="campo in camposFaltantesFinalizacion" :key="campo">
                            {{ campo }}
                        </li>
                    </ul>
                </div>
                <div v-if="!visita.es_capacitacion" class="mb-4 rounded-xl border border-stone-200 bg-stone-50 p-3 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-stone-700 mb-1">Correo para informe técnico de la visita
</label>
                        <input v-model="formServicio.correo_cliente" type="email"
                            class="block w-full rounded-lg border-stone-300 text-sm py-2.5 focus:border-[#C8102E] focus:ring-red-100" />
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-stone-700 mb-1">Fecha fin <span class="text-red-500">*</span></label>
                            <input v-model="formFinalizar.fecha_fin" type="date" required
                                class="block w-full rounded-lg border-stone-300 text-sm py-2.5 focus:border-[#C8102E] focus:ring-red-100" />
                            <p v-if="formFinalizar.errors.fecha_fin" class="mt-1 text-xs text-red-600">
                                {{ formFinalizar.errors.fecha_fin }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-stone-700 mb-1">Hora fin <span class="text-red-500">*</span></label>
                            <input v-model="formFinalizar.hora_fin" type="time" required
                                class="block w-full rounded-lg border-stone-300 text-sm py-2.5 focus:border-[#C8102E] focus:ring-red-100" />
                            <p v-if="formFinalizar.errors.hora_fin" class="mt-1 text-xs text-red-600">
                                {{ formFinalizar.errors.hora_fin }}
                            </p>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-stone-700 mb-1">Observaciones finales</label>
                    <textarea v-model="formFinalizar.observaciones" rows="3"
                        class="block w-full rounded-lg border-stone-300 text-sm focus:border-[#C8102E] focus:ring-red-100"></textarea>
                </div>
                <div v-if="!visita.es_capacitacion" class="mb-4 mt-4">
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-gray-700">
                            Firma del cliente
                        </label>
                        <button type="button" @click="limpiarFirma"
                            class="text-xs text-gray-400 hover:text-gray-600 underline">
                            Limpiar
                        </button>
                    </div>
                    <canvas ref="canvasFirma"
                        class="block w-full rounded-lg border border-gray-300 bg-white shadow-inner touch-none cursor-crosshair"
                        @pointerdown="iniciarFirma"
                        @pointermove="dibujarFirma"
                        @pointerup="terminarFirma"
                        @pointercancel="terminarFirma"
                        @pointerleave="terminarFirma" />
                    <p class="mt-1 text-xs text-gray-400">Firma opcional</p>
                    <p v-if="formFinalizar.errors.firma" class="mt-1 text-xs text-red-600">
                        {{ formFinalizar.errors.firma }}
                    </p>
                </div>
                <div class="mt-5 flex gap-3">
                    <button @click="modalFinalizar = false"
                        :class="buttonClass('neutral', 'md', 'flex-1')">
                        Cancelar
                    </button>
                    <button @click="finalizar" :disabled="formFinalizar.processing"
                        :class="buttonClass('success', 'md', 'flex-1')">
                        <span v-if="formFinalizar.processing" class="inline-flex items-center justify-center gap-2">
                            <svg class="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            Procesando...
                        </span>
                        <span v-else>Confirmar</span>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="modalGuardadoBorrador" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-stone-900">Los cambios han sido guardados</h3>
                <div class="mt-5">
                    <button
                        @click="modalGuardadoBorrador = false"
                        :class="buttonClass('primary', 'md', 'w-full')"
                    >
                        Entendido
                    </button>
                </div>
            </div>
        </div>

        <div v-if="modalInformeReenviado" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center">
            <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-stone-900">Informe enviado exitosamente</h3>
                <p class="mt-1 text-sm text-stone-500">El informe técnico fue reenviado al correo diligenciado.</p>
                <div class="mt-5">
                    <button
                        @click="modalInformeReenviado = false"
                        :class="buttonClass('primary', 'md', 'w-full')"
                    >
                        Entendido
                    </button>
                </div>
            </div>
        </div>

        <div v-if="modalCotizacionEnviada" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center">
            <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-stone-900">Solicitud enviada exitosamente</h3>
                <p class="mt-1 text-sm text-stone-500">La solicitud de cotización fue enviada al asesor.</p>
                <div class="mt-5">
                    <button
                        @click="modalCotizacionEnviada = false"
                        :class="buttonClass('primary', 'md', 'w-full')"
                    >
                        Entendido
                    </button>
                </div>
            </div>
        </div>

        <div v-if="modalFinalizacionExitosa" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center">
            <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-stone-900">Informe enviado exitosamente</h3>
                <p class="mt-1 text-sm text-stone-500">La visita fue finalizada y el informe técnico se envió correctamente.</p>
                <div class="mt-5">
                    <button
                        @click="modalFinalizacionExitosa = false"
                        :class="buttonClass('primary', 'md', 'w-full')"
                    >
                        Entendido
                    </button>
                </div>
            </div>
        </div>

        <div v-if="modalReenviarInforme" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center">
            <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-stone-900">Reenviar informe técnico</h3>
                <p class="mt-1 text-sm text-stone-500">Ingresa el correo al que deseas enviar nuevamente el informe.</p>

                <div class="mt-4">
                    <label class="mb-1 block text-xs font-medium text-stone-700">Correo destinatario</label>
                    <input
                        v-model="formReenviarInforme.correo"
                        type="email"
                        class="block w-full rounded-lg border-stone-300 text-sm py-2.5 focus:border-[#C8102E] focus:ring-red-100"
                        placeholder="correo@empresa.com"
                    />
                    <p v-if="formReenviarInforme.errors.correo" class="mt-1 text-xs text-red-600">
                        {{ formReenviarInforme.errors.correo }}
                    </p>
                </div>

                <div class="mt-5 flex gap-3">
                    <button
                        @click="modalReenviarInforme = false"
                        :class="buttonClass('neutral', 'md', 'flex-1')"
                        :disabled="formReenviarInforme.processing">
                        Cancelar
                    </button>
                    <button
                        @click="reenviarInforme"
                        :class="buttonClass('emerald', 'md', 'flex-1')"
                        :disabled="formReenviarInforme.processing">
                        {{ formReenviarInforme.processing ? 'Enviando...' : 'Reenviar' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="modalCotizacion" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
                <h3 class="mb-2 text-base font-semibold text-stone-900">Enviar a cotización</h3>
                <p class="mb-4 text-sm text-stone-500">
                    Se confirmarán los equipos y repuestos agregados. Después de esto, la visita quedará en espera de repuestos y ya no podrás editar, agregar o eliminar detalles.
                </p>
                <div v-if="mostrarAlertaCotizacion" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3">
                    <p class="text-sm font-semibold text-red-700">No fue posible enviar la visita a cotización.</p>
                    <p class="mt-1 text-sm text-red-600">Revisa la información registrada de la visita e inténtalo nuevamente.</p>
                </div>
                <div class="mt-5 flex gap-3">
                    <button @click="modalCotizacion = false"
                        :class="buttonClass('neutral', 'md', 'flex-1')">
                        Cancelar
                    </button>
                    <button @click="solicitarCotizacion" :disabled="enviandoCotizacion"
                        :class="buttonClass('warning', 'md', 'flex-1')">
                        {{ enviandoCotizacion ? 'Enviando...' : 'Confirmar envío' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="modalInstalacion" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
                <h3 class="mb-4 text-base font-semibold text-stone-900">Confirmar instalación</h3>

                <!-- Repuesto -->
                <div class="mb-4 rounded-lg bg-blue-50 border border-blue-200 p-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-600">Repuesto a instalar</p>
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-stone-900">{{ repuestoInstalacion?.id_cod_max }}</p>
                            <p v-if="repuestosConDescripcion[repuestoInstalacion?.id_cod_max]?.descripcion" class="text-xs text-stone-600">{{ repuestosConDescripcion[repuestoInstalacion?.id_cod_max].descripcion }}</p>
                            <p v-if="repuestosConDescripcion[repuestoInstalacion?.id_cod_max]?.codigo_proveedor" class="text-xs text-stone-600">Cod Comodidad: {{ repuestosConDescripcion[repuestoInstalacion?.id_cod_max].codigo_proveedor }}</p>
                            <p class="text-xs text-stone-600 mt-1">Cantidad: {{ repuestoInstalacion?.cantidad }}</p>
                        </div>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-stone-700 mb-2">Observaciones de la instalación</label>
                    <textarea
                        v-model="formInstalacion.observacion_instalacion"
                        rows="3"
                        placeholder="Observación sobre la instalación del repuesto..."
                        class="block w-full rounded-lg border-stone-300 text-sm focus:border-[#C8102E] focus:ring-red-100"
                    ></textarea>
                </div>

                <!-- Evidencia Final -->
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-stone-700 mb-2">Evidencia Final</label>
                    <!-- Fotos cargadas -->
                    <div v-if="fotosDespuesInstalacion.length > 0" class="grid grid-cols-3 gap-2 mb-3">
                        <div v-for="(foto, index) in fotosDespuesInstalacion" :key="index" class="relative group">
                            <img :src="foto.url" class="w-full h-24 object-cover rounded-lg border border-stone-200 cursor-pointer hover:opacity-80" @click="abrirFotoAmpliada(foto.url)" />
                            <button type="button"
                                @click="eliminarFotoDespuesInstalacion(index)"
                                :class="buttonClass('danger', 'xs', 'absolute top-1 right-1 hidden h-6 w-6 px-0 py-0 group-hover:flex shadow')">
                                ✕
                            </button>
                        </div>
                    </div>
                    <div v-else class="text-xs text-stone-500 py-1.5 text-center mb-2">
                        Sin evidencia final registrada
                    </div>
                    <!-- Subir fotos -->
                    <label for="inputFotosDespues"
                        class="flex items-center justify-center gap-2 w-full rounded-lg border-2 border-dashed border-stone-300 bg-stone-50 py-4 cursor-pointer transition hover:bg-stone-100">
                        <svg class="h-5 w-5 text-stone-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span class="text-sm text-stone-600">Agregar evidencia</span>
                        <input id="inputFotosDespues" type="file" accept="image/*" multiple class="hidden" @change="agregarFotosDespuesInstalacion" />
                    </label>
                </div>

                <div class="mt-5 flex gap-3">
                    <button @click="modalInstalacion = false"
                        :class="buttonClass('neutral', 'md', 'flex-1')">
                        Cancelar
                    </button>
                    <button @click="confirmarInstalacion" :disabled="formInstalacion.processing"
                        :class="buttonClass('success', 'md', 'flex-1')">
                        {{ formInstalacion.processing ? 'Confirmando...' : 'Confirmar instalación' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="modalSolucionesComplementarias" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center">
            <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
                <div class="mb-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Soluciones complementarias</p>
                    <h3 class="mt-1 text-lg font-semibold text-stone-900">
                        {{ equiposConDescripcion[equipoSeleccionado?.id_cod_max]?.descripcion || equipoSeleccionado?.id_cod_max }}
                    </h3>
                    <p class="mt-1 text-sm text-stone-600">Selecciona las soluciones aplicadas luego de la instalación de los repuestos.</p>
                </div>

                <div class="mb-4 space-y-1.5 rounded-2xl border border-slate-200 bg-slate-50 p-3.5">
                    <label class="block text-xs font-medium text-slate-700">Observaciones del equipo</label>
                    <textarea v-model="equipoObservacionesTemporal" rows="2"
                        placeholder="Observaciones adicionales..."
                        class="block w-full resize-none rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-[#C8102E] focus:ring-4 focus:ring-red-100"></textarea>
                </div>

                <div v-if="tiposSolucionComplementarios.length" class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-3">
                    <div class="space-y-1">
                        <label
                            v-for="solucion in tiposSolucionComplementarios"
                            :key="solucion.ID"
                            class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 transition hover:bg-emerald-100/70"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-stone-300 text-emerald-600 focus:ring-emerald-500"
                                :checked="solucionComplementariaSeleccionada(solucion.ID)"
                                @change="toggleSolucionComplementaria(solucion.ID)"
                            />
                            <span
                                class="text-sm"
                                :class="solucionComplementariaSeleccionada(solucion.ID) ? 'font-semibold text-stone-900' : 'font-medium text-stone-700'"
                            >
                                {{ solucion.TIPO_SOLUCION }}
                            </span>
                        </label>
                    </div>

                    <p class="mt-2 text-[11px] text-stone-500">
                        {{ formInstalacion.soluciones_adicionales.length }} seleccionada<span v-if="formInstalacion.soluciones_adicionales.length !== 1">s</span>
                    </p>
                </div>

                <div v-else class="rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-600">
                    Este equipo ya tiene todas las soluciones complementarias disponibles.
                </div>

                <div class="mt-5 flex gap-3">
                    <button
                        @click="cerrarModalSolucionesComplementarias"
                        :class="buttonClass('neutral', 'md', 'flex-1')"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="guardarSolucionesComplementarias"
                        :disabled="formInstalacion.processing || !formInstalacion.soluciones_adicionales.length"
                        :class="buttonClass('emerald', 'md', 'flex-1')"
                    >
                        {{ formInstalacion.processing ? 'Guardando...' : 'Guardar soluciones' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Visor foto ampliada -->
        <div v-if="fotoAmpliada"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Vista ampliada de imagen"
            @click.self="cerrarFotoAmpliada()">
            <button
                type="button"
                class="absolute left-4 top-4 inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-800 shadow-lg transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-white/70"
                @click.stop="cerrarFotoAmpliada()"
            >
                <span class="text-lg leading-none">×</span>
                Cerrar imagen
            </button>
            <img
                :src="fotoAmpliada"
                class="max-h-full max-w-full rounded-xl shadow-2xl"
                @click.stop
            />
        </div>
    </AppLayout>
</template>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: height 0.32s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.24s ease;
    overflow: hidden;
}
</style>