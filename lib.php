<?php

require __DIR__ . '/interfaces.php';

const CONTROL_STRUCTURES = [
    'T_IF' => ['T_ELSEIF', 'T_ELSE', 'T_ENDIF'],
    'T_ELSEIF' => ['T_ELSEIF', 'T_ELSE', 'T_ENDIF'],
    'T_ELSE' => ['T_ENDIF'],
    'T_WHILE' => ['T_ENDWHILE'],
    'T_FOR' => ['T_ENDFOR'],
    'T_FOREACH' => ['T_ENDFOREACH'],
    'T_SWITCH' => ['T_ENDSWITCH'],
];

const DEBUG_PREFIX = '        ';

const ENDS = [
    'T_IF' => 'endif',
    'T_ELSEIF' => 'endif',
    'T_ELSE' => 'endif',
    'T_WHILE' => 'endwhile',
    'T_FOR' => 'endfor',
    'T_FOREACH' => 'endforeach',
    'T_SWITCH' => 'endswitch',
];

const ENDERS = [
    'T_ENDIF',
    'T_ENDWHILE',
    'T_ENDFOR',
    'T_ENDFOREACH',
    'T_ENDSWITCH',
];

const MATCHING_BRACES = [
    '${' => '}',
    '(' => ')',
    '[' => ']',
    'T_CURLY_OPEN' => '}',
    'T_DOLLAR_OPEN_CURLY_BRACES' => '}',
    '{' => '}',
];

function perform_conversion(string $handler_class)
{
    global $argv;

    $arguments = parse_single_conversion_args($argv);

    $arguments['pipeline'] = [$handler_class];

    do_pipeline($arguments);
}

function parse_single_conversion_args($argv)
{
    $command = array_shift($argv);

    $parameters = [
        'outfile' => (object) ['short' => 'o', 'default' => '-'],
    ];

    [$arguments, $flags, $remaining] = load_arguments_and_flags($parameters, $argv);

    if (count($remaining) > 1) {
        error_log("Usage: $command [-p] [--outfile=OUTFILE|-o OUTFILE] INFILE");

        exit(1);
    }

    $arguments['infile'] = $remaining[0] ?? '-';

    if ($arguments['in-place'] = in_array('p', $flags)) {
        $arguments['outfile'] = $arguments['infile'];
    }

    return $arguments;
}

function do_pipeline(array $arguments)
{
    require_once __DIR__ . '/classes/signaller.php';

    if (!$stream = $arguments['infile'] === '-' ? STDIN : fopen($arguments['infile'], 'r')) {
        error_log('Failed to open IN_FILE for reading');

        exit(1);
    }

    foreach ($arguments['pipeline'] as $handler_class) {
        require_once __DIR__ . '/classes/' . $handler_class . '.php';

        $tokens = PhpToken::tokenize(stream_get_contents($stream));

        fclose($stream);

        $handler = new $handler_class;
        $signaller = new signaller($handler, $tokens);
        $stream = fopen('php://temp', 'r+');
        $signaller->convert($stream);

        rewind($stream);
    }

    if (!$outstream = $arguments['outfile'] === '-' ? STDOUT : fopen($arguments['outfile'], 'w')) {
        error_log('Failed to open OUT_FILE for writing');

        exit(1);
    }

    stream_copy_to_stream($stream, $outstream);

    fclose($stream);
    fclose($outstream);
}

function load_arguments_and_flags($parameters, $argv)
{
    $rem_argv = [];
    $arguments = array_map(fn ($p) => $p->default ?? null, $parameters);
    $flags = [];

    for ($i = 0; $i < count($argv); $i++) {
        foreach ($parameters as $param => $details) {
            $pattern = '--' . str_replace('_', '-', $param) . '(?:=(.*))?';

            if ($short = @$details->short) {
                $pattern = '(?:-' . $short . '|' . $pattern . ')';
            }

            $pattern = '/^' . $pattern . '$/';

            if (preg_match($pattern, $argv[$i], $matches)) {
                $arguments[$param] = @$matches[1] ?? @$argv[++$i];

                continue 2;
            }
        }

        if (preg_match('/^-([a-zA-Z])$/', $argv[$i], $groups) || preg_match('/^--([a-zA-Z-]+)$/', $argv[$i], $groups)) {
            $flags[] = $groups[1];

            continue;
        }

        $rem_argv[] = $argv[$i];
    }

    return [$arguments, $flags, $rem_argv];
}
