<script setup>
    import { Link } from '@inertiajs/vue3';
    const hasSubmodulos = modulo => modulo.children && modulo.children.length > 0;

    const isActive = (name) => route().current(name?.replace(/\.+$/, '') + '*');
    const getRouteName = (sistema, modulo) => `${sistema.url}.${modulo.url}s.index`;
    const isAnyChildActive = (sistema, modulo) => {
        return modulo.children?.some(sub =>
            isActive(getRouteName(sistema, sub))
        );
    };
</script>
<template>
    <div id="kt_app_sidebar" class="app-sidebar flex-column" data-kt-drawer="true" data-kt-drawer-name="app-sidebar" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="225px" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">
        <!--begin::Logo-->
        <div class="app-sidebar-logo px-6 d-flex justify-content-center align-items-center" id="kt_app_sidebar_logo">
            <!--begin::Logo image-->
            <Link :href="route('dashboard')">
                <img alt="Logo" src="/assets/media/logos/logo_sisma.png" class="h-90px app-sidebar-logo-default" />
                <img alt="Logo" src="/assets/media/logos/logo_sisma.png" class="h-70px app-sidebar-logo-minimize" />
            </Link>
            <!--end::Logo image-->
            <!--begin::Sidebar toggle-->
            <div id="kt_app_sidebar_toggle" class="app-sidebar-toggle btn btn-icon btn-shadow btn-sm btn-color-muted btn-active-color-primary h-30px w-30px position-absolute top-50 start-100 translate-middle rotate" data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body" data-kt-toggle-name="app-sidebar-minimize">
                <i class="ki-duotone ki-black-left-line fs-3 rotate-180">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </div>
            <!--end::Sidebar toggle-->
        </div>
        <!--end::Logo-->
        <!--begin::sidebar menu-->
        <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
            <!--begin::Menu wrapper-->
            <div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper">
                <!--begin::Scroll wrapper-->
                <div id="kt_app_sidebar_menu_scroll" class="scroll-y my-5 mx-3" data-kt-scroll="true" data-kt-scroll-activate="true" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer" data-kt-scroll-wrappers="#kt_app_sidebar_menu" data-kt-scroll-offset="5px" data-kt-scroll-save-state="true">
                    <!--begin::Menu-->
                    <div class="menu menu-column menu-rounded menu-sub-indention fw-semibold fs-6" id="kt_app_sidebar_menu" data-kt-menu="true" data-kt-menu-expand="false">

                        <!--begin:INICIO-->
                        <div class="menu-item">
                            <!--begin:Menu link-->
                            <Link :class="['menu-link', isActive('dashboard') ? 'active' : '']" :href="route('dashboard')">
                                <span class="menu-icon">
                                    <el-icon class="fs-2">
                                        <component is="HomeFilled" />
                                    </el-icon>
                                </span>
                                <span class="menu-title">Inicio</span>
                            </Link>
                            <!--end:Menu link-->
                        </div>
                        <!--end:INICIO-->

                        <!-- Iterar sistemas -->
                        <div v-for="sistema in $page.props.menu" :key="sistema.id" :class="['menu-item menu-accordion', isActive(sistema.url) ? 'hover show': '']" data-kt-menu-trigger="click">
                            <span :class="['menu-link', isActive(sistema.url) ? 'active' : '']">
                                <span class="menu-icon">
                                    <el-icon class="fs-2">
                                        <component :is="sistema.icon" />
                                    </el-icon>
                                </span>
                                <span class="menu-title">{{ sistema.label }}</span>
                                <span class="menu-arrow"></span>
                            </span>

                            <div class="menu-sub menu-sub-accordion">
                                <!-- Iterar módulos -->
                                <template v-for="modulo in sistema.children" :key="modulo.id">
                                    <div
                                        v-if="hasSubmodulos(modulo)"
                                        :class="['menu-item menu-accordion', isAnyChildActive(sistema, modulo) ? 'hover show': '']"
                                        data-kt-menu-trigger="click"
                                    >
                                        <span class="menu-link" :class="{ active: isAnyChildActive(sistema, modulo) }">
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">{{ modulo.label }}</span>
                                            <span class="menu-arrow"></span>
                                        </span>
                                        <div class="menu-sub menu-sub-accordion">
                                            <div class="menu-item" v-for="sub in modulo.children" :key="sub.id">
                                                <Link class="menu-link"
                                                    :class="{ active: isActive(getRouteName(sistema, sub)) }"
                                                    :href="route(getRouteName(sistema, sub))"
                                                >
                                                    <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                                    <span class="menu-title">{{ sub.label }}</span>
                                                </Link>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-else class="menu-item">
                                        <Link
                                            :class="{ active: isActive(getRouteName(sistema, modulo)) }"
                                            class="menu-link"
                                            :href="route(getRouteName(sistema, modulo))"
                                        >
                                            <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
                                            <span class="menu-title">{{ modulo.label }}</span>
                                        </Link>
                                    </div>
                                </template>
                            </div>
                        </div>


                    </div>
                    <!--end::Menu-->
                </div>
                <!--end::Scroll wrapper-->
            </div>
            <!--end::Menu wrapper-->
        </div>
        <!--end::sidebar menu-->
    </div>
</template>
