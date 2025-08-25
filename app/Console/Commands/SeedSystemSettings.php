<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use Illuminate\Console\Command;

class SeedSystemSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:seed-settings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed initial system settings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Seeding system settings...');

        $settings = [
            [
                'key' => 'system_name',
                'value' => 'Baremetrics Dashboard',
                'type' => 'text',
                'description' => 'Nombre del sistema',
            ],
            [
                'key' => 'system_logo',
                'value' => null,
                'type' => 'image',
                'description' => 'Logo del sistema',
            ],
            [
                'key' => 'system_favicon',
                'value' => null,
                'type' => 'image',
                'description' => 'Favicon del sistema',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
            
            $this->line("✓ Created/Updated setting: {$setting['key']}");
        }

        $this->info('System settings seeded successfully!');
        
        return Command::SUCCESS;
    }
}
