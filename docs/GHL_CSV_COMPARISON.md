# Comando para Comparar GHL CSV con Baremetrics

## Descripción
Este comando lee un archivo CSV exportado de GoHighLevel y lo compara con los usuarios de Baremetrics para identificar usuarios faltantes.

## Uso Básico

```bash
# Comparar todos los usuarios del CSV con Baremetrics
php artisan ghl:compare-csv --file=storage/csv/creetelo_ghl.csv

# Filtrar por tags específicos
php artisan ghl:compare-csv --file=storage/csv/creetelo_ghl.csv --tags=creetelo_mensual,creetelo_anual

# Excluir usuarios con ciertos tags
php artisan ghl:compare-csv --file=storage/csv/creetelo_ghl.csv --exclude-tags=unsubscribe

# Combinar filtros
php artisan ghl:compare-csv --file=storage/csv/creetelo_ghl.csv --tags=creetelo_mensual,creetelo_anual --exclude-tags=unsubscribe

# Limitar número de usuarios procesados
php artisan ghl:compare-csv --file=storage/csv/creetelo_ghl.csv --limit=100

# Guardar resultados en archivos
php artisan ghl:compare-csv --file=storage/csv/creetelo_ghl.csv --save

# Cambiar formato de salida
php artisan ghl:compare-csv --file=storage/csv/creetelo_ghl.csv --format=json
```

## Parámetros

- `--file`: Ruta al archivo CSV (por defecto: `storage/csv/creetelo_ghl.csv`)
- `--tags`: Tags a incluir separados por comas (operador OR)
- `--exclude-tags`: Tags a excluir separados por comas
- `--limit`: Límite de usuarios a procesar (0 = sin límite)
- `--format`: Formato de salida (`table` o `json`)
- `--save`: Guardar resultados en archivos JSON y CSV

## Estructura del CSV Esperada

El archivo CSV debe tener las siguientes columnas:
- Contact Id
- First Name
- Last Name
- Business Name
- Company Name
- Phone
- Email
- Created
- Last Activity
- Tags
- Additional Emails
- Additional Phones

## Archivos Generados

Cuando se usa `--save`, se generan dos archivos:
1. **JSON completo**: Contiene el resumen y todos los datos
2. **CSV usuarios faltantes**: Solo los usuarios que no están en Baremetrics

## Ejemplo Completo

```bash
# Procesar el archivo completo con filtros y guardar resultados
php artisan ghl:compare-csv \
  --file=storage/csv/creetelo_ghl.csv \
  --tags=creetelo_mensual,creetelo_anual \
  --exclude-tags=unsubscribe \
  --save
```

Este comando:
1. Lee el archivo CSV
2. Filtra usuarios que tengan los tags `creetelo_mensual` o `creetelo_anual`
3. Excluye usuarios con tag `unsubscribe`
4. Compara con usuarios de Baremetrics
5. Genera reportes en JSON y CSV
