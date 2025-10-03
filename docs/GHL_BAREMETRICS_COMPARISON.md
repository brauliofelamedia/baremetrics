# Comandos para Comparar Usuarios GHL vs Baremetrics

## Descripción

Estos comandos te permiten comparar los usuarios de GoHighLevel (GHL) con los usuarios de Baremetrics para identificar cuáles usuarios de GHL no están incluidos en Baremetrics, basándose en el correo electrónico. **También muestran qué usuarios SÍ están en ambos sistemas.**

## Comandos Disponibles

### 1. `ghl:show-complete-comparison` (Recomendado para resumen completo)

Comando específico para mostrar un resumen completo de la comparación.

```bash
php artisan ghl:show-complete-comparison
```

#### Opciones:
- `--tags`: Tags de GHL a incluir (separados por coma)
- `--exclude-tags`: Tags a excluir (separados por coma)
- `--limit`: Límite de usuarios a procesar

#### Ejemplos:

```bash
# Uso básico
php artisan ghl:show-complete-comparison

# Con tags específicos
php artisan ghl:show-complete-comparison --tags=creetelo_mensual,creetelo_anual

# Con límite personalizado
php artisan ghl:show-complete-comparison --limit=100
```

### 2. `ghl:list-missing-users` (Recomendado para análisis detallado)

Comando simple y directo para obtener la lista de usuarios faltantes con resumen completo.

```bash
php artisan ghl:list-missing-users
```

#### Opciones:
- `--tags`: Tags de GHL a incluir (separados por coma)
- `--exclude-tags`: Tags a excluir (separados por coma)
- `--limit`: Límite de usuarios a procesar
- `--format`: Formato de salida (table, list, json)
- `--save`: Guardar resultado en archivo

#### Ejemplos:

```bash
# Uso básico
php artisan ghl:list-missing-users

# Con tags específicos
php artisan ghl:list-missing-users --tags=creetelo_mensual,creetelo_anual

# Con límite y formato de tabla
php artisan ghl:list-missing-users --limit=50 --format=table

# Guardar resultado en archivo
php artisan ghl:list-missing-users --save --format=json

# Excluir tags específicos
php artisan ghl:list-missing-users --exclude-tags=unsubscribe,test
```

### 3. `ghl:compare-with-baremetrics`

Comando completo de comparación con más opciones.

```bash
php artisan ghl:compare-with-baremetrics
```

### 4. `ghl:missing-users`

Comando alternativo con enfoque específico en usuarios faltantes.

```bash
php artisan ghl:missing-users
```

## Configuración por Defecto

- **Tags incluidos**: `creetelo_mensual`, `creetelo_anual`, `créetelo_mensual`, `créetelo_anual`
- **Tags excluidos**: `unsubscribe`
- **Límite**: 100 usuarios
- **Formato**: table

## Proceso de Comparación

1. **Obtener usuarios de GHL**: Se obtienen usuarios filtrados por tags especificados
2. **Obtener usuarios de Baremetrics**: Se obtienen todos los emails de usuarios en Baremetrics
3. **Comparar**: Se identifican usuarios de GHL que NO están en Baremetrics
4. **Mostrar resultados**: Se muestran los usuarios faltantes Y los usuarios que SÍ están en ambos sistemas
5. **Estadísticas**: Se muestran estadísticas por tag para ambos grupos

## Resumen Completo Incluye

### 📊 Resumen General
- Total usuarios GHL (filtrados)
- Total emails Baremetrics
- Usuarios en AMBOS sistemas
- Usuarios GHL faltantes en Baremetrics
- Porcentajes de sincronización

### ✅ Usuarios Sincronizados
Lista de usuarios que SÍ están en ambos sistemas:
```
✅ USUARIOS QUE SÍ ESTÁN EN AMBOS SISTEMAS:
==========================================
• usuario@email.com - Nombre Usuario - Tags: creetelo_mensual
```

### ❌ Usuarios Faltantes
Lista de usuarios que NO están en Baremetrics:
```
⚠️ USUARIOS DE GHL FALTANTES EN BAREMETRICS:
=============================================
• usuario@email.com - Nombre Usuario - Tags: creetelo_mensual
```

### 📈 Estadísticas por Tag
Para ambos grupos de usuarios:
```
📈 ESTADÍSTICAS POR TAG - USUARIOS SINCRONIZADOS:
=====================================
• creetelo_mensual: 15 usuarios
• creetelo_anual: 8 usuarios

📈 ESTADÍSTICAS POR TAG - USUARIOS FALTANTES:
=====================================
• creetelo_mensual: 5 usuarios
• creetelo_anual: 2 usuarios
```

