<?php
namespace Langchecker;

/**
 * GetTextManager class
 *
 * This class is used to read reference and localized files in GetText format
 *
 *
 * @package Langchecker
 */
class GetTextManager
{
    /**
     * Read .po file and return array of translated (not fuzzy)
     * strings [original]=>translation
     *
     * @param string $path Path to the file to read
     *
     * @return array Array of strings
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

    /**
     * Read .po file from Locamotion's github repository, return updated strings
     *
     * @param array  $locale_data      Array of data for locale file
     * @param string $current_filename Analyzed file
     * @param string $current_locale   Requested locale
     * @param string $lomocation_repo  Path to local clone of Locamotion's repository
     *
     * @return array Result of import (boolean), errors (array),
     *               updated strings (array)
     */
    public static function importLocamotion($locale_data, $current_filename, $current_locale, $locamotion_repo)
    {
        $result = [
          'imported' => false,
          'errors'   => [],
          'strings'  => [],
        ];

        Utils::logger("== {$current_locale} ==");

        $local_import = $locamotion_repo != '' ? true : false;

        if ($local_import) {
            // Import from local clone
            $file_path = $locamotion_repo .
                         str_replace('-', '_', $current_locale)
                         . '/' . $current_filename . '.po';
            $po_exists = file_exists($file_path);
        } else {
            // Import data from remote
            $locamotion_url = 'https://raw.githubusercontent.com/translate/mozilla-lang/master/'
                              . str_replace('-', '_', $current_locale)
                              . '/' . $current_filename . '.po';
            $http_response = get_headers($locamotion_url, 1)[0];
            $po_exists = strstr($http_response, '200') ? true : false;
        }

        if ($po_exists) {
            if ($local_import) {
                $po_strings = self::loadPoFile($file_path);
            } else {
                Utils::logger("Fetching {$current_filename} from Locamotion.");
                // Create temporary file (temp.po), delete it after extracting strings
                file_put_contents('temp.po', file_get_contents($locamotion_url));
                $po_strings = self::loadPoFile('temp.po');
                unlink('temp.po');
            }

            if (count($po_strings) == 0) {
                Utils::logger('.po file is empty.');
            } else {
                foreach ($po_strings as $string_id => $translation) {
                    if (isset($locale_data['strings'][$string_id])) {
                        // String is available in the local lang file, check if is different
                        if (Utils::startsWith($po_strings[$string_id], ';')) {
                            // Translations can't start with ";"
                            $result['errors'][] = "({$current_locale} - {$current_filename}): translation starts with ;\n{$po_strings[$string_id]}";
                        } elseif ($po_strings[$string_id] !== $locale_data['strings'][$string_id]) {
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
            if ($local_import) {
                Utils::logger("{$file_path} does not exist.");
            } else {
                Utils::logger("{$locamotion_url} does not exist, http code was {$http_response}");
            }

            return $result;
        }

        if ($result['imported']) {
            Utils::logger('Locamotion data extracted and added to local repository.');
        } else {
            Utils::logger('No new strings from Locamotion added to local repository.');
        }

        return $result;
    }

    /**
     * Read local .po file, return updated strings
     *
     * @param string  $po_filename    Path to po file
     * @param array   $locale_data    Array of data for locale file
     * @param boolean $output_message True (default) to output messages in console
     *
     * @return array Result of import (boolean), errors (array),
     *               updated strings (array)
     */
    public static function importLocalPoFile($po_filename, $locale_data, $output_message = true)
    {
        $result = [
          'imported' => false,
          'errors'   => [],
          'strings'  => [],
        ];

        // Read po file
        $po_strings = self::loadPoFile($po_filename);

        if (count($po_strings) == 0 && $output_message) {
            Utils::logger('.po file is empty.');
        } else {
            foreach ($po_strings as $string_id => $translation) {
                if (isset($locale_data['strings'][$string_id])) {
                    // String is available in the local lang file, check if is different
                    if (Utils::startsWith($po_strings[$string_id], ';')) {
                        // Translations can't start with ";"
                        $result['errors'][] = "Translation starts with ;\n{$po_strings[$string_id]}";
                    } elseif ($po_strings[$string_id] !== $locale_data['strings'][$string_id]) {
                        // Translation in the .po file is different
                        if ($output_message) {
                            Utils::logger("Updated translation: {$string_id} => {$translation}");
                        }
                        $locale_data['strings'][$string_id] = $translation;
                        $result['imported'] = true;
                    }
                }
            }
            $result['strings'] = $locale_data['strings'];
        }

        if ($output_message) {
            if ($result['imported']) {
                Utils::logger('Data imported.');
            } else {
                Utils::logger('No data imported.');
            }
        }

        return $result;
    }
}
