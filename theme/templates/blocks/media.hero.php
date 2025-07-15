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
        <div class="hero-content{custom_align}"><div class="drop-area"></div>
            <div class="drop-area"></div>
        </div>
    </div>
</section>
