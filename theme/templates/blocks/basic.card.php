<!-- File: basic.card.php -->
<!-- Template: basic.card -->
<templateSetting caption="Card Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Image URL</dt>
        <dd>
            <input type="text" name="custom_img" id="custom_img" value="https://via.placeholder.com/600x400">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_img')">Browse</button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Heading</dt>
        <dd><input type="text" name="custom_heading" value="Card Title"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Text</dt>
        <dd><textarea name="custom_text">Lorem ipsum dolor sit amet.</textarea></dd>
    </dl>
</templateSetting>
<div class="card-block" data-tpl-tooltip="Card">
    <img src="{custom_img}" alt="">
    <div class="card-content">
        <h3 class="card-title" data-editable>{custom_heading}</h3>
        <p class="card-text" data-editable>{custom_text}</p>
    </div>
</div>
