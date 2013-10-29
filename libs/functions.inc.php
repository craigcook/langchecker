<?php

/**
 * If the string exists in the language cache, return it, otherwise return what was
 * given to us. The language cache is build in the l10n_moz class
 */

function ___($str)
{
    $foo = !empty($GLOBALS['__l10n_moz'][$str]) ? $GLOBALS['__l10n_moz'][$str] : $str;
    return (str_replace(' & ', ' &amp; ', $foo));
}


/**
 * print localized string
 *
 */

function e__($str, $clean = 0)
{
    echo ___($str, $clean);
}


/**
 * Checks if a string is localized returns true/false
 */

function c__($str)
{
    return (!empty($GLOBALS['__l10n_moz'][$str])) ? true : false;
}

/**
 * Checks if a string is localized and not identical, returns true/false
 */

function i__($str)
{
    if (!empty($GLOBALS['__l10n_moz'][$str]) && $GLOBALS['__l10n_moz'][$str] != $str) {
        return true;
    } else {
        return false;
    }
}



function getEnglishSource($locale, $website, $filename)
{

    /* data sources */
    include __DIR__ . '/../config/sources.inc.php';

    $path = $sites[$website][1] . $sites[$website][2] . $locale . '/' . $filename;

    /* load the English source file stored in $GLOBALS['__english_moz'] */
    l10n_moz::load($path);

    // it is important to reset the reference value just after getting the English strings
    unset($GLOBALS['reflang']);
}

function getTranslations($locale, $website, $filename)
{
    /* data sources */
    include __DIR__ . '/../config/sources.inc.php';

    $local_file = $sites[$website][1] . $sites[$website][2] . $locale . '/' . $filename;
    /* load the English source file stored in $GLOBALS['__english_moz'] */
    l10n_moz::load($path);
    // it is important to reset the reference value just after getting the English strings
}

function analyseLangFile($locale, $website, $filename)
{
    /* data sources */
    include __DIR__ . '/../config/sources.inc.php';

    $path = $sites[$website][1] . $sites[$website][2] . $locale . '/' . $filename;

    /* load the localized file stored in $GLOBALS['__l10n_moz'], we reset $locale here */
    l10n_moz::load($path);

    /* define sub-arrays for a locale */
    $GLOBALS[$locale]['Identical']   = array();
    $GLOBALS[$locale]['Translated']  = array();
    $GLOBALS[$locale]['Missing']     = array();
    $GLOBALS[$locale]['Obsolete']    = array();
    $GLOBALS[$locale]['python_vars'] = array();
    $GLOBALS[$locale]['activated']   = false;


    if (isset($GLOBALS['__l10n_moz'])) {
        foreach ($GLOBALS['__l10n_moz'] as $key => $val) {

            if ($key == 'filedescription') {
                continue;
            }

            if ($key == 'activated') {
                $GLOBALS[$locale]['activated'] = $val;
                continue;
            }

            if (array_key_exists($key, $GLOBALS['__english_moz'])) {

                if ($val == $GLOBALS['__english_moz'][$key]) {
                    // add to identical strings global array
                    $GLOBALS[$locale]['Identical'][] = $key;
                } elseif ($val != $GLOBALS['__english_moz'][$key]) {
                    // add to OK translated strings global array
                    $GLOBALS[$locale]['Translated'][] = $key;
                    $GLOBALS[$filename][$key] = $val;

                    // We now test if all python variables are ok
                    $regex = '#%\(' . '[a-z0-9._-]+' .'\)s#';
                    preg_match_all($regex, $GLOBALS['__english_moz'][$key], $matches1);
                    preg_match_all($regex, $val, $matches2);

                    if ($matches1[0] != $matches2[0]) {
                        // var_dump($matches1[0], $matches2[0]); exit;
                        foreach ($matches1[0] as $python_var) {
                            if (!in_array($python_var, $matches2[0])) {
                                $GLOBALS[$locale]['python_vars'][$key] = $python_var;
                            }
                        }
                    }

                } elseif ($val == '') {
                    // add to missing strings global array
                    $GLOBALS[$locale]['Missing'][] = $key;
                }
            } elseif (strstr($key, '{l10n-extra}') == true) {
                // add to Identical strings global array
                $GLOBALS[$locale]['Obsolete'][] = str_replace('{l10n-extra}', '', $key);
            } else {
                // add to obsolete strings global array
                $GLOBALS[$locale]['Obsolete'][] = $key;
            }
        }



        foreach ($GLOBALS['__english_moz'] as $key => $val) {

            if ($key == 'filedescription' || $key == 'activated') {
                continue;
            }

            if (!array_key_exists($key, $GLOBALS['__l10n_moz']) && strstr($key, '{l10n-extra}') == false) {
                $GLOBALS[$locale]['Missing'][] = $key;
            }
        }
        /* We destroy $GLOBALS['__l10n_moz'] to allow multiple locales analysis */
        unset($GLOBALS['__l10n_moz']);
    }
}


