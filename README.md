# Apex Systems — Server Migration

**Laravel package for automated server migration with SSH provisioning and email notifications.**

Whenever you migrate to a new server, this package automatically:
1. Generates a fresh ED25519 (or RSA) SSH key pair for each dedicated user
2. Creates the Linux user account on the new server
3. Installs the public key into `~/.ssh/authorized_keys`
4. Emails the private key + connection instructions directly to each admin

---

## Requirements

- PHP ^8.1
- Laravel ^10.0 or ^11.0
- `ssh-keygen` available on the server running Laravel
- A master SSH key already authorized on the **target** server (to provision users remotely)

---

## Installation

```bash
composer require apexsys/server-migration
```

Run the interactive setup wizard:

```bash
php artisan server-migration:install
```

The wizard will:
- Publish the config file, migrations, and email views
- Ask for your master SSH key path, key type, mail settings, and queue name
- Write all values to your `.env`
- Optionally run the database migrations

---

## Usage

### Trigger a migration

```bash
php artisan server-migration:migrate
```

The wizard will:
- Ask for old and new server IP addresses
- Show you all active dedicated users who will be provisioned
- Confirm before dispatching the background job

You can also pass options directly:

```bash
php artisan server-migration:migrate \
    --old-ip=192.168.1.10 \
    --new-ip=192.168.1.20 \
    --notes="Upgraded to 32GB RAM"
```

### Start the queue worker

```bash
php artisan queue:work --queue=default
```

---

## Configuration

After publishing with `php artisan server-migration:install`, edit `config/server-migration.php`:

```php
return [
    'master_key_path' => env('SSH_MASTER_KEY_PATH', '/root/.ssh/id_ed25519'),
    'master_user'     => env('SSH_MASTER_USER', 'root'),
    'ssh_port'        => env('SSH_PORT', 22),

    'mail' => [
        'from_address' => env('MIGRATION_MAIL_FROM', 'no-reply@example.com'),
        'from_name'    => env('MIGRATION_MAIL_NAME', 'Apex Systems'),
        'reply_to'     => env('MIGRATION_MAIL_REPLY_TO', 'service@example.com'),
    ],

    'key_type'    => env('SSH_KEY_TYPE', 'ed25519'), // or "rsa"
    'rsa_bits'    => env('SSH_RSA_BITS', 4096),

    'queue'       => env('MIGRATION_QUEUE', 'default'),
    'job_tries'   => 3,
    'job_backoff' => 120,
];
```

---

## Dedicated Users

Users are stored in the `dedicated_users` table. Add them via the migration wizard
or directly in the database:

| Column           | Description                              |
|------------------|------------------------------------------|
| `name`           | Full name                                |
| `email`          | Where SSH credentials are emailed        |
| `linux_username` | Linux account created on the new server  |
| `public_key`     | Stored after provisioning                |
| `private_key`    | Stored encrypted (Laravel `Crypt`)       |
| `status`         | `active` or `suspended`                  |

Only `active` users are provisioned during a migration.

---

## How it Works

```
php artisan server-migration:migrate
        ↓
ServerMigration record created (status: pending)
        ↓
ProvisionSSHUsersJob dispatched to queue
        ↓
For each active DedicatedUser:
  ① ssh-keygen → fresh ED25519 key pair (temp files deleted immediately)
  ② SSH into new server → create Linux user → add authorized_keys
  ③ Public key saved to DB, private key saved encrypted
  ④ Email private key as attachment + connection instructions
        ↓
Migration marked as completed (or failed with error_log)
```

---

## Email

Each user receives an email containing:
- New server IP, username, and port
- Step-by-step connection instructions
- Private key attached as `id_ed25519_{username}`

To customize the email template, publish the views:

```bash
php artisan vendor:publish --tag=server-migration-views
```

Then edit `resources/views/vendor/server-migration/emails/ssh-credentials.blade.php`.

---

## Security

- Private keys are generated in a temp directory and **deleted immediately** after use
- Private keys are stored in the database **encrypted** using Laravel's `Crypt` facade
- The email is sent once — if lost, re-provision by triggering a new migration
- Uses ED25519 by default (faster and more secure than RSA)

---

## License

MIT © Apex Systems
