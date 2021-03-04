<% loop $Terms.Sort('Sort') %>
    <tr>
        <% if $TermLevel == 0 %>
        <td><strong>{$Name}</strong></td>
        <% else %>
        <td><% loop $TermLevelList %> - <% end_loop %>{$Name}</td>
        <% end_if %>
        <td>{$Description}</td>
        <td>{$AuthorDefinition}</td>
        <td><% with $AllConceptClasses %>
            <% if $Primary %>Primary: {$Primary.Name}<% end_if %>
            <% if $Other %><% if $Primary %><br><% end_if %>
                Other: <% loop $Other %>{$Name}<% if not $Last %>, <% end_if %><% end_loop %>
            <% end_if %>
        <% end_with %></td>
    </tr>
    <% if $Children.count %>
        <% include Chrometoaster\AdvancedTaxonomies\Controllers\TaxonomyOverviewController_TermRow Terms=$Children %>
    <% end_if %>
<% end_loop %>
