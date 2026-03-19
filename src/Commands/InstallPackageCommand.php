<?php

namespace Apexsys\ServerMigration\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallPackageCommand extends Command
{
    protected $signature   = 'server-migration:install';
    protected $description = 'Install and configure the Apex Systems Server Migration package';

    public function handle(): int
    {
        $this->displayBanner();

        // ── Step 1: Publish assets ────────────────────────────────────────────
        $this->info('📦  Step 1 of 5 — Publishing config, migrations & views...');
        $this->callSilent('vendor:publish', ['--tag' => 'server-migration-config']);
        $this->callSilent('vendor:publish', ['--tag' => 'server-migration-migrations']);
        $this->callSilent('vendor:publish', ['--tag' => 'server-migration-views']);
        $this->line('   <fg=green>✅</> Config      → <fg=yellow>config/server-migration.php</>');
        $this->line('   <fg=green>✅</> Migrations  → <fg=yellow>database/migrations/</>');
        $this->line('   <fg=green>✅</> Views       → <fg=yellow>resources/views/vendor/server-migration/</>');
        $this->newLine();

        // ── Step 2: SSH master key ────────────────────────────────────────────
        $this->info('🔑  Step 2 of 5 — SSH Master Key Configuration');
        $this->line('   <fg=gray>This key is used by the server to connect to the NEW server and provision users.</>');
        $this->newLine();

        $masterKeyPath = $this->ask('   Path to master SSH private key', '/root/.ssh/id_ed25519');
        $masterUser    = $this->ask('   Master SSH username on new server', 'root');
        $sshPort       = $this->ask('   SSH port', '22');
        $this->newLine();

        // ── Step 3: Key type for user keys ───────────────────────────────────
        $this->info('🔐  Step 3 of 5 — User SSH Key Type');
        $this->line('   <fg=gray>Key type generated for each dedicated user.</>');
        $this->newLine();

        $keyType = $this->choice('   Key type', ['ed25519', 'rsa'], 0);
        $rsaBits = '4096';
        if ($keyType === 'rsa') {
            $rsaBits = $this->choice('   RSA key size (bits)', ['2048', '4096'], 1);
        }
        $this->newLine();

        // ── Step 4: Mail ──────────────────────────────────────────────────────
        $this->info('📧  Step 4 of 5 — Mail Configuration');
        $this->line('   <fg=gray>Credentials email is sent from these settings.</>');
        $this->newLine();

        $mailFrom    = $this->ask('   From email address', 'no-reply@apexsys.in');
        $mailName    = $this->ask('   From name', 'Apex Systems');
        $mailReplyTo = $this->ask('   Reply-to email', 'service@apexsys.in');
        $this->newLine();

        // ── Step 5: Queue ─────────────────────────────────────────────────────
        $this->info('⚙️   Step 5 of 5 — Queue Configuration');
        $this->newLine();
        $queue = $this->ask('   Queue name for provisioning jobs', 'default');
        $this->newLine();

        // ── Write .env ────────────────────────────────────────────────────────
        $this->info('💾  Writing environment variables to .env...');
        $this->writeEnv([
            'SSH_MASTER_KEY_PATH'     => $masterKeyPath,
            'SSH_MASTER_USER'         => $masterUser,
            'SSH_PORT'                => $sshPort,
            'SSH_KEY_TYPE'            => $keyType,
            'SSH_RSA_BITS'            => $rsaBits,
            'MIGRATION_MAIL_FROM'     => $mailFrom,
            'MIGRATION_MAIL_NAME'     => "\"{$mailName}\"",
            'MIGRATION_MAIL_REPLY_TO' => $mailReplyTo,
            'MIGRATION_QUEUE'         => $queue,
        ]);
        $this->line('   <fg=green>✅</> .env updated');
        $this->newLine();

        // ── Validate master key ───────────────────────────────────────────────
        $this->info('🔍  Validating master SSH key...');
        if (!File::exists($masterKeyPath)) {
            $this->line("   <fg=yellow>⚠  Key not found at {$masterKeyPath}.</>");
            $this->line('   <fg=gray>   Make sure it exists and is authorized on the target server before running a migration.</>');
        } else {
            $this->line('   <fg=green>✅</> Master key found at ' . $masterKeyPath);
        }
        $this->newLine();

        // ── Run migrations ────────────────────────────────────────────────────
        if ($this->confirm('   Run database migrations now?', true)) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->displaySuccess($queue);

        return self::SUCCESS;
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function writeEnv(array $values): void
    {
        $envPath    = base_path('.env');
        $envContent = File::exists($envPath) ? File::get($envPath) : '';

        foreach ($values as $key => $value) {
            if (preg_match("/^{$key}=/m", $envContent)) {
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
            } else {
                $envContent .= PHP_EOL . "{$key}={$value}";
            }
        }

        File::put($envPath, $envContent);
    }

    private function displayBanner(): void
    {
        $this->newLine();
        $this->line('  <fg=cyan>╔══════════════════════════════════════════════╗</>');
        $this->line('  <fg=cyan>║</>  <fg=white;options=bold>  Apex Systems — Server Migration v1.0.0 </>  <fg=cyan>║</>');
        $this->line('  <fg=cyan>║</>      SSH Provisioning + Email Notifications      <fg=cyan>║</>');
        $this->line('  <fg=cyan>╚══════════════════════════════════════════════╝</>');
        $this->newLine();
        $this->line('  This wizard will configure the package and write all required');
        $this->line('  values to your <fg=yellow>.env</> file.');
        $this->newLine();
    }

    private function displaySuccess(string $queue): void
    {
        $this->line('  <fg=green>╔══════════════════════════════════════════════╗</>');
        $this->line('  <fg=green>║</>         🎉  Installation Complete!              <fg=green>║</>');
        $this->line('  <fg=green>╚══════════════════════════════════════════════╝</>');
        $this->newLine();
        $this->line('  <options=bold>Next steps:</>');
        $this->newLine();
        $this->line('  1. Start the queue worker:');
        $this->line('     <fg=yellow>php artisan queue:work --queue=' . $queue . '</>');
        $this->newLine();
        $this->line('  2. Add dedicated users & trigger a migration:');
        $this->line('     <fg=yellow>php artisan server-migration:migrate</>');
        $this->newLine();
        $this->line('  3. Packagist / GitHub:');
        $this->line('     <fg=yellow>https://packagist.org/packages/apexsys/server-migration</>');
        $this->newLine();
    }
}
