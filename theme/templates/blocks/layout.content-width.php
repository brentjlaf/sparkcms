<!-- File: layout.content-width.php -->
<!-- Template: layout.content-width -->
<templateSetting caption="Options" order="1">
    <dl class="mwDialog">
        <dt>Container Width:</dt>
        <dd>
            <select name="custom_width">
                <option value=" container-sm">Small</option>
                <option value=" container-md">Medium</option>
                <option value=" container-lg" selected="selected">Large (default)</option>
                <option value=" container-xl">Extra Large</option>
                <option value=" container-xxl">XX Large</option>
                <option value=" container-fluid">Full Width</option>
            </select>
        </dd>
        <dt>Position:</dt>
        <dd>
            <select name="custom_position">
                <option value=" mx-auto" selected="selected">Center (default)</option>
                <option value=" me-auto">Left</option>
                <option value=" ms-auto">Right</option>
            </select>
        </dd>
    </dl>
</templateSetting>
<div class="content-width {custom_width}{custom_position} _content-style" data-tpl-tooltip="Content Width">
    <div class="drop-area"></div>
</div>

