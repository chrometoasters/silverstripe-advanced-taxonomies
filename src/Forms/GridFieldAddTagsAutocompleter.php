<?php

namespace Chrometoaster\AdvancedTaxonomies\Forms;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\TextField;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

class GridFieldAddTagsAutocompleter extends GridFieldAddExistingAutocompleter
{
    private $buttonText = 'Add tag';


    /**
     * A copy from its original Class, in order to:
     * 1. change 'Link Existing' button text to 'Add tag'
     * 2. don't set the addAction to `readonly`, cos currently the frontend doesn't change it back to active if a object
     *    is found, and it seemed a GridFieldAddRelation legacy component / or hard-coded config is missing;
     *
     * @param GridField $gridField
     * @return string[] - HTML
     */
    public function getHTMLFragments($gridField)
    {
        $dataClass = $gridField->getModelClass();

        $forTemplate         = new ArrayData([]);
        $forTemplate->Fields = new FieldList();

        $searchField = new TextField('gridfield_relationsearch', _t('SilverStripe\\Forms\\GridField\\GridField.RelationSearch', 'Relation search'));

        $searchField->setAttribute('data-search-url', Controller::join_links($gridField->Link('search')));
        $searchField->setAttribute('placeholder', $this->getPlaceholderText($dataClass));
        $searchField->addExtraClass('relation-search no-change-track action_gridfield_relationsearch');

        $findAction = new GridField_FormAction(
            $gridField,
            'gridfield_relationfind',
            _t('SilverStripe\\Forms\\GridField\\GridField.Find', 'Find'),
            'find',
            'find'
        );
        $findAction->setAttribute('data-icon', 'relationfind');
        $findAction->addExtraClass('action_gridfield_relationfind');

        $addAction = new GridField_FormAction(
            $gridField,
            'gridfield_relationadd',
            $this->getButtonText(),
            'addto',
            'addto'
        );
        $addAction->setAttribute('data-icon', 'chain--plus');
        $addAction->addExtraClass('btn btn-outline-secondary font-icon-link action_gridfield_relationadd');

        // If an object is not found, disable the action
//        if (!is_int($gridField->State->GridFieldAddRelation(null))) {
//            $addAction->setReadonly(true);
//        }

        $forTemplate->Fields->push($searchField);
        $forTemplate->Fields->push($findAction);
        $forTemplate->Fields->push($addAction);
        if ($form = $gridField->getForm()) {
            $forTemplate->Fields->setForm($form);
        }

        $template = SSViewer::get_templates_by_class($this, '', __CLASS__);

        return [
            $this->targetFragment => $forTemplate->renderWith($template),
        ];
    }


    public function setButtonText($text)
    {
        $this->buttonText = $text;

        return $this;
    }


    public function getButtonText()
    {
        return $this->buttonText;
    }
}
