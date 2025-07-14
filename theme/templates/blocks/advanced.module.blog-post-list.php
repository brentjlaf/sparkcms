<!-- File: advanced.module.blog-post-list.php -->
<!-- Template: advanced.module.blog-post-list -->
<?php
$postsFile = __DIR__ . '/../../../CMS/data/blog_posts.json';
$blogCategories = [];
if (file_exists($postsFile)) {
    $posts = json_decode(file_get_contents($postsFile), true) ?: [];
    foreach ($posts as $p) {
        if (!empty($p['category']) && !in_array($p['category'], $blogCategories)) {
            $blogCategories[] = $p['category'];
        }
    }
}
$list_id = uniqid('blog-cat-');
?>
<templateSetting caption="Blog List Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Categories (comma separated)</dt>
        <dd>
            <input type="text" name="custom_categories" value="" list="<?= $list_id ?>">
            <datalist id="<?= $list_id ?>">
                <?php foreach ($blogCategories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Number of Posts</dt>
        <dd><input type="number" name="custom_count" value="3" min="1" max="20"></dd>
    </dl>
</templateSetting>
<div class="blog-post-list" data-categories="{custom_categories}" data-count="{custom_count}" data-tpl-tooltip="Blog List">
    <div class="blog-posts"></div>
</div>
<script>
(function(){
    const script = document.currentScript;
    const block = script.previousElementSibling;
    if(!block) return;
    const container = block.querySelector('.blog-posts');
    const count = parseInt(block.dataset.count) || 3;
    const cats = block.dataset.categories.split(',').map(c=>c.trim()).filter(c=>c);

    const input = block.querySelector('input[list]');
    const datalist = block.querySelector('datalist');
    if(input && datalist){
        const listId = 'blog-cat-' + Math.random().toString(36).substr(2,8);
        datalist.id = listId;
        input.setAttribute('list', listId);
        fetch((window.cmsBase || '') + '/CMS/modules/blogs/list_categories.php')
            .then(r=>r.json())
            .then(catsData=>{
                datalist.innerHTML = catsData.map(c=>`<option value="${c}"></option>`).join('');
            });
    }
    fetch((window.cmsBase || '') + '/CMS/modules/blogs/list_posts.php')
        .then(r=>r.json())
        .then(posts=>{
            posts = posts.filter(p => p.status === 'published');
            if(cats.length) posts = posts.filter(p => cats.includes(p.category));
            posts.sort((a,b)=> new Date(b.publishDate || b.createdAt) - new Date(a.publishDate || a.createdAt));
            posts.slice(0, count).forEach(p=>{
                const el = document.createElement('article');
                el.className = 'blog-item';
                el.innerHTML = '<h3 class="blog-title"><a href="'+p.slug+'">'+p.title+'</a></h3>'+
                               '<p class="blog-excerpt">'+p.excerpt+'</p>';
                container.appendChild(el);
            });
        });
})();
</script>
