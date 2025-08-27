<?php

namespace App\Jobs;

use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateBaremetricsFromGHLJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        // no-op
    }

    public function handle()
    {
        $cacheKey = 'baremetrics:update_fields:progress';

        /** @var BaremetricsService $baremetricsService */
        $baremetricsService = app(BaremetricsService::class);
        /** @var GoHighLevelService $ghlService */
        $ghlService = app(GoHighLevelService::class);

        $sources = $baremetricsService->getSources();

        // Normalizar
        $sourcesNew = [];
        if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
            $sourcesNew = $sources['sources'];
        } elseif (is_array($sources)) {
            $sourcesNew = $sources;
        }

        $stripeSources = array_values(array_filter($sourcesNew, function ($source) {
            return isset($source['provider']) && $source['provider'] === 'stripe';
        }));

        $sourceIds = array_values(array_filter(array_column($stripeSources, 'id'), function ($id) {
            return !empty($id);
        }));

        $customersExtract = [];
        foreach ($sourceIds as $sourceId) {
            $page = 0;
            $hasMore = true;
            while ($hasMore) {
                $response = $baremetricsService->getCustomersAll($sourceId, $page);
                if (!$response) {
                    $hasMore = false;
                    continue;
                }

                $customers = [];
                $pagination = [];
                if (isset($response['customers']) && is_array($response['customers'])) {
                    $customers = $response['customers'];
                } elseif (is_array($response)) {
                    $customers = $response;
                }

                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                } else {
                    $hasMore = false;
                }

                if (!empty($customers)) {
                    $customersExtract = array_merge($customersExtract, $customers);
                }

                $page++;
                usleep(100000);
            }
        }

        $totalCustomers = count($customersExtract);
        $progress = Cache::get($cacheKey, []);
        $progress['total'] = $totalCustomers;
        $progress['status'] = 'running';
        $progress['updated'] = 0;
        Cache::put($cacheKey, $progress, 60 * 60);

        foreach ($customersExtract as $customer) {
            $email = $customer['email'] ?? null;
            $progress = Cache::get($cacheKey, []);
            $progress['current_email'] = $email;
            Cache::put($cacheKey, $progress, 60 * 60);

            try {
                $ghl_customer = $ghlService->getContacts($email);
                if (!empty($ghl_customer['contacts'])) {
                    $customFields = collect($ghl_customer['contacts'][0]['customFields']);
                    $country = $ghl_customer['contacts'][0]['country'] ?? '-';
                    $city = $ghl_customer['contacts'][0]['city'] ?? '-';
                    $score = $customFields->firstWhere('id', 'j175N7HO84AnJycpUb9D');
                    $birthplace = $customFields->firstWhere('id', 'q3BHfdxzT2uKfNO3icXG');
                    $sign = $customFields->firstWhere('id', 'JuiCbkHWsSc3iKfmOBpo');
                    $hasKids = $customFields->firstWhere('id', 'xy0zfzMRFpOdXYJkHS2c');
                    $isMarried = $customFields->firstWhere('id', '1fFJJsONHbRMQJCstvg1');

                    $ghlData = [
                        'relationship_status' => $isMarried['value'] ?? '-',
                        'community_location' => $birthplace['value'] ?? '-',
                        'country' => $country ?? '-',
                        'engagement_score' => $score['value'] ?? '-',
                        'has_kids' => $hasKids['value'] ?? '-',
                        'state' => $ghl_customer['contacts'][0]['state'] ?? '-',
                        'location' => $city,
                        'zodiac_sign' => $sign['value'] ?? '-',
                    ];

                    // Actualizar en Baremetrics
                    $baremetricsService->updateCustomerAttributes($customer['oid'], $ghlData);

                    // Increment counter
                    $progress = Cache::get($cacheKey, []);
                    $progress['updated'] = ($progress['updated'] ?? 0) + 1;
                    Cache::put($cacheKey, $progress, 60 * 60);
                    Log::info('Updated customer from GHL', ['email' => $email]);
                }
            } catch (\Exception $e) {
                Log::error('Error updating customer: ' . $e->getMessage(), ['email' => $email]);
            }
        }

        $progress = Cache::get($cacheKey, []);
        $progress['status'] = 'finished';
        $progress['finished_at'] = now()->toDateTimeString();
        Cache::put($cacheKey, $progress, 60 * 60);
    }
}
