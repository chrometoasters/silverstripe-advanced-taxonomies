Advanced Taxonomies
===================


# Overview

Inspired by SilverStripe's Taxonomy module, this module provides a more advanced tagging system. Like the Taxonomy
module, Advanced Taxonomies adds the capability to add and edit simple taxonomies within SilverStripe. It also provides
generic CMS interfaces to tag taxonomy terms to `DataObject`, in both *React* and *non-React* context UI. Beyond basic
tagging, the module's features are aimed at professional information architects who wish to use taxonomies to classify
most — if not all — of the content across a site, using a suite of interrelated hierarchical taxonomies. Advanced logic
makes the use of taxonomies a more robust option for content-wide relationships, especially where multiple taxonomies
must be used in conjunction.

*Important note*: Advanced Taxonomies is _inspired by_ SilverStripe's taxonomy module but it is _not an extension to_
that module. This module is standalone and intended to be used _instead of_ the Taxonomy module. It uses separate
namespaces and separate database tables. Though some legacy field names are the same as SilverStripe's Taxonomy module,
this is simply an affordance for making data migration between the two modules a little easier.


# Features

1. Additional features have been added to a `TaxonomyTerm` data object. 
    * A `URLSegment` attribute is given to the `TaxonomyTerm` object. This attribute is globally unique so that is can
    be used as another identifier (besides its database record ID) in any indexing or look-up operation.
    * Singular and Plural display names can be added to any `TaxonomyTerm`, in addition to the Name of the term.
    * A Description can be added to the `TaxonomyTerm`, which is intended to be used throughout the CMS (e.g. within
    GridFields).
    * Two Definition fields are available per term. One is intended for use in authoring scenarios (within the CMS), the
    other intended to be shown to end-users (i.e. integrated into the front-end).
    * A taxonomy can be designated as either 'single-' or 'multi-select', dependent on the nature of the terms/concepts
    — being synergistic or mutually exclusive. Validation rules apply when authors tag data objects with a 
    single-select taxonomy term (i.e. only one term can be tagged at a time).
    * A root-level Display preference determines whether terms from a taxonomy should be shown to end-users, or not.
    This flag allows for some taxonomies to optionally be used for 'administrative purposes' only. 
    * Any term can be configured with one or more Required taxonomies. Required taxonomies will prompt authors to select
    a term from other taxonomies, too, when the current term is assigned to a data object. If required taxonomies are
    set on the root term, then all descendant terms will inherit the requirements. Inheritance can be manually
    overridden, and further requirements configured, on individual terms.
2. The `TaxonomyType` data object has been removed from the SilverStripe Taxonomy module. Instead, this module assumes
that the root terms of all taxonomies (those shown in the first view of the ModelAdmin) are, in fact, always the top
terms of individual, hierarchical taxonomies. The attributes typically present in the `TaxonomyType` (those which tend
to group and provide management functionality to many individual terms) have been moved to the taxonomy's root term. 
3. Inheritance has been employed to manage various attributes of descendant terms. Specifically:
    * `TypeID` — the root term's system ID is spread to the rest of the taxonomy tree as `TypeID`, including the root
    term itself. 
    * `SingleSelect` — as this is used in the tagging logic, the value of this flag is not allowed to change once a term
    from this taxonomy tree is found being used (i.e. tagged to a data object).
    * `DisplayPreference`— a flag determining whether the term should be shown on the front-end.
4. To achieve the Required taxonomies feature, all taxonomy terms have a many_many relationship to other taxonomy terms,
called `RequiredTypes`. `RequiredTypes` configured on the root term act as the base for the rest of this taxonomy tree;
other terms can customise their own `RequiredTypes`. Non-root terms can set a flag 'RequiredTypesInheritRoot' to be
either `true` (default value) or `false`. When set as `true`, the overall required types for that term object will be
the conjunction of the root term's `RequiredTypes` and the term's own `RequiredTypes`; when `false`, only the term's own
`RequiredTypes` is used in the tagging logic.
5. Once tagged to a data object (e.g. a page), the term's Name can be seen in List Views and GridFields, styled as a
tag. Additional metadata, including author-specific definitions, are revealed through tooltips.
6. The two most used types of DataObject are configured to have tagging relations between the data object and taxonomy
term, they are:
    * `SiteTree` dependent module `silverstripe/cms`
    * `File`dependent module `silverstripe/assets`

All the attributes of `TaxonomyTerm` carry with them a handy explanation, visible in the CMS interface. Some of the
explanations are contextual and differ based on the status of the switch, e.g. the `SingleSelect` explanation changes
depending on whether it has become read-only, due to terms having been tagged to data objects already.


# Usage

## Requirements

* SilverStripe 4.x
* PHP 7.x


## Installation

```composer
composer require chrometoaster/silverstripe-advanced-taxonomies
```
## Configure

By default, the module adds extension `DataObjectTaxonomiesDataExtension` to `SiteTree` and `File` by
[config.yml](_config/config.yml). To add the extension to your project-specific DataObject, please refer to
[config.yml](_config/config.yml) as code sample and make another YML file under your app/_config folder, add some config
like the following:

```yaml
App\Models\YourDataModel:
  extensions:
    - Chrometoaster\AdvancedTaxonomies\Extensions\DataObjectTaxonomiesDataExtension
SilverStripe\Security\Member:
  extensions:
    - Chrometoaster\AdvancedTaxonomies\Extensions\DataObjectTaxonomiesDataExtension
```

There are some settings that can be modified in this module, depending on requirements. These are listed below.

### disable ModelTagLogicValidator to output its error message in HTML

```yaml
Chrometoaster\AdvancedTaxonomies\Validators\ModelTagLogicValidator:
  - output_html_enabled: false
```
When the flag is set to `false`, the error messages created by the validator don't contain any HTML tags. When it is set
to `true`, the error messages created by the validator contain HTML tags. For example, a <br /\> between two paragraphs
of invalid messages for `SingleSelect` and `RequiredTypes` logic; in the messages where a set of offending terms are
mentioned, the CMS edit links will be provided inline with the terms.

Enabling output as HTML could be very useful when the error messages could be displayed as HTML, e.g in the 'Tags' tab
of the CMS edit form for a page. Here, the validator is used to generate warning message which could be displayed as
HTML for a nice looking UI, while also being handy for end-users to click on the terms' links and find out more about
the offending terms.

In the `asset-admin` interface, when a file is saved with tags that break the logic rules, the error messages are
injected into a React component. Currently, not all FormField React components can handle messages that contain HTML
tags; all the HTML elements in the error messages will be displayed as raw HTML code. In such instances, we have to
disable the HTML output. Sorry about that — we tried.


# Contributing

## Code guidelines

This project follows the standards defined in:

* [PSR-1](http://www.php-fig.org/psr/psr-1/)
* [PSR-2](http://www.php-fig.org/psr/psr-2/)
* [RSR-4](http://www.php-fig.org/psr/psr-4/)

If you are helping out, please also follow the standards above.

## Translations

The module is targeting to support multiple languages in the long term. please see the 
["i18n" topic](https://docs.silverstripe.org/en/developer_guides/i18n/) on docs.silverstripe.org for more details.