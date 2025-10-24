<?php

// Simular envío del formulario de survey
$data = [
    'customer_id' => 'cus_QxVRFKAf5DKL7q',
    'email' => 'melortegag@gmail.com',
    'reason' => 'No conecté con el estilo, enfoque o dinámica de la comunidad',
    'additional_comments' => 'Es una prueba de cancelación'
];

echo "=== Simulando envío del survey ===\n\n";
echo "Datos del formulario:\n";
echo "- Customer ID: " . $data['customer_id'] . "\n";
echo "- Email: " . $data['email'] . "\n";
echo "- Reason: " . $data['reason'] . "\n";
echo "- Comments: " . $data['additional_comments'] . "\n\n";

echo "Para probar la página de confirmación, visita:\n";
echo "https://baremetrics.local/gohighlevel/cancellation/survey\n\n";

echo "Y envía el formulario con estos datos usando una herramienta como Postman o el navegador.\n\n";

echo "O usa este comando curl:\n";
echo "curl -X POST \"https://baremetrics.local/gohighlevel/cancellation/survey/save\" \\\n";
echo "  -H \"Content-Type: application/x-www-form-urlencoded\" \\\n";
echo "  -d \"customer_id=" . urlencode($data['customer_id']) . "&email=" . urlencode($data['email']) . "&reason=" . urlencode($data['reason']) . "&additional_comments=" . urlencode($data['additional_comments']) . "&_token=YOUR_CSRF_TOKEN\"\n\n";

echo "=== Simulación completada ===\n";