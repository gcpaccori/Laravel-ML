<script setup>
    import axios from 'axios'
    import { onMounted, ref, watch, computed } from 'vue';
    import * as ElementPlusIconsVue from '@element-plus/icons-vue'
    import { ElMessage } from 'element-plus'
    import useSubmitForm from '@/Composables/useSubmitForm';
    import FormSistema from './FormSistema.vue';
    import FormAccions from './FormAccions.vue';

    const { loading, progress, errors, submitForm } = useSubmitForm();

    const props = defineProps({
        modelValue: Boolean,
        dataForm: {
            type: Object,
            required: false,
            default: null
        },
    });

    const dialogVisible = ref(props.modelValue);
    const dialogSistema = ref(false);
    const dialogAccion = ref(false);

    const iconOptions = Object.entries(ElementPlusIconsVue).map(([name, component]) => ({
        label: name,
        value: name,
        component,
    }));
    const form = ref({
        sistema_id : '',
        name : '',
        cod_father : '',
        url : '',
        icon : '',
        order : '',
        active : true,
        acciones: []
    });

    const optionsSistemas = ref([]);
    const optionsAcciones = ref([]);
    const optionsPadre = ref([]);
    const selectAccion = ref(null);

    const emit = defineEmits(['update:modelValue', 'saved']);

    const submitFormulario = () => {
        const routeName = props.dataForm ? 'seguridad.modulos.update' : 'seguridad.modulos.store';
        submitForm({
            url: props.dataForm
                ? route(routeName, props.dataForm.id)
                : route(routeName),
            data: form.value,
            method: props.dataForm ? 'put' : 'post',
            emit,
            onSuccess: (response) => {
                dialogVisible.value = false;
                ElMessage({
                    message: response.data.message,
                    type: 'success',
                });
                resetForm();
            }
        });
    };

    const resetForm = () => {
        for (const key in form.value) {
            form.value[key] = '';
        }
        errors.value = {};
        form.value.acciones = [];
        form.value.active = true;
        optionsPadre.value = [];
    };

    const sistemasOptions = async () => {
        const response = await axios.get(route('seguridad.sistemas.options'));
        optionsSistemas.value = response.data.data;
    }

    const accionesOptions = async () => {
        const response = await axios.get(route('seguridad.accions.options'));
        optionsAcciones.value = response.data.data;
    }

    const padreOptions = async ( id = false ) => {
        if (id) {
            const response = await axios.get(route('seguridad.padres', id));
            optionsPadre.value = response.data;
        }else{
            optionsPadre.value = [];
        }
    }

    // Calcula las opciones disponibles quitando las ya seleccionadas
    const opcionesDisponibles = computed(() =>
        optionsAcciones.value.filter(
            (opt) => !form.value.acciones.find(sel => sel.id === opt.id)
        )
    )

    const agregarAccion = async () => {
        const accion = await optionsAcciones.value.find(item => item.id === selectAccion.value)
        if (accion) {
            form.value.acciones.push(accion)
            selectAccion.value = null
        }
    }

    const quitarAccion = (id) => {
        form.value.acciones = form.value.acciones.filter(item => item.id !== id)
    }

    // AGREGAR SISTEMA
    const savedSistema = async (sistema) => {
        await sistemasOptions();
        form.value.sistema_id = sistema.id
        dialogSistema.value = false;
    }

    // AGREGAR ACCION
    const savedAccion = async (accion) => {
        await accionesOptions();
        selectAccion.value = accion.id;
        await agregarAccion();
        dialogAccion.value = false;
    }


    // Sincronizar cambios del padre
    watch(() => props.modelValue, (val) => {
        dialogVisible.value = val;
        if (val) {
            resetForm();
            if (props.dataForm) {
                form.value = { ...props.dataForm };
                padreOptions( form.value.sistema_id );
            }
        }
    });

    // Emitir cambios al padre
    watch(dialogVisible, (val) => {
        emit('update:modelValue', val);
    });

    onMounted(() => {
        sistemasOptions();
        accionesOptions();
    })
</script>

