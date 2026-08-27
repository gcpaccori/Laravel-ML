<script setup>
    import { Link, router } from '@inertiajs/vue3';
    import { useThemeMode } from '@/Composables/useThemeMode';
    import AlarmasDropdown from '@/Components/AlarmasDropdown.vue';

    const { themeMode, setThemeMode } = useThemeMode();

    const logout = () => {
        router.post(route('logout'));
    };
</script>

<template>
    <!--begin::Header-->
    <div id="kt_app_header"
        class="app-header"
        data-kt-sticky="true"
        data-kt-sticky-activate="{default: true, lg: true}"
        data-kt-sticky-name="app-header-minimize"
        data-kt-sticky-offset="{default: '200px', lg: '0'}"
        data-kt-sticky-animation="false"
        data-kt-sticky-enabled="true"
    >
        <!--begin::Header container-->
        <div class="app-container container-fluid d-flex align-items-stretch justify-content-between" id="kt_app_header_container">
            <!--begin::Sidebar mobile toggle-->
            <div class="d-flex align-items-center d-lg-none ms-n3 me-1 me-md-2" title="Show sidebar menu">
                <div class="btn btn-icon btn-active-color-primary w-35px h-35px" id="kt_app_sidebar_mobile_toggle">
                    <i class="ki-duotone ki-abstract-14 fs-2 fs-md-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <!--end::Sidebar mobile toggle-->
            <!--begin::Mobile logo-->
            <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
                <Link :href="route('dashboard')" class="d-lg-none">
                    <img alt="Logo" src="/assets/media/logos/logo_sisma.png" class="h-50px" />
                </Link>
            </div>
            <!--end::Mobile logo-->
            <!--begin::Header wrapper-->
            <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1" id="kt_app_header_wrapper">
                <!--begin::Menu wrapper-->
                <div class="app-header-menu app-header-mobile-drawer align-items-stretch" data-kt-drawer="true" data-kt-drawer-name="app-header-menu" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="250px" data-kt-drawer-direction="end" data-kt-drawer-toggle="#kt_app_header_menu_toggle" data-kt-swapper="true" data-kt-swapper-mode="{default: 'append', lg: 'prepend'}" data-kt-swapper-parent="{default: '#kt_app_body', lg: '#kt_app_header_wrapper'}">
                    <!--begin::Menu-->
                    <div class="menu menu-rounded menu-column menu-lg-row my-5 my-lg-0 align-items-stretch fw-semibold px-2 px-lg-0" id="kt_app_header_menu" data-kt-menu="true">
                        <!-- ACCESOS DIRECTOS -->
                    </div>
                    <!--end::Menu-->
                </div>
                <!--end::Menu wrapper-->
                <!--begin::Navbar-->
                <div class="app-navbar flex-shrink-0">
                    <!--begin::Notifications-->
                    <AlarmasDropdown />
                    <!--end::Notifications-->
                    <!--begin::User menu-->
                    <div class="app-navbar-item ms-1 ms-md-4" id="kt_header_user_menu_toggle">
                        <!--begin::Menu wrapper-->
                        <div v-if="$page.props.jetstream.managesProfilePhotos" class="cursor-pointer symbol symbol-35px" data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                            <img
                                :src="$page.props.auth.user.profile_photo_path ? '/storage/'+$page.props.auth.user.profile_photo_path: $page.props.auth.user.profile_photo_url "
                                :alt="$page.props.auth.user.name"
                                class="rounded-3"
                            />
                        </div>
                        <!--begin::User account menu-->
                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px" data-kt-menu="true">
                            <!--begin::Menu item-->
                            <div class="menu-item px-3">
                                <div class="menu-content d-flex align-items-center px-3">
                                    <!--begin::Avatar-->
                                    <div class="symbol symbol-50px me-5">
                                        <img
                                            :src="$page.props.auth.user.profile_photo_path ? '/storage/'+$page.props.auth.user.profile_photo_path: $page.props.auth.user.profile_photo_url "
                                            :alt="$page.props.auth.user.name"
                                        />
                                    </div>
                                    <!--end::Avatar-->
                                    <!--begin::Username-->
                                    <div class="d-flex flex-column">
                                        <div class="fw-bold align-items-center fs-5">
                                            <span>{{$page.props.auth.user.name}}</span>
                                            <span class="badge badge-light-success fw-bold fs-8 px-2 py-1 ms-2">{{$page.props.roles}}</span>
                                        </div>
                                        <a href="#" class="fw-semibold text-muted text-hover-primary fs-7">{{$page.props.auth.user.email}}</a>
                                    </div>
                                    <!--end::Username-->
                                </div>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu separator-->
                            <div class="separator my-2"></div>
                            <!--end::Menu separator-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5">
                                <Link :href="route('profile.show')" class="menu-link px-5">Mi Perfil</Link>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5" v-if="$page.props.jetstream.hasApiFeatures">
                                <Link :href="route('api-tokens.index')" class="menu-link px-5">API Tokens</Link>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5">
                                <a @click.prevent="logout" class="menu-link px-5">Salir</a>
                            </div>
                            <!--end::Menu item-->
                        </div>
                        <!--end::User account menu-->
                        <!--end::Menu wrapper-->
                    </div>
                    <!--end::User menu-->
                </div>
                <!--end::Navbar-->
            </div>
            <!--end::Header wrapper-->
        </div>
        <!--end::Header container-->
    </div>
    <!--end::Header-->
</template>
