<!-- File: media.hero.php -->
<!-- Template: media.hero -->
<templateSetting caption="Hero Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Background Image</dt>
        <dd>
            <input type="text" name="custom_bg" id="custom_bg" value="">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_bg')">Browse</button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Heading</dt>
        <dd><input type="text" name="custom_heading" value="Welcome to Our Site"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Subheading</dt>
        <dd><textarea name="custom_subheading">We are glad you are here.</textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Button Text</dt>
        <dd><input type="text" name="custom_btn_text" value="Learn More"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Button Link</dt>
        <dd><input type="text" name="custom_btn_link" value="#"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Open in New Window</dt>
        <dd><label><input type="checkbox" name="custom_btn_new" value=' target="_blank"'> New window</label></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Size</dt>
        <dd>
            <select name="custom_size">
                <option value="side">Side</option>
                <option value="small">Small</option>
                <option value="medium" selected="selected">Medium</option>
                <option value="large">Large</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Alignment</dt>
        <dd class="align-options">
            <label><input type="radio" name="custom_align" value=" text-start"> Left</label>
            <label><input type="radio" name="custom_align" value=" text-center" checked> Center</label>
            <label><input type="radio" name="custom_align" value=" text-end"> Right</label>
        </dd>
    </dl>
</templateSetting>
<section class="hero-section hero-{custom_size} d-flex align-items-center" style="background-image:url('{custom_bg}');" data-tpl-tooltip="Hero">
    <div class="container">
        <div class="hero-content{custom_align}">
            <h1 data-editable>{custom_heading}</h1>
            <p data-editable>{custom_subheading}</p>
            <a href="{custom_btn_link}" class="btn btn-primary"{custom_btn_new} data-editable>{custom_btn_text}</a>
        </div>
    </div>
</section>
