# Alarmas derivadas de modelos

## Alcance

Este modulo presenta y persiste exclusivamente alarmas cuyo origen es la salida de un modelo versionado. No sustituye las alarmas generales de Laravel ni convierte lecturas directas de sensores en alarmas ML.

- `OD medido por debajo de un umbral`: alarma directa del sistema general, fuera de este modulo.
- `OD proyectado por un modelo activo por debajo de una politica aprobada`: alarma de modelo, dentro de este modulo.
- `Sensor de luz desconectado`: alarma IoT, fuera de este modulo.
- `Probabilidad modelada de baja respuesta alimentaria bajo el fotoperiodo observado`: alarma de modelo, dentro de este modulo cuando exista un artefacto activo.

## Hallazgo sobre el avance existente

La captura del esquema vivo muestra la tabla compartida `alarmas` con los campos esperados (`piscigranja_id`, `piscina_id`, `modulo`, `parametro`, `nivel`, `valor_detectado`, `titulo`, `mensaje`, `estado` y datos de reconocimiento/resolucion). Sin embargo, al 26 de agosto de 2026 el repositorio remoto no contiene la migracion, el modelo `Alarma` ni el evento `AlarmaGenerada`; `origin/main` permanece en el commit `11aac9d` del 14 de julio.

Para evitar una arquitectura paralela:

1. La migracion `2026_08_24_000001_create_alarmas_table_if_missing.php` crea la tabla solo en instalaciones que no la tengan.
2. Si la tabla ya existe en la VM, la migracion no la modifica.
3. El rollback de este modulo nunca elimina `alarmas`, porque es una tabla compartida y pertenece al sistema general.
4. La trazabilidad especifica se guarda en `alarma_modelo_evidencias`, enlazada uno a uno con la alarma general.

## Flujo operativo

```text
MySQL real
   -> FastAPI local ejecuta el modelo y evalua una politica aprobada
   -> contrato de evento con productive=true
   -> Laravel valida modelo, piscina e idempotencia
   -> alarmas (modulo=inteligencia)
   -> alarma_modelo_evidencias (modelo, version, horizonte, prediccion, evidencia)
   -> AlarmaGenerada
   -> canal privado alarmas.modelos
   -> pestaña Alarmas de modelos
```

Laravel no vuelve a calcular el modelo. FastAPI no administra acuses de usuario. La tabla compartida conserva `activa`, `reconocida` y `resuelta`; la tabla hija conserva el linaje ML.

## Regla de seguridad funcional

`ModelAlarmPersistenceService` exige simultaneamente:

- `productive === true` en el evento.
- Un `model.code` permitido.
- Una piscina Laravel resoluble.
- Un `source_event_id` nuevo.

Los snapshots antiguos, observaciones tecnicas, eventos de sensores y escenarios manuales se rechazan. El escenario de luz siempre devuelve `can_emit=false` y `status=not_emitted`.

## Mapeo a la tabla compartida

| Modelo | `modulo` | `parametro` |
|---|---|---|
| `WATER_QUALITY_INDEX_ICA` | `inteligencia` | `indice_calidad_agua` |
| `WATER_QUALITY_INDEX_ICA_SVM` | `inteligencia` | `indice_calidad_agua` |
| `TILAPIA_GROWTH_TEMPERATURE` | `inteligencia` | `crecimiento` |
| `SVM_OD_FORECAST_1H` | `inteligencia` | `oxigeno_disuelto` |
| `OXYGEN_STATUS_MODEL` | `inteligencia` | `oxigeno_disuelto` |
| `LIGHT_FEED_RESPONSE_CLASSIFIER_V1` | `inteligencia` | `luz_subacuatica` |

Severidades recibidas se normalizan al enum existente:

| Contrato | Laravel |
|---|---|
| `info`, `normal` | `normal` |
| `warning` o valor desconocido | `advertencia` |
| `critical` | `critico` |
| `emergency` | `emergencia` |

La severidad solo se usa cuando una politica de modelo ya fue aprobada. No hay umbrales cientificos ocultos en Vue ni en el adaptador Laravel.

## Evidencia ML

`alarma_modelo_evidencias` agrega:

- `source_event_id` unico para idempotencia.
- `model_code`, `model_version` y `asset_id`.
- `policy_code`.
- `horizon_minutes` y `prediction_for`.
- `predicted_value`.
- `evidence` JSON con el contrato completo recibido.

El indice unico impide crear dos alarmas para el mismo evento aunque coincidan la tarea programada, una recarga manual y una reconexion del navegador.

## Contrato esperado de FastAPI

El endpoint objetivo es:

```text
GET /api/v1/ponds/{pond_id}/model-alerts/dashboard
```

Ejemplo minimo de un evento persistible:

