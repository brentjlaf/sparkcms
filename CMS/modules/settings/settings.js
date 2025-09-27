// File: settings.js
$(function(){
    const $form = $('#settingsForm');
    const $dashboard = $('#settingsDashboard');
    const $lastSaved = $('#settingsLastSaved');
    const $saveButton = $('#saveSettingsButton');
    const $logoPreview = $('#logoPreview');
    const $ogPreview = $('#ogImagePreview');
    const $clearLogo = $('#clearLogo');
    const $clearOgImage = $('#clearOgImage');

    function formatTimestamp(value){
        if(!value){
            return 'Not saved yet';
        }
        const date = new Date(value);
        if(Number.isNaN(date.getTime())){
            return value;
        }
        return date.toLocaleString(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short'
        });
    }

    function updateHeroMeta(timestamp){
        $dashboard.attr('data-last-saved', timestamp || '');
        $lastSaved.text(formatTimestamp(timestamp));
    }

    function updateOverview(){
        const siteName = $('#site_name').val().trim();
        $('#settingsOverviewName').text(siteName || 'Not set');

        const socialSelectors = [
            '#facebookLink',
            '#twitterLink',
            '#instagramLink',
            '#linkedinLink',
            '#youtubeLink',
            '#tiktokLink'
        ];
        const socialCount = socialSelectors.reduce((count, selector) => {
            return count + ($(selector).val().trim() ? 1 : 0);
        }, 0);
        $('#settingsOverviewSocials').text(socialCount);

        const trackingFields = [
            '#googleAnalytics',
            '#googleSearchConsole',
            '#facebookPixel'
        ];
        const trackingCount = trackingFields.reduce((count, selector) => {
            return count + ($(selector).val().trim() ? 1 : 0);
        }, 0);
        $('#settingsOverviewTracking').text(trackingCount);

        const sitemapOn = $('#generateSitemap').is(':checked');
        const indexingOn = $('#allowIndexing').is(':checked');
        const visibility = indexingOn ? 'Public' : 'Restricted';
        const details = [];
        details.push(sitemapOn ? 'Sitemap on' : 'Sitemap off');
        details.push(indexingOn ? 'Indexing allowed' : 'Indexing blocked');
        $('#settingsOverviewVisibility').text(visibility).attr('title', details.join(' • '));
    }

    function togglePreview($img, src){
        if(src){
            $img.attr('src', src).removeAttr('hidden');
        } else {
            $img.attr('src', '').attr('hidden', true);
        }
    }

    function setPreviewState($preview, $checkbox, src){
        const hasSrc = Boolean(src);
        $checkbox.data('previewSrc', hasSrc ? src : '');
        $checkbox.prop('checked', false).prop('disabled', !hasSrc);
        togglePreview($preview, src);
    }

    function bindClearToggle($checkbox, $preview){
        $checkbox.on('change', function(){
            if(this.checked){
                togglePreview($preview, '');
            } else {
                const stored = $checkbox.data('previewSrc') || '';
                togglePreview($preview, stored);
            }
        });
    }

    bindClearToggle($clearLogo, $logoPreview);
    bindClearToggle($clearOgImage, $ogPreview);

    function loadSettings(){
        $.getJSON('modules/settings/list_settings.php', function(data){
            data = data || {};
            $('#site_name').val(data.site_name || '');
            $('#tagline').val(data.tagline || '');
            $('#admin_email').val(data.admin_email || '');

            setPreviewState($logoPreview, $clearLogo, data.logo || '');

            $('#timezone').val(data.timezone || 'America/Los_Angeles');
            $('#googleAnalytics').val(data.googleAnalytics || '');
            $('#googleSearchConsole').val(data.googleSearchConsole || '');
            $('#facebookPixel').val(data.facebookPixel || '');

            $('#generateSitemap').prop('checked', data.generateSitemap !== false);
            $('#allowIndexing').prop('checked', data.allowIndexing !== false);

            const social = data.social || {};
            $('#facebookLink').val(social.facebook || '');
            $('#twitterLink').val(social.twitter || '');
            $('#instagramLink').val(social.instagram || '');
            $('#linkedinLink').val(social.linkedin || '');
            $('#youtubeLink').val(social.youtube || '');
            $('#tiktokLink').val(social.tiktok || '');

            const openGraph = data.open_graph || {};
            $('#ogTitle').val(openGraph.title || '');
            $('#ogDescription').val(openGraph.description || '');
            setPreviewState($ogPreview, $clearOgImage, openGraph.image || '');

            updateHeroMeta(data.last_updated || '');
            updateOverview();
        });
    }

    $('#logoFile').on('change', function(){
        const file = this.files && this.files[0];
        if(file){
            const reader = new FileReader();
            reader.onload = function(e){
                setPreviewState($logoPreview, $clearLogo, e.target.result);
                updateOverview();
            };
            reader.readAsDataURL(file);
        }
    });

    $('#ogImageFile').on('change', function(){
        const file = this.files && this.files[0];
        if(file){
            const reader = new FileReader();
            reader.onload = function(e){
                setPreviewState($ogPreview, $clearOgImage, e.target.result);
            };
            reader.readAsDataURL(file);
        }
    });

    $form.on('input change', 'input, textarea, select', function(){
        updateOverview();
    });

    $form.on('submit', function(e){
        e.preventDefault();
        const fd = new FormData(this);
        $.ajax({
            url: 'modules/settings/save_settings.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function(){
                $saveButton.addClass('is-loading').prop('disabled', true);
            },
            complete: function(){
                $saveButton.removeClass('is-loading').prop('disabled', false);
            },
            success: function(response){
                if(response && response.status === 'ok'){
                    alertModal('Settings saved');
                    if(response.last_updated){
                        updateHeroMeta(response.last_updated);
                    }
                    loadSettings();
                } else {
                    const message = (response && response.message) ? response.message : 'Unable to save settings';
                    alertModal(message);
                }
            },
            error: function(xhr){
                let message = 'Unable to save settings';
                if(xhr && xhr.responseJSON && xhr.responseJSON.message){
                    message = xhr.responseJSON.message;
                } else if(xhr && xhr.responseText){
                    try {
                        const parsed = JSON.parse(xhr.responseText);
                        if(parsed && parsed.message){
                            message = parsed.message;
                        }
                    } catch (e) {
                        // ignore JSON parse errors
                    }
                }
                alertModal(message);
            }
        });
    });

    loadSettings();
});
