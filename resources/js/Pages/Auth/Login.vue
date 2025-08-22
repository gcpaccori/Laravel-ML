<script setup>
    import { Head, Link, useForm } from '@inertiajs/vue3';
    import AuthenticationCard from '@/Components/AuthenticationCard.vue';
    import InputError from '@/Components/InputError.vue';

    defineProps({
        canResetPassword: Boolean,
        canRegister: Boolean,
        status: String,
    });

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = () => {
        form.transform(data => ({
            ...data,
            remember: form.remember ? 'on' : '',
        })).post(route('login'), {
            onFinish: () => form.reset('password'),
        });
    };
</script>

<template>
    <Head title="Login" />
    <AuthenticationCard>
        <div v-if="status" class="mb-4">
            {{ status }}
        </div>
        <div class="d-flex flex-center flex-column flex-lg-row-fluid">
            <div class="w-lg-500px p-10">
                <form class="form w-100" @submit.prevent="submit">
                    <div class="text-center mb-11">
                        <h1 class="text-dark fw-bolder mb-3">Inicar Sesión</h1>
                        <div class="text-gray-500 fw-semibold fs-6">Ingrese sus credenciales</div>
                    </div>
                    <div class="separator separator-content my-10">
                        <span class="w-125px text-gray-500 fw-semibold fs-7">*</span>
                    </div>
                    <div class="fv-row mb-8">
                        <el-input type="email" v-model="form.email" size="large" placeholder="Correo Electrónico" />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>
                    <div class="fv-row mb-3">
                        <el-input
                            type="password"
                            v-model="form.password"
                            size="large"
                            placeholder="Contraseña"
                            show-password
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>
                    <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
                        <el-checkbox v-model="form.remember" label="Recordar" size="large" />
                        <Link v-if="canResetPassword" :href="route('password.request')" class="link-primary">¿Has olvidado tu contraseña?</Link>
                    </div>
                    <div class="d-grid mb-10">
                        <el-button
                            native-type="submit"
                            type="primary"
                            size="large"
                            :loading="form.processing"
                        >
                            Ingresar
                        </el-button>
                    </div>
                    <div v-if="canRegister" class="text-gray-500 text-center fw-semibold fs-6">¿Aún no eres miembro?
                    <Link :href="route('register')" class="link-primary">Registrarse</Link></div>
                </form>
            </div>
        </div>
    </AuthenticationCard>
</template>
