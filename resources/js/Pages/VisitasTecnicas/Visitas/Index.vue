<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    visitas:        { type: Array,  default: () => [] },
    filtros:        { type: Object, default: () => ({}) },
    estados_visita: { type: Array,  default: () => [] },
    esCapacitacionModulo: { type: Boolean, default: false },
    puedeCrearCapacitacionLibre: { type: Boolean, default: false },
})

const buscar = ref(props.filtros.buscar ?? '')
const ESTADO_PENDIENTE_POR_INICIAR = 0
const ESTADO_EN_PROCESO = 1
const ESTADO_COMPLETADO = 2
const ESTADO_REPROGRAMADO = 3
const ESTADO_CANCELADO = 4
const ESTADO_PENDIENTE_REPUESTOS = 6

const estado = ref(props.filtros.estado ?? String(ESTADO_PENDIENTE_POR_INICIAR))

const estadosPorMostrar = computed(() => props.estados_visita)

const hayFiltrosActivos = computed(() => {
    return buscar.value.trim() !== '' || estado.value !== String(ESTADO_PENDIENTE_POR_INICIAR)
})

const totalVisitasTexto = computed(() => {
    const total = props.visitas.length
    if (total === 0) return ''

    return `${total} ${total === 1 ? 'visita' : 'visitas'}`
})

const estadoEtiqueta = (visita) => {
    if (Number(visita.estado_id) === ESTADO_PENDIENTE_POR_INICIAR) return 'Pendiente'
    return visita.estado ?? 'Pendiente'
}

const estadoColor = (estadoId) => {
    const colores = {
        [ESTADO_EN_PROCESO]: 'bg-blue-100 text-blue-700 border-blue-200',
        [ESTADO_COMPLETADO]: 'bg-green-100 text-green-700 border-green-200',
        [ESTADO_REPROGRAMADO]: 'bg-yellow-100 text-yellow-700 border-yellow-200',
        [ESTADO_CANCELADO]: 'bg-red-100 text-red-700 border-red-200',
        [ESTADO_PENDIENTE_REPUESTOS]: 'bg-orange-100 text-orange-700 border-orange-200',
        [ESTADO_PENDIENTE_POR_INICIAR]: 'bg-gray-100 text-gray-600 border-gray-200',
    }

    return colores[Number(estadoId)] ?? 'bg-gray-100 text-gray-600 border-gray-200'
}

const modalReprogramar = ref(false)
const visitaReprogramar = ref(null)
const formReprogramar = ref({ fecha_reprogramacion: '', motivo: '' })
const procesandoReprogram = ref(false)

