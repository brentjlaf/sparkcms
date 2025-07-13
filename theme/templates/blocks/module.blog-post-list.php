<!-- File: module.blog-post-list.php -->
<!-- Template: module.blog-post-list -->
<templateSetting caption="Blog List Settings" order="1">
    <?php $listId = 'blog-cat-' . uniqid(); ?>
    <dl class="sparkDialog _tpl-box">
        <dt>Categories (comma separated)</dt>
        <dd>
            <input type="text" name="custom_categories" value="" list="<?php echo $listId; ?>">
            <datalist id="<?php echo $listId; ?>"></datalist>
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

    const datalist = block.querySelector('#<?php echo $listId; ?>');
    if(datalist){
        fetch('CMS/modules/blogs/list_categories.php')
            .then(r=>r.json())
            .then(catsData=>{
                datalist.innerHTML = catsData.map(c=>`<option value="${c}"></option>`).join('');
            });
    }
    fetch('CMS/modules/blogs/list_posts.php')
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
