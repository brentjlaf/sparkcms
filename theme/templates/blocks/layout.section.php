<!-- File: layout.section.php -->
<!-- Template: layout.section -->
<templateSetting caption="Section Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Width</dt>
        <dd>
            <select name="custom_type">
                <option value="container" selected="selected">Standard</option>
                <option value="container-fluid">Full Width</option>
            </select>
        </dd>
    </dl>
</templateSetting>
<toggle rel="custom_type" value="container">
    <section class="container" data-tpl-tooltip="Section">
        <div class="drop-area"></div></section>
</toggle>
<toggle rel="custom_type" value="container-fluid">
    <section class="container-fluid" data-tpl-tooltip="Section">
        <div class="drop-area"></div>
        
        </section>
</toggle>
