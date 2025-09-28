import { ref, unref } from 'vue';

/**
 * Composable para resetear formularios a su estado inicial
 * @param {Object} initialValues - Valores iniciales del formulario
 * @returns {Object} - form (reactivo) y resetForm (función)
 */
export function useFormReset(initialValues) {
    // Crear una copia profunda de los valores iniciales
    const getInitialValues = () => {
        if (typeof initialValues === 'function') {
            return initialValues();
        }
        return JSON.parse(JSON.stringify(initialValues));
    };

    // Crear el objeto reactivo del formulario
    const form = ref(getInitialValues());

    // Función para resetear el formulario
    const resetForm = () => {
        const initial = getInitialValues();

        // Resetear cada propiedad individualmente para mantener la reactividad
        Object.keys(initial).forEach(key => {
            form.value[key] = initial[key];
        });
    };

    // Función para setear valores al formulario (útil para edición)
    const setFormValues = (newValues) => {
        Object.keys(newValues).forEach(key => {
            if (key in form.value) {
                form.value[key] = newValues[key];
            }
        });
    };

    // Función para obtener solo los valores del formulario (sin reactividad)
    const getFormValues = () => {
        return unref(form);
    };

    return {
        form,
        resetForm,
        setFormValues,
        getFormValues
    };
}
