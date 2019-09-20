<?php

namespace Chrometoaster\AdvancedTaxonomies\Extensions;

use SilverStripe\Admin\LeftAndMainExtension;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\Requirements;

/**
 * Class TaxonomyModelAdminExtension
 *
 * This extension, configured to apply to ModelAdmin controller, to load customised style sheet, and tweak the GridField
 * columns 'Tags' in a way that populate the column with HTML tag and class attributes
 */
class TaxonomyModelAdminExtension extends LeftAndMainExtension
{
    public function init()
    {
        Requirements::css('chrometoaster/silverstripe-advanced-taxonomies:client/style.css');
    }


    /**
     * @param mixed $form
     *
     * The function sets the column TagNames (labeled 'Tags') casting as HTMLFragment, if the DataObject managed by
     * this ModelAdmin has applied the DataObjectTaxonomiesDataExtension hence having a `Tags` column in the landing
     * GridField of the managed model. The HTML in the columns contains much rich information about the tag, so it need
     * to be hidden, and then shown as tooltips.
     */
    public function updateEditForm($form)
    {
        $modelClass = $this->owner->getModelClass();
        if (DataObject::has_extension($modelClass, DataObjectTaxonomiesDataExtension::class)) {
            if ($grid = $form->Fields()->dataFieldByName(str_replace('\\', '-', $modelClass))) {
                $config      = $grid->getConfig();
                $dataColumns = $config->getComponentByType(GridFieldDataColumns::class);
                if (isset($dataColumns->getDisplayFields($grid)['TagNames'])) {
                    $dataColumns->setFieldCasting([
                        'TagNames' => 'HTMLFragment->RAW',
                    ]);
                }
            }
        }
    }
}
