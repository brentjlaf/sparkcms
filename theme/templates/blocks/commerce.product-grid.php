<!-- File: commerce.product-grid.php -->
<!-- Template: commerce.product-grid -->
<?php $blockId = uniqid('commerce-grid-'); ?>
<templateSetting caption="Section Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Tagline</dt>
        <dd><input type="text" name="custom_tagline" value="New this week"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Title</dt>
        <dd><input type="text" name="custom_title" value="Featured products"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Description</dt>
        <dd><textarea class="form-control" name="custom_description">Discover merchandise your customers will love.</textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Background style</dt>
        <dd>
            <select name="custom_theme">
                <option value="">Soft surface</option>
                <option value=" commerce-showcase--light">Bright</option>
                <option value=" commerce-showcase--brand">Brand gradient</option>
                <option value=" commerce-showcase--dark">Midnight</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Alignment</dt>
        <dd class="align-options">
            <label><input type="radio" name="custom_alignment" value=" commerce-showcase--align-left" checked> Left</label>
            <label><input type="radio" name="custom_alignment" value=" commerce-showcase--align-center"> Center</label>
            <label><input type="radio" name="custom_alignment" value=" commerce-showcase--align-right"> Right</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Primary button label</dt>
        <dd><input type="text" name="custom_button_text" value="Shop all products"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Primary button link</dt>
        <dd><input type="text" name="custom_button_url" value="#"></dd>
    </dl>
</templateSetting>
<templateSetting caption="Product Feed" order="2">
    <dl class="sparkDialog _tpl-box">
        <dt>Number of products</dt>
        <dd>
            <select name="custom_limit">
                <option value="3">3 products</option>
                <option value="4">4 products</option>
                <option value="6" selected>6 products</option>
                <option value="9">9 products</option>
                <option value="12">12 products</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Filter categories</dt>
        <dd>
            <input type="text" name="custom_categories" placeholder="All categories">
            <small class="form-text text-muted">Comma separated. Leave blank to include every published category.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Product detail URL prefix</dt>
        <dd>
            <input type="text" name="custom_detail_base" value="/store">
            <small class="form-text text-muted">Example: <code>/store</code> or <code>https://example.com/shop</code>. The product slug is appended automatically.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Product link label</dt>
        <dd><input type="text" name="custom_product_link_label" value="View product"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Empty state message</dt>
        <dd><input type="text" name="custom_empty" value="No products available right now. Check back soon!"></dd>
    </dl>
</templateSetting>
<section id="<?= $blockId ?>" class="commerce-showcase{custom_theme}{custom_alignment}" data-tpl-tooltip="Commerce products" data-commerce-grid data-limit="{custom_limit}" data-categories="{custom_categories}" data-base="{custom_detail_base}" data-link-text="{custom_product_link_label}" data-empty="{custom_empty}">
    <div class="container">
        <div class="commerce-showcase-inner">
            <header class="commerce-showcase-header">
                <p class="commerce-showcase-eyebrow" data-editable>{custom_tagline}</p>
                <h2 class="commerce-showcase-title" data-editable>{custom_title}</h2>
                <p class="commerce-showcase-subtitle" data-editable>{custom_description}</p>
                <div class="commerce-showcase-actions">
                    <a href="{custom_button_url}" class="commerce-showcase-button" data-editable>{custom_button_text}</a>
                </div>
            </header>
            <div class="commerce-showcase-grid" data-commerce-items>
                <article class="commerce-product-card commerce-product-card--placeholder">
                    <div class="commerce-product-body">
                        <h3 class="commerce-product-title">Loading products…</h3>
                        <p class="commerce-product-description">Latest catalogue items will appear here automatically.</p>
                    </div>
                </article>
            </div>
            <div class="commerce-showcase-empty d-none" data-commerce-empty>{custom_empty}</div>
        </div>
    </div>
</section>
