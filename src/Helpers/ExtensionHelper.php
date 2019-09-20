<?php

namespace Chrometoaster\AdvancedTaxonomies\Helpers;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Extensible;
use SilverStripe\Dev\TestOnly;

class ExtensionHelper
{
    use Extensible;


    public static function classes_being_applied(
        $baseClass,
        $extensionName,
        $includeBaseClass = false,
        $includeSubClass = false,
        $excludeTestOnly = true
    ) {
        $doClasses = ClassInfo::subclassesFor($baseClass, $includeBaseClass);
        $excluded  = [];

        foreach ($doClasses as $doClassKey => $doClassName) {
            if (array_key_exists($doClassKey, $excluded)) {
                continue;
            }

            if ($excludeTestOnly) {
                if (is_a($doClassName, TestOnly::class, true)) {
                    unset($doClasses[$doClassKey]);
                    $excluded[$doClassKey] = $doClassName;
                    if (!$includeSubClass) {
                        foreach (ClassInfo::subclassesFor($doClassName, false) as $subClassKey => $subClassName) {
                            if (isset($doClasses[$subClassKey])) {
                                unset($doClasses[$subClassName]);
                                $excluded[$subClassKey] = $subClassName;
                            }
                        }
                    }

                    continue;
                }
            }

            if (!self::has_extension($doClassName, $extensionName)) {
                unset($doClasses[$doClassKey]);
            } else { //has applied the extension
                if (!$includeSubClass) {
                    foreach (ClassInfo::subclassesFor($doClassName, false) as $subClassKey => $subClassName) {
                        if (isset($doClasses[$subClassKey])) {
                            unset($doClasses[$subClassKey]);
                            $excluded[$subClassKey] = $subClassName;
                        }
                    }
                }
            }
        }

        return $doClasses;
    }
}
