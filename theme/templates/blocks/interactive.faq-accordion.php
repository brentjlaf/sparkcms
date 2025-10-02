<!-- File: interactive.faq-accordion.php -->
<!-- Template: interactive.faq-accordion -->
<templateSetting caption="FAQ Accordion Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>FAQ Items</dt>
        <dd>
            <p class="small text-muted mb-3">Add common questions with their answers. Items will appear as an accessible accordion on the page.</p>
            <div class="faq-setting-items" data-faq-list></div>
            <button type="button" class="btn btn-secondary mt-3" data-faq-add>Add FAQ Item</button>
            <textarea name="custom_faq_markup" class="faq-markup-field" hidden><div class="accordion open">
    <h3 class="accordion-header">
        <button type="button" class="accordion-button" id="faq-accordion-default-item-1-button" aria-expanded="true" aria-controls="faq-accordion-default-item-1-panel">
            <span class="accordion-button__label" data-editable>What services does your team provide?</span>
        </button>
    </h3>
    <div id="faq-accordion-default-item-1-panel" class="accordion-panel" role="region" aria-labelledby="faq-accordion-default-item-1-button" aria-hidden="false">
        <div class="accordion-panel-inner" data-editable>
            <p>We offer strategy, design, and development solutions tailored to your business goals.</p>
        </div>
    </div>
</div>
<div class="accordion">
    <h3 class="accordion-header">
        <button type="button" class="accordion-button" id="faq-accordion-default-item-2-button" aria-expanded="false" aria-controls="faq-accordion-default-item-2-panel">
            <span class="accordion-button__label" data-editable>How can I get support?</span>
        </button>
    </h3>
    <div id="faq-accordion-default-item-2-panel" class="accordion-panel" role="region" aria-labelledby="faq-accordion-default-item-2-button" aria-hidden="true">
        <div class="accordion-panel-inner" data-editable>
            <p>Reach out through our contact form or email support@example.com for assistance.</p>
        </div>
    </div>
</div>
<div class="accordion">
    <h3 class="accordion-header">
        <button type="button" class="accordion-button" id="faq-accordion-default-item-3-button" aria-expanded="false" aria-controls="faq-accordion-default-item-3-panel">
            <span class="accordion-button__label" data-editable>What are your business hours?</span>
        </button>
    </h3>
    <div id="faq-accordion-default-item-3-panel" class="accordion-panel" role="region" aria-labelledby="faq-accordion-default-item-3-button" aria-hidden="true">
        <div class="accordion-panel-inner" data-editable>
            <p>Our team is available Monday through Friday from 9:00 AM to 6:00 PM.</p>
        </div>
    </div>
