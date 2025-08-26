export default function useUbigeo() {

  // Funciones reutilizables (carga en cualquier ref)
  const loadCountriesTo = async (targetRef) => {
    try {
      const response = await axios.get(route('api.countries'))
      targetRef.value = response.data.data
    } catch (error) {
      console.error('Error al cargar países:', error);
      targetRef.value = [];
    }
  }
  const loadDepartamentosTo = async (targetRef) => {
    try {
        const response = await axios.get(route('api.departamentos'))
        targetRef.value = response.data
    } catch (error) {
        console.error('Error al cargar departamentos:', error);
        targetRef.value = [];
    }
  }

  const loadProvinciasTo = async (departamentoId, targetRef) => {
    try {
      const response = await axios.get(route('api.provincias', departamentoId));
      targetRef.value = response.data;
    } catch (error) {
      console.error('Error al cargar provincias dinámicas:', error);
      targetRef.value = [];
    }
  }

  const loadDistritosTo = async (provinciaId, targetRef) => {
    try {
      const response = await axios.get(route('api.distritos', provinciaId));
      targetRef.value = response.data;
    } catch (error) {
      console.error('Error al cargar distritos dinámicos:', error);
      targetRef.value = [];
    }
  }

  return {
    loadCountriesTo,
    loadDepartamentosTo,
    loadProvinciasTo,
    loadDistritosTo,
  }
}
