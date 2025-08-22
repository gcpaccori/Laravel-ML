import { ref } from 'vue';
import axios from 'axios';

export default function useSubmitForm() {
  const loading = ref(false);
  const progress = ref(null);
  const errors = ref({});

  const submitForm = async ({ url, method, data, emit, onSuccess }) => {
    loading.value = true;
    progress.value = null;
    errors.value = {};

    try {
      const response = await axios[method](url, data, {
        onUploadProgress: (event) => {
          if (event.lengthComputable) {
            const percent = Math.round((event.loaded * 100) / event.total);
            progress.value = { percentage: percent };
          }
        },
      });

      emit && emit('saved', response.data.data);
      onSuccess && onSuccess(response);
    } catch (error) {
      if (error.response && error.response.status === 422) {
        errors.value = error.response.data.errors;
      }
    } finally {
      loading.value = false;
    }
  };

  return {
    loading,
    progress,
    errors,
    submitForm,
  };
}
