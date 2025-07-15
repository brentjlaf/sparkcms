<!-- File: layout.layout.column_2.php -->
<!-- Template: layout.layout.column_2 -->
<templateSetting caption="Column Settings" order="1">
    <dl class="mwDialog">
        <dt>Layout:</dt>
        <dd>
            <select name="custom_layout">
                <option value="20_80">20% | 80%</option>
                <option value="30_70">30% | 70%</option>
                <option value="40_60">40% | 60%</option>
                <option value="50_50" selected="selected">50% | 50% (default)</option>
                <option value="60_40">60% | 40%</option>
                <option value="70_30">70% | 30%</option>
                <option value="80_20">80% | 20%</option>
            </select>
        </dd>
        <dt>Gap:</dt>
        <dd>
            <select name="custom_gap">
                <option value=" g-0">None</option>
                <option value=" g-1">Small</option>
                <option value=" g-3" selected="selected">Medium (default)</option>
                <option value=" g-5">Large</option>
                <option value=" g-5">Extra Large</option>
            </select>
        </dd>
        <dt>Alignment:</dt>
        <dd>
            <select name="custom_alignment">
                <option value="" selected="selected">Align columns top (default)</option>
                <option value=" align-items-center">Align columns centered</option>
                <option value=" align-items-end">Align columns bottom</option>
            </select>
        </dd>
    </dl>
</templateSetting>
<div class="row {custom_layout} {custom_gap} {custom_alignment}" data-tpl-tooltip="2 Columns">
    <div class="col"><div class="drop-area"></div></div>
    <div class="col"><div class="drop-area"></div></div>
</div>

