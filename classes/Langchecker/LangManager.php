<?php
namespace Langchecker;

/*
 * LangManager class
 *
 * This class is used to read reference and localized files
 *
 * @package Langchecker
 */
class LangManager
{
    /*
     * Load file, remove empty lines and return an array of strings
     *
     * @param   array   $website   Website data
     * @param   string  $locale    Locale to analyze
     * @param   string  $filename  File to analyze
     * @return  array              Data parsed from lang file
     */
    public static function loadSource($website, $locale, $filename)
    {
        $is_reference_language = ($locale == Project::getReferenceLocale($website));
        $path = Project::getLocalFilePath($website, $locale, $filename);

        return DotLangParser::parseFile($path, $is_reference_language);
    }

    /*
     * Load file, remove empty lines and return an array of strings
     *
     * @param   array   $website         Website data
     * @param   string  $locale          Locale to analyze
     * @param   string  $filename        File to analyze
     * @param   array   $reference_data  Array with data from reference locale
     * @return  array                    Analysis data
     */
    public static function analyzeLangFile($website, $locale, $filename, $reference_data)
    {
        $locale_data = self::loadSource($website, $locale, $filename);

        $analysis_data['Identical']   = [];
        $analysis_data['Translated']  = [];
        $analysis_data['Missing']     = [];
        $analysis_data['Obsolete']    = [];
        $analysis_data['python_vars'] = [];
        $analysis_data['activated'] = $locale_data['activated'];

        foreach ($locale_data['strings'] as $key => $val) {
            if (isset($reference_data['strings'][$key])) {
                if ($val === $reference_data['strings'][$key]) {
                    // Store as identical string
                    $analysis_data['Identical'][] = $key;
                } elseif ($val !== $reference_data['strings'][$key]) {
                    // Store in translated strings
                    $analysis_data['Translated'][] = $key;

                    // Test if all Python variables are present
                    $regex = '#%\(' . '[a-z0-9._-]+' .'\)s#';
                    preg_match_all($regex, $reference_data['strings'][$key], $matches1);
                    preg_match_all($regex, $val, $matches2);

                    if ($matches1[0] != $matches2[0]) {
                        foreach ($matches1[0] as $python_var) {
                            if (! in_array($python_var, $matches2[0])) {
                                $analysis_data['python_vars'][$key]['text'] = $val;
                                $analysis_data['python_vars'][$key]['var'] = $python_var;
                            }
                        }
                    }
                } elseif ($val === '') {
                    // Store in missing strings
                    $analysis_data['Missing'][] = $key;
                }
            } else {
                // Store in obsolete strings
                $analysis_data['Obsolete'][] = $key;
            }
        }

        // Use reference to determine missing strings
        foreach ($reference_data['strings'] as $key => $val) {
            if (! isset($locale_data['strings'][$key])) {
                $analysis_data['Missing'][] = $key;
            }
        }

        return $analysis_data;
    }

    /*
     * Check if a string is localized
     *
     * @param   string   $string_id       String id to check
     * @param   array    $locale_data     Array with data for locale
     * @param   array    $reference_data  Array with data for reference locale
     * @return  boolean                   True if string is localized
     */
    public static function isStringLocalized($string_id, $locale_data, $reference_data)
    {
        if (isset($locale_data['strings'][$string_id])) {
            // Only check if string is not missing from locale file
            if ($locale_data['strings'][$string_id] !== $reference_data['strings'][$string_id]) {
                return true;
            }
        }

        return false;
    }

