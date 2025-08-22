import './bootstrap';
import '../css/app.css';

import { createApp, h, nextTick } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import ElementPlus from "element-plus";
import es from 'element-plus/es/locale/lang/es';
import 'element-plus/dist/index.css';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseDataTable from '@/Components/BaseDataTable.vue';
import DialogForm from '@/Components/DialogForm.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const vueApp = createApp({ render: () => h(App, props) })
        vueApp.use(plugin)
        vueApp.use(ZiggyVue)
        vueApp.use(ElementPlus,{
            locale: es,
        })
        for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
            vueApp.component(key, component)
        }
        vueApp.component('App', AppLayout)
        vueApp.component('BaseDataTable', BaseDataTable)
        vueApp.component('DialogForm', DialogForm)
        vueApp.mount(el)
    },
    progress: {
        color: '#4B5563',
    },
});
