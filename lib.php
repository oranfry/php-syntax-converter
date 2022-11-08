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

    require __DIR__ . '/classes/signaller.php';

    $arguments = (function() use ($argv) {
        $command = array_shift($argv);

        $parameters = [
            'outfile' => (object) ['short' => 'o', 'default' => '-'],
        ];

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

        if (count($rem_argv) > 1) {
            error_log("Usage: $command [--outfile=OUTFILE|-o OUTFILE] INFILE");

            exit(1);
        }

        $arguments['infile'] = $rem_argv[0] ?? '-';

        if ($arguments['in-place'] = in_array('i', $flags)) {
            $arguments['outfile'] = $arguments['infile'];
        }

        return $arguments;
    })();

    if (!$instream = $arguments['infile'] === '-' ? STDIN : fopen($arguments['infile'], 'r')) {
        error_log('Failed to open IN_FILE for reading');

        exit(1);
    }

    $tokens = PhpToken::tokenize(stream_get_contents($instream));

    fclose($instream);

    if (!$outstream = $arguments['outfile'] === '-' ? STDOUT : fopen($arguments['outfile'], 'w')) {
        error_log('Failed to open OUT_FILE for writing');

        exit(1);
    }

    $handler = new $handler_class;
    $signaller = new signaller($handler, $tokens);

    $signaller->convert($outstream);

    fclose($outstream);
}
