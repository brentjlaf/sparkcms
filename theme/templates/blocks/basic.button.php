<!-- File: basic.button.php -->
<!-- Template: basic.button -->
<templateSetting caption="Button Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Text</dt>
        <dd><input type="text" name="custom_text" value="Click Me"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Link</dt>
        <dd><input type="text" name="custom_link" value="#"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Open in New Window</dt>
        <dd><label><input type="checkbox" name="custom_new_window" value=' target="_blank"'> New window</label></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Alignment</dt>
        <dd>
            <label><input type="checkbox" name="custom_align_left" value=" _text-left"> Left</label>
            <label><input type="checkbox" name="custom_align_center" value=" _text-center"> Center</label>
            <label><input type="checkbox" name="custom_align_right" value=" _text-right"> Right</label>
        </dd>
    </dl>
</templateSetting>
<div class="{custom_align_left}{custom_align_center}{custom_align_right}">
    <a href="{custom_link}" class="btn btn-primary"{custom_new_window} data-tpl-tooltip="Button" data-editable>{custom_text}</a>
</div>
