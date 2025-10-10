# Test de Importación de Usuarios con Plan

## Preparación

Antes de ejecutar las pruebas, asegúrate de:

1. Tener usuarios pendientes en la tabla `missing_users`
2. Tener los planes creados en Baremetrics (creetelo_mensual, creetelo_anual)
3. Tener configurado el entorno correcto en `.env`

## Verificación de Usuarios Pendientes

```bash
# Ver usuarios pendientes
php artisan tinker

# En tinker, ejecutar:
\App\Models\MissingUser::where('import_status', 'pending')->count();

# Ver los primeros 5 usuarios pendientes con sus tags
\App\Models\MissingUser::where('import_status', 'pending')->limit(5)->get(['id', 'email', 'name', 'tags']);
```

## Test 1: Importar 5 Usuarios de Prueba

### Pasos:

1. **Acceder a la vista**:
   ```
   URL: https://baremetrics.local/admin/ghl-comparison/20/missing-users
   ```

2. **Filtrar usuarios pendientes**:
   - En el dropdown de "Estado", seleccionar "Pendientes"
   - Clic en el botón de búsqueda

3. **Iniciar importación de prueba**:
   - Clic en el botón amarillo "Prueba"
   - Seleccionar "Importar 5 usuarios"
   - Confirmar la acción

4. **Esperar respuesta**:
   - El proceso puede tardar 1-2 segundos por usuario (5-10 segundos total)
   - Verás un mensaje de éxito con el resumen

5. **Verificar resultados**:
   - Cambiar filtro a "Importados"
   - Debes ver 5 usuarios con:
     - Estado: "✓ Importado" (en verde)
     - OID Baremetrics: Código único (ej: ghl_17339485821234)
     - Notas: "Importado con plan: creetelo_mensual - Suscripción creada"
   - Las filas deben tener fondo verde claro

### Verificación en Base de Datos:

```bash
php artisan tinker

# Ver usuarios importados
\App\Models\MissingUser::where('import_status', 'imported')->get(['id', 'email', 'baremetrics_customer_id', 'import_notes']);

# Ver el último usuario importado
\App\Models\MissingUser::where('import_status', 'imported')->latest('imported_at')->first();
```

### Verificación en Baremetrics:

1. Copiar el OID de uno de los usuarios (clic en ícono de copiar)
2. Ir a Baremetrics > Customers
3. Buscar por el OID
4. Verificar que existe el cliente, la suscripción y el plan

## Test 2: Verificar OID Copiado

### Pasos:

1. En la tabla de usuarios importados
2. Buscar un usuario
3. Hacer clic en el ícono de copiar junto al OID
4. Debe aparecer una alerta: "OID copiado al portapapeles: ghl_xxxxx"
5. Pegar en un editor de texto para verificar

## Test 3: Verificar Logs

```bash
# Ver logs en tiempo real durante la importación
tail -f storage/logs/laravel.log

# Buscar el proceso de importación
grep "INICIANDO IMPORTACIÓN MASIVA" storage/logs/laravel.log

# Ver detalles de usuarios procesados
grep "Procesando usuario" storage/logs/laravel.log | tail -5

# Ver usuarios importados exitosamente
grep "Usuario importado exitosamente" storage/logs/laravel.log | tail -5
```

## Test 4: Verificar Datos Guardados

### En Base de Datos:

```sql
-- Ver usuarios importados con sus datos
SELECT 
    id,
    email,
    name,
    tags,
    import_status,
    baremetrics_customer_id,
    import_notes,
    imported_at
FROM missing_users
WHERE import_status = 'imported'
ORDER BY imported_at DESC
LIMIT 5;
```

### Verificación de Campos:

Para cada usuario importado, verificar:

- ✅ `import_status` = 'imported'
- ✅ `baremetrics_customer_id` empieza con 'ghl_'
- ✅ `import_notes` contiene el plan (ej: "Importado con plan: creetelo_mensual")
- ✅ `imported_at` tiene fecha y hora actual
- ✅ `import_error` es NULL

## Test 5: Importar 10 Usuarios

Similar al Test 1, pero seleccionando "Importar 10 usuarios" en el menú de prueba.

## Test 6: Importación Masiva Completa

⚠️ **Solo ejecutar después de validar Tests 1-5**

