<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import ConfirmsPassword from '@/Components/ConfirmsPassword.vue';

const props = defineProps({
    requiresConfirmation: Boolean,
});

const page = usePage();
const enabling = ref(false);
const confirming = ref(false);
const disabling = ref(false);
const qrCode = ref(null);
const setupKey = ref(null);
const recoveryCodes = ref([]);

const confirmationForm = useForm({
    code: '',
});

const twoFactorEnabled = computed(
    () => !enabling.value && page.props.auth.user?.two_factor_enabled,
);

watch(twoFactorEnabled, () => {
    if (! twoFactorEnabled.value) {
        confirmationForm.reset();
        confirmationForm.clearErrors();
    }
});

const enableTwoFactorAuthentication = () => {
    enabling.value = true;

    router.post(route('two-factor.enable'), {}, {
        preserveScroll: true,
        onSuccess: () => Promise.all([
            showQrCode(),
            showSetupKey(),
            showRecoveryCodes(),
        ]),
        onFinish: () => {
            enabling.value = false;
            confirming.value = props.requiresConfirmation;
        },
    });
};

const showQrCode = () => {
    return axios.get(route('two-factor.qr-code')).then(response => {
        qrCode.value = response.data.svg;
    });
};

const showSetupKey = () => {
    return axios.get(route('two-factor.secret-key')).then(response => {
        setupKey.value = response.data.secretKey;
    });
}

const showRecoveryCodes = () => {
    return axios.get(route('two-factor.recovery-codes')).then(response => {
        recoveryCodes.value = response.data;
    });
};

const confirmTwoFactorAuthentication = () => {
    confirmationForm.post(route('two-factor.confirm'), {
        errorBag: "confirmTwoFactorAuthentication",
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            confirming.value = false;
            qrCode.value = null;
            setupKey.value = null;
        },
    });
};

const regenerateRecoveryCodes = () => {
    axios
        .post(route('two-factor.recovery-codes'))
        .then(() => showRecoveryCodes());
};

const disableTwoFactorAuthentication = () => {
    disabling.value = true;

    router.delete(route('two-factor.disable'), {
        preserveScroll: true,
        onSuccess: () => {
            disabling.value = false;
            confirming.value = false;
        },
    });
};
</script>

<template>
    <div class="card mb-5 mb-xl-5 shadow-sm">
        <div class="card-header collapsible cursor-pointer rotate" data-bs-toggle="collapse" data-bs-target="#two_factor">
            <h3 class="card-title align-items-start m-0 flex-column">
                <span class="fw-bold m-0">Autenticación de dos factores</span>
                <span class="text-muted fs-7">
                    Agregue seguridad adicional a su cuenta utilizando la autenticación de dos factores.
                </span>
            </h3>
            <div class="card-toolbar rotate-180">
                <i class="fa-solid fa-chevron-down"></i>
            </div>
        </div>
        <div id="two_factor" class="collapse">
            <div class="card-body p-9">
                <div class="notice bg-light-primary rounded border-primary border border-dashed p-6">
                    <h3 v-if="twoFactorEnabled && ! confirming">
                        Ha habilitado la autenticación de dos factores.
                    </h3>

                    <h3 v-else-if="twoFactorEnabled && confirming">
                        Terminar de habilitar la autenticación de dos factores.
                    </h3>

                    <h3 v-else>
                        No ha habilitado la autenticación de dos factores.
                    </h3>
                    <div class="mt-3 text-gray-600">
                        <p>
                            Cuando la autenticación de dos factores esté habilitada, se te solicitará un token aleatorio seguro durante la autenticación. Puedes obtener este token desde la aplicación Google Authenticator de tu teléfono.
                        </p>
                    </div>
                </div>

                <div v-if="twoFactorEnabled">
                    <div v-if="qrCode">
                        <div class="mt-4 text-gray-600">
                            <p v-if="confirming">
                                Para terminar de habilitar la autenticación de dos factores, escanee el siguiente código QR usando la aplicación de autenticación de su teléfono o ingrese la clave de configuración y proporcione el código OTP generado.
                            </p>
                            <p v-else>
                                La autenticación de dos factores ya está habilitada. Escanea el siguiente código QR con la aplicación de autenticación de tu teléfono o introduce la clave de configuración.
                            </p>
                        </div>

                        <div class="mt-4 p-2 inline-block bg-white text-center" v-html="qrCode" />

                        <div v-if="setupKey" class="mt-4 text-gray-600 text-center">
                            <p>
                                Clave de configuración: <span v-html="setupKey"></span>
                            </p>
                        </div>

                        <div v-if="confirming" class="mt-4 text-center">
                            <el-row :gutter="20" class="justify-content-center">
                                <el-col :lg="12">
                                    <el-form-item for="code" label="Código" :error="confirmationForm.errors.code" required>
                                        <el-input
                                            id="code"
                                            type="number"
                                            v-model="confirmationForm.code"
                                            autofocus
                                            @keyup.enter="confirmTwoFactorAuthentication"
                                            :min="1"
                                        />
                                    </el-form-item>
                                </el-col>
                            </el-row>
                        </div>
                    </div>

                    <div v-if="recoveryCodes.length > 0 && ! confirming">
                        <div class="mt-4 text-gray-700">
                            <p>
                                Guarde estos códigos de recuperación en un administrador de contraseñas seguro. Pueden usarse para recuperar el acceso a su cuenta si pierde su dispositivo de autenticación de dos factores.
                            </p>
                        </div>

                        <div class="notice bg-light-success rounded border-success border border-dashed p-6 text-center">
                            <div v-for="code in recoveryCodes" :key="code">
                                {{ code }}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="card-footer d-flex justify-content-end">
                <div v-if="! twoFactorEnabled">
                    <ConfirmsPassword @confirmed="enableTwoFactorAuthentication">
                        <el-button
                            type="info"
                            :loading="enabling"
                        >
                            Habilitar
                        </el-button>
                    </ConfirmsPassword>
                </div>

                <div v-else>
                    <ConfirmsPassword @confirmed="confirmTwoFactorAuthentication">
                        <el-button
                            v-if="confirming"
                            type="info"
                            class="me-3"
                            :loading="enabling"
                        >
                            Confirmar
                        </el-button>
                    </ConfirmsPassword>

                    <ConfirmsPassword @confirmed="regenerateRecoveryCodes">
                        <el-button
                            v-if="recoveryCodes.length > 0 && ! confirming"
                            type="success"
                            class="me-3"
                        >
                            Regenerar Códigos
                        </el-button>
                    </ConfirmsPassword>

                    <ConfirmsPassword @confirmed="showRecoveryCodes">
                        <el-button
                            v-if="recoveryCodes.length === 0 && ! confirming"
                            class="me-3"
                            type="success"
                        >
                            Mostrar Códigos
                        </el-button>
                    </ConfirmsPassword>

                    <ConfirmsPassword @confirmed="disableTwoFactorAuthentication">
                        <el-button
                            v-if="confirming"
                            type="danger"
                            :loading="enabling"
                        >
                            Cancelar
                        </el-button>
                    </ConfirmsPassword>

                    <ConfirmsPassword @confirmed="disableTwoFactorAuthentication">
                        <el-button
                            v-if="!confirming"
                            type="danger"
                            :loading="disabling"
                        >
                            Desactivar
                        </el-button>
                    </ConfirmsPassword>
                </div>
            </div>
        </div>
    </div>
</template>
