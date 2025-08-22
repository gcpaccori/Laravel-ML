<script setup>
    import { Head, Link, useForm } from '@inertiajs/vue3';
    import AuthenticationCard from '@/Components/AuthenticationCard.vue';
    import InputError from '@/Components/InputError.vue';

    defineProps({
        status: String,
    });

    const form = useForm({
        email: '',
    });

    const submit = () => {
        form.post(route('password.email'));
    };
</script>

<template>
    <Head title="Forgot Password" />
    <AuthenticationCard>
        <div class="d-flex flex-center flex-column flex-lg-row-fluid">
            <div class="w-lg-500px p-10">
                <form class="form w-100" @submit.prevent="submit">
                    <div class="text-center mb-10">
                        <h1 class="text-dark fw-bolder mb-3">¿Has olvidado tu contraseña?</h1>
                        <div class="text-gray-500 fw-semibold fs-6">No hay problema. Solo indícanos tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña y podrás elegir una nueva.</div>
                    </div>
                    <div v-if="status" class="mb-4">
                        {{ status }}
                    </div>
                    <div class="fv-row mb-8">
                        <el-input type="email" v-model="form.email" size="large" placeholder="Correo Electrónico" />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>
                    <div class="d-flex flex-wrap justify-content-center pb-lg-0">
                        <el-button
                            native-type="submit"
                            type="primary"
                            size="large"
                            :loading="form.processing"
                        >
                            Enviar
                        </el-button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticationCard>
</template>
