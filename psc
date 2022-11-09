#!/usr/bin/env php
<?php

require __DIR__ . '/lib.php';

require __DIR__ . '/classes/signaller.php';

$arguments = (function() use ($argv) {
    $command = array_shift($argv);

    $parameters = [
        'infile' => (object) ['short' => 'i', 'default' => '-'],
        'outfile' => (object) ['short' => 'o', 'default' => '-'],
    ];

    [$arguments, $flags, $remaining] = load_arguments_and_flags($parameters, $argv);

    if (!count($remaining)) {
        error_log("Usage: $command [--outfile=OUTFILE|-o OUTFILE] [--infile=INFILE|-i INFILE] [-p] conversion1 [conversion 2] [...]");

        exit(1);
    }

    $arguments['pipeline'] = $remaining;

    if ($arguments['in-place'] = in_array('p', $flags)) {
        $arguments['outfile'] = $arguments['infile'];
    }

    return $arguments;
})();

do_pipeline($arguments);
