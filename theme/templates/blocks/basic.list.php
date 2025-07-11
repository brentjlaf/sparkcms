<!-- File: basic.list.php -->
<!-- Template: basic.list -->
<templateSetting caption="List Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Type</dt>
        <dd>
            <select name="custom_type">
                <option value="ul" selected="selected">Unordered</option>
                <option value="ol">Ordered</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Item 1</dt>
        <dd><input type="text" name="custom_item1" value="First"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Item 2</dt>
        <dd><input type="text" name="custom_item2" value="Second"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Item 3</dt>
        <dd><input type="text" name="custom_item3" value="Third"></dd>
    </dl>
</templateSetting>
<toggle rel="custom_type" value="ul">
    <ul class="list-block" data-tpl-tooltip="List">
        <li data-editable>{custom_item1}</li>
        <li data-editable>{custom_item2}</li>
        <li data-editable>{custom_item3}</li>
    </ul>
</toggle>
<toggle rel="custom_type" value="ol">
    <ol class="list-block" data-tpl-tooltip="List">
        <li data-editable>{custom_item1}</li>
        <li data-editable>{custom_item2}</li>
        <li data-editable>{custom_item3}</li>
    </ol>
</toggle>