<template>
  <el-form @submit.prevent="submitFormulario" :model="form" label-position="top" class="w-100">
    <DialogForm v-model="dialogVisible" :title="props.dataForm ? 'Formulario Editar Módulo' : 'Formulario Registrar Módulo'" width="50%">
        <el-row :gutter="20">
            <el-col :lg="12">
                <el-form-item label="Nombre" :error="errors.name?.[0]" required>
                    <el-input v-model="form.name" />
                </el-form-item>
            </el-col>

            <el-col :lg="12">
                <el-form-item :error="errors.sistema_id?.[0]" required>
                  <template #label>
                      <span>Sistema </span>
                      <a @click.prevent="dialogSistema = true" class="text-primary text-hover-info cursor-pointer">[+Nuevo]</a>
                  </template>
                  <el-select  @change="padreOptions(form.sistema_id)" v-model="form.sistema_id" placeholder="Seleccione un Sistema" filterable clearable>
                      <el-option
                          v-for="item in optionsSistemas"
                          :key="item.id"
                          :label="item.name"
                          :value="item.id"
                      >
                          {{ item.name }}
                      </el-option>
                  </el-select>
                </el-form-item>
            </el-col>
        </el-row>

        <el-row :gutter="20">
            <el-col :lg="12">
                <el-form-item label="Padre" :error="errors.cod_father?.[0]">
                  <el-select  v-model="form.cod_father" placeholder="Seleccione un padre" filterable clearable>
                      <el-option
                          v-for="item in optionsPadre"
                          :key="item.id"
                          :label="item.name"
                          :value="item.id"
                      >
                          {{ item.name }}
                      </el-option>
                  </el-select>
                </el-form-item>
            </el-col>

            <el-col :lg="12">
                <el-form-item :error="errors.acciones?.[0]">
                  <template #label>
                      <span>Acciones </span>
                      <a @click.prevent="dialogAccion = true" class="text-primary text-hover-info cursor-pointer">[+Nuevo]</a>
                  </template>
                  <el-select @change="agregarAccion" v-model="selectAccion" placeholder="Seleccione las acciones" filterable clearable>
                      <el-option
                          v-for="item in opcionesDisponibles"
                          :key="item.id"
                          :label="item.accion"
                          :value="item.id"
                      >
                            <div class="d-flex align-items-center">
                                <el-icon :size="20">
                                    <component :is="item.icon" />
                                </el-icon>
                                <div class="ms-2">
                                    {{ item.accion }}
                                </div>
                            </div>
                      </el-option>
                  </el-select>
                </el-form-item>
            </el-col>
        </el-row>

        <el-row :gutter="20">
            <el-col :lg="12">
                <el-row :gutter="20">
                    <el-col :lg="12">
                        <el-form-item label="URL" :error="errors.url?.[0]">
                            <el-input v-model="form.url" />
                            <el-text size="small" class="text-muted">* Ingresar url en singular: Ejem. 'usuario'</el-text>
                        </el-form-item>
                    </el-col>

                    <el-col :lg="12">
                        <el-form-item label="Orden" :error="errors.order?.[0]">
                            <el-input type="number" :min="0" v-model="form.order" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="20">
                    <el-col :lg="18">
                        <el-form-item label="Icono" :error="errors.icon?.[0]" required>
                          <el-select v-model="form.icon" placeholder="Seleccione un ícono" filterable clearable>
                              <el-option
                                  v-for="item in iconOptions"
                                  :key="item.value"
                                  :label="item.label"
                                  :value="item.value"
                              >
                                  <div class="d-flex align-items-center">
                                      <el-icon :size="20">
                                          <component :is="item.label" />
                                      </el-icon>
                                      <div class="ms-2">
                                          {{ item.label }}
                                      </div>
                                  </div>
                              </el-option>
                          </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :lg="6">
                        <el-form-item label="Activo" :error="errors.active?.[0]">
                            <el-switch class="ml-2" v-model="form.active" style="--el-switch-on-color: #13ce66; --el-switch-off-color: #ff4949"/>
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-col>
            <el-col :lg="12">
                <el-row :gutter="20">
                    <el-col :lg="24" class="mb-2">
                        <el-table :data="form.acciones" style="width: 100%;">
                            <el-table-column label="Acción">
                                <template #default="{ row }">
                                    <div class="d-flex align-items-center">
                                        <el-icon :size="18">
                                            <component :is="row.icon" />
                                        </el-icon>
                                        <div class="ms-2">
                                            {{ row.accion }}
                                        </div>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="Opciones">
                                <template #default="{ row }">
                                    <div class="text-center">
                                        <el-button size="small" type="danger" icon="Delete" @click="quitarAccion(row.id)" />
                                    </div>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-col>
                </el-row>
            </el-col>
        </el-row>

        <template #footer>
            <div class="dialog-footer">
                <el-button type="primary" native-type="submit" :loading="loading">{{props.dataForm ? 'Actualizar' : 'Registrar'}}</el-button>
                <el-button type="danger" @click="dialogVisible = false">Cancelar</el-button>
            </div>
        </template>
    </DialogForm>
  </el-form>

  <FormSistema v-model="dialogSistema" @saved="savedSistema"/>
  <FormAccions v-model="dialogAccion" @saved="savedAccion"/>
</template>



