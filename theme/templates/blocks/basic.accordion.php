<!-- File: basic.accordion.php -->
<!-- Template: basic.accordion -->
<templateSetting caption="Accordion Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dd>
            <label><input type="checkbox" name="custom_open" value=" open"> Open by default</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Heading Text</dt>
        <dd><input type="text" name="custom_heading" value="Accordion Heading"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Heading Level</dt>
        <dd>
            <select name="custom_heading_tag">
                <option value="h2">H2</option>
                <option value="h3" selected="selected">H3</option>
                <option value="h4">H4</option>
                <option value="h5">H5</option>
                <option value="h6">H6</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Icon</dt>
        <dd>
            <label><input type="radio" name="custom_icon" value="" checked> None</label>
            <label><input type="radio" name="custom_icon" value="fa-solid fa-plus"> +</label>
            <label><input type="radio" name="custom_icon" value="fa-solid fa-chevron-down"> &#xf078;</label>
            <label><input type="radio" name="custom_icon" value="fa-solid fa-caret-down"> &#xf0d7;</label>
        </dd>
    </dl>
</templateSetting>
<div class="accordion accordion-style-1{custom_open}" data-tpl-tooltip="Accordion">
    <div class="accordion-wrap">
        <toggle rel="custom_heading_tag" value="h2">
            <h2 class="accordion-header">
                <button type="button" class="accordion-button" aria-expanded="false">
                    <span>{custom_heading}</span>
                    <i class="{custom_icon}" aria-hidden="true"></i>
                </button>
            </h2>
        </toggle>
        <toggle rel="custom_heading_tag" value="h3">
            <h3 class="accordion-header">
                <button type="button" class="accordion-button" aria-expanded="false">
                    <span>{custom_heading}</span>
                    <i class="{custom_icon}" aria-hidden="true"></i>
                </button>
            </h3>
        </toggle>
        <toggle rel="custom_heading_tag" value="h4">
            <h4 class="accordion-header">
                <button type="button" class="accordion-button" aria-expanded="false">
                    <span>{custom_heading}</span>
                    <i class="{custom_icon}" aria-hidden="true"></i>
                </button>
            </h4>
        </toggle>
        <toggle rel="custom_heading_tag" value="h5">
            <h5 class="accordion-header">
                <button type="button" class="accordion-button" aria-expanded="false">
                    <span>{custom_heading}</span>
                    <i class="{custom_icon}" aria-hidden="true"></i>
                </button>
            </h5>
        </toggle>
        <toggle rel="custom_heading_tag" value="h6">
            <h6 class="accordion-header">
                <button type="button" class="accordion-button" aria-expanded="false">
                    <span>{custom_heading}</span>
                    <i class="{custom_icon}" aria-hidden="true"></i>
                </button>
            </h6>
        </toggle>
        <div class="accordion-panel">
            <div class="accordion-panel-inner">
                <mwPageArea rel="mainContent" info="Drag and drop widgets or sub-templates here." sortable="page"></mwPageArea>
            </div>
        </div>
    </div>
</div>
