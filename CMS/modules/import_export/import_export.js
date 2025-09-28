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
        datasets.forEach(function(key){
            const label = formatDatasetLabel(key);
            const $item = $('<li>', {
                class: 'import-datasets__item',
                text: label || key,
            });
            $datasetList.append($item);
        });
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
                    const label = count === 1 ? '1 data set is included in exports.' : count.toLocaleString() + ' data sets are included in exports.';
                    $datasetSummary.text(label);
                } else {
                    $datasetSummary.empty();
                }
            }
            renderDatasets(data.datasets);
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
