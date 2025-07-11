<!-- Template: layout.columns -->
<templateSetting caption="Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Columns:</dt>
        <dd>
            <select name="custom_cols">
                <option value="2" selected="selected">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
            </select>
        </dd>
    </dl>
</templateSetting>

<div class="row drop-area" data-tpl-tooltip="Columns">
    <toggle rel="custom_cols" value="2">
        <div class="col"><div class="drop-area"></div></div>
        <div class="col"><div class="drop-area"></div></div>
    </toggle>
    <toggle rel="custom_cols" value="3">
        <div class="col"><div class="drop-area"></div></div>
        <div class="col"><div class="drop-area"></div></div>
        <div class="col"><div class="drop-area"></div></div>
    </toggle>
    <toggle rel="custom_cols" value="4">
        <div class="col"><div class="drop-area"></div></div>
        <div class="col"><div class="drop-area"></div></div>
        <div class="col"><div class="drop-area"></div></div>
        <div class="col"><div class="drop-area"></div></div>
    </toggle>
</div>
