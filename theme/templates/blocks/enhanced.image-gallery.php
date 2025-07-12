<!-- File: enhanced.image-gallery.php -->
<!-- Template: enhanced.image-gallery -->
<templateSetting caption="Gallery Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Gallery Layout</dt>
        <dd>
            <select name="gallery_layout">
                <option value="grid">Grid</option>
                <option value="masonry">Masonry</option>
                <option value="carousel">Carousel</option>
                <option value="single">Single Column</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Number of Columns</dt>
        <dd>
            <select name="gallery_columns">
                <option value="1">1 Column</option>
                <option value="2">2 Columns</option>
                <option value="3" selected>3 Columns</option>
                <option value="4">4 Columns</option>
                <option value="5">5 Columns</option>
                <option value="6">6 Columns</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image Spacing</dt>
        <dd>
            <select name="image_spacing">
                <option value="none">None</option>
                <option value="small">Small</option>
                <option value="medium" selected>Medium</option>
                <option value="large">Large</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image Aspect Ratio</dt>
        <dd>
            <select name="aspect_ratio">
                <option value="original" selected>Original</option>
                <option value="square">Square (1:1)</option>
                <option value="landscape">Landscape (16:9)</option>
                <option value="portrait">Portrait (3:4)</option>
            </select>
        </dd>
    </dl>
</templateSetting>

<templateSetting caption="Visual Effects" order="2">
    <dl class="sparkDialog _tpl-box">
        <dt>Border Radius</dt>
        <dd>
            <input type="range" name="border_radius" min="0" max="50" value="0">
            <span>0px</span>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Drop Shadow</dt>
        <dd>
            <label><input type="checkbox" name="drop_shadow" value="1"> Enable Shadow</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Hover Effect</dt>
        <dd>
            <select name="hover_effect">
                <option value="none">None</option>
                <option value="zoom">Zoom</option>
                <option value="fade">Fade</option>
                <option value="slide">Slide</option>
                <option value="overlay">Overlay</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image Filter</dt>
        <dd>
            <select name="image_filter">
                <option value="none">None</option>
                <option value="grayscale">Grayscale</option>
                <option value="sepia">Sepia</option>
                <option value="brightness">Bright</option>
                <option value="contrast">High Contrast</option>
            </select>
        </dd>
    </dl>
</templateSetting>

<templateSetting caption="Lightbox & Interaction" order="3">
    <dl class="sparkDialog _tpl-box">
        <dt>Enable Lightbox</dt>
        <dd>
            <label><input type="checkbox" name="enable_lightbox" value="1" checked> Enable Lightbox</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Show Captions</dt>
        <dd>
            <label><input type="checkbox" name="show_captions" value="1"> Show Captions</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Caption Position</dt>
        <dd>
            <select name="caption_position">
                <option value="below">Below Image</option>
                <option value="overlay-bottom">Overlay Bottom</option>
                <option value="overlay-center">Overlay Center</option>
                <option value="overlay-top">Overlay Top</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Lazy Loading</dt>
        <dd>
            <label><input type="checkbox" name="lazy_loading" value="1" checked> Enable Lazy Loading</label>
        </dd>
    </dl>
</templateSetting>

<templateSetting caption="Mobile Settings" order="4">
    <dl class="sparkDialog _tpl-box">
        <dt>Mobile Columns</dt>
        <dd>
            <select name="mobile_columns">
                <option value="1" selected>1 Column</option>
                <option value="2">2 Columns</option>
                <option value="3">3 Columns</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Touch Gestures</dt>
        <dd>
            <label><input type="checkbox" name="touch_gestures" value="1" checked> Enable Swipe Navigation</label>
        </dd>
    </dl>
</templateSetting>

<templateSetting caption="Image 1" order="5">
    <dl class="sparkDialog _tpl-box">
        <dt>Image 1 URL</dt>
        <dd>
            <input type="text" name="custom_img1" id="custom_img1" value="https://via.placeholder.com/300">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_img1')">Browse</button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 1 Alt Text</dt>
        <dd><input type="text" name="custom_alt1" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 1 Caption</dt>
        <dd><input type="text" name="custom_caption1" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 1 Link URL</dt>
        <dd><input type="text" name="custom_link1" value=""></dd>
    </dl>
</templateSetting>

<templateSetting caption="Image 2" order="6">
    <dl class="sparkDialog _tpl-box">
        <dt>Image 2 URL</dt>
        <dd>
            <input type="text" name="custom_img2" id="custom_img2" value="https://via.placeholder.com/300">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_img2')">Browse</button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 2 Alt Text</dt>
        <dd><input type="text" name="custom_alt2" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 2 Caption</dt>
        <dd><input type="text" name="custom_caption2" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 2 Link URL</dt>
        <dd><input type="text" name="custom_link2" value=""></dd>
    </dl>
</templateSetting>

