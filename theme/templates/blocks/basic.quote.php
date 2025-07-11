<!-- File: basic.quote.php -->
<!-- Template: basic.quote -->
<templateSetting caption="Quote Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Quote</dt>
        <dd><textarea name="custom_text">Inspirational quote goes here.</textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Author</dt>
        <dd><input type="text" name="custom_author" value="Author Name"></dd>
    </dl>
</templateSetting>
<blockquote class="quote-block" data-tpl-tooltip="Quote">
    <p data-editable>{custom_text}</p>
    <cite data-editable>{custom_author}</cite>
</blockquote>
