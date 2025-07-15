<!-- File: content-elements.basic.heading.php -->
<!-- Template: content-elements.basic.heading -->
<templateSetting caption="Heading Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Text</dt>
        <dd><input type="text" name="custom_text" value="Heading"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Level</dt>
        <dd>
            <select name="custom_level">
                <option value="h1">H1</option>
                <option value="h2" selected="selected">H2</option>
                <option value="h3">H3</option>
                <option value="h4">H4</option>
                <option value="h5">H5</option>
                <option value="h6">H6</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Alignment</dt>
        <dd class="align-options">
            <label><input type="radio" name="custom_align" value=" text-start" checked> Left</label>
            <label><input type="radio" name="custom_align" value=" text-center"> Center</label>
            <label><input type="radio" name="custom_align" value=" text-end"> Right</label>
        </dd>
    </dl>
</templateSetting>
<div class="{custom_align}">
    <toggle rel="custom_level" value="h1"><h1 data-tpl-tooltip="Heading" data-editable>{custom_text}</h1></toggle>
    <toggle rel="custom_level" value="h2"><h2 data-tpl-tooltip="Heading" data-editable>{custom_text}</h2></toggle>
    <toggle rel="custom_level" value="h3"><h3 data-tpl-tooltip="Heading" data-editable>{custom_text}</h3></toggle>
    <toggle rel="custom_level" value="h4"><h4 data-tpl-tooltip="Heading" data-editable>{custom_text}</h4></toggle>
    <toggle rel="custom_level" value="h5"><h5 data-tpl-tooltip="Heading" data-editable>{custom_text}</h5></toggle>
    <toggle rel="custom_level" value="h6"><h6 data-tpl-tooltip="Heading" data-editable>{custom_text}</h6></toggle>
</div>
