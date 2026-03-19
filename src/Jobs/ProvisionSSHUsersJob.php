<?php

namespace Apexsys\ServerMigration\Jobs;

use Apexsys\ServerMigration\Mail\SSHCredentialsMail;
use Apexsys\ServerMigration\Models\DedicatedUser;
use Apexsys\ServerMigration\Models\ServerMigration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProvisionSSHUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;
    public int $backoff;

    public function __construct(
        public readonly ServerMigration $migration
    ) {
        $this->tries   = config('server-migration.job_tries', 3);
        $this->backoff = config('server-migration.job_backoff', 120);
        $this->onQueue(config('server-migration.queue', 'default'));
    }

    public function handle(): void
    {
        $this->migration->update(['status' => 'running']);

        $users = DedicatedUser::active()->get();

        if ($users->isEmpty()) {
            Log::warning('[ServerMigration] No active dedicated users found.');
            $this->migration->update(['status' => 'completed', 'migrated_at' => now()]);
            return;
        }

        $errors = [];

        foreach ($users as $user) {
            try {
                [$publicKey, $privateKey] = $this->generateSSHKeyPair($user->linux_username);

                $this->provisionOnServer(
                    $this->migration->new_server_ip,
                    $user->linux_username,
                    $publicKey
                );

                // Save keys — private key auto-encrypted via model mutator
                $user->update([
                    'public_key'  => $publicKey,
                    'private_key' => $privateKey,
                ]);

                Mail::to($user->email)->send(
                    new SSHCredentialsMail($user, $this->migration, $privateKey)
                );

                Log::info("[ServerMigration] SSH provisioned and email sent for {$user->linux_username}");

            } catch (\Throwable $e) {
                $errors[] = "{$user->linux_username}: " . $e->getMessage();
                Log::error("[ServerMigration] Failed for {$user->linux_username} — " . $e->getMessage());
            }
        }

        $this->migration->update([
            'status'      => $errors ? 'failed' : 'completed',
            'migrated_at' => now(),
            'error_log'   => $errors ? implode("\n", $errors) : null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $this->migration->update([
            'status'    => 'failed',
            'error_log' => $e->getMessage(),
        ]);

        Log::error('[ServerMigration] Job permanently failed: ' . $e->getMessage());
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function generateSSHKeyPair(string $username): array
    {
        $type    = config('server-migration.key_type', 'ed25519');
        $tmpDir  = sys_get_temp_dir();
        $keyPath = "{$tmpDir}/sm_key_{$username}_" . uniqid();

        $typeFlag = $type === 'rsa'
            ? "-t rsa -b " . config('server-migration.rsa_bits', 4096)
            : "-t ed25519";

        exec(
            "ssh-keygen {$typeFlag} -C '{$username}@server-migration' -f {$keyPath} -N '' 2>&1",
            $output,
            $exitCode
        );

        if ($exitCode !== 0) {
            throw new \RuntimeException("ssh-keygen failed: " . implode("\n", $output));
        }

        $publicKey  = trim(file_get_contents("{$keyPath}.pub"));
        $privateKey = trim(file_get_contents($keyPath));

        // Remove temp key files immediately
        @unlink($keyPath);
        @unlink("{$keyPath}.pub");

        return [$publicKey, $privateKey];
    }

    private function provisionOnServer(string $serverIp, string $username, string $publicKey): void
    {
        $masterKey  = config('server-migration.master_key_path');
        $masterUser = config('server-migration.master_user', 'root');
        $port       = config('server-migration.ssh_port', 22);

        $safeKey      = escapeshellarg($publicKey);
        $safeUsername = escapeshellarg($username);

        $commands = implode(' && ', [
            "id -u {$username} &>/dev/null || adduser --disabled-password --gecos '' {$username}",
            "mkdir -p /home/{$username}/.ssh",
            "echo {$safeKey} >> /home/{$username}/.ssh/authorized_keys",
            "chmod 700 /home/{$username}/.ssh",
            "chmod 600 /home/{$username}/.ssh/authorized_keys",
            "chown -R {$safeUsername}:{$safeUsername} /home/{$username}/.ssh",
        ]);

        exec(
            "ssh -i {$masterKey} -p {$port} -o StrictHostKeyChecking=no {$masterUser}@{$serverIp} \"{$commands}\" 2>&1",
            $output,
            $exitCode
        );

        if ($exitCode !== 0) {
            throw new \RuntimeException(
                "Provisioning failed on {$serverIp} for {$username}: " . implode("\n", $output)
            );
        }
    }
}