    /*
     * Create lang file content
     *
     * @param   array   $reference_data  Array with data for reference locale
     * @param   array   $locale_data     Array with data for locale
     * @param   string  $current_locale  Requested locale
     * @param   string  $eol             EOL character to use
     * @return  string                   Lang file content
     */
    public static function buildLangFile($reference_data, $locale_data, $current_locale, $eol)
    {
        ob_start();
        header('Content-type: text/plain; charset=UTF-8');

        // Two empty lines between strings
        $spacer = "{$eol}{$eol}";

        // Activation status
        if ($locale_data['activated']) {
            echo "## active ##{$eol}";
        }

        // Tags: if reference doesn't have tags, locale shouldn't have them either
        if (isset($locale_data['tags']) &&
            isset($reference_data['tags'])) {
            $tags = $locale_data['tags'];
            // Ignore tags not available in reference
            $tags = array_intersect($tags, $reference_data['tags']);
            foreach ($tags as $tag) {
                echo "## {$tag} ##{$eol}";
            }
        }

        // Description
        if (isset($reference_data['filedescription'])) {
            foreach ($reference_data['filedescription'] as $description) {
                echo "## NOTE: {$description}{$eol}";
            }
            echo $spacer;
        }

        // List of string exceptions
        $exceptions = [
            '@@this is a test, do not remove@@', // Don't remove, used for tests
        ];

        foreach ($reference_data['strings'] as $string_id => $string_value) {
            // Do we have comments for this string?
            if (isset($reference_data['comments'][$string_id])) {
                echo "# " . $reference_data['comments'][$string_id] . $eol;
            }

            $translation = isset($locale_data['strings'][$string_id]) ?
                           $locale_data['strings'][$string_id] :
                           '';

            if (in_array($string_id, $exceptions)) {
                // This string needs to be managed as an exception
                $exception_string = self::manageStringExceptions($string_id, $translation, $current_locale);
                echo ";{$exception_string['reference']}{$eol}";
                echo "{$exception_string['translation']}{$eol}";
            } else {
                echo ";{$string_id}{$eol}";
                if ($translation !== '') {
                    // I have a translation
                    echo "{$translation}{$eol}";
                } else {
                    // No translation available, copy reference
                    echo "{$string_id}{$eol}";
                }
            }
            echo $spacer;
        }

        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    /*
     * Manage string exceptions
     *
     * @param   string  $string_id       String we need to manage
     * @param   string  $translation     Current translation
     * @param   string  $current_locale  Requested locale
     * @return  array                    Modified reference and translated strings
     */
    public static function manageStringExceptions($string_id, $translation, $current_locale)
    {
        /* This function is used to manage exceptions for strings.
         * Function must return both reference and translation, since me may need
         * to change any of them.
         */

        // Example function used in tests, don't delete
        if ($string_id == '@@this is a test, do not remove@@' && $current_locale == 'it') {
            $result['reference'] = 'this is a test';
            $result['translation'] = str_replace('test', '<a href="%(url)s">test</a>', $translation);

            return $result;
        }

        return [
            'reference'   => $string_id,
            'translation' => $translation,
        ];
    }

    /*
     * Read .po file and return array of translated (not fuzzy) strings [original]=>translation
     *
     * @param   string  $path  Path to the file to read
     * @return  array          Array of strings
     */
    public static function loadPoFile($path)
    {
        $po_parser = new \Sepia\PoParser();
        $po_strings = $po_parser->read($path);

        $po_data = [];
        if (count($po_strings) > 0) {
            foreach ($po_strings as $entry) {
                if (!isset($entry['fuzzy']) && implode($entry['msgstr']) != '') {
                    if (implode($entry['msgid']) == implode($entry['msgstr'])) {
                        // Add {ok} if the translation is identical to the English string
                        $string_status = ' {ok}';
                    } else {
                        $string_status = '';
                    }
                    $po_data[implode($entry['msgid'])] = trim(implode($entry['msgstr']) . $string_status);
                }
            }
        }

        return $po_data;
    }

    /*
     * Read .po file from Locamotion, store data in local lang file
     *n
     * @param   array    $locale_data       Array of data for locale file
     * @param   string   $current_filename  Analyzed file
     * @param   string   $current_locale    Requested locale
     * @return  array                       Result of import (boolean), updated strings (array)
     */
    public static function importLocamotion($locale_data, $current_filename, $current_locale)
    {
        $result['imported'] = false;
        $result['strings'] = [];

        Utils::logger('== ' . $current_locale . ' ==');

        // Import data from locamotion
        $locamotion_url = 'https://raw.githubusercontent.com/translate/mozilla-lang/master/'
                          . str_replace('-', '_', $current_locale)
                          . '/' . $current_filename . '.po';
        $http_response = get_headers($locamotion_url, 1)[0];
        $po_exists = strstr($http_response, '200') ? true : false;

        if ($po_exists) {
            Utils::logger("Fetching {$current_filename} from Locamotion.");

            // Create temporary file (temp.po), delete it after extracting strings
            file_put_contents('temp.po', file_get_contents($locamotion_url));
            $po_strings = self::loadPoFile('temp.po');
            unlink('temp.po');

            if (count($po_strings) == 0) {
                Utils::logger(".po file is empty.");
            } else {
                foreach ($po_strings as $string_id => $translation) {
                    if (isset($locale_data['strings'][$string_id])) {
                        // String is available in the local lang file, check if is different
                        if ($po_strings[$string_id] !== $locale_data['strings'][$string_id]) {
                            // Translation in the .po file is different
                            Utils::logger("Updated translation: {$string_id} => {$translation}");
                            $locale_data['strings'][$string_id] = $translation;
                            $result['imported'] = true;
                        }
                    }
                }
                $result['strings'] = $locale_data['strings'];
            }
        } else {
            Utils::logger("{$locamotion_url} does not exist, http code was {$http_response}");
            return $result;
        }

        if ($result['imported']) {
            Utils::logger("Data from Locamotion extracted and added to local repository.");
        } else {
            Utils::logger("No new strings from Locamotion added to local repository.");
        }

        return $result;
    }
}