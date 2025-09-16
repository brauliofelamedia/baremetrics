<?php

/**
 * Este método debería añadirse a la clase BaremetricsService para obtener información
 * sobre los atributos personalizados disponibles en Baremetrics.
 */

/**
 * Get available custom fields from Baremetrics
 *
 * @return array|null
 */
public function getCustomFields(): ?array
{
    try {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl . '/attributes/fields');

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('Baremetrics API Error - Custom Fields', [
            'status' => $response->status(),
            'response' => $response->body(),
        ]);

        return null;
    } catch (\Exception $e) {
        Log::error('Baremetrics Service Exception - Custom Fields', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return null;
    }
}

/**
 * Get attributes for a specific customer
 *
 * @param string $customerId The customer ID
 * @return array|null
 */
public function getCustomerAttributes(string $customerId): ?array
{
    try {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl . '/attributes?customer_oid=' . $customerId);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('Baremetrics API Error - Customer Attributes', [
            'status' => $response->status(),
            'response' => $response->body(),
            'customer_id' => $customerId,
        ]);

        return null;
    } catch (\Exception $e) {
        Log::error('Baremetrics Service Exception - Customer Attributes', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'customer_id' => $customerId,
        ]);

        return null;
    }
}