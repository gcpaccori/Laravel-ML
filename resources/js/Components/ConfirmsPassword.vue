<script setup>
import { ref, reactive, nextTick } from 'vue';

const emit = defineEmits(['confirmed']);

defineProps({
    title: {
        type: String,
        default: 'Confirmar Contraseña',
    },
    content: {
        type: String,
        default: 'Para su seguridad, por favor confirme su contraseña para continuar.',
    },
    button: {
        type: String,
        default: 'Confirmar',
    },
});

const confirmingPassword = ref(false);

const form = reactive({
    password: '',
    error: '',
    processing: false,
});

const passwordInput = ref(null);

const startConfirmingPassword = () => {
    axios.get(route('password.confirmation')).then(response => {
        if (response.data.confirmed) {
            emit('confirmed');
        } else {
            confirmingPassword.value = true;

            setTimeout(() => passwordInput.value.focus(), 250);
        }
    });
};

const confirmPassword = () => {
    form.processing = true;

    axios.post(route('password.confirm'), {
        password: form.password,
    }).then(() => {
        form.processing = false;

        closeModal();
        nextTick().then(() => emit('confirmed'));

    }).catch(error => {
        form.processing = false;
        form.error = error.response.data.errors.password[0];
        passwordInput.value.focus();
    });
};

const closeModal = () => {
    confirmingPassword.value = false;
    form.password = '';
    form.error = '';
};
</script>

<template>
    <span>
        <span @click="startConfirmingPassword">
            <slot />
        </span>

        <DialogForm v-model="confirmingPassword" :title="title" width="40%">
            <p>{{ content }}</p>

            <el-form-item :error="form.error" required>
                <el-input
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    placeholder="Contraseña"
                    @keyup.enter="confirmPassword"
                    show-password
                />
            </el-form-item>

            <template #footer>
                <el-button type="danger" @click="closeModal">
                    Cancelar
                </el-button>

                <el-button
                    class="ms-3"
                    :loading="form.processing"
                    @click="confirmPassword"
                >
                    {{ button }}
                </el-button>
            </template>
        </DialogForm>
    </span>
</template>
