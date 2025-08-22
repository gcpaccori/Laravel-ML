<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            ElMessage.success('Datos actualizados correctamente');
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
            }

            if (form.errors.current_password) {
                form.reset('current_password');
            }
        },
    });
};
</script>

<template>
    <div class="card mb-5 mb-xl-5 shadow-sm">
        <el-form @submit.prevent="updatePassword" :model="form" label-position="top" class="w-100">
            <div class="card-header collapsible cursor-pointer rotate" data-bs-toggle="collapse" data-bs-target="#password_update">
                <h3 class="card-title align-items-start m-0 flex-column">
                    <span class="fw-bold m-0">Actualizar Contraseña</span>
                    <span class="text-muted fs-7">
                        Asegúrese de que su cuenta utilice una contraseña larga y aleatoria para mantener su seguridad.
                    </span>
                </h3>
                <div class="card-toolbar rotate-180">
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
            </div>
            <div id="password_update" class="collapse">
                <div class="card-body p-9">
                    <el-row :gutter="20">
                        <el-col :lg="8">
                            <el-form-item label="Contraseña actual" :error="form.errors.current_password" required>
                                <el-input v-model="form.current_password" type="password" show-password />
                            </el-form-item>
                        </el-col>

                        <el-col :lg="8">
                            <el-form-item label="Contraseña nueva" :error="form.errors.password" required>
                                <el-input v-model="form.password" type="password" show-password />
                            </el-form-item>
                        </el-col>

                        <el-col :lg="8">
                            <el-form-item label="Confirmar contraseña" :error="form.errors.password_confirmation" required>
                                <el-input v-model="form.password_confirmation" type="password" show-password />
                            </el-form-item>
                        </el-col>
                    </el-row>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <el-button
                        native-type="submit"
                        type="primary"
                        :loading="form.processing"
                    >
                        Guardar
                    </el-button>
                </div>
            </div>

        </el-form>
    </div>
</template>
