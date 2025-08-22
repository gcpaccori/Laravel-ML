<script setup>
    import { ref } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import ActionMessage from '@/Components/ActionMessage.vue';
    import InputError from '@/Components/InputError.vue';
    import { ElMessage } from 'element-plus';

    defineProps({
        sessions: Array,
    });

    const confirmingLogout = ref(false);
    const passwordInput = ref(null);

    const form = useForm({
        password: '',
    });

    const confirmLogout = () => {
        confirmingLogout.value = true;

        setTimeout(() => passwordInput.value.focus(), 250);
    };

    const logoutOtherBrowserSessions = () => {
        form.delete(route('other-browser-sessions.destroy'), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                ElMessage.success('Sesiones cerradas correctamente');
            },
            onError: () => passwordInput.value.focus(),
            onFinish: () => form.reset(),
        });
    };

    const closeModal = () => {
        confirmingLogout.value = false;
        form.reset();
    };
</script>

<template>
    <div>
        <div class="card mb-5 mt-0 mb-xl-5 shadow-sm">
            <div class="card-header collapsible cursor-pointer rotate" data-bs-toggle="collapse" data-bs-target="#logout_browser">
                <h3 class="card-title align-items-start m-0 flex-column">
                    <span class="fw-bold m-0">Sesiones del navegador</span>
                    <span class="text-muted fs-7">
                        Administre y cierre sesión en sus sesiones activas en otros navegadores y dispositivos.
                    </span>
                </h3>
                <div class="card-toolbar rotate-180">
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
            </div>
            <div id="logout_browser" class="collapse">
                <div class="card-body p-9">
                    <div class="notice bg-light-warning rounded border-warning border border-dashed p-7">
                        <div class="fs-6 text-gray-700 fw-semibold">
                            Si es necesario, puede cerrar sesión en todas sus demás sesiones de navegador en todos sus dispositivos. A continuación, se enumeran algunas de sus sesiones recientes; sin embargo, esta lista puede no ser exhaustiva. Si cree que su cuenta ha sido vulnerada, también debería actualizar su contraseña.
                        </div>
                    </div>

                     <div class="mt-5">
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle gs-0 gy-3 my-0">
                                <thead>
                                    <tr class="fs-7 fw-bold text-gray-400 border-bottom-0 text-uppercase">
                                        <th class="text-center">Item</th>
                                        <th>Dispositivo</th>
                                        <th class="text-center">Dirección IP</th>
                                        <th>Tiempo</th>
                                    </tr>
                                </thead>
                                <tbody v-if="sessions.length > 0">
                                    <tr v-for="(session, i) in sessions" :key="i">
                                        <td class="text-center">
                                            <span class="text-gray-700 fw-bold d-block fs-7">{{ (i+1)  }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                        <el-icon :size="50" v-if="session.agent.is_desktop" class="text-gray-700">
                                                            <Monitor />
                                                        </el-icon>
                                                        <el-icon :size="50" v-else class="text-gray-700">
                                                            <Cellphone />
                                                        </el-icon>
                                                </div>
                                                <div class="d-flex justify-content-start flex-column">
                                                    <a class="text-gray-800 fw-bold text-hover-primary mb-1 fs-6 cursor-pointer">{{ session.agent.platform ? session.agent.platform : 'Desconocido' }} - {{ session.agent.browser ? session.agent.browser : 'Desconocido' }}</a>
                                                    <span v-if="session.is_current_device" class="text-gray-400 fw-semibold d-block fs-7">Dispositivo actual</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="text-gray-700 fw-bold fs-7">{{ session.ip_address }}</span>
                                        </td>
                                        <td class="text-gray-700 fw-bold fs-7">
                                            <span >Última actividad {{ session.last_active }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                     </div>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <el-button
                        type="info"
                        @click="confirmLogout"
                    >
                        Cerrar sesiones
                    </el-button>

                    <ActionMessage :on="form.recentlySuccessful" class="ms-3">
                        Done.
                    </ActionMessage>
                </div>
            </div>
        </div>

        <DialogForm v-model="confirmingLogout" title="Cerrar sesión en otras sesiones del navegador" width="40%">
            <p>Ingrese su contraseña para confirmar que desea cerrar sesión en otras sesiones de su navegador en todos sus dispositivos.</p>

            <div class="mt-4">
                <el-input
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    placeholder="Contraseña"
                    @keyup.enter="logoutOtherBrowserSessions"
                    show-password
                />
                <InputError :message="form.errors.password" class="mt-1" />
            </div>

            <template #footer>
                <el-button
                    type="danger"
                    @click="closeModal"
                >
                    Cancelar
                </el-button>

                <el-button
                    class="ms-3"
                    :loading="form.processing"
                    @click="logoutOtherBrowserSessions"
                >
                    Confirmar
                </el-button>
            </template>
        </DialogForm>
    </div>
</template>
