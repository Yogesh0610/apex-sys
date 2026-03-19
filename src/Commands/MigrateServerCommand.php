<?php

namespace Apexsys\ServerMigration\Commands;

use Apexsys\ServerMigration\Jobs\ProvisionSSHUsersJob;
use Apexsys\ServerMigration\Models\DedicatedUser;
use Apexsys\ServerMigration\Models\ServerMigration;
use Illuminate\Console\Command;

class MigrateServerCommand extends Command
{
    protected $signature = 'server-migration:migrate
                            {--old-ip= : Old server IP address}
                            {--new-ip= : New server IP address}
                            {--notes=  : Optional migration notes}';

    protected $description = 'Trigger a server migration and provision SSH for all dedicated users';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=cyan>🚀  Server Migration Wizard</>');
        $this->line('  <fg=gray>SSH access will be provisioned for all active dedicated users.</>');
        $this->newLine();

        // ── Collect server details ────────────────────────────────────────────
        $oldIp = $this->option('old-ip') ?? $this->ask('   Old server IP address');
        $newIp = $this->option('new-ip') ?? $this->ask('   New server IP address');
        $notes = $this->option('notes')  ?? $this->ask('   Migration notes (optional)', '');

        if (!filter_var($oldIp, FILTER_VALIDATE_IP) || !filter_var($newIp, FILTER_VALIDATE_IP)) {
            $this->error('   Invalid IP address provided. Please enter valid IPv4/IPv6 addresses.');
            return self::FAILURE;
        }

        // ── Show active users ─────────────────────────────────────────────────
        $this->newLine();
        $users = DedicatedUser::active()->get();

        if ($users->isEmpty()) {
            $this->warn('   ⚠  No active dedicated users found.');
            $this->newLine();

            if ($this->confirm('   Add a dedicated user now?', true)) {
                $this->addUser();
                $users = DedicatedUser::active()->get();
            }

            if ($users->isEmpty()) {
                $this->error('   No users to provision. Migration aborted.');
                return self::FAILURE;
            }
        }

        $this->info('   👥 SSH will be provisioned for these users:');
        $this->newLine();
        $this->table(
            ['#', 'Name', 'Email', 'Linux Username', 'Status'],
            $users->map(fn ($u, $i) => [
                $i + 1,
                $u->name,
                $u->email,
                $u->linux_username,
                $u->status === 'active' ? '✅ active' : '🔴 suspended',
            ])
        );

        // ── Confirm ───────────────────────────────────────────────────────────
        $this->newLine();
        $this->line("   <fg=yellow>Migration:</>  {$oldIp}  →  {$newIp}");
        $this->line("   <fg=yellow>Users:</>      {$users->count()} active user(s) will be provisioned");
        $this->newLine();

        if (!$this->confirm('   Proceed with migration?', true)) {
            $this->line('   Migration cancelled.');
            return self::SUCCESS;
        }

        // ── Dispatch job ──────────────────────────────────────────────────────
        $migration = ServerMigration::create([
            'old_server_ip' => $oldIp,
            'new_server_ip' => $newIp,
            'notes'         => $notes ?: null,
            'status'        => 'pending',
        ]);

        ProvisionSSHUsersJob::dispatch($migration);

        $this->newLine();
        $this->line('  <fg=green>╔══════════════════════════════════════════════╗</>');
        $this->line('  <fg=green>║</>   ✅  Migration job dispatched successfully!   <fg=green>║</>');
        $this->line('  <fg=green>╚══════════════════════════════════════════════╝</>');
        $this->newLine();
        $this->line("   <fg=gray>Migration ID:</>  #{$migration->id}");
        $this->line("   <fg=gray>New server:</>    {$newIp}");
        $this->line("   <fg=gray>Users:</>         {$users->count()} SSH credential email(s) will be sent");
        $this->newLine();
        $this->line('   Make sure your queue worker is running:');
        $this->line('   <fg=yellow>php artisan queue:work --queue=' . config('server-migration.queue', 'default') . '</>');
        $this->newLine();

        return self::SUCCESS;
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function addUser(): void
    {
        $this->newLine();
        $this->info('   ➕ Add a Dedicated User');

        $name     = $this->ask('   Full name');
        $email    = $this->ask('   Email address');
        $username = $this->ask('   Linux username (lowercase, no spaces, e.g. john_doe)');

        if (!preg_match('/^[a-z_][a-z0-9_-]*$/', $username)) {
            $this->error('   Invalid linux username format. Use only lowercase letters, numbers, underscores, hyphens.');
            return;
        }

        if (DedicatedUser::where('linux_username', $username)->exists()) {
            $this->error("   Username '{$username}' already exists.");
            return;
        }

        DedicatedUser::create([
            'name'           => $name,
            'email'          => $email,
            'linux_username' => $username,
            'status'         => 'active',
        ]);

        $this->line("   <fg=green>✅</> User <fg=yellow>{$username}</> added successfully.");
        $this->newLine();
    }
}
