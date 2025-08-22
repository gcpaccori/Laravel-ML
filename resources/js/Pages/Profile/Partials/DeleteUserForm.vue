<script setup>
    import { ref } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import ActionSection from '@/Components/ActionSection.vue';
    import DangerButton from '@/Components/DangerButton.vue';
    import DialogModal from '@/Components/DialogModal.vue';
    import InputError from '@/Components/InputError.vue';
    import SecondaryButton from '@/Components/SecondaryButton.vue';
    import TextInput from '@/Components/TextInput.vue';
    import { ElMessage, ElMessageBox } from 'element-plus';

    const confirmingUserDeletion = ref(false);
    const passwordInput = ref(null);

    const form = useForm({
        password: '',
    });

    const checkConfirmation = ref(false);
    const checkError = ref(false);

    const confirmUserDeletion = () => {
        if (!checkConfirmation.value) {
            checkError.value = true;
            return;
        }
        checkError.value = false;

        confirmingUserDeletion.value = true;
        setTimeout(() => passwordInput.value.focus(), 250);
    };

    const deleteUser = () => {
        if (!form.password) {
            passwordInput.value.focus();
            return;
        }

        ElMessageBox.confirm(
            '¿Estás seguro de eliminar tu cuenta?',
            'Advertencia',
            {
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                type: 'warning',
                center: true,
            }
        ).then( () => {
            form.delete(route('current-user.destroy'), {
                preserveScroll: true,
                onSuccess: () => {
                    closeModal();
                    ElMessage.success('Cuenta eliminada correctamente');
                },
                onError: () => passwordInput.value.focus(),
                onFinish: () => form.reset(),
            });
        }).catch( (e) => {
            console.log(e);
        } );

    };

    const closeModal = () => {
        confirmingUserDeletion.value = false;
        form.reset();
    };
</script>

<template>
    <div class="card mb-5 mb-xl-5 shadow-sm">
        <div class="card-header collapsible cursor-pointer rotate" data-bs-toggle="collapse" data-bs-target="#delete-acount">
            <h3 class="card-title align-items-start m-0 flex-column">
                <span class="fw-bold m-0">Eliminar Cuenta</span>
                <span class="text-muted fs-7">
                    Eliminar permanentemente su cuenta.
                </span>
            </h3>
            <div class="card-toolbar rotate-180">
                <i class="fa-solid fa-chevron-down"></i>
            </div>
        </div>
        <div id="delete-acount" class="collapse">
            <div class="card-body p-9">
                <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed mb-9 p-6">
                    <i class="ki-duotone ki-information fs-2tx text-warning me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-stack flex-grow-1">
                        <div class="fw-semibold">
                            <h4 class="text-gray-900 fw-bold">Estás desactivando tu cuenta</h4>
                            <div class="fs-6 text-gray-700">Una vez eliminada su cuenta, todos sus recursos y datos se eliminarán permanentemente. Antes de eliminar su cuenta, descargue cualquier dato o información que desee conservar.</div>
                        </div>
                    </div>
                </div>

                <el-form-item :error="checkError ? 'Marque la casilla para desactivar su cuenta' : ''">
                    <el-checkbox size="large" v-model="checkConfirmation">
                        Confirmo la desactivación de mi cuenta
                    </el-checkbox>
                </el-form-item>
            </div>
            <div class="card-footer d-flex justify-content-end">
                <el-button
                    type="danger"
                    @click="confirmUserDeletion"
                >
                    Eliminar Cuenta
                </el-button>
            </div>
        </div>
    </div>

    <DialogForm v-model="confirmingUserDeletion" title="Eliminar Cuenta" width="40%">
        <p>¿Seguro que desea eliminar su cuenta? Una vez eliminada, todos sus recursos y datos se eliminarán permanentemente. Ingrese su contraseña para confirmar que desea eliminar su cuenta permanentemente.</p>

        <div class="mt-4">
            <el-input
                ref="passwordInput"
                v-model="form.password"
                type="password"
                placeholder="Contraseña"
                @keyup.enter="deleteUser"
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
                @click="deleteUser"
            >
                Confirmar
            </el-button>
        </template>
    </DialogForm>
</template>