/**
 * Function sanitizing a string or an array of strings.
 * Returns a string or an array, depending on the input
 *
 */

function secureText($var, $tablo = true)
{
    if (!is_array($var)) {
        $var   = array($var);
        $tablo = false;
    }

    foreach ($var as $item => $value) {
        // CRLF XSS
        $item  = str_replace('%0D', '', $item);
        $item  = str_replace('%0A', '', $item);
        $value = str_replace('%0D', '', $value);
        $value = str_replace('%0A', '', $value);

        // Remove html tags and ASCII characters below 32
        // Deactivated filter since we are still on PHP <5.2 on mozilla.com...
        /*
        $value = filter_var(
            $value,
            FILTER_SANITIZE_STRING,
            FILTER_FLAG_STRIP_LOW
        );
        */
        //

        $item  = htmlspecialchars(strip_tags($item), ENT_QUOTES);
        $value = htmlspecialchars(strip_tags($value), ENT_QUOTES);

        // Repopulate value
        $var2[$item] = $value;
    }

    return ($tablo == true) ? $var2 : $var2[0];
}

function var_dump2($val)
{
    echo '<pre>';
    echo 'LINE: ' . __LINE__ . '<br>';
    var_dump($val);
    echo '</pre>';
}

function showPythonVar($str)
{
    $regex = '#%\(' . '[a-z0-9._-]+' . '\)s#';
    preg_match_all($regex, $str, $matches);
    foreach ($matches[0] as $val) {
        $str = str_replace($val, "<em>${val}</em>", $str);
    }

    return $str;
}


function jsonOutput(array $data, $jsonp = false)
{
    $json = json_encode($data);
    $mime = 'application/json';

    if ($jsonp && is_string($jsonp)) {
        $mime = 'application/javascript';
        $json = $jsonp . '(' . $json . ')';
    }

    ob_start();
    header("access-control-allow-origin: *");
    header("Content-type: {$mime}; charset=UTF-8");
    echo $json;
    $json = ob_get_contents();
    ob_end_clean();
    return $json;
}

/**
 * getUserBaseCoverage()
 *
 * @param $locales an array of locales
 * @param $no_japan boolean, default to true, do we include Japanese?
 *
 * @return a percent value of our coverage for the user base
 */

function getUserBaseCoverage($locales, $no_japan = true)
{
    include __DIR__ . '/../config/adu.inc.php';

    if ($no_japan) {
        $adu['ja'] = $adu['ja-JP-mac'] = 0;
    }

    $english = $adu['en-GB'] + $adu['en-US'] + $adu['en-ZA'];
    $locales = array_intersect_key($adu, array_flip($locales));

    return number_format( array_sum($locales) / (array_sum($adu) - $english) *100, 2);
}


/*
 *  SAPI file (lang_update)
 */

function buildFile($eol, $exceptions = array())
{
    ob_start();
    header('Content-type: text/plain; charset=UTF-8');

    if ($GLOBALS['__l10n_moz']['activated']) {
        echo '## active ##' . $eol;
    }

    foreach ($GLOBALS['__english_moz'] as $key => $val) {
        if ($key == 'activated') {
            continue;
        }

        if ($key == 'filedescription') {
            foreach ($GLOBALS['__english_moz']['filedescription'] as $header) {
                echo '## NOTE: ' . $header . $eol;
            }
            echo  $eol . $eol;
            continue;
        }
        echo dumpString($key, $eol, $exceptions);
    }

    /* put l10n extras at the end of the file */
    foreach ($GLOBALS['__l10n_moz'] as $key => $val) {
        if (strstr($key, '{l10n-extra}') == true) {
            echo dumpString($key, $eol);
        }
    }

    $content = ob_get_contents();
    ob_end_clean();

    return $content;
}