### Pasos:

1. Verificar que las pruebas anteriores fueron exitosas
2. Ir a la vista de usuarios pendientes
3. Clic en "Importar Todos (Con Plan)" (botón azul)
4. Confirmar la acción
5. Esperar a que termine (puede tardar varios minutos según cantidad de usuarios)
6. Verificar el mensaje de resumen
7. Cambiar filtro a "Importados"
8. Verificar que todos tienen OID y estado correcto

## Resultados Esperados

### Usuario Exitoso:

```
Estado: ✓ Importado
OID: ghl_17339485821234
Notas: Importado con plan: creetelo_mensual - Suscripción creada
```

### Usuario sin Plan Tag:

```
Estado: Fallido
Error: No se pudo determinar el plan del cliente. Tags: otros_tags
```

### Usuario con Error:

```
Estado: Fallido
Error: [mensaje de error específico]
```

## Marcadores de Usuarios Importados

Los 5 usuarios de prueba importados se pueden identificar fácilmente porque:

1. **Fondo verde claro** en toda la fila
2. **Badge verde** con ✓ "Importado"
3. **OID en verde** en formato código
4. **Fecha de importación** reciente
5. **Notas de importación** con el plan asignado

## Checklist de Validación

Después de importar 5 usuarios, verificar:

- [ ] Los 5 usuarios tienen estado "Importado"
- [ ] Todos tienen OID que empieza con 'ghl_'
- [ ] Las notas indican el plan correcto
- [ ] El OID se puede copiar al hacer clic
- [ ] Las filas tienen fondo verde
- [ ] Los usuarios se pueden filtrar por estado "Importados"
- [ ] Los logs muestran el proceso completo
- [ ] Los usuarios existen en Baremetrics
- [ ] Las suscripciones están activas en Baremetrics
- [ ] Los planes están correctamente asignados

## Troubleshooting

### No aparecen usuarios pendientes

```bash
# Verificar en base de datos
php artisan tinker
\App\Models\MissingUser::where('import_status', 'pending')->count();

# Si es 0, cambiar algunos a pending
\App\Models\MissingUser::where('import_status', 'failed')->limit(5)->update(['import_status' => 'pending']);
```

### Error "No se pudo obtener el source ID"

```bash
# Verificar configuración
php artisan tinker
config('services.baremetrics.production_source_id');

# Debe retornar: d9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8
```

### Error "No se pudo determinar el plan"

El usuario no tiene tags válidos. Verificar:
```bash
php artisan tinker
\App\Models\MissingUser::find(ID_USUARIO)->tags;
```

Debe contener: `creetelo_mensual`, `créetelo_mensual`, `creetelo_anual`, o `créetelo_anual`

### OID no se copia

- Verificar que el navegador soporte `navigator.clipboard`
- Usar navegador moderno (Chrome, Firefox, Edge)
- Si no funciona, usar el fallback manual (seleccionar y copiar)

## Comandos Útiles

```bash
# Ver usuarios importados
php artisan tinker
\App\Models\MissingUser::where('import_status', 'imported')->count();

# Ver usuarios pendientes
\App\Models\MissingUser::where('import_status', 'pending')->count();

# Ver usuarios fallidos
\App\Models\MissingUser::where('import_status', 'failed')->count();

# Ver último importado
\App\Models\MissingUser::where('import_status', 'imported')->latest('imported_at')->first();

# Cambiar usuarios a pending para pruebas
\App\Models\MissingUser::where('import_status', 'failed')->limit(5)->update([
    'import_status' => 'pending',
    'baremetrics_customer_id' => null,
    'imported_at' => null,
    'import_error' => null
]);
```

## Notas Finales

- Los usuarios importados quedan marcados permanentemente
- El OID es único y no se repite
- Si necesitas reimportar, usa la función "Borrar usuarios importados"
- Siempre hacer pruebas antes de importación masiva
- Revisar logs durante el proceso
- Verificar en Baremetrics después de importar

## Próximos Pasos

Después de validar con 5 usuarios:
1. Si todo funciona correctamente → Importar 10 usuarios
2. Si los 10 funcionan → Importar todos los pendientes
3. Verificar periódicamente el progreso
4. Revisar usuarios fallidos y corregir
5. Mantener registro de los OIDs para referencia futura
