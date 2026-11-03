<?php

namespace Config;

use CodeIgniter\Events\Events;
Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        require_once APPPATH . 'Helpers/encoding_helper.php';
        ob_start(static fn(string $buffer): string => mcc_normalize_mojibake($buffer));
    }
});
