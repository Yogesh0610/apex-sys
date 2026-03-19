<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master SSH Key
    |--------------------------------------------------------------------------
    | The private key used by this application to SSH into the new server
    | and provision user accounts. Must already be authorized on the server.
    */
    'master_key_path' => env('SSH_MASTER_KEY_PATH', '/root/.ssh/id_ed25519'),
    'master_user'     => env('SSH_MASTER_USER', 'root'),
    'ssh_port'        => env('SSH_PORT', 22),

    /*
    |--------------------------------------------------------------------------
    | Mail Settings
    |--------------------------------------------------------------------------
    */
    'mail' => [
        'from_address' => env('MIGRATION_MAIL_FROM', 'no-reply@apexsys.in'),
        'from_name'    => env('MIGRATION_MAIL_NAME', 'Apex Systems'),
        'reply_to'     => env('MIGRATION_MAIL_REPLY_TO', 'service@apexsys.in'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Key Type
    |--------------------------------------------------------------------------
    | Supported: "ed25519", "rsa"
    */
    'key_type' => env('SSH_KEY_TYPE', 'ed25519'),
    'rsa_bits'  => env('SSH_RSA_BITS', 4096),

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'migrations'      => 'server_migrations',
        'dedicated_users' => 'dedicated_users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    */
    'queue'       => env('MIGRATION_QUEUE', 'default'),
    'job_tries'   => 3,
    'job_backoff' => 120,

];
