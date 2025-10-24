#!/bin/bash
# Script para monitorear los logs durante la prueba de cancelación

echo "=== MONITOREANDO LOGS DE CANCELACIÓN ==="
echo "Presiona Ctrl+C para detener"
echo ""

tail -f storage/logs/laravel.log | grep --line-buffered -i "cus_T1weUTpi3zzsSV\|cancelación\|barecancel\|survey\|stripe.*cancel"
