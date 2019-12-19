<% loop $Terms.Sort('Sort') %>
    <tr>
        <% if $TermLevel == 0 %>
        <td><strong>{$Name}</strong></td>
        <% else %>
        <td><% loop $TermLevelList %> - <% end_loop %>{$Name}</td>
        <% end_if %>
        <td>{$Description}</td>
        <td>{$AuthorDefinition}</td>
    </tr>
    <% if $Children.count %>
        <% include Chrometoaster\AdvancedTaxonomies\Controllers\TaxonomyOverviewController_TermRow Terms=$Children %>
    <% end_if %>
<% end_loop %>
