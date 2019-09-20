<?php

namespace Chrometoaster\AdvancedTaxonomies\Generators;

/*
    The MIT License (MIT)
    Copyright (c) 2015

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.
*/

/**
 * This class is a partial copy of a Gist:
 * https://gist.github.com/tbrianjones/ba0460cc1d55f357e00b with very minor changes. As required by the author of the
 * Gist, we included the whole block of copyright and license related statements as above.
 *
 * Class PluralGenerator
 */
class PluralGenerator
{
    // A mapping from word pattens to pluralising rule
    public static $plural = [
        '/(quiz)$/i'                     => '$1zes',
        '/^(ox)$/i'                      => '$1en',
        '/([m|l])ouse$/i'                => '$1ice',
        '/(matr|vert|ind)ix|ex$/i'       => '$1ices',
        '/(x|ch|ss|sh)$/i'               => '$1es',
        '/([^aeiouy]|qu)y$/i'            => '$1ies',
        '/(hive)$/i'                     => '$1s',
        '/(?:([^f])fe|([lr])f)$/i'       => '$1$2ves',
        '/(shea|lea|loa|thie)f$/i'       => '$1ves',
        '/sis$/i'                        => 'ses',
        '/([ti])um$/i'                   => '$1a',
        '/(tomat|potat|ech|her|vet)o$/i' => '$1oes',
        '/(bu)s$/i'                      => '$1ses',
        '/(alias)$/i'                    => '$1es',
        '/(octop)us$/i'                  => '$1i',
        '/(ax|test)is$/i'                => '$1es',
        '/(us)$/i'                       => '$1es',
        '/s$/i'                          => 's',
        '/$/'                            => 's',
    ];

    // A list of words that don't follow any of the rules in the self::$plural map above
    public static $irregular = [
        'move'   => 'moves',
        'foot'   => 'feet',
        'goose'  => 'geese',
        'sex'    => 'sexes',
        'child'  => 'children',
        'man'    => 'men',
        'woman'  => 'women',
        'tooth'  => 'teeth',
        'person' => 'people',
        'valve'  => 'valves',
    ];

    // A list of words that are uncountable
    public static $uncountable = [
        'sheep',
        'fish',
        'deer',
        'series',
        'species',
        'money',
        'rice',
        'information',
        'equipment',
    ];


    /**
     * @param $string
     * @return string
     */
    public static function generate($string)
    {

        // For singular and plural are the same
        if (in_array(mb_strtolower($string), self::$uncountable)) {
            return $string;
        }


        // Check for irregular singular forms
        foreach (self::$irregular as $pattern => $result) {
            $pattern = '/' . $pattern . '$/i';

            if (preg_match($pattern, $string)) {
                return preg_replace($pattern, $result, $string);
            }
        }

        // Check for matches using regular expressions
        foreach (self::$plural as $pattern => $result) {
            if (preg_match($pattern, $string)) {
                return preg_replace($pattern, $result, $string);
            }
        }

        return $string;
    }
}
