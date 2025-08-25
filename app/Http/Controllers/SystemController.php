<?php

namespace App\Http\Controllers;

use App\Models\SystemConfiguration;
use App\Services\SystemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;

class SystemController extends Controller
{
    protected $systemService;

    public function __construct(SystemService $systemService)
    {
        $this->systemService = $systemService;
    }

    /**
     * Display system configuration index
     */
    public function index()
    {
        $config = $this->systemService->getConfiguration();
        $systemHealth = $this->systemService->getSystemHealth();
        
        return view('admin.system.index', compact('config', 'systemHealth'));
    }

    /**
     * Show the form for editing system configuration
     */
    public function edit()
    {
        $config = $this->systemService->getConfiguration();
        
        return view('admin.system.edit', compact('config'));
    }

    /**
     * Update the system configuration
     */
    public function update(Request $request)
    {
        $config = $this->systemService->getConfiguration();

        $request->validate([
            'system_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'system_logo' => ['nullable', File::image()->max(2048)],
            'system_favicon' => ['nullable', File::image()->max(1024)],
        ]);

        $data = [
            'system_name' => $request->system_name,
            'description' => $request->description,
        ];

        // Handle logo upload
        if ($request->hasFile('system_logo')) {
            // Delete old logo if exists
            if ($config->system_logo && Storage::disk('public')->exists($config->system_logo)) {
                Storage::disk('public')->delete($config->system_logo);
            }
            
            $logoPath = $request->file('system_logo')->store('system/logos', 'public');
            $data['system_logo'] = $logoPath;
        }

        // Handle favicon upload
        if ($request->hasFile('system_favicon')) {
            // Delete old favicon if exists
            if ($config->system_favicon && Storage::disk('public')->exists($config->system_favicon)) {
                Storage::disk('public')->delete($config->system_favicon);
            }
            
            $faviconPath = $request->file('system_favicon')->store('system/favicons', 'public');
            $data['system_favicon'] = $faviconPath;
        }

        $this->systemService->updateConfiguration($data);

        return redirect()->route('admin.system.index')
            ->with('success', 'Configuración del sistema actualizada exitosamente.');
    }

    /**
     * Remove system logo
     */
    public function removeLogo()
    {
        $config = $this->systemService->getConfiguration();
        
        if ($config->system_logo && Storage::disk('public')->exists($config->system_logo)) {
            Storage::disk('public')->delete($config->system_logo);
        }
        
        $this->systemService->updateConfiguration(['system_logo' => null]);

        return redirect()->route('admin.system.index')
            ->with('success', 'Logo del sistema eliminado exitosamente.');
    }

    /**
     * Remove system favicon
     */
    public function removeFavicon()
    {
        $config = $this->systemService->getConfiguration();
        
        if ($config->system_favicon && Storage::disk('public')->exists($config->system_favicon)) {
            Storage::disk('public')->delete($config->system_favicon);
        }
        
        $this->systemService->updateConfiguration(['system_favicon' => null]);

        return redirect()->route('admin.system.index')
            ->with('success', 'Favicon del sistema eliminado exitosamente.');
    }

    /**
     * Show general system information
     */
    public function info()
    {
        $systemInfo = $this->systemService->getSystemStats();

        return view('admin.system.info', compact('systemInfo'));
    }

    /**
     * Clear system cache
     */
    public function clearCache()
    {
        try {
            $success = $this->systemService->clearAllCaches();
            
            if ($success) {
                return redirect()->route('admin.system.index')
                    ->with('success', 'Cache del sistema limpiado exitosamente.');
            } else {
                return redirect()->route('admin.system.index')
                    ->with('error', 'Error al limpiar el cache del sistema.');
            }
        } catch (\Exception $e) {
            return redirect()->route('admin.system.index')
                ->with('error', 'Error al limpiar el cache: ' . $e->getMessage());
        }
    }

    /**
     * Download system logs
     */
    public function downloadLogs()
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (file_exists($logPath)) {
            return response()->download($logPath, 'system-logs-' . date('Y-m-d') . '.log');
        }
        
        return redirect()->route('admin.system.index')
            ->with('error', 'No se encontraron logs del sistema.');
    }
}
