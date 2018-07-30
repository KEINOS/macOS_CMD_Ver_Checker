<?php
/**
 * Checks macOS (OSX) command and program's version
 * and exports in Markdown style.
 */
namespace KEINOS\verChecker;

/* [SETUP] ================================================================== */

const WIDTH_SCREEN    = 90;
const LINES_TO_DETAIL =  5; //Max line nums to start hiding with detail tag

$name_file_json   = 'config.json';
$name_file_result = 'output.md';
$result           = '';

if (! file_exists($name_file_json)) {
    createFileConfig($name_file_json);
    die;
}

/* [MAIN] =================================================================== */

$data_raw  = file_get_contents($name_file_json);
$list_cmds = json_decode($data_raw, JSON_OBJECT_AS_ARRAY);

// Sort command list
sortByTitle($list_cmds);
$result .= '（ABC順）' . PHP_EOL . PHP_EOL;

// Loop
echo 'Fetching version info .';
foreach ($list_cmds as $cmd) {
    $result .= drawHRwithIndex($cmd['title']); //ABC順付きの区切り線描画
    $result .= checkCmd($cmd);
    echo '.';
}

// Display result
echo PHP_EOL;
echo $result, PHP_EOL;

// Save result
if (! file_put_contents($name_file_result, $result)) {
    die('Error while crreating outup file.');
}

// Update (sorted) config file
if (! updateFileConfig($list_cmds, $name_file_json)) {
    die('Error while updating config file.');
}

die(PHP_EOL . 'DONE' . PHP_EOL);

/* [FUNCTIONS] ============================================================== */

/* ---------------------------------------------------------------------- [C] */

function checkCmd($cmd)
{
    $title  = $cmd['title'];
    $cmd    = $cmd['cmd'];
    $result = '';

    if (empty(trim($cmd))) {
        return;
    }

    // get version
    $return_var = runCmd($cmd);

    // version found
    if (0 === $return_var['return']) {
        if (isCmdMan($cmd)) {
            $version = fetchVerFromMan($return_var['output']);
        } else {
            $version = 'v' . fetchVerFromArray($return_var['output']);
        }

        $result .= generateTitle($title, $version);
        $result .= PHP_EOL;
        $result .= generateCodeBlock($cmd, $return_var['output']);
        $result .= PHP_EOL;

        return $result;
    }

    // version not found
    $result .= generateTitle($title, '未インストール');
    $result .= PHP_EOL;
    $result .= generateCodeBlock($cmd, $return_var['output']);
    $result .= PHP_EOL;

    return $result;
}


function createFileConfig($name_file_json)
{
    echo 'Configuration file not found.', PHP_EOL;

    // Sample data
    $data_array = [
        [
            'title' => 'Title of check command1',
            'cmd'   => 'php -v',
        ],
        [
            'title' => 'PHP',
            'cmd'   => 'php -v',
        ],
    ];

    $data_json = json_encode($data_array, JSON_PRETTY_PRINT);

    if (! file_put_contents($name_file_json, $data_json)) {
        die('Error writing config file. Check permission.' . PHP_EOL);
    }

    echo 'Configuration file created. Edit file and re-run the script.', PHP_EOL;
    echo 'File: ', $name_file_json, PHP_EOL;
}


/* ---------------------------------------------------------------------- [D] */

function drawHRwithIndex($title)
{
    static $index_curr='';

    $index_tmp = strtoupper($title[0]);

    if ($index_curr !== $index_tmp) {
        $index_curr = $index_tmp;
        $head       = '## ' . $index_curr . PHP_EOL;
        $hr         = generateHR($index_curr) . PHP_EOL;
        return $hr . PHP_EOL . $head . PHP_EOL;
    }

    return;
}

/* ---------------------------------------------------------------------- [F] */

function fetchVerFromArray($array)
{
    foreach ($array as $line) {
        $result = fetchVerFromString($line);
        if (! empty($result)) {
            return $result;
        }
    }

    return 'n/a';
}

function fetchVerFromMan($array)
{
    $last_line = $array[count($array)-1];
    $last_line = array_values(array_filter(explode(' ', $last_line)));
    
    return implode(' ', $last_line);
}

function fetchVerFromString($string)
{
    $string = (string) $string;

    // 3桁タイプ（xx.xx.xx）
    $pattern = '/(\d+\.)(\d+\.)(\d+)/';

    preg_match($pattern, $string, $array_result);

    if (isset($array_result[0])) {
        return $array_result[0];
    }

    // 2桁タイプ（xx.xx）
    $pattern = '/(\d+\.)(\d+)/';

    preg_match($pattern, $string, $array_result);

    if (isset($array_result[0])) {
        return $array_result[0];
    }

    return $array_result;
}

/* ---------------------------------------------------------------------- [G] */

function generateAsDetailsBlock($title, $content)
{
    return <<<EOL
<details><summary>$ {$title}</summary>
<div>

{$content}

</div></details>

EOL;
}

function generateCodeBlock($cmd, $array)
{
    $array  = (array) $array;
    $string = implodeAndWrodwapArray($array);

    $result  = '```bash' . PHP_EOL;
    $result .= '$ ' . $cmd . PHP_EOL;
    $result .= trim($string) . PHP_EOL;
    $result .= '```' . PHP_EOL;

    if (LINES_TO_DETAIL < count($array)) {
        $result = generateAsDetailsBlock($cmd, trim($result));
    }

    return $result;
}

function generateHR($index, $width = WIDTH_SCREEN)
{
    $head  = '<!-- ';
    $tail  = ' -->';
    $index = "[{$index}]";
    $len   = strlen($head . $tail . $index);
    $diff  = $width-$len ?: $len;
    $body  = str_repeat('-', $width-$len);

    // generates horizontal line: <!-- ---...--- [X] -->
    return $head . $body . $index . $tail . PHP_EOL;
}

function generateTitle($title, $version)
{
    $title   = (string) $title;
    $version = (string) $version;
    $version = "({$version})";

    if (false !== strpos($version, 'n/a')) {
        $version = '（一覧）';
    }

    return "### {$title} {$version}" . PHP_EOL;
}

/* ---------------------------------------------------------------------- [I] */

function isCmdMan($cmd)
{
    return (false !== strpos($cmd, 'man '));
}

function implodeAndWrodwapArray($array)
{
    $result = '';

    foreach ($array as $line) {
        $line = str_replace("\t", '    ', $line);
        if (WIDTH_SCREEN < strlen($line)) {
            $result .= wordwrap($line, WIDTH_SCREEN, PHP_EOL) . PHP_EOL;
            continue;
        }
        $result .= $line . PHP_EOL;
    }

    return $result;
}

/* ---------------------------------------------------------------------- [R] */

function runCmd($cmd)
{
    $cmd        = (string) $cmd . " 2>&1";
    $output     = array();
    $return_var = 0;
    $last_line  = exec($cmd, $output, $return_var);

    return [
        'output' => array_values(array_filter($output)),
        'return' => $return_var,
    ];
}

/* ---------------------------------------------------------------------- [S] */

function sortByTitle(&$list_cmds)
{
    array_multisort(
        array_map(
            'strtolower',
            array_column($list_cmds, 'title')
        ),
        SORT_STRING,
        $list_cmds
    );
}

/* ---------------------------------------------------------------------- [U] */

function updateFileConfig(array $array, $name_file_json)
{
    if (! file_exists($name_file_json)) {
        echo 'Error: Can not find JSON to update.', PHP_EOL;
        die;
    }

    $json = json_encode($array, JSON_PRETTY_PRINT);

    return file_put_contents($name_file_json, $json);
}
