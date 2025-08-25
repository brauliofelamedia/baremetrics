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

        return view('admin.dashboard', compact('stats'));
    }

    private function calculateCancellationRate($startDate, $endDate)
    {
        $totalUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();
        $cancellations = Cancellation::whereBetween('created_at', [$startDate, $endDate])->count();
        
        if ($totalUsers == 0) return 0;
        
        return round(($cancellations / $totalUsers) * 100, 1);
    }
}
