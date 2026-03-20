<?php
$capabilities = [
    'local/jurnalmengajar:submit' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'teacher' => CAP_ALLOW
        ],
    ],
    'local/jurnalmengajar:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'teacher' => CAP_ALLOW
        ],
    ],
    'local/jurnalmengajar:submitsuratizin' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        // jangan tambahkan archetypes custom
    ],
    'local/jurnalmengajar:viewallsuratizin' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        // jangan tambahkan archetypes custom
    ],
];