</div></textarea>
            <textarea name="custom_faq_items" class="faq-items-data" hidden>[
    {
        "question": "What services does your team provide?",
        "answer": "We offer strategy, design, and development solutions tailored to your business goals."
    },
    {
        "question": "How can I get support?",
        "answer": "Reach out through our contact form or email support@example.com for assistance."
    },
    {
        "question": "What are your business hours?",
        "answer": "Our team is available Monday through Friday from 9:00 AM to 6:00 PM."
    }
]</textarea>
            <input type="hidden" name="custom_faq_prefix" value="faq-accordion-default">
            <template data-faq-template>
                <div class="faq-setting-item" data-faq-item>
                    <label class="form-label">Question
                        <input type="text" class="form-control" data-faq-question placeholder="Enter the question">
                    </label>
                    <label class="form-label mt-2">Answer
                        <textarea class="form-control" data-faq-answer rows="4" placeholder="Provide the answer"></textarea>
                    </label>
                    <button type="button" class="btn btn-link text-danger px-0 mt-2" data-faq-remove>Remove</button>
                    <hr>
                </div>
            </template>
        </dd>
    </dl>
    <script>
        (function () {
            var script = document.currentScript;
            if (!script) return;
            var root = script.closest('templateSetting');
            if (!root || root.dataset.faqInit === 'true') return;
            root.dataset.faqInit = 'true';

            var list = root.querySelector('[data-faq-list]');
            var template = root.querySelector('template[data-faq-template]');
            var addBtn = root.querySelector('[data-faq-add]');
            var markupField = root.querySelector('textarea[name="custom_faq_markup"]');
            var dataField = root.querySelector('textarea[name="custom_faq_items"]');
            var prefixField = root.querySelector('input[name="custom_faq_prefix"]');

            if (!list || !template || !addBtn || !markupField || !dataField || !prefixField) {
                return;
            }

            var defaultItems = [
                {
                    question: 'What services does your team provide?',
                    answer: 'We offer strategy, design, and development solutions tailored to your business goals.'
                },
                {
                    question: 'How can I get support?',
                    answer: 'Reach out through our contact form or email support@example.com for assistance.'
                },
                {
                    question: 'What are your business hours?',
                    answer: 'Our team is available Monday through Friday from 9:00 AM to 6:00 PM.'
                }
            ];

            function escapeHtml(str) {
                return str.replace(/[&<>"']/g, function (char) {
                    switch (char) {
                        case '&':
                            return '&amp;';
                        case '<':
                            return '&lt;';
                        case '>':
                            return '&gt;';
                        case '"':
                            return '&quot;';
                        case "'":
                            return '&#39;';
                        default:
                            return char;
                    }
                });
            }

            function ensurePrefix() {
                var value = (prefixField.value || '').trim();
                if (!value || value === 'faq-accordion-default') {
                    value = 'faq-accordion-' + Math.random().toString(36).slice(2, 10);
                    prefixField.value = value;
                    dispatchChange(prefixField);
                }
                return value;
            }

            function dispatchChange(el) {
                var event = new Event('input', { bubbles: true });
                el.dispatchEvent(event);
            }

            function formatAnswer(text) {
                var trimmed = (text || '').trim();
                if (!trimmed) {
                    return '<p></p>';
                }
                var paragraphs = trimmed.split(/\n{2,}/);
                return paragraphs
                    .map(function (segment) {
                        var safe = escapeHtml(segment.trim()).replace(/\n/g, '<br>');
                        return safe ? '<p>' + safe + '</p>' : '';
                    })
                    .filter(Boolean)
                    .join('');
            }

            function buildMarkup(items, prefix) {
                return items
                    .map(function (item, index) {
                        var question = escapeHtml((item.question || '').trim() || 'Untitled question');
                        var answerHtml = formatAnswer(item.answer || '');
                        var itemIndex = index + 1;
                        var baseId = prefix + '-item-' + itemIndex;
                        var buttonId = baseId + '-button';
                        var panelId = baseId + '-panel';
                        var isOpen = index === 0;
                        return (
                            '<div class="accordion' + (isOpen ? ' open' : '') + '">\n' +
                            '    <h3 class="accordion-header">\n' +
                            '        <button type="button" class="accordion-button" id="' + buttonId + '" aria-expanded="' + (isOpen ? 'true' : 'false') + '" aria-controls="' + panelId + '">\n' +
                            '            <span class="accordion-button__label" data-editable>' + question + '</span>\n' +
                            '        </button>\n' +
                            '    </h3>\n' +
                            '    <div id="' + panelId + '" class="accordion-panel" role="region" aria-labelledby="' + buttonId + '" aria-hidden="' + (isOpen ? 'false' : 'true') + '">\n' +
                            '        <div class="accordion-panel-inner" data-editable>' + (answerHtml || '<p></p>') + '</div>\n' +
                            '    </div>\n' +
                            '</div>'
                        );
                    })
                    .join('\n');
            }

            function htmlToText(html) {
                if (!html) return '';
                var temp = document.createElement('div');
                temp.innerHTML = html;
                var paragraphs = Array.from(temp.querySelectorAll('p'));
                if (paragraphs.length) {
                    return paragraphs
                        .map(function (p) {
                            return p.textContent.trim();
                        })
                        .filter(Boolean)
                        .join('\n\n');
                }
                return temp.textContent.trim();
            }

            function parseMarkup(markup) {
                var wrapper = document.createElement('div');
                wrapper.innerHTML = markup;
                var nodes = wrapper.querySelectorAll('.accordion');
                var results = [];
                nodes.forEach(function (node) {
                    var button = node.querySelector('.accordion-button');
                    var panel = node.querySelector('.accordion-panel-inner');
                    var question = button ? button.textContent.trim() : '';
                    var answer = panel ? htmlToText(panel.innerHTML) : '';
                    if (question || answer) {
                        results.push({ question: question, answer: answer });
                    }
                });
                return results;
            }

            function loadItems() {
                var items = [];
                var dataValue = (dataField.value || '').trim();
                if (dataValue) {
                    try {
                        var parsed = JSON.parse(dataValue);
                        if (Array.isArray(parsed)) {
                            items = parsed
                                .map(function (item) {
                                    return {
                                        question: (item && item.question) || '',
                                        answer: (item && item.answer) || ''
                                    };
                                })
                                .filter(function (item) {
                                    return item.question || item.answer;
                                });
                        }
                    } catch (err) {
                        items = [];
                    }
                }
                if (!items.length && markupField.value.trim()) {
                    items = parseMarkup(markupField.value);
                }
                if (!items.length) {
                    items = defaultItems.slice();
                }
                return items;
            }

            var items = loadItems();
            var lastMarkupValue = markupField.value;
            var lastDataValue = dataField.value;

            function renderItems() {
                list.innerHTML = '';
                items.forEach(function (item, index) {
                    var fragment = document.importNode(template.content, true);
                    var container = fragment.querySelector('[data-faq-item]');
                    var questionInput = container.querySelector('[data-faq-question]');
                    var answerInput = container.querySelector('[data-faq-answer]');
                    var removeBtn = container.querySelector('[data-faq-remove]');
                    questionInput.value = item.question || '';
                    answerInput.value = item.answer || '';

                    questionInput.addEventListener('input', function () {
                        items[index].question = questionInput.value;
                        updateFields();
                    });
                    answerInput.addEventListener('input', function () {
                        items[index].answer = answerInput.value;
                        updateFields();
                    });
                    removeBtn.addEventListener('click', function () {
                        if (items.length > 1) {
                            items.splice(index, 1);
                        } else {
                            items[0] = { question: '', answer: '' };
                        }
                        renderItems();
                        updateFields();
                    });

                    list.appendChild(fragment);
                });
            }

            function updateFields() {
                var prefix = ensurePrefix();
                var markup = buildMarkup(items, prefix);
                if (markupField.value !== markup) {
                    markupField.value = markup;
                    lastMarkupValue = markup;
                    dispatchChange(markupField);
                }
                var json = JSON.stringify(items);
                if (dataField.value !== json) {
                    dataField.value = json;
                    lastDataValue = json;
                    dispatchChange(dataField);
                }
            }

            addBtn.addEventListener('click', function () {
                items.push({ question: 'New question', answer: 'Add the answer here.' });
                renderItems();
                updateFields();
            });

            renderItems();
            updateFields();
        })();
    </script>
</templateSetting>
<section class="faq-accordion-block" data-tpl-tooltip="FAQ Accordion" data-accordion-prefix="{custom_faq_prefix}">
    <div class="faq-accordion-block__inner">
        {custom_faq_markup}
    </div>
</section>
