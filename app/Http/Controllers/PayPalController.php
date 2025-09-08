<?php

namespace App\Http\Controllers;

use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalController extends Controller
{
    protected $paypalService;

    public function __construct(PayPalService $paypalService)
    {
        $this->paypalService = $paypalService;
        $this->middleware('auth');
        $this->middleware('role:Admin');
    }

    /**
     * Display the PayPal subscriptions dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('admin.paypal.index');
    }

    /**
     * Get paginated list of PayPal subscriptions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubscriptions(Request $request)
    {
        try {
            $params = [
                'status' => $request->input('status', 'ACTIVE'),
                'page' => $request->input('page', 1),
                'page_size' => $request->input('per_page', 10),
                'total_required' => 'true'
            ];

            // Add search filter if provided
            if ($request->has('search')) {
                $params['email_address'] = $request->input('search');
            }

            $result = $this->paypalService->getSubscriptions($params);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to retrieve subscriptions',
                    'details' => $result['details'] ?? null
                ], 500);
            }

            // Format the response for the frontend
            $subscriptions = $result['data']['subscriptions'] ?? [];
            $totalItems = $result['data']['total_items'] ?? 0;
            $totalPages = $result['data']['total_pages'] ?? 1;
            $currentPage = $params['page'];

            return response()->json([
                'success' => true,
                'data' => [
                    'subscriptions' => $subscriptions,
                    'total_items' => $totalItems,
                    'total_pages' => $totalPages,
                    'current_page' => $currentPage,
                    'per_page' => $params['page_size']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching PayPal subscriptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching subscriptions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get details of a specific subscription.
     *
     * @param  string  $subscriptionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubscriptionDetails($subscriptionId)
    {
        try {
            $result = $this->paypalService->getSubscriptionDetails($subscriptionId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to retrieve subscription details',
                    'details' => $result['details'] ?? null
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching PayPal subscription details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching subscription details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription statistics.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubscriptionStats()
    {
        try {
            // Get all active subscriptions
            $activeSubs = $this->paypalService->getSubscriptions(['status' => 'ACTIVE', 'page_size' => 100]);
            $cancelledSubs = $this->paypalService->getSubscriptions(['status' => 'CANCELLED', 'page_size' => 1]);
            $expiredSubs = $this->paypalService->getSubscriptions(['status' => 'EXPIRED', 'page_size' => 1]);
            $suspendedSubs = $this->paypalService->getSubscriptions(['status' => 'SUSPENDED', 'page_size' => 1]);

            // Calculate MRR (Monthly Recurring Revenue)
            $mrr = 0;
            if (isset($activeSubs['data']['subscriptions'])) {
                foreach ($activeSubs['data']['subscriptions'] as $sub) {
                    if (isset($sub['billing_info']['last_payment']['amount']['value'])) {
                        $mrr += (float)$sub['billing_info']['last_payment']['amount']['value'];
                    } elseif (isset($sub['billing_info']['last_failed_payment']['amount']['value'])) {
                        // Fallback to last failed payment if available (for active subscriptions with failed payments)
                        $mrr += (float)$sub['billing_info']['last_failed_payment']['amount']['value'];
                    }
                }
            }

            // Get total counts from API if available, otherwise count the returned subscriptions
            $totalActive = $activeSubs['success'] ? ($activeSubs['data']['total_items'] ?? count($activeSubs['data']['subscriptions'] ?? [])) : 0;
            $totalCancelled = $cancelledSubs['success'] ? ($cancelledSubs['data']['total_items'] ?? count($cancelledSubs['data']['subscriptions'] ?? [])) : 0;
            $totalExpired = $expiredSubs['success'] ? ($expiredSubs['data']['total_items'] ?? count($expiredSubs['data']['subscriptions'] ?? [])) : 0;
            $totalSuspended = $suspendedSubs['success'] ? ($suspendedSubs['data']['total_items'] ?? count($suspendedSubs['data']['subscriptions'] ?? [])) : 0;

            // Prepare statistics
            $stats = [
                'total_active' => $totalActive,
                'total_cancelled' => $totalCancelled,
                'total_expired' => $totalExpired,
                'total_suspended' => $totalSuspended,
                'mrr' => number_format($mrr, 2),
                'currency' => 'USD', // Default currency, adjust based on your needs
                'last_updated' => now()->toDateTimeString()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching PayPal subscription stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching subscription statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
