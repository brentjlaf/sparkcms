<!-- File: blog.post-detail.php -->
<!-- Template: blog.post-detail -->
<?php $blockId = uniqid('blog-detail-'); ?>
<templateSetting caption="Blog Detail Settings" order="1">
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Back Link Label</dt>
        <dd>
            <input type="text" class="form-control" name="custom_back_label" value="← Back to all posts">
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Back Link URL</dt>
        <dd>
            <input type="text" class="form-control" name="custom_back_url" value="/blog">
            <small class="form-text text-muted">Choose where the back link directs visitors.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Auto-detect Slug from URL?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_auto_slug" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_auto_slug" value="no"> No</label>
        </dd>
        <small class="form-text text-muted">When enabled, the block attempts to load a post based on the current page URL.</small>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Manual Slug (optional)</dt>
        <dd>
            <input type="text" class="form-control" name="custom_slug" placeholder="e.g. getting-started-web-development">
            <small class="form-text text-muted">Used when auto-detect is disabled or as a fallback value.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Detail Base Path</dt>
        <dd>
            <input type="text" class="form-control" name="custom_base" value="/blog">
            <small class="form-text text-muted">Matches the path segment that precedes the slug, e.g. <code>/blog</code>.</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Slug Query Parameter</dt>
        <dd>
            <input type="text" class="form-control" name="custom_query_param" placeholder="post">
            <small class="form-text text-muted">Optional. Provide a query parameter name to detect the slug (e.g. <code>?post=slug</code>).</small>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Featured Image?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_image" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_image" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Author &amp; Date?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_meta" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_meta" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Category?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_category" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_category" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box mb-3">
        <dt>Show Tags?</dt>
        <dd class="align-options">
            <label class="me-2"><input type="radio" name="custom_show_tags" value="yes" checked> Yes</label>
            <label><input type="radio" name="custom_show_tags" value="no"> No</label>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Not Found Message</dt>
        <dd>
            <input type="text" class="form-control" name="custom_empty" value="We couldn’t find that blog post. Please check back soon!">
        </dd>
    </dl>
</templateSetting>
<article id="<?= $blockId ?>" class="blog-post-detail" data-tpl-tooltip="Blog Post Detail" data-blog-detail data-base="{custom_base}" data-auto-slug="{custom_auto_slug}" data-slug="{custom_slug}" data-query-param="{custom_query_param}" data-show-image="{custom_show_image}" data-show-meta="{custom_show_meta}" data-show-category="{custom_show_category}" data-show-tags="{custom_show_tags}" data-empty="{custom_empty}">
    <div class="container">
        <div class="blog-detail-loading text-muted" data-blog-loading style="display: none;">Loading blog post…</div>
        <div class="blog-detail-empty text-muted" data-blog-empty style="display: none;">{custom_empty}</div>
        <div class="blog-detail-body" data-blog-body>
            <div class="blog-detail-back mb-4">
                <a class="blog-detail-back-link" href="{custom_back_url}" data-blog-back data-editable>{custom_back_label}</a>
            </div>
            <header class="blog-detail-header">
                <span class="blog-detail-category" data-blog-category>Category</span>
                <h1 class="blog-detail-title" data-blog-title>Blog Post Title</h1>
                <div class="blog-detail-meta" data-blog-meta>
                    <span class="blog-detail-author" data-blog-author>Author Name</span>
                    <span class="blog-detail-date" data-blog-date>Jan 1, 2024</span>
                </div>
            </header>
            <figure class="blog-detail-image-wrapper" data-blog-image-wrapper>
                <img class="blog-detail-image" src="" alt="" data-blog-image>
            </figure>
            <div class="blog-detail-content" data-blog-content>
                <p>Blog post content will appear here once published.</p>
            </div>
            <div class="blog-detail-tags" data-blog-tags>
                <h2 class="blog-detail-tags-title">Tags</h2>
                <ul class="blog-detail-tag-list" data-blog-tag-list>
                    <li class="blog-detail-tag">Example Tag</li>
                </ul>
            </div>
        </div>
    </div>
</article>
