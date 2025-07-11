<templateSetting caption="Button Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Text</dt>
        <dd><input type="text" name="custom_text" value="Click Me"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Link</dt>
        <dd><input type="text" name="custom_link" value="#"></dd>
    </dl>
</templateSetting>
<a href="{custom_link}" class="btn btn-primary" data-tpl-tooltip="Button" data-editable>{custom_text}</a>