<templateSetting caption="Image 3" order="7">
    <dl class="sparkDialog _tpl-box">
        <dt>Image 3 URL</dt>
        <dd>
            <input type="text" name="custom_img3" id="custom_img3" value="https://via.placeholder.com/300">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_img3')">Browse</button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 3 Alt Text</dt>
        <dd><input type="text" name="custom_alt3" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 3 Caption</dt>
        <dd><input type="text" name="custom_caption3" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 3 Link URL</dt>
        <dd><input type="text" name="custom_link3" value=""></dd>
    </dl>
</templateSetting>

<templateSetting caption="Image 4" order="8">
    <dl class="sparkDialog _tpl-box">
        <dt>Image 4 URL</dt>
        <dd>
            <input type="text" name="custom_img4" id="custom_img4" value="">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_img4')">Browse</button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 4 Alt Text</dt>
        <dd><input type="text" name="custom_alt4" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 4 Caption</dt>
        <dd><input type="text" name="custom_caption4" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 4 Link URL</dt>
        <dd><input type="text" name="custom_link4" value=""></dd>
    </dl>
</templateSetting>

<templateSetting caption="Image 5" order="9">
    <dl class="sparkDialog _tpl-box">
        <dt>Image 5 URL</dt>
        <dd>
            <input type="text" name="custom_img5" id="custom_img5" value="">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_img5')">Browse</button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 5 Alt Text</dt>
        <dd><input type="text" name="custom_alt5" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 5 Caption</dt>
        <dd><input type="text" name="custom_caption5" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 5 Link URL</dt>
        <dd><input type="text" name="custom_link5" value=""></dd>
    </dl>
</templateSetting>

<templateSetting caption="Image 6" order="10">
    <dl class="sparkDialog _tpl-box">
        <dt>Image 6 URL</dt>
        <dd>
            <input type="text" name="custom_img6" id="custom_img6" value="">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_img6')">Browse</button>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 6 Alt Text</dt>
        <dd><input type="text" name="custom_alt6" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 6 Caption</dt>
        <dd><input type="text" name="custom_caption6" value=""></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Image 6 Link URL</dt>
        <dd><input type="text" name="custom_link6" value=""></dd>
    </dl>
</templateSetting>

<div class="image-gallery layout-{gallery_layout} columns-{gallery_columns} spacing-{image_spacing} aspect-{aspect_ratio} {hover_effect} {image_filter} {drop_shadow:shadow} {enable_lightbox:lightbox} {show_captions:captions} caption-{caption_position} mobile-{mobile_columns} {touch_gestures:touch-enabled} {lazy_loading:lazy}"
     data-tpl-tooltip="Enhanced Image Gallery"
     data-border-radius="{border_radius}"
     style="--border-radius: {border_radius}px;">
    
    {if custom_img1}
        <div class="gallery-item">
            {if custom_link1}<a href="{custom_link1}">{/if}
                <img src="{custom_img1}" alt="{custom_alt1}" {lazy_loading:loading="lazy"}>
                {if show_captions && custom_caption1}<div class="caption">{custom_caption1}</div>{/if}
            {if custom_link1}</a>{/if}
        </div>
    {/if}
    
    {if custom_img2}
        <div class="gallery-item">
            {if custom_link2}<a href="{custom_link2}">{/if}
                <img src="{custom_img2}" alt="{custom_alt2}" {lazy_loading:loading="lazy"}>
                {if show_captions && custom_caption2}<div class="caption">{custom_caption2}</div>{/if}
            {if custom_link2}</a>{/if}
        </div>
    {/if}
    
    {if custom_img3}
        <div class="gallery-item">
            {if custom_link3}<a href="{custom_link3}">{/if}
                <img src="{custom_img3}" alt="{custom_alt3}" {lazy_loading:loading="lazy"}>
                {if show_captions && custom_caption3}<div class="caption">{custom_caption3}</div>{/if}
            {if custom_link3}</a>{/if}
        </div>
    {/if}
    
    {if custom_img4}
        <div class="gallery-item">
            {if custom_link4}<a href="{custom_link4}">{/if}
                <img src="{custom_img4}" alt="{custom_alt4}" {lazy_loading:loading="lazy"}>
                {if show_captions && custom_caption4}<div class="caption">{custom_caption4}</div>{/if}
            {if custom_link4}</a>{/if}
        </div>
    {/if}
    
    {if custom_img5}
        <div class="gallery-item">
            {if custom_link5}<a href="{custom_link5}">{/if}
                <img src="{custom_img5}" alt="{custom_alt5}" {lazy_loading:loading="lazy"}>
                {if show_captions && custom_caption5}<div class="caption">{custom_caption5}</div>{/if}
            {if custom_link5}</a>{/if}
        </div>
    {/if}
    
    {if custom_img6}
        <div class="gallery-item">
            {if custom_link6}<a href="{custom_link6}">{/if}
                <img src="{custom_img6}" alt="{custom_alt6}" {lazy_loading:loading="lazy"}>
                {if show_captions && custom_caption6}<div class="caption">{custom_caption6}</div>{/if}
            {if custom_link6}</a>{/if}
        </div>
    {/if}
</div>
