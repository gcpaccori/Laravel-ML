<script setup>
    import { computed, nextTick, onMounted, ref } from 'vue';
    import { Head, useForm } from '@inertiajs/vue3';
    import AuthenticationCard from '@/Components/AuthenticationCard.vue';
    import InputError from '@/Components/InputError.vue';

    const recovery = ref(false);
    const form = useForm({
        code: '',
        recovery_code: '',
    });
    const recoveryCodeInput = ref(null);
    const codeInput = ref(null);
    const fullCode = computed(() => codeValues.value.join(''));
    const codeValues = ref(['', '', '', '', '', '']);

    const toggleRecovery = async () => {
        recovery.value ^= true;

        await nextTick();

        if (recovery.value) {
            recoveryCodeInput.value.focus();
            form.code = '';
        } else {
            codeInput.value[0].focus();
            form.recovery_code = '';
        }
    };

    const submit = () => {
        form.transform((data) => ({
            ...data,
            code: fullCode.value,
        })).post(route('two-factor.login'));
    };

    // Manejo de escritura y avance
    const handleInput = (index, event) => {
        const value = event.target.value.replace(/\D/g, '').slice(0, 1); // solo dígito
        codeValues.value[index] = value;
        if (value && index < 5) {
            codeInput.value[index + 1].focus();
        }
    };

    // Retroceso para volver al campo anterior
    const handleKeydown = (index, event) => {
        if (event.key === 'Backspace' && !codeValues.value[index] && index > 0) {
            codeInput.value[index - 1].focus();
        }
    };

    onMounted(() => {
        nextTick(() => {
            if (codeInput.value[0]) codeInput.value[0].focus();
        });
    });
</script>

<template>
    <Head title="Two-factor Confirmation" />

    <AuthenticationCard>
        <div class="d-flex flex-center flex-column flex-lg-row-fluid">
            <div class="w-lg-500px p-10">
                <form @submit.prevent="submit">
                    <div class="text-center mb-10">
                        <img alt="Logo" class="mh-125px" src="/assets/media/svg/misc/smartphone-2.svg" />
                    </div>
                    <div class="text-center mb-10">
                        <h1 class="text-dark mb-3">Verificación de dos factores</h1>
                        <div v-if="! recovery" class="text-muted fw-semibold fs-6 mb-5">
                            Por favor, confirme el acceso a su cuenta ingresando el código de autenticación proporcionado por su aplicación de autenticación.
                        </div>
                        <div v-else class="text-muted fw-semibold fs-6 mb-5">
                            Confirme el acceso a su cuenta ingresando uno de sus códigos de recuperación de emergencia.
                        </div>
                    </div>
                    <div v-if="! recovery" class="mb-10">
                        <div class="fw-bold text-start text-dark fs-6 mb-1 ms-1">Escriba su código de seguridad de 6 dígitos</div>
                        <div class="d-flex flex-wrap flex-stack">
                            <input
                                ref="codeInput"
                                v-for="(digit, index) in codeValues"
                                :key="index"
                                v-model="codeValues[index]"
                                maxlength="1"
                                inputmode="numeric"
                                class="form-control bg-transparent h-60px w-60px fs-2qx text-center mx-1 my-2"
                                @input="event => handleInput(index, event)"
                                @keydown="event => handleKeydown(index, event)"
                            />
                            <InputError class="mt-2" :message="form.errors.code" />
                        </div>
                    </div>
                    <div v-else class="mb-10">
                        <div class="fw-bold text-start text-dark fs-6 mb-1 ms-1">Ingrese un código de recuperación</div>
                        <div class="d-flex flex-wrap flex-stack">
                            <el-input
                                ref="recoveryCodeInput"
                                v-model="form.recovery_code"
                                class="mt-1"
                                placeholder="Código de recuperación"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.recovery_code" />
                        </div>
                    </div>
                    <div class="d-flex flex-center">
                        <el-button
                            type="success"
                            @click.prevent="toggleRecovery"
                        >
                            <template v-if="! recovery">
                                Usar código de recuperación
                            </template>

                            <template v-else>
                                Usar aplicación de autenticación
                            </template>
                        </el-button>
                        <el-button
                            class="ms-4"
                            native-type="submit"
                            type="primary"
                            :loading="form.processing"
                        >
                            Ingresar
                        </el-button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticationCard>
</template>