### 🎯 Resumen Final
```
🎯 RESUMEN FINAL:
==================
✅ ¡Perfecto! Todos los usuarios de GHL están sincronizados en Baremetrics
```
O
```
🎯 RESUMEN FINAL:
==================
⚠️ Hay 7 usuarios de GHL que necesitan ser importados a Baremetrics
✅ 23 usuarios ya están sincronizados correctamente
```

## Formatos de Salida

### Table (Tabla)
Muestra los resultados en formato de tabla con columnas:
- Email
- Nombre
- Teléfono
- Empresa
- Tags

### List (Lista)
Muestra los resultados en formato de lista simple:
```
• usuario@email.com - Nombre Usuario - Tags: creetelo_mensual
```

### JSON
Muestra los resultados en formato JSON estructurado.

### CSV
Genera un archivo CSV con los usuarios faltantes Y sincronizados.

## Guardar Resultados

Usa la opción `--save` para guardar los resultados en un archivo:

```bash
php artisan ghl:list-missing-users --save --format=csv
```

Los archivos se guardan en `storage/` con formato:
- `comparacion-completa-ghl-baremetrics-YYYY-MM-DD-HH-mm-ss.csv`
- `comparacion-completa-ghl-baremetrics-YYYY-MM-DD-HH-mm-ss.json`

## Ejemplos de Uso Comunes

### 1. Ver resumen completo rápido
```bash
php artisan ghl:show-complete-comparison
```

### 2. Análisis detallado con tabla
```bash
php artisan ghl:list-missing-users --limit=200 --format=table
```

### 3. Exportar lista completa para importación
```bash
php artisan ghl:list-missing-users --save --format=csv
```

### 4. Análisis de tags específicos
```bash
php artisan ghl:list-missing-users --tags=creetelo_mensual --exclude-tags=unsubscribe,test
```

### 5. Obtener datos en JSON para procesamiento
```bash
php artisan ghl:list-missing-users --format=json --save
```

## Interpretación de Resultados

### ✅ Todos los usuarios están sincronizados
```
✅ ¡Perfecto! Todos los usuarios de GHL están sincronizados en Baremetrics
```

### ⚠️ Usuarios faltantes encontrados
```
⚠️ Hay 7 usuarios de GHL que necesitan ser importados a Baremetrics
✅ 23 usuarios ya están sincronizados correctamente
```

### 📊 Ejemplo de resumen completo
```
📊 RESUMEN COMPLETO DE LA COMPARACIÓN
=====================================
👥 Total usuarios GHL (filtrados): 30
👥 Total emails Baremetrics: 25
✅ Usuarios en AMBOS sistemas: 23
❌ Usuarios GHL faltantes en Baremetrics: 7

📈 PORCENTAJES:
   • Sincronizados: 76.67%
   • Faltantes: 23.33%
```

## Troubleshooting

### Error: "No se encontraron usuarios de GHL"
- Verifica que los tags especificados existan en GHL
- Usa `php artisan ghl:count-users-by-tags` para verificar tags disponibles

### Error: "No se pudieron obtener usuarios de Baremetrics"
- Verifica la conexión con Baremetrics
- Usa `php artisan baremetrics:test-connection` para verificar

### Error: "Token de GoHighLevel inválido"
- El token se renovará automáticamente
- Si persiste, verifica las credenciales en la configuración

## Logs

Los logs se guardan en `storage/logs/laravel.log` con información detallada del proceso.

## Consideraciones de Rendimiento

- **Límite recomendado**: 100-200 usuarios por ejecución
- **Pausas automáticas**: El sistema incluye pausas entre requests para evitar rate limiting
- **Memoria**: Los comandos procesan usuarios en lotes para optimizar memoria

## Próximos Pasos

Después de identificar usuarios faltantes, puedes:

1. **Importar usuarios faltantes**:
   ```bash
   php artisan ghl:import-complete-to-baremetrics --limit=50
   ```

2. **Verificar importación**:
   ```bash
   php artisan baremetrics:verify-import
   ```

3. **Re-ejecutar comparación** para verificar que se importaron correctamente:
   ```bash
   php artisan ghl:show-complete-comparison
   ```

¡Los comandos están listos para usar! 🚀