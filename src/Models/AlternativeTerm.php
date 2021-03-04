<?php

namespace Chrometoaster\AdvancedTaxonomies\Models;

/**
 * Represents a base alternative term that can be associated to a taxonomy term
 *
 * Separate class existing to support ORM relationships without involving all term classes.
 */
class AlternativeTerm extends BaseTerm
{
    private static $table_name = 'AT_AlternativeTerm';

    private static $singular_name = 'Alternative term';

    private static $plural_name = 'Alternative terms';


    /**
     * @return string
     */
    public function getAltTermTitle(): string
    {
        return $this->Name ?: ('Alternative term ID #' . $this->ID);
    }
}
