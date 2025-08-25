<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Cancellation;
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
        
        // Datos para gráficos
        $chartData = [
            'users_by_month' => $this->getUsersByMonth(),
            'cancellations_by_month' => $this->getCancellationsByMonth(),
            'membership_types' => $this->getMembershipTypeDistribution(),
            'recent_activity' => $this->getRecentActivity(),
        ];
        
        return view('admin.dashboard', compact('stats', 'chartData'));
    }

    private function calculateCancellationRate($startDate, $endDate)
    {
        $totalUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();
        $cancellations = Cancellation::whereBetween('created_at', [$startDate, $endDate])->count();
        
        if ($totalUsers == 0) return 0;
        
        return round(($cancellations / $totalUsers) * 100, 1);
    }

    private function getUsersByMonth()
    {
        return User::select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('COUNT(*) as count')
        )
        ->where('created_at', '>=', Carbon::now()->subMonths(6))
        ->groupBy('month')
        ->orderBy('month')
        ->get();
    }

    private function getCancellationsByMonth()
    {
        return Cancellation::select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('COUNT(*) as count')
        )
        ->where('created_at', '>=', Carbon::now()->subMonths(6))
        ->groupBy('month')
        ->orderBy('month')
        ->get();
    }

    private function getMembershipTypeDistribution()
    {
        return [
            'activos' => User::whereNotNull('email_verified_at')->count(),
            'cancelados' => Cancellation::count(),
            'prueba' => User::whereNull('email_verified_at')->count(),
        ];
    }

    private function getRecentActivity()
    {
        $recentUsers = User::latest()->take(5)->get(['name', 'email', 'created_at']);
        $recentCancellations = Cancellation::with('user')->latest()->take(5)->get();
        
        return [
            'users' => $recentUsers,
            'cancellations' => $recentCancellations,
        ];
    }
}
