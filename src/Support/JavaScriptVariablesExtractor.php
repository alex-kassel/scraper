<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Support;

trait JavaScriptVariablesExtractor
{
    /**
     * Extracts JavaScript variables from a text and processes them.
     *
     * @param string $script The text containing JavaScript variables.
     * @param array $desired_vars The desired variables and their edits.
     *                          Example:
     *                          [
     *                              'buyer_type' => [],
     *                              'SHIPPING_COST' => [
     *                                  'Versand:' => 'Versand',
     *                              ],
     *                          ]
     * @return array|array[] The processed array of extracted variables.
     */
    protected function extractJsVars(string $script, array $desired_vars = []): array
    {
        preg_match_all('~(\w+)\s*=\s*(.*?);~', html_entity_decode($script), $matches);
        $parsed_vars = array_combine($matches[1], $matches[2]);

        if ($desired_vars) {
            foreach($desired_vars as $desired_var => $replaces) {
                if (! in_array($desired_var, $matches[1])) {
                    $desired_vars['vars_not_found'][] = $desired_var;
                    unset($desired_vars[$desired_var]);
                    continue;
                }

                $value = str_replace(
                    array_keys($replaces),
                    array_values($replaces),
                    $parsed_vars[$desired_var]
                );

                $value = preg_replace('/(\w+):/i', '"\1":', str_replace("'", '"', $value));
                $desired_vars[$desired_var] = json_decode($value, true);
            }
        } else {
            foreach ($parsed_vars as $key => &$value) {
                $value = preg_replace('/(\w+):/i', '"\1":', str_replace("'", '"', $value));
                $value = json_decode($value, true);
            }
        }

        return $desired_vars ?: $parsed_vars;
    }

    /**
     * @param string $string
     * @return array
     *
     * $string = 'das ist ein test mit [1, 2, 5, "kuku"] array';
     * [$start, $length] = $this->getObjStartLength($string);
     * dd(json_decode(substr($string, $start, $length)));
     */
    function getObjStartLength(string $string): array
    {
        for($i = 0, $brackets = '{}[]""'; $i < strlen($brackets); $i += 2) {
            $pairs[$brackets[$i]] = $brackets[$i+1];
        }

        for($i = 0, $check = []; $i < strlen($string); $i++) {
            if(!isset($start)) {
                if(isset($pairs[$string[$i]])) {
                    $start = $string[$i];
                    $check[] = $start;
                    $output[0] = $i;
                }
            } elseif($string[$i] == $start && !($start == '"' && $start == end($check))) {
                $check[] = $string[$i];
            } elseif($string[$i] == $pairs[$start]) {
                array_pop($check);
            }

            if(isset($start) && !$check) {
                $output[1] = $i - $output[0] + 1;
                return $output;
            }
        }

        return [];
    }
}
