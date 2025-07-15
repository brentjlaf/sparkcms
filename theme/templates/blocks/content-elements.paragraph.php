<!-- File: content-elements.paragraph.php -->
<!-- Template: content-elements.paragraph -->
<templateSetting caption="Paragraph Settings" order="1">
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Text</dt>
        <dd><textarea class="form-control" name="custom_text">Sample paragraph text.</textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Style</dt>
        <dd>
            <select name="custom_style" class="form-select">
                <option value="">Normal</option>
                <option value="lead">Lead</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Alignment</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_align" value=" text-start" checked> Left</label>
            <label class="me-2"><input type="radio" name="custom_align" value=" text-center"> Center</label>
            <label><input type="radio" name="custom_align" value=" text-end"> Right</label>
        </dd>
    </dl>
</templateSetting>
<p class="paragraph mb-3{custom_align} {custom_style}" data-tpl-tooltip="Paragraph" data-editable>{custom_text}</p>
