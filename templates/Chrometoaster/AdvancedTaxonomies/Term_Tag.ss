<span class="Select--multi">
    <span class="Select-value-label Select-value<% if $ShowTermExtraInfo %> with-tooltip<% end_if %>">
        {$Name}
        <% if $ShowTermExtraInfo %>
        <span class="at-tooltip">
            <%-- Tooltip content --%>
            <% if $AuthorDefinition %>
                {$AuthorDefinition}
                <br><br>
            <% end_if %>

            <b>Taxonomy</b>: {$TermHierarchy}

            <% if $Title %>
                <br><br>
                <b>Singular</b>: {$Title}
            <% end_if %>

            <% if $TitlePlural %>
                <br><br>
                <b>Plural</b>: {$TitlePlural}
            <% end_if %>

            <% if $TypeNameWithFlags %>
                <br><br>
                <b>Type</b>: {$TypeNameWithFlags}
            <% end_if %>

            <% if $AllRequiredTypes %>
                <br><br>
                <b>Required taxonomies</b>: <% loop $AllRequiredTypes %>{$Name}<% if not $Last %>, <% end_if %><% end_loop %>
            <% end_if %>
        </span>
        <% end_if %>
     </span>
</span>
