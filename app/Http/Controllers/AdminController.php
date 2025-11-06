<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Cancellation;
use App\Models\CancellationSurvey;
use App\Models\CancellationTracking;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:Admin');
    }

    public function dashboard()
    {
        // Fechas para filtros
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();
        
        // Métricas principales
        $stats = [
            'total_users' => User::count(),
            'new_contacts' => User::whereBetween('created_at', [$startDate, $endDate])->count(),
            'active_memberships' => User::whereNotNull('email_verified_at')->count(),
            'cancelled_memberships' => Cancellation::whereBetween('created_at', [$startDate, $endDate])->count(),
            'revenue' => 0, // Por ahora en 0, se puede integrar con Stripe más tarde
            'cancellation_rate' => $this->calculateCancellationRate($startDate, $endDate),
        ];

        return view('admin.dashboard', compact('stats'));
    }

    private function calculateCancellationRate($startDate, $endDate)
    {
        $totalUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();
        $cancellations = Cancellation::whereBetween('created_at', [$startDate, $endDate])->count();
        
        if ($totalUsers == 0) return 0;
        
        return round(($cancellations / $totalUsers) * 100, 1);
    }

    /**
     * Listar todos los surveys de cancelación
     */
    public function cancellationSurveysIndex(Request $request)
    {
        $query = CancellationSurvey::query();

        // Filtro por email
        if ($request->has('email') && $request->email) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        // Filtro por motivo
        if ($request->has('reason') && $request->reason) {
            $query->where('reason', 'like', '%' . $request->reason . '%');
        }

        // Filtro por fecha
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filtro por estado del proceso (usando whereIn con emails)
        if ($request->has('status') && $request->status) {
            $status = $request->status;
            $trackingQuery = CancellationTracking::query();
            
            if ($status === 'completed') {
                $trackingQuery->where('process_completed', true);
            } elseif ($status === 'incomplete') {
                $trackingQuery->where('process_completed', false);
            } elseif ($status === 'email_only') {
                $trackingQuery->where('email_requested', true)
                      ->where('survey_viewed', false);
            } elseif ($status === 'survey_viewed') {
                $trackingQuery->where('survey_viewed', true)
                      ->where('survey_completed', false);
            } elseif ($status === 'survey_completed') {
                $trackingQuery->where('survey_completed', true)
                      ->where(function($q) {
                          $q->where('baremetrics_cancelled', false)
                                ->orWhere('stripe_cancelled', false);
                      });
            }
            
            $emails = $trackingQuery->pluck('email')->filter();
            if ($emails->isNotEmpty()) {
                $query->whereIn('email', $emails);
            } else {
                // Si no hay emails, no mostrar resultados
                $query->whereRaw('1 = 0');
            }
        }

        $surveys = $query->orderBy('created_at', 'desc')->paginate(20);

        // Obtener estadísticas del tracking
        $stats = $this->getCancellationTrackingStats();

        return view('admin.cancellation-surveys.index', compact('surveys', 'stats'));
    }

    /**
     * Obtener estadísticas del seguimiento de cancelaciones
     */
    private function getCancellationTrackingStats()
    {
        $total = CancellationTracking::count();
        $completed = CancellationTracking::where('process_completed', true)->count();
        $incomplete = CancellationTracking::where('process_completed', false)->count();
        
        // Usuarios que solicitaron correo pero no vieron la encuesta
        $emailOnly = CancellationTracking::where('email_requested', true)
            ->where('survey_viewed', false)
            ->count();
        
        // Usuarios que vieron la encuesta pero no la completaron
        $surveyViewedNotCompleted = CancellationTracking::where('survey_viewed', true)
            ->where('survey_completed', false)
            ->count();
        
        // Usuarios que completaron la encuesta pero no cancelaron en algún sistema
        $surveyCompletedNotCancelled = CancellationTracking::where('survey_completed', true)
            ->where(function($query) {
                $query->where('baremetrics_cancelled', false)
                      ->orWhere('stripe_cancelled', false);
            })
            ->count();
        
        // Cancelados en ambos sistemas pero proceso no marcado como completo
        $cancelledBothNotCompleted = CancellationTracking::where('baremetrics_cancelled', true)
            ->where('stripe_cancelled', true)
            ->where('process_completed', false)
            ->count();
        
        // Cancelados solo en Baremetrics
        $baremetricsOnly = CancellationTracking::where('baremetrics_cancelled', true)
            ->where('stripe_cancelled', false)
            ->count();
        
        // Cancelados solo en Stripe
        $stripeOnly = CancellationTracking::where('stripe_cancelled', true)
            ->where('baremetrics_cancelled', false)
            ->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'incomplete' => $incomplete,
            'email_only' => $emailOnly,
            'survey_viewed_not_completed' => $surveyViewedNotCompleted,
            'survey_completed_not_cancelled' => $surveyCompletedNotCancelled,
            'cancelled_both_not_completed' => $cancelledBothNotCompleted,
            'baremetrics_only' => $baremetricsOnly,
            'stripe_only' => $stripeOnly,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Ver detalle de un survey de cancelación
     */
    public function cancellationSurveysShow($id)
    {
        $survey = CancellationSurvey::findOrFail($id);
        
        // Obtener el tracking asociado
        $tracking = CancellationTracking::where('email', $survey->email)
            ->orWhere('customer_id', $survey->customer_id)
            ->latest()
            ->first();

        return view('admin.cancellation-surveys.show', compact('survey', 'tracking'));
    }
}
