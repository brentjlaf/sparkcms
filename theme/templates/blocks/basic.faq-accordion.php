<!-- File: basic.faq-accordion.php -->
<!-- Template: basic.faq-accordion -->
<templateSetting caption="FAQ Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Question 1</dt>
        <dd><input type="text" name="custom_q1" value="What is your first FAQ question?"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Answer 1</dt>
        <dd><textarea name="custom_a1">This is the answer to the first question.</textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Question 2</dt>
        <dd><input type="text" name="custom_q2" value="What is your second FAQ question?"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Answer 2</dt>
        <dd><textarea name="custom_a2">This is the answer to the second question.</textarea></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Question 3</dt>
        <dd><input type="text" name="custom_q3" value="What is your third FAQ question?"></dd>
    </dl>
    <dl class="sparkDialog _tpl-box">
        <dt>Answer 3</dt>
        <dd><textarea name="custom_a3">This is the answer to the third question.</textarea></dd>
    </dl>
</templateSetting>
<div class="faq-accordion" data-tpl-tooltip="FAQ Accordion">
    <details class="faq-item">
        <summary data-editable>{custom_q1}</summary>
        <p data-editable>{custom_a1}</p>
    </details>
    <details class="faq-item">
        <summary data-editable>{custom_q2}</summary>
        <p data-editable>{custom_a2}</p>
    </details>
    <details class="faq-item">
        <summary data-editable>{custom_q3}</summary>
        <p data-editable>{custom_a3}</p>
    </details>
</div>
