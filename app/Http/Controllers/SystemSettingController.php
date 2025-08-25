<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\SystemSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SystemSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:Admin', 'permission:manage-system-settings']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $settings = SystemSetting::orderBy('key')->get();
        return view('admin.system.index', compact('settings'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.system.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|max:255|unique:system_settings,key',
            'value' => 'nullable|string',
            'type' => 'required|in:text,image,file',
            'description' => 'nullable|string',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = $request->only(['key', 'type', 'description']);

        if ($request->type === 'image' && $request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('system', $filename, 'public');
            $data['value'] = $path;
        } else {
            $data['value'] = $request->value;
        }

        SystemSetting::create($data);
        
        // Clear cache
        SystemSettingsService::clearCache();

        return redirect()->route('admin.system.index')
            ->with('success', 'Configuración creada exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(SystemSetting $systemSetting)
    {
        return view('admin.system.show', compact('systemSetting'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SystemSetting $systemSetting)
    {
        return view('admin.system.edit', compact('systemSetting'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SystemSetting $systemSetting)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|max:255|unique:system_settings,key,' . $systemSetting->id,
            'value' => 'nullable|string',
            'type' => 'required|in:text,image,file',
            'description' => 'nullable|string',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = $request->only(['key', 'type', 'description']);

        if ($request->type === 'image' && $request->hasFile('image_file')) {
            // Delete old image if exists
            if ($systemSetting->value && Storage::disk('public')->exists($systemSetting->value)) {
                Storage::disk('public')->delete($systemSetting->value);
            }

            $file = $request->file('image_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('system', $filename, 'public');
            $data['value'] = $path;
        } elseif ($request->type !== 'image') {
            $data['value'] = $request->value;
        }

        $systemSetting->update($data);
        
        // Clear cache
        SystemSettingsService::clearCache();

        return redirect()->route('admin.system.index')
            ->with('success', 'Configuración actualizada exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SystemSetting $systemSetting)
    {
        // Delete associated file if exists
        if ($systemSetting->isImage() && $systemSetting->value && Storage::disk('public')->exists($systemSetting->value)) {
            Storage::disk('public')->delete($systemSetting->value);
        }

        $systemSetting->delete();
        
        // Clear cache
        SystemSettingsService::clearCache();

        return redirect()->route('admin.system.index')
            ->with('success', 'Configuración eliminada exitosamente.');
    }
}
