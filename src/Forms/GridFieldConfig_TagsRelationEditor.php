<?php

namespace Chrometoaster\AdvancedTaxonomies\Forms;

use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Versioned\GridFieldArchiveAction;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Similar to {@link GridFieldConfig_RelationEditor}, but removes components early on and changes the fragment for
 * the auto-completer.
 */
class GridFieldConfig_TagsRelationEditor extends GridFieldConfig
{
    public function __construct(string $sortField = 'Sort')
    {
        parent::__construct();

        $this->addComponent(GridFieldButtonRow::create('before'));
        $this->addComponent(GridFieldAddTagsAutocompleter::create('buttons-before-left'));
        $this->addComponent(GridFieldToolbarHeader::create());
        $this->addComponent(GridFieldSortableHeader::create());
        $this->addComponent(GridFieldDataColumns::create());
        $this->addComponent(GridFieldDeleteAction::create(true));
        $this->addComponent(GridField_ActionMenu::create());
        $this->addComponent(GridFieldPageCount::create('toolbar-header-right'));
        $this->addComponent(GridFieldPaginator::create());
        $this->addComponent(GridFieldDetailForm::create());
        $this->addComponent(GridFieldOrderableRows::create($sortField));
        $this->extend('updateConfig');
        $this->removeComponentsByType(GridFieldArchiveAction::class);
    }
}
