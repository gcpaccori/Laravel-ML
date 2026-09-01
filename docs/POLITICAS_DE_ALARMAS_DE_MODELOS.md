# Politicas de alarmas de modelos

La tabla `model_alert_policies` es propiedad del modulo de alarmas de modelos. FastAPI la consulta en modo de solo lectura desde la misma base MySQL local; no usa Vercel ni otro servicio externo.

Una prediccion solo se convierte en una fila de `alarmas` cuando se cumplen todos estos requisitos:

1. El modelo tiene un artefacto o formula elegible para uso productivo.
2. La politica asociada esta en estado `approved`.
3. El valor calculado cumple `operator` y `threshold`.
4. Laravel resuelve la piscina y deduplica el `source_event_id` en `alarma_modelo_evidencias`.

No se insertan umbrales por defecto. El responsable tecnico debe aprobara una politica de forma explicita. Por ejemplo:

```bash
php artisan model-alerts:approve-policy OD-SVM-LOW-1H \
  --model=SVM_OD_FORECAST_1H \
  --piscina=1 \
  --operator=lte \
  --threshold=5 \
  --unit=mg/L \
  --severity=advertencia \
  --reason="Limite aprobado por el responsable acuicola" \
  --approved-by=1
```

El comando valida el modelo, la piscina, la severidad, el valor y la justificacion. Una politica global se crea omitiendo `--piscina`. Cambiar el umbral o la formula debe incrementar `--policy-version` y dejar una justificacion nueva.

El modelo de luz no puede emitir todavia: requiere lecturas reales de luz vinculadas con racion y respuesta alimentaria, un artefacto validado y una politica aprobada. Los escenarios manuales del gemelo digital nunca generan alarmas.
