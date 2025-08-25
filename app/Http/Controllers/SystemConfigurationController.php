<?php

namespace App\Http\Controllers;

use App\Models\SystemConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SystemConfigurationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:Admin', 'permission:manage-system-settings']);
    }

    /**
     * Show the system configuration page
     */
    public function index()
    {
        $config = SystemConfiguration::getInstance();
        return view('admin.system-config.index', compact('config'));
    }

    /**
     * Show the form for editing the system configuration
     */
    public function edit()
    {
        $config = SystemConfiguration::getInstance();
        return view('admin.system-config.edit', compact('config'));
    }

    /**
     * Update the system configuration
     */
    public function update(Request $request)
    {
        $config = SystemConfiguration::getInstance();

        $validator = Validator::make($request->all(), [
            'system_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'favicon_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,ico|max:1024',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = [
            'system_name' => $request->system_name,
            'description' => $request->description,
        ];

        // Handle logo upload
        if ($request->hasFile('logo_file')) {
            // Delete old logo if exists
            if ($config->system_logo && Storage::disk('public')->exists($config->system_logo)) {
                Storage::disk('public')->delete($config->system_logo);
            }

            $file = $request->file('logo_file');
            $filename = 'logo_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('system', $filename, 'public');
            $data['system_logo'] = $path;
        }

        // Handle favicon upload
        if ($request->hasFile('favicon_file')) {
            // Delete old favicon if exists
            if ($config->system_favicon && Storage::disk('public')->exists($config->system_favicon)) {
                Storage::disk('public')->delete($config->system_favicon);
            }

            $file = $request->file('favicon_file');
            $filename = 'favicon_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('system', $filename, 'public');
            $data['system_favicon'] = $path;
        }

        $config->update($data);

        return redirect()->route('admin.system-config.index')
            ->with('success', 'Configuración del sistema actualizada exitosamente.');
    }

    /**
     * Remove logo
     */
    public function removeLogo()
    {
        $config = SystemConfiguration::getInstance();
        
        if ($config->system_logo && Storage::disk('public')->exists($config->system_logo)) {
            Storage::disk('public')->delete($config->system_logo);
        }
        
        $config->update(['system_logo' => null]);
        
        return redirect()->back()
            ->with('success', 'Logo eliminado exitosamente.');
    }

    /**
     * Remove favicon
     */
    public function removeFavicon()
    {
        $config = SystemConfiguration::getInstance();
        
        if ($config->system_favicon && Storage::disk('public')->exists($config->system_favicon)) {
            Storage::disk('public')->delete($config->system_favicon);
        }
        
        $config->update(['system_favicon' => null]);
        
        return redirect()->back()
            ->with('success', 'Favicon eliminado exitosamente.');
    }
}
