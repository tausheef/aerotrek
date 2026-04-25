<?php

return [
    'models' => [
        'wallet'      => \App\Models\Wallet::class,
        'transaction' => \App\Models\WalletTransaction::class,
        'transfer'    => \Bavix\Wallet\Models\Transfer::class,
    ],
];
