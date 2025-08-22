<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

const props = defineProps({
    user: Object,
});

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => {
            clearPhotoFileInput()
            ElMessage.success('Datos actualizados correctamente');
        },
        onError: (errors) => {
            ElMessage.error(errors)
        },
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];
    if (! photo) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };
    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <div class="card mb-5 mb-xl-5 shadow-sm">
        <el-form @submit.prevent="updateProfileInformation" :model="form" label-position="top" class="w-100">
            <div class="card-header collapsible cursor-pointer rotate" data-bs-toggle="collapse" data-bs-target="#profile_information">
                <h3 class="card-title align-items-start m-0 flex-column">
                    <span class="fw-bold m-0">Información del Perfil</span>
                    <span class="text-muted fs-7">
                        Actualice la información del perfil y la dirección de correo electrónico de su cuenta.
                    </span>
                </h3>
                <div class="card-toolbar rotate-180">
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
            </div>
            <div id="profile_information" class="collapse">
                <div class="card-body p-9">
                    <el-row :gutter="20">
                        <el-col :lg="8">
                            <el-form-item label="Nombre" :error="form.errors.name" required>
                                <el-input v-model="form.name" />
                            </el-form-item>
                        </el-col>
                        <el-col :lg="8">
                            <el-form-item label="Correo Electrónico" :error="form.errors.email" required>
                                <el-input v-model="form.email" />
                            </el-form-item>
                        </el-col>
                        <el-col :lg="8" v-if="$page.props.jetstream.managesProfilePhotos">
                            <input
                                id="photo"
                                ref="photoInput"
                                type="file"
                                class="d-none"
                                @change="updatePhotoPreview"
                            >
                            <el-form-item label="Foto" :error="form.errors.photo">
                                <div class="d-fex flex-column">
                                    <div class="image-input image-input-outline">
                                        <div v-show="!photoPreview" class="mt-2">
                                            <div class="symbol symbol-100px">
                                                <img
                                                    :src="user.profile_photo_path ? '/storage/'+user.profile_photo_path : user.profile_photo_url"
                                                    :alt="user.name"
                                                >
                                            </div>
                                        </div>
                                        <div
                                            v-show="photoPreview"
                                            class="image-input-wrapper w-100px h-100px"
                                            :style="'background-image: url(\'' + photoPreview + '\');'"
                                        >
                                        </div>
                                    </div>

                                    <div class="d-flex">
                                        <el-button
                                            class="mt-2 me-2"
                                            @click.prevent="selectNewPhoto"
                                            size="small"
                                            type="success"
                                        >
                                            Nueva Foto
                                        </el-button>

                                        <el-button
                                            v-if="user.profile_photo_path"
                                            class="mt-2"
                                            @click.prevent="deletePhoto"
                                            size="small"
                                            type="danger"
                                        >
                                            Remover Foto
                                        </el-button>
                                    </div>
                                </div>
                            </el-form-item>
                        </el-col>
                        <el-col :lg="8" v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null">
                            <el-form-item label="Correo Electrónico no verificado">
                                <div class="d-flex flex-column">
                                    <Link
                                        :href="route('verification.send')"
                                        method="post"
                                        as="button"
                                        class="btn btn-success btn-sm"
                                        @click.prevent="sendEmailVerification"
                                    >
                                        Verificar
                                    </Link>
                                    <div v-show="verificationLinkSent" class="fs-7 text-success">
                                        Enlace enviado a su correo electrónico.
                                    </div>
                                </div>
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
