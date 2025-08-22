<script setup>
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import SessionsLogs from '@/Pages/Profile/Partials/SessionsLogs.vue';

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});

const toolbar = {
    title: 'Perfil',
    toolbar: [
        {label : 'Inicio', route : 'dashboard'}
    ]
};
</script>

<template>
    <App :title="toolbar.title" :toolbar="toolbar.toolbar">
        <div class="card mb-5 mb-xl-10">
            <div class="card-body pt-9 pb-0">
                <div class="d-flex flex-wrap flex-sm-nowrap">
                    <div class="me-7 mb-4">
                        <div class="symbol symbol-100px symbol-lg-160px symbol-fixed position-relative">
                            <img
                                :src="$page.props.auth.user.profile_photo_path ? '/storage/'+$page.props.auth.user.profile_photo_path: $page.props.auth.user.profile_photo_url "
                                :alt="$page.props.auth.user.name"
                            />
                            <div class="position-absolute translate-middle bottom-0 start-100 mb-6 bg-success rounded-circle border border-4 border-body h-20px w-20px"></div>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
                            <div class="d-flex flex-column">
                                <div class="d-flex align-items-center mb-2">
                                    <a href="#" class="text-gray-900 text-hover-primary fs-2 fw-bold me-1">{{$page.props.auth.user.name}}</a>
                                    <a v-if="$page.props.auth.user.email_verified_at">
                                        <i class="ki-duotone ki-verify fs-1 text-primary">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </a>
                                </div>
                                <div class="d-flex flex-wrap fw-semibold fs-6 mb-4 pe-2">
                                    <a href="#" class="d-flex align-items-center text-gray-400 text-hover-primary me-5 mb-2">
                                    <i class="ki-duotone ki-profile-circle fs-4 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>{{$page.props.roles}}</a>
                                    <a href="#" class="d-flex align-items-center text-gray-400 text-hover-primary mb-2">
                                    <i class="ki-duotone ki-sms fs-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>{{$page.props.auth.user.email}}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold">
                    <li class="nav-item mt-2">
                        <a class="nav-link text-active-primary ms-0 me-10 py-5 active" data-bs-toggle="tab" href="#kt_tab_pane_1">Resumen General</a>
                    </li>
                    <li class="nav-item mt-2">
                        <a class="nav-link text-active-primary ms-0 me-10 py-5" data-bs-toggle="tab" href="#kt_tab_pane_2">Información</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="tab-content" id="myTabProfile">

            <div class="tab-pane fade show active" id="kt_tab_pane_1" role="tabpanel">
                <SessionsLogs :user="$page.props.auth.user"/>
            </div>

            <div class="tab-pane fade" id="kt_tab_pane_2" role="tabpanel">

                <div v-if="$page.props.jetstream.canUpdateProfileInformation">
                    <UpdateProfileInformationForm :user="$page.props.auth.user" />
                </div>

                <div v-if="$page.props.jetstream.canUpdatePassword">
                    <UpdatePasswordForm />
                </div>

                <div v-if="$page.props.jetstream.canManageTwoFactorAuthentication">
                    <TwoFactorAuthenticationForm
                        :requires-confirmation="confirmsTwoFactorAuthentication"
                    />
                </div>

                <LogoutOtherBrowserSessionsForm :sessions="sessions" />

                <div v-if="$page.props.jetstream.hasAccountDeletionFeatures">
                    <DeleteUserForm />
                </div>

            </div>
        </div>
    </App>
</template>
