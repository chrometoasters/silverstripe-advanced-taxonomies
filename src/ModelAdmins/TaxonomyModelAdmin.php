<?php

namespace Chrometoaster\AdvancedTaxonomies\ModelAdmins;

use Chrometoaster\AdvancedTaxonomies\Models\TaxonomyTerm;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\Requirements;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Management interface for Taxonomies, TaxonomyTerms
 */
class TaxonomyModelAdmin extends ModelAdmin
{
    private static $url_segment = 'at_taxonomy';

    private static $managed_models = [TaxonomyTerm::class];

    private static $menu_title = 'Taxonomies';

    private static $menu_icon_class = 'font-icon-tags';


    public function init()
    {
        parent::init();

        /*
         * TODO: the requirement of cms.js, which is a workaround for the issue described as
         * https://github.com/silverstripe/silverstripe-admin/issues/911, need to be removed once the issue is solved
         */
        Requirements::javascript('chrometoaster/silverstripe-advanced-taxonomies:client/cms.js');
    }


    /**
     * If terms are the models being managed, filter for only top-level terms - no children
     *
     * @return SS_List
     */
    public function getList()
    {
        if ($this->modelClass === TaxonomyTerm::class) {
            $list = parent::getList();

            return $list->filter('ParentID', '0');
        }

        return parent::getList();
    }


    /**
     * Specific editform for taxonomy terms
     *
     * @param null $id
     * @param null $fields
     * @return \SilverStripe\Forms\Form
     */
    public function getEditForm($id = null, $fields = null)
    {
        if ($this->modelClass !== TaxonomyTerm::class) {
            return parent::getEditForm($id, $fields);
        }

        $form = parent::getEditForm($id, $fields);

        /** @var GridField $gf */
        $gf = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass));

        // Setup sorting
            $gf->getConfig()->addComponent(GridFieldOrderableRows::create('Sort'));

        // Customise the GridFieldAddNewButton's label
        $gf->getConfig()->getComponentByType(GridFieldAddNewButton::class)->setButtonName('Add taxonomy');

        $form->addExtraClass('at-modeladmin-editform');

        return $form;
    }
}
