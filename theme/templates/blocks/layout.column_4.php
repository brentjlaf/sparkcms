<!-- File: layout.column_4.php -->
<!-- Template: layout.column_4 -->
<templateSetting caption="Column Settings" order="1">
    <dl class="mwDialog">
        <dt>Gap:</dt>
        <dd>
            <select name="custom_gap">
                <option value="_gutter-0">None</option>
                <option value="_gutter-10">Small</option>
                <option value="_gutter-30" selected="selected">Medium (default)</option>
                <option value="_gutter-60">Large</option>
                <option value="_gutter-80">Extra Large</option>
            </select>
        </dd>
        <dt>Alignment:</dt>
        <dd>
            <select name="custom_alignment">
                <option value="" selected="selected">Align columns top (default)</option>
                <option value="_align-items-center">Align columns centered</option>
                <option value="_align-items-end">Align columns bottom</option>
            </select>
        </dd>
    </dl>
</templateSetting>
<div class="row {custom_gap}{custom_alignment} drop-area" data-tpl-tooltip="4 Columns">
    <div class="col"><div class="drop-area"></div></div>
    <div class="col"><div class="drop-area"></div></div>
    <div class="col"><div class="drop-area"></div></div>
    <div class="col"><div class="drop-area"></div></div>
</div>

