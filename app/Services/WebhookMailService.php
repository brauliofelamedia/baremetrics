<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class WebhookMailService
{
    /**
     * Envía un correo electrónico a través de un webhook
     * 
     * @param string $email Dirección de correo del destinatario
     * @param string $subject Asunto del correo
     * @param string $view Nombre de la vista Blade
     * @param array $data Datos para la vista
     * @return bool Indica si el correo se envió correctamente
     */
    public function send(string $email, string $subject, string $view, array $data = []): bool
    {
        try {
            $webhookUrl = env('WEBHOOK_MAIL_URL');
            
            if (empty($webhookUrl)) {
                Log::error('WEBHOOK_MAIL_URL no está configurado en el archivo .env');
                return false;
            }

            // Renderizar la plantilla Blade a HTML
            $html = View::make($view, $data)->render();

            // Preparar los datos para el webhook
            $payload = [
                'email' => $email,
                'subject' => $subject,
                'html' => $html
            ];

            Log::info('Enviando correo vía webhook', [
                'email' => $email,
                'subject' => $subject,
                'webhook_url' => $webhookUrl
            ]);

            // Enviar la petición al webhook
            $response = Http::timeout(30)
                ->post($webhookUrl, $payload);

            if ($response->successful()) {
                Log::info('Correo enviado exitosamente vía webhook', [
                    'email' => $email,
                    'subject' => $subject,
                    'status' => $response->status()
                ]);
                return true;
            } else {
                Log::error('Error al enviar correo vía webhook', [
                    'email' => $email,
                    'subject' => $subject,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Excepción al enviar correo vía webhook', [
                'email' => $email,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Envía un correo con HTML personalizado directamente
     * 
     * @param string $email Dirección de correo del destinatario
     * @param string $subject Asunto del correo
     * @param string $html Contenido HTML del correo
     * @return bool Indica si el correo se envió correctamente
     */
    public function sendRaw(string $email, string $subject, string $html): bool
    {
        try {
            $webhookUrl = env('WEBHOOK_MAIL_URL');
            
            if (empty($webhookUrl)) {
                Log::error('WEBHOOK_MAIL_URL no está configurado en el archivo .env');
                return false;
            }

            // Preparar los datos para el webhook
            $payload = [
                'email' => $email,
                'subject' => $subject,
                'html' => $html
            ];

            Log::info('Enviando correo vía webhook (raw HTML)', [
                'email' => $email,
                'subject' => $subject,
                'webhook_url' => $webhookUrl
            ]);

            // Enviar la petición al webhook
            $response = Http::timeout(30)
                ->post($webhookUrl, $payload);

            if ($response->successful()) {
                Log::info('Correo enviado exitosamente vía webhook (raw HTML)', [
                    'email' => $email,
                    'subject' => $subject,
                    'status' => $response->status()
                ]);
                return true;
            } else {
                Log::error('Error al enviar correo vía webhook (raw HTML)', [
                    'email' => $email,
                    'subject' => $subject,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Excepción al enviar correo vía webhook (raw HTML)', [
                'email' => $email,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}

