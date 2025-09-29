// File: import_export.js
$(function(){
    const $exportBtn = $('#startExportBtn');
    const $importLastRun = $('#importLastRun');
    const $exportLastRun = $('#exportLastRun');
    const $importProfilesCount = $('#importProfilesCount');
    const $exportGeneratedCount = $('#exportGeneratedCount');
    const $status = $('#importExportStatus');
    const $datasetSection = $('#importDatasetSection');
    const $datasetSummary = $('#importDatasetSummary');
    const $datasetList = $('#importDatasetList');
    const $historySection = $('#importHistorySection');
    const $historyList = $('#importHistoryList');
    const $historyEmptyState = $('#importHistoryEmpty');

    const datasetDescriptions = {
        settings: 'Site-wide configuration such as the site name, metadata, themes, and integrations.',
        pages: 'Published page content, layouts, SEO fields, and routing information.',
        page_history: 'Revision history for pages, enabling rollbacks to previous versions.',
        menus: 'Menu structures, links, and hierarchy used throughout the site.',
        media: 'Records for uploaded images, documents, and other media assets.',
        blog_posts: 'Blog post articles, metadata, authorship, and publishing status.',
        forms: 'Form definitions including fields, validations, and notification settings.',
        form_submissions: 'Entries submitted through site forms with captured response data.',
        users: 'User accounts, roles, and access permissions for the CMS.',
        speed_snapshot: 'Performance metrics collected from site speed monitoring tools.',
        drafts: 'Unpublished draft items stored for future editing or review.',
    };

    function formatTimestamp(value){
        if (!value) {
            return '—';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '—';
        }
        return date.toLocaleString(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    }

    function formatDatasetLabel(key){
        if (!key) {
            return '';
        }
        return key
            .split('_')
            .map(function(part){
                return part.charAt(0).toUpperCase() + part.slice(1).toLowerCase();
            })
            .join(' ');
    }

    function clearStatus(){
        if (!$status.length) {
            return;
        }
        $status.removeClass('import-status--success import-status--error import-status--info is-visible')
            .attr('aria-hidden', 'true')
            .empty();
    }

    function setStatus(message, type){
        if (!$status.length) {
            return;
        }
        $status.removeClass('import-status--success import-status--error import-status--info is-visible');
        if (!message) {
            $status.attr('aria-hidden', 'true').empty();
            return;
        }

        let className = 'import-status--info';
        if (type === 'success') {
            className = 'import-status--success';
        } else if (type === 'error') {
            className = 'import-status--error';
        }

        $status
            .addClass(className + ' is-visible')
            .attr('aria-hidden', 'false')
            .text(message);
    }

    function setButtonLoading($button, loading){
        if (!$button.length) {
            return;
        }
        const $label = $button.find('span').first();
        const defaultLabel = $button.data('defaultLabel') || $label.text();
        if (!$button.data('defaultLabel')) {
            $button.data('defaultLabel', defaultLabel);
        }

        if (loading) {
            $label.text('Generating…');
            $button.addClass('is-loading').prop('disabled', true).attr('aria-busy', 'true');
        } else {
            $label.text($button.data('defaultLabel'));
            $button.removeClass('is-loading').prop('disabled', false).removeAttr('aria-busy');
        }
    }

    function renderDatasets(datasets){
        if (!$datasetSection.length || !$datasetList.length) {
            return;
        }

        if (!Array.isArray(datasets) || datasets.length === 0) {
            $datasetSection.attr('hidden', 'hidden');
            $datasetSummary.empty();
            $datasetList.empty();
            return;
        }

        $datasetSection.removeAttr('hidden');
        $datasetList.empty();
        datasets.forEach(function(item){
            let key = '';
            let label = '';
            let description = '';

            if (typeof item === 'string') {
                key = item;
            } else if (item && typeof item === 'object') {
                key = item.key || '';
                label = item.label || '';
                description = item.description || '';
            }

            if (!label) {
                label = formatDatasetLabel(key);
            }

            if (!description && key && datasetDescriptions[key]) {
                description = datasetDescriptions[key];
            }

            const $item = $('<li>', {
                class: 'import-datasets__item',
            });

            if (description) {
                $item.attr('title', description);
            }

            const $title = $('<span>', {
                class: 'import-datasets__item-title',
                text: label || key,
            });
            $item.append($title);

            if (description) {
                $item.append($('<span>', {
                    class: 'import-datasets__item-description',
                    text: description,
                }));
            }

            $datasetList.append($item);
        });
    }

    function renderHistory(historyEntries){
        if (!$historySection.length || !$historyList.length) {
            return;
        }

        $historySection.removeAttr('hidden');

        if (!Array.isArray(historyEntries) || historyEntries.length === 0) {
            $historyList.empty();
            if ($historyEmptyState.length) {
                $historyEmptyState.removeAttr('hidden');
            }
            return;
        }

        $historyList.empty();

        historyEntries.forEach(function(entry){
            if (!entry || typeof entry !== 'object') {
                return;
            }

            const typeRaw = entry.type ? String(entry.type).toLowerCase() : 'activity';
            const typeClass = typeRaw === 'import' || typeRaw === 'export' ? typeRaw : 'activity';
            const typeLabel = typeRaw === 'import' ? 'Import' : (typeRaw === 'export' ? 'Export' : 'Activity');
            const label = entry.label || (typeLabel + ' completed');
            const timestamp = entry.timestamp || null;
            const datasetCount = Number.isFinite(entry.dataset_count) ? Number(entry.dataset_count) : null;
            const fileName = entry.file ? String(entry.file) : '';

            let summary = entry.summary ? String(entry.summary) : '';
            if (!summary) {
                const summaryParts = [];
                if (fileName) {
                    summaryParts.push(fileName);
                }
                if (datasetCount !== null) {
                    summaryParts.push(datasetCount === 1 ? '1 data set' : datasetCount + ' data sets');
                }
                summary = summaryParts.join(' • ');
            }

            const $item = $('<li>', {
                class: 'import-history__item',
            });

            const $header = $('<div>', { class: 'import-history__item-header' });
            const $typeBadge = $('<span>', {
                class: 'import-history__item-type import-history__item-type--' + typeClass,
                text: typeLabel,
            });
            const $time = $('<time>', {
                class: 'import-history__item-time',
                text: formatTimestamp(timestamp),
            });
            $header.append($typeBadge, $time);

            const $title = $('<div>', {
                class: 'import-history__item-title',
                text: label,
            });

            $item.append($header, $title);

            if (summary) {
                $item.append($('<div>', {
                    class: 'import-history__item-summary',
                    text: summary,
                }));
            }

            $historyList.append($item);
        });

        if ($historyEmptyState.length) {
            $historyEmptyState.attr('hidden', 'hidden');
        }
    }

    function loadStatus(){
        const request = $.getJSON('modules/import_export/status.php');
        request.done(function(data){
            if ($importLastRun.length) {
                $importLastRun.text(formatTimestamp(data.last_import_at));
            }
            if ($exportLastRun.length) {
                $exportLastRun.text(formatTimestamp(data.last_export_at));
            }
            if ($importProfilesCount.length) {
                const profilesValue = Number(data.available_profiles);
                const profiles = Number.isFinite(profilesValue) ? profilesValue : 0;
                $importProfilesCount.text(profiles);
            }
            if ($exportGeneratedCount.length) {
                const exportValue = Number(data.export_count);
                const exports = Number.isFinite(exportValue) ? exportValue : 0;
                $exportGeneratedCount.text(exports);
            }
            if ($datasetSummary.length && typeof data.dataset_count === 'number') {
                const count = data.dataset_count;
                if (count > 0) {
                    const label = data.dataset_count_label || (count === 1 ? '1 data set' : count.toLocaleString() + ' data sets');
                    const verb = count === 1 ? ' is ' : ' are ';
                    $datasetSummary.text(label + verb + 'included in exports.');
                } else {
                    $datasetSummary.empty();
                }
            }
            const datasetPayload = Array.isArray(data.dataset_details) && data.dataset_details.length > 0 ? data.dataset_details : data.datasets;
            renderDatasets(datasetPayload);
            renderHistory(data.history);
        });
        return request;
    }

    function triggerExport(){
        clearStatus();
        setButtonLoading($exportBtn, true);

        $.ajax({
            url: 'modules/import_export/export.php',
            method: 'GET',
            xhrFields: {
                responseType: 'blob',
            },
        }).done(function(blob, _status, xhr){
            try {
                const disposition = xhr.getResponseHeader('Content-Disposition') || '';
                let filename = 'sparkcms-export.json';
                const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
                if (utfMatch && utfMatch[1]) {
                    filename = decodeURIComponent(utfMatch[1]);
                } else {
                    const asciiMatch = disposition.match(/filename="?([^";]+)"?/i);
                    if (asciiMatch && asciiMatch[1]) {
                        filename = asciiMatch[1];
                    }
                }

                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);

                setStatus('Export generated successfully. Your download should begin automatically.', 'success');
            } catch (error) {
                console.error('Import/export download failed', error);
                setStatus('The export was generated but the download could not be started. Please try again.', 'error');
            }

            loadStatus().fail(function(){
                setStatus('Export generated, but the latest status could not be loaded.', 'info');
            });
        }).fail(function(jqXHR){
            let message = 'Unable to generate an export right now. Please try again later.';
            if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                message = jqXHR.responseJSON.error;
            }
            setStatus(message, 'error');
        }).always(function(){
            setButtonLoading($exportBtn, false);
        });
    }

    if ($exportBtn.length) {
        $exportBtn.on('click', function(){
            triggerExport();
        });
    }

    loadStatus().fail(function(){
        setStatus('Unable to load import/export status right now. Please try again later.', 'error');
    });
});
