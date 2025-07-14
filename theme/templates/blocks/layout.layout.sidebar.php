<!-- File: layout.layout.sidebar.php -->
<!-- Template: layout.layout.sidebar -->
<?php $tpl_id = uniqid('tpl-'); ?>
<templateSetting caption="Options" order="1">
    <dl class="sparkDialog">
        <dt>Layout:</dt>
        <dd>
            <label><input type="radio" name="custom_layout" value="is-left" checked> Side | Main</label>
            <label><input type="radio" name="custom_layout" value="is-right"> Main | Side</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt class="formGroup">General Options</dt>
        <dt>Border:</dt>
        <dd>
            <select name="custom_border">
                <option value="no-border" selected>Disable (default)</option>
                <option value="has-border">Enable</option>
            </select>
        </dd>
        <dt>Gap Between the Side Area and the Main Area:</dt>
        <dd>
            <select name="custom_gap">
                <option value="_gutter-10">Small</option>
                <option value="_gutter-30" selected>Medium (default)</option>
                <option value="_gutter-60">Large</option>
                <option value="_gutter-80">Extra Large</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt class="formGroup">Side Area</dt>
        <dt>Side Area Size:</dt>
        <dd>
            <select name="custom_size">
                <option value="col-xl-3 col-lg-4">Small</option>
                <option value="col-lg-4" selected>Medium (default)</option>
                <option value="col-lg-5">Large</option>
            </select>
        </dd>
        <dt>Side Area Background Color:</dt>
        <dd class="color-options">
            <label><input type="radio" name="custom_side_bg" value="" checked> None</label>
            <label><input type="radio" name="custom_side_bg" value="_bg-primary"> Primary</label>
            <label><input type="radio" name="custom_side_bg" value="_bg-secondary"> Secondary</label>
            <label><input type="radio" name="custom_side_bg" value="_bg-third"> Third</label>
            <label><input type="radio" name="custom_side_bg" value="_bg-fourth"> Fourth</label>
            <label><input type="radio" name="custom_side_bg" value="_bg-white"> White</label>
            <label><input type="radio" name="custom_side_bg" value="_bg-light"> Light</label>
            <label><input type="radio" name="custom_side_bg" value="_bg-gray"> Gray</label>
            <label><input type="radio" name="custom_side_bg" value="_bg-dark"> Dark</label>
        </dd>
    </dl>
</templateSetting>

<div id="<?= $tpl_id ?>" class="sidebar sidebar-default {custom_layout} {custom_border}" data-tpl-tooltip="Sidebar - Style 1">
    <div class="row {custom_gap}">
        <div class="sidebar-side {custom_size}">
            <div class="sidebar-side-wrap">
                <div class="sidebar-inner {custom_side_bg}">
                    <div class="drop-area"></div>
                </div>
            </div>
        </div>
        <div class="sidebar-main col">
            <div class="sidebar-main-wrap">
                <div class="sidebar-inner">
                    <div class="drop-area"></div>
                </div>
            </div>
        </div>
    </div>
</div>
