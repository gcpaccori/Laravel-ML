<script setup>
    import { Head, Link, useForm } from '@inertiajs/vue3';
    import AuthenticationCard from '@/Components/AuthenticationCard.vue';
    import InputError from '@/Components/InputError.vue';

    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms: false,
    });

    const submit = () => {
        form.post(route('register'), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };
</script>

<template>
    <Head title="Register" />
    <AuthenticationCard>
        <div class="d-flex flex-center flex-column flex-lg-row-fluid">
            <div class="w-lg-500px p-10">
                <form class="form w-100" @submit.prevent="submit">
                    <div class="text-center mb-11">
                        <h1 class="text-dark fw-bolder mb-3">Registrarse</h1>
                        <div class="text-gray-500 fw-semibold fs-6">Ingrese sus datos</div>
                    </div>
                    <div class="separator separator-content my-10">
                        <span class="w-125px text-gray-500 fw-semibold fs-7">*</span>
                    </div>
                    <div class="fv-row mb-5">
                        <el-input
                            v-model="form.name"
                            type="text"
                            size="large"
                            placeholder="Nombres Completos"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div class="fv-row mb-5">
                        <el-input
                            type="email"
                            v-model="form.email"
                            size="large"
                            placeholder="Correo Electrónico"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>
                    <div class="fv-row mb-5">
                        <el-input
                            type="password"
                            v-model="form.password"
                            size="large"
                            placeholder="Contraseña"
                            show-password
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>
                    <div class="fv-row mb-5">
                        <el-input
                            type="password"
                            v-model="form.password_confirmation"
                            size="large"
                            placeholder="Confirmar Contraseña"
                            show-password
                        />
                        <InputError class="mt-2" :message="form.errors.password_confirmation" />
                    </div>
                    <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
                        <el-checkbox v-model="form.terms" size="large">
                            <span class="form-check-label fw-semibold text-gray-700 fs-base ms-1">Acepto los
                            <a target="_blank" :href="route('terms.show')" class="ms-1 link-primary">Términos</a>
                            <span> y </span>
                            <a target="_blank" :href="route('policy.show')" class="ms-1 link-primary">Políticas de Privacidad</a></span>
                        </el-checkbox>
                        <InputError class="mt-2" :message="form.errors.terms" />
                    </div>
                    <div class="d-grid mb-10">
                        <el-button
                            native-type="submit"
                            type="primary"
                            size="large"
                            :loading="form.processing"
                        >
                        Registrar
                        </el-button>
                    </div>
                    <div class="text-gray-500 text-center fw-semibold fs-6">¿Ya estás registrado?
                    <Link :href="route('login')" class="link-primary">Iniciar Sesión</Link></div>
                </form>
            </div>
        </div>
    </AuthenticationCard>
</template>
