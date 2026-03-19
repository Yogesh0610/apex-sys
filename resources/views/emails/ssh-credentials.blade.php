<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body        { font-family: Arial, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 20px; }
        .container  { max-width: 600px; margin: 0 auto; }
        .header     { background: #1a1a2e; color: #fff; padding: 20px 24px; border-radius: 8px 8px 0 0; }
        .header h2  { margin: 0; font-size: 18px; }
        .header p   { margin: 4px 0 0; font-size: 13px; color: #aaa; }
        .body       { background: #fff; border: 1px solid #e0e0e0; border-top: none; padding: 24px; border-radius: 0 0 8px 8px; }
        table       { border-collapse: collapse; width: 100%; margin: 16px 0; }
        td, th      { border: 1px solid #ddd; padding: 10px 14px; text-align: left; font-size: 13px; }
        th          { background: #f5f5f5; font-weight: 600; width: 40%; }
        pre         { background: #1e1e2e; color: #cdd6f4; padding: 16px; border-radius: 6px; font-size: 13px; overflow-x: auto; line-height: 1.6; }
        .warning    { background: #fff3cd; border-left: 4px solid #e8a000; padding: 12px 16px; border-radius: 4px; margin: 16px 0; font-size: 13px; }
        .warning strong { color: #c0392b; }
        .footer     { margin-top: 24px; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 16px; }
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <h2>✅ Server Migration Complete</h2>
        <p>Your SSH access has been provisioned on the new server</p>
    </div>

    <div class="body">
        <p>Dear <strong>{{ $user->name }}</strong>,</p>
        <p>
            The server migration has completed successfully. A new SSH key pair has been generated
            specifically for your account and your access has been configured on the new server.
        </p>

        <h3 style="margin-bottom:8px;">🖥 Connection Details</h3>
        <table>
            <tr><th>New Server IP</th><td><code>{{ $migration->new_server_ip }}</code></td></tr>
            <tr><th>Old Server IP</th><td><code>{{ $migration->old_server_ip }}</code></td></tr>
            <tr><th>Username</th><td><code>{{ $user->linux_username }}</code></td></tr>
            <tr><th>Port</th><td><code>{{ config('server-migration.ssh_port', 22) }}</code></td></tr>
            <tr><th>Auth Method</th><td>SSH Key (see attached file)</td></tr>
            @if($migration->migrated_at)
            <tr><th>Migrated At</th><td>{{ $migration->migrated_at->format('d M Y, h:i A') }}</td></tr>
            @endif
        </table>

        <h3 style="margin-bottom:8px;">🔑 How to Connect</h3>
        <p>Save the attached private key file <code>id_ed25519_{{ $user->linux_username }}</code>, then run the following commands:</p>

        <pre># Set correct permissions on the key
chmod 600 ~/Downloads/id_ed25519_{{ $user->linux_username }}

# Connect to the new server
ssh -i ~/Downloads/id_ed25519_{{ $user->linux_username }} {{ $user->linux_username }}@{{ $migration->new_server_ip }}

# Optional: add to SSH config for convenience
echo "Host apexsys-server
    HostName {{ $migration->new_server_ip }}
    User {{ $user->linux_username }}
    IdentityFile ~/Downloads/id_ed25519_{{ $user->linux_username }}" >> ~/.ssh/config

ssh apexsys-server</pre>

        <div class="warning">
            <strong>⚠ Security Notice:</strong><br>
            Keep this private key secure and do not share it with anyone.
            Store it in a safe location. This email will <strong>not</strong> be resent —
            if you lose the key, contact your administrator to re-provision access.
        </div>

        @if($migration->notes)
        <h3 style="margin-bottom:8px;">📝 Migration Notes</h3>
        <p>{{ $migration->notes }}</p>
        @endif

        <div class="footer">
            <p>
                Regards,<br>
                <strong>{{ config('server-migration.mail.from_name') }}</strong><br>
                <a href="mailto:{{ config('server-migration.mail.reply_to') }}">
                    {{ config('server-migration.mail.reply_to') }}
                </a>
            </p>
            <p style="color:#bbb;">
                This is an automated message from the Apex Systems Server Migration package.
                Do not reply directly to this email.
            </p>
        </div>
    </div>

</div>
</body>
</html>
