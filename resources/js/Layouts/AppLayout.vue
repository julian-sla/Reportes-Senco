<script setup>
import {onBeforeMount, onMounted, ref} from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome";

defineProps({
    title: String,
});

const showingNavigationDropdown = ref(false);

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};

</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="min-h-screen bg-gray-100">
            <nav class="bg-white border-b border-gray-100">
                <!-- Primary Navigation Menu -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationMark class="block h-9 w-auto" />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    <font-awesome-icon icon="house" class="mr-1.5"/>
                                    Dashboard
                                </NavLink>

                                <NavLink :href="route('report.index')"
                                         :active="route().current('report.index')"
                                         v-permission:any="'report.create|report.edit|report.destroy|report.view|report.index'">
                                    <font-awesome-icon icon="chart-simple" class="mr-2"/>
                                    Reportes
                                </NavLink>
                                <!-- <NavLink :href="route('service-orders.index')" 
                                         :active="route().current('service-orders.*')">
                                    <font-awesome-icon icon="clipboard-list" class="mr-2" />
                                    Órdenes de Servicio
                                </NavLink> -->

                                <template v-if="$page.props.auth.user.permissions?.includes('ver-lista-precios')">
                                    <NavLink :href="route('lista-precios.index')" :active="route().current('lista-precios.*')">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                            </svg>
                                            Lista de Precios
                                        </div>
                                    </NavLink>
                                </template>
                                <!-- <template v-if="$page.props.auth.user.permissions?.some(p => p.startsWith('rutas-tecnicas.'))">
                                    <NavLink :href="route('rutas-tecnicas.create')" :active="route().current('rutas-tecnicas.*')">
                                        <div class="flex items-center">
                                            <font-awesome-icon icon="fa-solid fa-screwdriver-wrench" class="mr-2"/>
                                            Rutas Técnicas
                                        </div>
                                    </NavLink>
                                </template> -->

                                <!-- Dropdown: Rutas Técnicas -->
                                <div class="relative inline-flex items-center px-1 pt-1 border-b-2 border-transparent"
                                     v-if="$page.props.auth.user.permissions?.includes('rutas-tecnicas.crear') || $page.props.auth.user.permissions?.includes('rutas-tecnicas.ver') || $page.props.auth.user.permissions?.includes('visitastecnicas.ver') || $page.props.auth.user.permissions?.includes('visitastecnicas.ver-todos') || $page.props.auth.user.permissions?.includes('visitastecnicas.repuestos.ver') || $page.props.auth.user.permissions?.includes('visitastecnicas.repuestos.ver-todos')">
                                    <Dropdown align="left" width="60">
                                        <template #trigger>
                                            <a href="javascript:void(0)"
                                               class="text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                                                <font-awesome-icon icon="fa-solid fa-screwdriver-wrench" class="mr-2"/>
                                                Rutas Técnicas
                                                <font-awesome-icon icon="chevron-down" class="ml-2"/>
                                            </a>
                                        </template>
                                        <template #content>
                                            <div class="w-48">
                                                <DropdownLink :href="route('rutas-tecnicas.create')"
                                                              v-if="$page.props.auth.user.permissions?.includes('rutas-tecnicas.crear')">
                                                    <font-awesome-icon icon="fa-solid fa-plus" class="mr-2"/>
                                                    Nueva Ruta Técnica
                                                </DropdownLink>
                                                <DropdownLink :href="route('rutas-tecnicas.index')"
                                                              v-if="$page.props.auth.user.permissions?.includes('rutas-tecnicas.ver')">
                                                    <font-awesome-icon icon="fa-solid fa-list" class="mr-2"/>
                                                    Mis Rutas Técnicas
                                                </DropdownLink>
                                                <div class="border-t border-gray-200" />
                                                <DropdownLink :href="route('visitastecnicas.visitas.index')"
                                                              v-if="$page.props.auth.user.permissions?.includes('visitastecnicas.ver') || $page.props.auth.user.permissions?.includes('visitastecnicas.ver-todos')">
                                                    <font-awesome-icon icon="fa-solid fa-toolbox" class="mr-2"/>
                                                    Visitas Técnicas
                                                </DropdownLink>
                                                <DropdownLink :href="route('visitastecnicas.repuestos.index')"
                                                              v-if="$page.props.auth.user.permissions?.includes('visitastecnicas.repuestos.ver') || $page.props.auth.user.permissions?.includes('visitastecnicas.repuestos.ver-todos')">
                                                    <font-awesome-icon icon="fa-solid fa-boxes-stacked" class="mr-2"/>
                                                    Repuestos
                                                </DropdownLink>
                                            </div>
                                        </template>
                                    </Dropdown>
                                </div>



                                <!-- Dropdown: Gestión de Clientes -->
                                <div class="relative inline-flex items-center px-1 pt-1 border-b-2 border-transparent"
                                     v-if="$page.props.auth.user.permissions?.includes('links-zeroOne')">
                                    <Dropdown align="left" width="60">
                                        <template #trigger>
                                            <a href="javascript:void(0)"
                                               class="text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                                                <font-awesome-icon icon="users" class="mr-2"/>
                                                Gestión de Clientes
                                                <font-awesome-icon icon="chevron-down" class="ml-2"/>
                                            </a>
                                        </template>
                                        <template #content>
                                            <div class="w-48">
                                                <!-- Portal ZeroOne -->
                                                <a
                                                    href="https://zeroone.la/login"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="block w-full px-4 py-2 text-left text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                                >
                                                    <font-awesome-icon icon="arrow-up-right-from-square" class="mr-2"/>
                                                    Portal ZeroOne
                                                </a>
                                                <!-- Clientes ZeroOne -->
                                                <a
                                                    href="https://senco.zeroone.la/formulario/623037ff-cbc4-445d-c865-08daa7034d2d?token=9c1a5cfd-c6f9-49e0-2f73-08dbae4dcb03"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="block w-full px-4 py-2 text-left text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                                >
                                                    <font-awesome-icon icon="users" class="mr-2"/>
                                                    Clientes ZeroOne
                                                </a>
                                            </div>
                                        </template>
                                    </Dropdown>
                                </div>

                                <div class="relative inline-flex items-center px-1 pt-1 border-b-2 border-transparent"
                                     v-permission:any="'user.create|user.edit|user.destroy|role.create|role.edit|role.destroy|permission.create|permission.edit|permission.destroy|import-report|report.filter.index|report.filter.edit|report.filter.create|report.filter.destroy'">
                                    <Dropdown align="left" width="60">
                                        <template #trigger>
                                            <a href="javascript:void(0)"
                                               class="text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                                                <font-awesome-icon icon="gears" class="mr-2"/>
                                                Administración
                                                <font-awesome-icon icon="chevron-down" class="ml-2"/>
                                            </a>
                                        </template>

                                        <template #content>
                                            <div class="w-48">
                                                <DropdownLink :href="route('users.index')"
                                                              v-permission:any="'user.create|user.edit|user.destroy'">
                                                    <font-awesome-icon icon="users" class="mr-2"/>
                                                    Usuarios
                                                </DropdownLink>

                                                <DropdownLink :href="route('roles.index')"
                                                              v-permission:any="'role.create|role.edit|role.destroy'">
                                                    <font-awesome-icon icon="lock" class="mr-2"/>
                                                    Roles
                                                </DropdownLink>

                                                <div class="border-t border-gray-200"
                                                     v-permission:any="'import-report'"/>

                                                <DropdownLink :href="route('report.import.index')"
                                                              v-permission="'import-report'">
                                                    <font-awesome-icon icon="download" class="mr-2"/>
                                                    Importar reportes
                                                </DropdownLink>

                                                <DropdownLink :href="route('report.filter.index')"
                                                              v-permission:any="'report.filter.index|report.filter.update|report.filter.store|report.filter.destroy'">
                                                    <font-awesome-icon icon="filter" class="mr-2"/>
                                                    Filtros
                                                </DropdownLink>
                                            </div>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ml-6">
                            <div class="ml-3 relative">
                                <!-- Teams Dropdown -->
                                <Dropdown v-if="$page.props.jetstream.hasTeamFeatures" align="right" width="60">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.current_team.name }}

                                                <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <div class="w-60">
                                            <!-- Team Management -->
                                            <template v-if="$page.props.jetstream.hasTeamFeatures">
                                                <div class="block px-4 py-2 text-xs text-gray-400">
                                                    Manage Team
                                                </div>

                                                <!-- Team Settings -->
                                                <DropdownLink :href="route('teams.show', $page.props.auth.user.current_team)">
                                                    Team Settings
                                                </DropdownLink>

                                                <DropdownLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')">
                                                    Create New Team
                                                </DropdownLink>

                                                <!-- Team Switcher -->
                                                <template v-if="$page.props.auth.user.all_teams.length > 1">
                                                    <div class="border-t border-gray-200" />

                                                    <div class="block px-4 py-2 text-xs text-gray-400">
                                                        Switch Teams
                                                    </div>

                                                    <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                                        <form @submit.prevent="switchToTeam(team)">
                                                            <DropdownLink as="button">
                                                                <div class="flex items-center">
                                                                    <svg v-if="team.id === $page.props.auth.user.current_team_id" class="mr-2 h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                    </svg>

                                                                    <div>{{ team.name }}</div>
                                                                </div>
                                                            </DropdownLink>
                                                        </form>
                                                    </template>
                                                </template>
                                            </template>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="ml-3 relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                            <img class="h-8 w-8 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                        </button>

                                        <span v-else class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.name }}

                                                <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <!-- Account Management -->
                                        <div class="block px-4 py-2 text-xs text-gray-400">
                                            Administrar cuenta
                                        </div>

                                        <DropdownLink :href="route('profile.show')">
                                            <font-awesome-icon icon="user" class="mr-2"/>
                                            Perfil
                                        </DropdownLink>

                                        <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                            <font-awesome-icon icon="lock" class="mr-2"/>
                                            API Tokens
                                        </DropdownLink>

                                        <div class="border-t border-gray-200" />

                                        <!-- Authentication -->
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                <font-awesome-icon icon="right-from-bracket" class="mr-2"/>
                                                Cerrar Sesión
                                            </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-mr-2 flex items-center sm:hidden">
                            <button class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out" @click="showingNavigationDropdown = ! showingNavigationDropdown">
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden">
                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            <font-awesome-icon icon="fa-solid fa-house" class="mr-2"/>
                            Dashboard
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('report.index')"
                                           :active="route().current('report.index')"
                                           v-permission:any="'report.index|report.store|report.update|report.destroy|report.view'">
                            <font-awesome-icon icon="chart-simple" class="mr-2"/>
                            Reportes
                        </ResponsiveNavLink>
                         <!-- <ResponsiveNavLink :href="route('service-orders.index')" 
                           :active="route().current('service-orders.*')">
                            <font-awesome-icon icon="clipboard-list" class="mr-2" />
                        </ResponsiveNavLink> -->
                        <ResponsiveNavLink :href="route('lista-precios.index')"
                                           :active="route().current('lista-precios.*')"
                                           v-permission:any="'ver-lista-precios'">
                            <font-awesome-icon icon="clipboard-list" class="mr-2"/>
                            Lista de Precios
                        </ResponsiveNavLink>

                        <template v-if="$page.props.auth.user.permissions?.some(p => p.startsWith('rutas-tecnicas.'))">
                            <ResponsiveNavLink
                                :href="route('rutas-tecnicas.index')"
                                :active="route().current('rutas-tecnicas.index')"
                            >
                                <font-awesome-icon icon="fa-solid fa-list" class="mr-2"/>
                                Mis Rutas Técnicas
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('rutas-tecnicas.create')"
                                :active="route().current('rutas-tecnicas.create')"
                            >
                                <font-awesome-icon icon="fa-solid fa-plus" class="mr-2"/>
                                Nueva Ruta Técnica
                            </ResponsiveNavLink>
                        </template>

                        <template v-if="$page.props.auth.user.permissions?.includes('visitastecnicas.ver') || $page.props.auth.user.permissions?.includes('visitastecnicas.ver-todos')">
                            <ResponsiveNavLink
                                :href="route('visitastecnicas.visitas.index')"
                                :active="route().current('visitastecnicas.*')"
                                class="pl-8"
                            >
                                <font-awesome-icon icon="fa-solid fa-toolbox" class="mr-2"/>
                                Visitas Técnicas
                            </ResponsiveNavLink>
                        </template>

                        <template v-if="$page.props.auth.user.permissions?.includes('visitastecnicas.repuestos.ver') || $page.props.auth.user.permissions?.includes('visitastecnicas.repuestos.ver-todos')">
                            <ResponsiveNavLink
                                :href="route('visitastecnicas.repuestos.index')"
                                :active="route().current('visitastecnicas.repuestos.*')"
                                class="pl-8"
                            >
                                <font-awesome-icon icon="fa-solid fa-boxes-stacked" class="mr-2"/>
                                Repuestos
                            </ResponsiveNavLink>
                        </template>

                        <template v-if="$page.props.auth.user.permissions?.includes('links-zeroOne')">
                            <a
                                href="https://zeroone.la/login"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block pl-3 pr-4 py-2 border-l-4 border-transparent
                                        text-base font-medium text-gray-600
                                        hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300
                                        focus:outline-none focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300
                                        transition"
                            >
                                <font-awesome-icon icon="arrow-up-right-from-square" class="mr-2"/>
                                Portal ZeroOne
                            </a>
                            <a
                                as ="a"
                                href="https://senco.zeroone.la/formulario/623037ff-cbc4-445d-c865-08daa7034d2d?token=9c1a5cfd-c6f9-49e0-2f73-08dbae4dcb03"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block pl-3 pr-4 py-2 border-l-4 border-transparent
                                        text-base font-medium text-gray-600
                                        hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300
                                        focus:outline-none focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300
                                        transition"
                            >
                                <font-awesome-icon icon="users" class="mr-2"/>
                                Clientes ZeroOne
                            </a>
                        </template>
                        <ResponsiveNavLink :href="route('roles.index')"
                                           :active="route().current('roles.index')"
                                           v-permission:any="'role.index|role.create|role.edit|role.destroy'">
                            <font-awesome-icon icon="lock" class="mr-2"/>
                            Roles
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('users.index')"
                                           :active="route().current('users.index')"
                                           v-permission:any="'user.index|user.create|user.edit|user.destroy'">
                            <font-awesome-icon icon="users" class="mr-2"/>
                            Usuarios
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="pt-4 pb-1 border-t border-gray-200">
                        <div class="flex items-center px-4">
                            <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 mr-3">
                                <img class="h-10 w-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                            </div>

                            <div>
                                <div class="font-medium text-base text-gray-800">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="font-medium text-sm text-gray-500">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')">
                                Perfil
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')" :active="route().current('api-tokens.index')">
                                API Tokens
                            </ResponsiveNavLink>

                            <!-- Authentication -->
                            <form method="POST" @submit.prevent="logout">
                                <ResponsiveNavLink as="button">
                                    Cerrar Sesión
                                </ResponsiveNavLink>
                            </form>

                            <!-- Team Management -->
                            <template v-if="$page.props.jetstream.hasTeamFeatures">
                                <div class="border-t border-gray-200" />

                                <div class="block px-4 py-2 text-xs text-gray-400">
                                    Administrar equipo
                                </div>

                                <!-- Team Settings -->
                                <ResponsiveNavLink :href="route('teams.show', $page.props.auth.user.current_team)" :active="route().current('teams.show')">
                                    Configuración del equipo
                                </ResponsiveNavLink>

                                <ResponsiveNavLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')" :active="route().current('teams.create')">
                                    Crear nuevo equipo
                                </ResponsiveNavLink>

                                <!-- Team Switcher -->
                                <template v-if="$page.props.auth.user.all_teams.length > 1">
                                    <div class="border-t border-gray-200" />

                                    <div class="block px-4 py-2 text-xs text-gray-400">
                                        Cambiar de equipo
                                    </div>

                                    <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                        <form @submit.prevent="switchToTeam(team)">
                                            <ResponsiveNavLink as="button">
                                                <div class="flex items-center">
                                                    <svg v-if="team.id === $page.props.auth.user.current_team_id" class="mr-2 h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <div>{{ team.name }}</div>
                                                </div>
                                            </ResponsiveNavLink>
                                        </form>
                                    </template>
                                </template>
                            </template>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header v-if="$slots.header" class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-4 px-4 sm:px-4 lg:px-8 flex items-center justify-between">
                    <slot name="header"/>
                    <div>
                        <slot name="actions"/>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