const aplicarFiltros = () => {
    router.get(route('visitastecnicas.visitas.index'), {
        buscar: buscar.value,
        estado: estado.value,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

const limpiarFiltros = () => {
    buscar.value = ''
    estado.value = String(ESTADO_PENDIENTE_POR_INICIAR)
    aplicarFiltros()
}

const abrirReprogramar = (visita) => {
    visitaReprogramar.value = visita
    formReprogramar.value.fecha_reprogramacion = ''
    formReprogramar.value.motivo = ''
    modalReprogramar.value = true
}

const confirmarReprogramar = () => {
    if (!formReprogramar.value.fecha_reprogramacion || !formReprogramar.value.motivo) return

    procesandoReprogram.value = true
    router.post(route('visitastecnicas.visitas.reprogramar-ruta', visitaReprogramar.value.id_visita), {
        fecha_reprogramacion: formReprogramar.value.fecha_reprogramacion,
        motivo: formReprogramar.value.motivo,
    }, {
        onSuccess: () => {
            modalReprogramar.value = false
            procesandoReprogram.value = false
        },
        onError: () => { procesandoReprogram.value = false },
    })
}

function formatearFechaNatural(fecha) {
    if (!fecha) return ''

    const [year, month, day] = fecha.split('-').map(Number)
    const date = new Date(year, month - 1, day)
    const texto = new Intl.DateTimeFormat('es-CO', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(date)

    return texto.charAt(0).toUpperCase() + texto.slice(1)
}
</script>

<template>
    <AppLayout title="Visitas Técnicas">
        <Head title="Visitas Técnicas" />
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Mis Visitas Técnicas
                </h2>
                
                <!-- Botón Crear - Solo si tiene permiso -->
                <Link
                v-if="puedeCrearCapacitacionLibre || esCapacitacionModulo"
                :href="route('visitastecnicas.capacitaciones.create-libre')"
                class="ml-2 inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 bg-blue-600 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
            >
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Registrar Capacitación
            </Link>
            </div>
        </template>

        <div class="py-2 sm:py-4">
            <div class="mx-auto max-w-3xl px-3 sm:px-6 lg:px-8">
                <div class="mb-6 rounded-3xl border border-slate-200 bg-white/95 p-3 shadow-sm shadow-slate-200/60 ring-1 ring-slate-100 sm:p-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-2.5 sm:p-3">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_220px]">
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
                                </svg>
                                <input v-model="buscar" type="text" placeholder="Buscar por cliente, NIT, ruta o técnico..."
                                    class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500"
                                    @input="aplicarFiltros" />
                            </div>

                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M6 12h12M10 17h4" />
                                </svg>
                                <select v-model="estado"
                                    class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500"
                                    @change="aplicarFiltros">
                                    <option :value="String(ESTADO_PENDIENTE_POR_INICIAR)">Pendiente por Iniciar</option>
                                    <option value="todos">Todos los estados</option>
                                    <option v-for="e in estadosPorMostrar" :key="e.ID" :value="e.ID">
                                        {{ e.ESTADO }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div v-if="hayFiltrosActivos || totalVisitasTexto" class="mt-2 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <button
                                v-if="hayFiltrosActivos"
                                @click="limpiarFiltros"
                                class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-400 hover:text-slate-800"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                Limpiar filtros
                            </button>
                            <p v-if="totalVisitasTexto" class="text-sm text-slate-500">{{ totalVisitasTexto }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="visitas.length === 0"
                    class="rounded-xl border border-gray-100 bg-white p-12 text-center shadow-sm">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <p class="mt-4 text-sm text-gray-400">No hay visitas con ese filtro.</p>
                </div>

                <div v-else class="space-y-3">
                    <div v-for="visita in visitas" :key="visita.id"
                        class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                        <div class="flex items-start justify-between px-4 pb-2 pt-4">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-base font-semibold text-gray-900">{{ visita.cliente?.nombre }}</p>
                                <p class="mt-0.5 text-xs text-gray-400">NIT: {{ visita.cliente?.nit }}</p>
                            </div>
                            <span class="ml-3 inline-flex shrink-0 items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                :class="estadoColor(visita.estado_id)">
                                {{ estadoEtiqueta(visita) }}
                            </span>
                        </div>

                        <div class="space-y-2 px-4 pb-3">
                            <div class="flex items-start gap-2 text-sm text-gray-600">
                                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="line-clamp-2">{{ visita.direccion ?? '—' }}</span>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-gray-600">
                                <div class="flex items-center gap-1.5">
                                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span>{{ formatearFechaNatural(visita.fecha_visita) || '—' }}</span>
                                </div>

                                <div v-if="visita.tipo_servicio" class="flex items-center gap-1.5">
                                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <span>{{ visita.tipo_servicio }}</span>
                                </div>

                                <div v-if="visita.tecnico" class="flex items-center gap-1.5">
                                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <span>{{ visita.tecnico }}</span>
                                </div>

                                <div v-if="visita.numero_ruta" class="text-xs text-gray-400">
                                    {{ visita.numero_ruta }}
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-50 px-4 py-3">
                            <Link v-if="visita.visita_id && !visita.puede_iniciar"
                                :href="route('visitastecnicas.visitas.show', visita.visita_id)"
                                class="flex w-full items-center justify-center rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-100">
                                Ver visita
                            </Link>

                            <div v-else-if="visita.puede_iniciar" class="flex flex-col gap-2 sm:flex-row">
                                <Link :href="route('visitastecnicas.visitas.create', visita.id_visita)"
                                    class="flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 sm:basis-2/3">
                                    Iniciar visita
                                </Link>
                                <button @click="abrirReprogramar(visita)"
                                    class="flex w-full items-center justify-center rounded-lg border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-200 sm:basis-1/3">
                                    Reprogramar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="modalReprogramar"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center">
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
                <h3 class="mb-1 text-base font-semibold text-gray-900">Reprogramar visita</h3>
                <p class="mb-4 text-xs text-gray-500">
                    {{ visitaReprogramar?.cliente?.nombre }} — {{ visitaReprogramar?.numero_ruta }}
                </p>

                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">
                            Nueva fecha <span class="text-red-500">*</span>
                        </label>
                        <input v-model="formReprogramar.fecha_reprogramacion" type="date"
                            class="block w-full rounded-lg border-gray-300 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">
                            Motivo <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="formReprogramar.motivo" rows="3"
                            placeholder="Motivo de la reprogramación..."
                            class="block w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                    </div>
                </div>

                <div class="mt-5 flex gap-3">
                    <button @click="modalReprogramar = false"
                        class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button @click="confirmarReprogramar"
                        :disabled="!formReprogramar.fecha_reprogramacion || !formReprogramar.motivo || procesandoReprogram"
                        class="flex-1 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ procesandoReprogram ? 'Guardando...' : 'Confirmar' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
