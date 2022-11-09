#!/usr/bin/env php
<?php

require __DIR__ . '/lib.php';

require __DIR__ . '/classes/signaller.php';

$arguments = (function() use ($argv) {
    $command = array_shift($argv);

    $parameters = [
        'help' => (object) ['short' => 'h'],
        'in-place' => (object) ['short' => 'p'],
        'infile' => (object) ['short' => 'i', 'default' => '-', 'values' => 1],
        'outfile' => (object) ['short' => 'o', 'default' => '-', 'values' => 1],
    ];

    [$arguments, $remaining] = load_arguments_and_flags($parameters, $argv);

    if (!$arguments['help']) {
        if (!count($remaining)) {
            error_log("Error: no conversions specified.");

            $arguments['help'] = true;
        }
    }

    if ($arguments['help']) {
        error_log("Usage: $command [-p] [--infile=INFILE|-i INFILE] [--outfile=OUTFILE|-o OUTFILE] conversion1 [conversion 2] [...]");
        error_log("       $command [--help|-h]");

        $handle = opendir(__DIR__ . '/classes/conversion');

        error_log('');
        error_log('Available conversions:');

        while ($file = readdir($handle)) {
            if (preg_match('/^\./', $file) || !preg_match(',([^/]+)\.php$,', $file, $matches)) {
                continue;
            }

            error_log('    - ' . $matches[1]);
        }

        closedir($handle);
        error_log('');

        exit(1);
    }

    $arguments['pipeline'] = array_map(fn ($conversion) => 'conversion\\' . $conversion, $remaining);

    if ($arguments['in-place']) {
        if ($arguments['outfile'] != $parameters['outfile']->default) {
            error_log('Warning: Specified OUTFILE overwritten due to --in-place mode.');
        }

        $arguments['outfile'] = $arguments['infile'];
    }

    return $arguments;
})();

do_pipeline($arguments);
