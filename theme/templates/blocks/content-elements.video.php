<!-- File: content-elements.video.php -->
<!-- Template: content-elements.video -->
<templateSetting caption="Video Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Embed URL</dt>
        <dd>
            <input type="text" name="custom_src" id="custom_src_video" value="https://www.youtube.com/embed/dQw4w9WgXcQ">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_src_video')">Browse</button>
        </dd>
    </dl>
</templateSetting>
<div class="video-block" data-tpl-tooltip="Video">
    <iframe src="{custom_src}" frameborder="0" allowfullscreen></iframe>
</div>
