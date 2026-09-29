<template>
    <div id="kt_app_footer" class="app-footer">
        <div class="app-container container-fluid text-center">
            <div class="text-dark">
                <span class="text-muted fw-semibold me-1">{{ currentYear }}&copy;</span>
                <a href="#" target="_blank" class="text-gray-800 text-hover-primary">SISMA PISCIS</a>
            </div>
        </div>
    </div>
    <div id="kt_scrolltop" class="scrolltop" data-kt-scrolltop="true">
        <i class="ki-duotone ki-arrow-up">
            <span class="path1"></span>
            <span class="path2"></span>
        </i>
    </div>
</template>

<script setup>
const currentYear = new Date().getFullYear();
</script>
