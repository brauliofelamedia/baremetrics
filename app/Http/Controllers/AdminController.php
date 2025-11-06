<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Cancellation;
use App\Models\CancellationSurvey;
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

        $surveys = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.cancellation-surveys.index', compact('surveys'));
    }

    /**
     * Ver detalle de un survey de cancelación
     */
    public function cancellationSurveysShow($id)
    {
        $survey = CancellationSurvey::findOrFail($id);

        return view('admin.cancellation-surveys.show', compact('survey'));
    }
}
