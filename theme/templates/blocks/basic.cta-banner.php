<!-- File: basic.cta-banner.php -->
<!-- Template: basic.cta-banner -->
<templateSetting caption="CTA Banner Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Title</dt>
        <dd><input type="text" name="custom_title" value="Join us today!"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Text</dt>
        <dd><textarea name="custom_text">Sign up now to start your journey.</textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Button Text</dt>
        <dd><input type="text" name="custom_btn_text" value="Get Started"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Button Link</dt>
        <dd><input type="text" name="custom_link" value="#"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Open in New Window</dt>
        <dd><label><input type="checkbox" name="custom_new_window" value=' target="_blank"'> New window</label></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Alignment</dt>
        <dd class="align-options">
            <label><input type="radio" name="custom_align" value=" _text-left" checked> Left</label>
            <label><input type="radio" name="custom_align" value=" _text-center"> Center</label>
            <label><input type="radio" name="custom_align" value=" _text-right"> Right</label>
        </dd>
    </dl>
</templateSetting>
<div class="cta-banner{custom_align}" data-tpl-tooltip="CTA Banner">
    <h2 class="cta-title" data-editable>{custom_title}</h2>
    <p class="cta-text" data-editable>{custom_text}</p>
    <a href="{custom_link}" class="btn btn-primary"{custom_new_window} data-editable>{custom_btn_text}</a>
</div>
