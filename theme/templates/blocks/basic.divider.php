<!-- File: basic.divider.php -->
<!-- Template: basic.divider -->
<templateSetting caption="Divider Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Style</dt>
        <dd>
            <select name="custom_style">
                <option value="solid" selected="selected">Solid</option>
                <option value="dashed">Dashed</option>
                <option value="dotted">Dotted</option>
            </select>
        </dd>
    </dl>
</templateSetting>
<hr class="divider-{custom_style}" data-tpl-tooltip="Divider"/>
