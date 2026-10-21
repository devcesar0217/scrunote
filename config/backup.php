<?php

return [
    'format' => 'scrunote.workspace-backup',
    'version' => 1,
    'maximum_upload_kilobytes' => 20 * 1024,
    'maximum_plaintext_bytes' => 16 * 1024 * 1024,
    'kdf' => [
        'opslimit' => SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
        'memlimit' => SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
    ],
];
