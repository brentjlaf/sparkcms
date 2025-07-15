<!-- File: content-elements.cta-banner.php -->
<!-- Template: content-elements.cta-banner -->
<templateSetting caption="CTA Banner Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Heading</dt>
        <dd><input type="text" name="custom_heading" value="Join Us Today"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Subtext</dt>
        <dd><textarea name="custom_subtext">Start your journey with us.</textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Button Text</dt>
        <dd><input type="text" name="custom_btn_text" value="Get Started"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Button Link</dt>
        <dd><input type="text" name="custom_btn_link" value="#"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Open in New Window</dt>
        <dd><label><input type="checkbox" name="custom_btn_new" value=' target="_blank"'> New window</label></dd>
    </dl>
</templateSetting>
<div class="cta-banner" data-tpl-tooltip="CTA Banner">
    <div class="container cta-banner-content">
        <h2 data-editable>{custom_heading}</h2>
        <p data-editable>{custom_subtext}</p>
        <a href="{custom_btn_link}" class="btn btn-primary"{custom_btn_new} data-editable>{custom_btn_text}</a>
    </div>
</div>