/*
 *  SAPI file (lang_update)
 */
function dumpString($english, $eol, $exceptions = array())
{
    if ($english == 'activated') {
        return;
    }

    $chunk = '';

    if (isset($GLOBALS['__l10n_comments'][$english])) {
        $chunk .= '# ' . trim($GLOBALS['__l10n_comments'][$english]) . $eol;
    }

    $chunk .= ";$english" . $eol;

    $span_to_br = function ($str) {
        return str_replace(array('<span>', '</span>'), array('<br />', ''), $str);
    };

    if (array_key_exists($english, $exceptions)
        && isset($GLOBALS['__l10n_moz'][$exceptions[$english]])
        ) {
        $tempString = strip_tags($GLOBALS['__l10n_moz'][$exceptions[$english]]);

        if ($english == 'Potentially vulnerable plugins') {
            $tempString = str_replace(':', '', $tempString);
        } elseif ($english == 'vulnerable') {
            $tempString = strtolower($tempString);
        }

        $chunk .= $tempString;
    } else {
        $chunk .= (array_key_exists($english, $GLOBALS['__l10n_moz'])) ? $GLOBALS['__l10n_moz'][$english]: $english;
    }

    $chunk .= $eol . $eol . $eol;

    return $chunk;
}


/*
 *  SAPI file (lang_update)
 */
function fileForceContents($dir, $contents)
{
    $parts = explode('/', $dir);
    $file = array_pop($parts);
    $dir = '';

    foreach ($parts as $part) {
        if (!is_dir($dir .= "/$part")) {
            mkdir($dir);
        }
    }

    file_put_contents("$dir/$file", $contents);
}


/*
 *  SAPI file (lang_update)
 */
function isWindowsEOL($line)
{
    return (substr($line, -2) === "\r\n") ? true : false;
}


/*
 *  SAPI file (lang_update)
 */
function scrapLocamotion($_lang, $filename, $source)
{
    global $mozillaorg_lang;
    global $locamotion_locales;

    if (!in_array($_lang, $locamotion_locales)) {
        return;
    }

    logger('== ' . $_lang . ' ==');

    /* import data from locamotion */
    $locamotion = 'https://raw.github.com/translate/mozilla-lang/master/'
                  . str_replace('-', '_', $_lang)
                  . '/' . $filename . '.po';
    $po_exists = strstr(get_headers($locamotion, 1)[0], '200') ? true : false;

    if ($po_exists) {
        logger("Fetching $filename from Locamotion");
        file_put_contents('temp.po', file_get_contents($locamotion));

        $po_parser = new PoParser();
        $po_strings = $po_parser->read('temp.po');

        $temp_lang  = '';

        if (count($po_strings) > 0) {

            foreach ($po_strings as $entry) {
                if (!isset($entry['fuzzy']) && $entry['msgstr'][0] != '') {
                    $temp_lang .=
                        ';'
                        . $entry['msgid'][0] . PHP_EOL
                        . $entry['msgstr'][0] . PHP_EOL. PHP_EOL. PHP_EOL;
                }
            }

            file_put_contents('temp.lang', $temp_lang);

            // we keep copies of the global array in $a and $b for diff puroposes
            $a = $GLOBALS['__l10n_moz'];
            l10n_moz::load('temp.lang');
            $b = $GLOBALS['__l10n_moz'];
            $have_imported_strings = false;

            foreach ($b as $key => $val) {
                $val = is_array($val) ? $val[0] : $val;
                if ($key != $val
                    && $key != 'filedescription'
                    && $key != 'activated'
                    && array_key_exists($key, $a)
                    ) {
                    if ($a[$key] == $key) {
                        logger('Imported string: ' . $key .' => ' . $val);
                        $have_imported_strings = true;
                    }
                }
            }

            if ($have_imported_strings) {
                logger("Data from Locamotion extracted and added to local repository.");
            } else {
                logger("No new strings from Locamotion added to local repository.");
            }

            unlink('temp.lang');
            unset($po_parser);

        } else {
            logger($filename . '.po has no strings in it');
        }

        unlink('temp.po');
    }
}


/*
 *  SAPI file (lang_update)
 */

function logger($str, $action='')
{
    error_log($str . "\n");
    if ($action == 'quit') {
        die;
    }
}
