<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Models\SystemConfiguration;
use Illuminate\Console\Command;

class MigrateSystemSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:migrate-settings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate system settings from old key-value format to new unified configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Migrating system settings to unified configuration...');

        // Get existing configuration or create new one
        $config = SystemConfiguration::getInstance();

        // Get values from old system
        $systemName = SystemSetting::getValue('system_name', 'Baremetrics Dashboard');
        $systemLogo = SystemSetting::getValue('system_logo');
        $systemFavicon = SystemSetting::getValue('system_favicon');

        // Update the configuration
        $config->update([
            'system_name' => $systemName,
            'system_logo' => $systemLogo,
            'system_favicon' => $systemFavicon,
            'description' => 'Configuración migrada desde el sistema anterior'
        ]);

        $this->info('✓ Migration completed successfully!');
        $this->line('');
        $this->info('Current configuration:');
        $this->line("- System Name: {$config->system_name}");
        $this->line("- Logo: " . ($config->system_logo ?? 'Not set'));
        $this->line("- Favicon: " . ($config->system_favicon ?? 'Not set'));
        
        $this->line('');
        $this->comment('You can now access the unified configuration at: /admin/system-config');
        $this->comment('The old system is still available at: /admin/system');

        return Command::SUCCESS;
    }
}
