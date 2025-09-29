<!-- File: blog.post-list.php -->
<!-- Template: blog.post-list -->
<?php $blockId = uniqid('blog-list-'); ?>
<templateSetting caption="Blog List Settings" order="1">
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Section Title</dt>
        <dd>
            <input type="text" class="form-control" name="custom_title" value="Latest Blog Posts">
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Intro Text</dt>
        <dd>
            <textarea class="form-control" name="custom_intro" rows="3">Explore insights and updates from our team.</textarea>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Number of Posts</dt>
        <dd>
            <select name="custom_limit" class="form-select">
                <option value="3">3 Posts</option>
                <option value="4">4 Posts</option>
                <option value="6" selected>6 Posts</option>
                <option value="9">9 Posts</option>
                <option value="12">12 Posts</option>
            </select>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Filter by Category</dt>
        <dd>
            <input type="text" class="form-control" name="custom_category" placeholder="All categories">
            <small class="form-text text-muted">Leave blank to include every published category.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Detail Page URL Prefix</dt>
        <dd>
            <input type="text" class="form-control" name="custom_base" value="/blog">
            <small class="form-text text-muted">Example: <code>/blog</code> or <code>https://example.com/blog</code>. The post slug is appended automatically.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Excerpts?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_excerpt" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_excerpt" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Author &amp; Date?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_meta" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_meta" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Empty State Message</dt>
        <dd>
            <input type="text" class="form-control" name="custom_empty" value="No posts available right now. Check back soon!">
        </dd>
    </dl>
</templateSetting>
<section id="<?= $blockId ?>" class="blog-post-list" data-tpl-tooltip="Blog Post List" data-blog-list data-limit="{custom_limit}" data-category="{custom_category}" data-base="{custom_base}" data-show-excerpt="{custom_show_excerpt}" data-show-meta="{custom_show_meta}" data-empty="{custom_empty}">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center mb-4">
                <h2 class="blog-list-title" data-editable>{custom_title}</h2>
                <p class="blog-list-intro text-muted" data-editable>{custom_intro}</p>
            </div>
        </div>
        <div class="blog-posts" data-blog-items>
            <div class="blog-item blog-item--placeholder text-muted">Blog posts will load here once published.</div>
        </div>
        <div class="blog-empty text-center text-muted d-none" data-blog-empty>{custom_empty}</div>
    </div>
</section>
