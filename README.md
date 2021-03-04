# Advanced Taxonomies for Silverstripe CMS

## Overview

Inspired by the [Silverstripe's Taxonomy module](https://github.com/silverstripe/silverstripe-taxonomy),
Chrometoaster's Advanced taxonomies module also adds the capability to manage hierarchical taxonomies
within Silverstripe.

It also provides generic CMS interfaces to assign taxonomy terms (tag) to pages and files, and provides means how to
extend this functionality to any other data objects, such as e.g. Elemental blocks.

Beyond basic terms tagging, the module aims to help professional information architects and content designers who wish
to use taxonomies to classify most - if not all - of the content across a site, using a suite of interrelated
hierarchical taxonomies. Advanced logic
makes the use of taxonomies a more robust option for content-wide relationships, especially where multiple taxonomies
must be used in conjunction.

Please note that this module is intended to replace Silverstripe's taxonomy module, it's not an extension to it.
This module discards taxonomy type as a standalone model, accommodating the _type_ atrributes via top-level
taxonomy terms, sometimes referenced as _types_ throughout the code base for legibility and context.


## Main features

Main features of the taxonomy term model and associated extensions, helpers, validators etc.

#### Singular and Plural display names
- Extra names to be used in different situations, both on front-end and in the back-end

#### Description
- A term description used throughout the CMS (e.g. within grid fields) to aid editors while tagging (useful when there are
similar terms under different taxonomies).

#### Definition fields
- Author facing definition to be used within the CMS
- End-users facing definition, e.g. used in glossaries

#### Single-select option
- A taxonomy can be defined as 'single-select' or 'multi-select', depending on the nature of the
terms/concepts — being synergistic or mutually exclusive.
- Validation rules apply when authors tag data objects with a single-select taxonomy term (i.e. only one term
can be tagged at a time).

#### Internal only option
- A taxonomy can be dedicated as internal only, i.e. it won't be visible to end-users, marking the taxonomy to be used
for 'administrative purposes' only — creating lists of items in the CMS, filtering etc.

#### Required taxonomies
- Any term can be configured with one or more Required taxonomies.
- Required taxonomies will prompt authors to select a term from other taxonomies, too, when the current term is
assigned to a data object.
- If required taxonomies are set on the root term, then all descendant terms will inherit the requirements.
Inheritance can be manually overridden, and further requirements configured, on individual terms.

#### URLSegment
- A globally unique slug, currently globally unique (this constrain may be relaxed going forward to allow the same
segments under different parents).


# Usage

## Requirements

* SilverStripe 4.x
* PHP ^7.3 (PHP8 not tested)

## Installation

```composer
composer require chrometoaster/silverstripe-advanced-taxonomies
```

## Configuration

By default, the module applies the `DataObjectTaxonomiesDataExtension` extension to `SiteTree` and `File` classes
in the [config.yml](_config/config.yml).

To add the extension to your project-specific data models, create a yaml config file similar to the example below:

```yaml
App\Models\YourDataModel:
  extensions:
    - Chrometoaster\AdvancedTaxonomies\Extensions\DataObjectTaxonomiesDataExtension
App\Models\YourOtherCustomDataModel:
  extensions:
    - Chrometoaster\AdvancedTaxonomies\Extensions\DataObjectTaxonomiesDataExtension
```

There are some settings that can be modified in this module, depending on requirements. These are listed below.

## Developer's notes

#### HTML in validation messages

The `Chrometoaster\AdvancedTaxonomies\Validators\TaxonomyRulesValidator` produces error messages in a HTML format.
This behaviour can be sometimes problematic, especially in a React-like parts of the CMS, e.g. the assets admin.
For this purpose the validator offers a method to disable the HTML output, effectively applying plaintext term
decorators instead of richtext ones.

If you need to use the same behaviour, i.e. output just a plaintext error messages, set the attribute after
getting an instance of the validator e.g. in a data extension like:

```php
use Chrometoaster\AdvancedTaxonomies\Validators\TaxonomyRulesValidator;

$validator = TaxonomyRulesValidator::create();
$validator->setHTMLOutput(false);

// get the validation error or empty string
$requiredTypesValidationError = $validator->validateRequiredTypes($this->getOwner()->Tags());

// use the validation message further
// ...
```

#### Validation in the CMS and the assets-admin section

In the 'asset-admin' interface, when tags are assigned to a file, the message only appears after saving the file, whereas
within the pages section (any non-react context), the validation is performed on the fly when adding the terms through
the 'Add tag' gridfield interface.


# Contributing

## Code guidelines

This project follows the standards defined in:

* [PSR-1](http://www.php-fig.org/psr/psr-1/)
* [PSR-2](http://www.php-fig.org/psr/psr-2/)
* [RSR-4](http://www.php-fig.org/psr/psr-4/)

If you are helping out, please also follow the standards above.

## Translations

We would like to support multiple languages in the long term, however only some aspects of the codebase are
translated at the moment.

Please refer to the ["i18n" topic](https://docs.silverstripe.org/en/developer_guides/i18n/) on docs.silverstripe.org
for more details if you wish to contribute in this are of development.
