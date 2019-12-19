<!DOCTYPE html>
<!--[if IE 9]><html class="ie ie9 lt-ie10" lang="en"><![endif]-->
<!--[if !IE]><!-->
<html lang="en"><!--<![endif]-->

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Advanced Taxonomies overview</title>

    <style>
        table {
            border-collapse: collapse;
        }

        thead {
            background-color: #eee;
        }

        th, td {
            border: 1px solid #ccc;
            text-align: left;
            padding: 4px;
        }
    </style>
</head>

<body>
<h1>Taxonomies overview</h1>
    <% if $ParentTerm %><p>Parent term: <strong>{$ParentTerm.Title}</strong></p><% end_if %>

    <% include Chrometoaster\AdvancedTaxonomies\Controllers\TaxonomyOverviewController_Terms %>
</body>

</html>