```json
{
  "id": "MODEL-EVENT-001",
  "event_type": "triggered",
  "productive": true,
  "pond_id": "LEGACY-POND-1",
  "alarm_code": "MODEL_OD_THRESHOLD_FORECAST",
  "title": "Oxigeno proyectado bajo",
  "message": "La proyeccion cruza el limite aprobado dentro de una hora.",
  "suggested_severity": "critical",
  "predicted_value": 3.8,
  "prediction_for": "2026-08-26T18:00:00Z",
  "horizon_minutes": 60,
  "model": {
    "code": "SVM_OD_FORECAST_1H",
    "version": "v2",
    "asset_id": "ASSET-..."
  },
  "policy": {
    "code": "OD-LOW-1H-v1"
  },
  "evidence": {
    "input_window_start": "2026-08-26T16:00:00Z",
    "input_window_end": "2026-08-26T17:00:00Z",
    "data_quality": "validated"
  }
}
```

Mientras ese contrato nativo no exista, `ModelAlertDashboardService` usa los endpoints locales actuales y presenta sus resultados como evidencia tecnica, sin persistirlos como alarmas productivas.

## Estado honesto de los modelos

| Modelo | Estado inicial para alarmas |
|---|---|
| ICA | Formula disponible; politica de deterioro pendiente de aprobacion. |
| Crecimiento | Modo sombra hasta validar biometria longitudinal y banda de error local. |
| SVM/SVR de OD | Modo sombra; el candidato actual pierde contra persistencia en validacion temporal. |
| Luz y respuesta alimentaria | Bloqueado por datos; hoy no existe sensor de luz ni etiqueta de respuesta alimentaria. |

Por ello, es correcto que la bandeja pueda iniciar con cero alarmas productivas. Inventar una proyeccion o activar una politica sin evidencia daria una falsa sensacion de proteccion.

## Sensor de luz y alimentacion

La card y el gemelo aceptan un escenario manual de fotoperiodo para diseno y demostracion. Ese escenario no es entrenamiento ni inferencia.

Para habilitar el modelo real se debe registrar, por piscina y con el timestamp operativo de 2020 en adelante:

- Iluminancia subacuatica en lux o PPFD, con unidad y calibracion.
- Posicion/profundidad del sensor y estado del equipo.
- Fotoperiodo y fase (amanecer, dia, atardecer, oscuridad).
- Evento de alimento: hora, racion ofrecida y tipo.
- Respuesta etiquetada: consumo, remanente o actividad alimentaria.
- Temperatura, OD, biomasa y edad/tamano del lote.

Primero se entrena un baseline interpretable y se valida cronologicamente por piscina. Solo despues se activa `LIGHT_FEED_RESPONSE_CLASSIFIER_V1` y su politica `MODEL_LIGHT_FEED_RESPONSE_RISK`.

## Rendimiento y tolerancia a fallos

- La pagina Inertia se entrega sin esperar el dashboard pesado.
- Vue muestra un estado de carga visible.
- Las llamadas FastAPI usan URL interna de Docker: `http://aquaculture_backend:8000/api/v1`.
- Hay cache corta y cache stale; un timeout no borra el ultimo resultado valido.
- El estado de luz usa un endpoint separado y liviano para no duplicar el dashboard de modelos al abrir el gemelo.
- No se consulta `/ml/model-assets` desde la interfaz porque incluye payloads de artefactos y produce respuestas de varios megabytes.

## Ejecucion programada

Laravel registra:

```bash
php artisan model-alerts:sync --window=24
```

La tarea se programa cada cinco minutos con `withoutOverlapping(10)`. En produccion debe existir un unico scheduler activo:

```bash
php artisan schedule:work
```

Cuando FastAPI implemente un outbox o webhook idempotente, ese transporte debe reemplazar el sondeo sin cambiar la tabla compartida ni la interfaz.

## Despliegue

1. Confirmar que `AQUACULTURE_BACKEND_URL` apunta al servicio Docker local, nunca a Vercel.
2. Ejecutar `php artisan migrate --force`.
3. Ejecutar `php artisan optimize:clear` y luego la estrategia de cache del entorno.
4. Construir los assets con dependencias Composer y Node instaladas.
5. Iniciar o verificar scheduler y Reverb.
6. Ejecutar `php artisan model-alerts:sync --pond=1 --refresh`.
7. Abrir Alarmas de modelos y confirmar `meta.alarm_storage.available=true`.
8. Repetir el sync y comprobar que el contador pasa a repetida, no a creada.

## Criterios de aceptacion

- No se crea una segunda tabla general de alarmas.
- Un evento productivo crea una fila en `alarmas` y una en `alarma_modelo_evidencias`.
- Repetir el mismo evento no crea filas nuevas.
- Un evento `productive=false` nunca llega a `alarmas`.
- Un origen de sensor no permitido nunca llega a `alarmas`.
- El escenario manual de luz nunca genera alarma.
- AlarmaGenerada se emite despues de persistir y la pantalla se actualiza por canal privado.
- Si FastAPI demora, la pagina carga y usa el ultimo resultado disponible.
- El resto de alarmas, reconocimientos y resoluciones de Laravel permanece intacto.
