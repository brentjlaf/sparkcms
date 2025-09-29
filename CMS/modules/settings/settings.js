// File: settings.js
$(function(){
    const $form = $('#settingsForm');
    const $dashboard = $('#settingsDashboard');
    const $lastSaved = $('#settingsLastSaved');
    const $saveButton = $('#saveSettingsButton');
    const $logoPreview = $('#logoPreview');
    const $faviconPreview = $('#faviconPreview');
    const $ogPreview = $('#ogImagePreview');
    const $tabs = $('.settings-tab');
    const $tabPanels = $('.settings-tab-panel');
    const $logoFileLabel = $('#logoFileName');
    const $faviconFileLabel = $('#faviconFileName');
    const $ogImageFileLabel = $('#ogImageFileName');
    const $clearLogo = $('#clearLogo');
    const $clearFavicon = $('#clearFavicon');
    const $clearOgImage = $('#clearOgImage');
    const $socialPreviewImage = $('#socialPreviewImage');
    const $socialPreviewFallback = $('#socialPreviewImageFallback');
    const $socialPreviewTitle = $('#socialPreviewTitle');
    const $socialPreviewDescription = $('#socialPreviewDescription');
    const $socialPreviewDomain = $('#socialPreviewDomain');

    const integrationValidators = [
        {
            selector: '#googleAnalytics',
            pattern: /^(G-[A-Z0-9]{8,12}|UA-\d{4,10}-\d{1,4})$/i,
            message: 'Enter a valid Google Analytics ID (e.g., G-XXXXXXXXXX or UA-XXXXXXXX-X).'
        },
        {
            selector: '#googleSearchConsole',
            pattern: /^(google-site-verification=)?[A-Za-z0-9_-]{10,100}$/,
            message: 'Enter a valid Google Search Console verification code (e.g., google-site-verification=XXXXX).'
        },
        {
            selector: '#facebookPixel',
            pattern: /^\d{15,16}$/,
            message: 'Enter a valid Facebook Pixel ID using 15-16 digits.'
        }
    ];

    function activateTab($tab, options = {}){
        if(!$tab || !$tab.length){
            return;
        }

        const targetId = $tab.attr('data-tab-target');
        if(!targetId){
            return;
        }

        const shouldFocus = Boolean(options.focus);

        $tabs.each(function(){
            const $button = $(this);
            const isActive = $button.is($tab);
            $button.attr('aria-selected', isActive ? 'true' : 'false')
                .attr('tabindex', isActive ? '0' : '-1')
                .toggleClass('is-active', isActive);
        });

        $tabPanels.each(function(){
            const $panel = $(this);
            if($panel.attr('id') === targetId){
                $panel.removeAttr('hidden');
            } else {
                $panel.attr('hidden', true);
            }
        });

        if(shouldFocus){
            $tab.trigger('focus');
        }
    }

    function focusAdjacentTab(currentIndex, direction){
        if(!$tabs.length){
            return;
        }
        const total = $tabs.length;
        let nextIndex = (currentIndex + direction + total) % total;
        const $next = $tabs.eq(nextIndex);
        activateTab($next, { focus: true });
    }

    function extractFileName(value){
        if(!value){
            return '';
        }
        if(/^data:/i.test(value)){
            return 'Uploaded image';
        }
        const cleaned = value.split('?')[0];
        const parts = cleaned.split(/[\\/]/);
        const candidate = parts.pop();
        return candidate || value;
    }

    function getDefaultFileLabel($label){
        return ($label && $label.data('default')) ? String($label.data('default')) : 'No file selected';
    }

    function getRemovalFileLabel($label){
        return ($label && $label.data('remove')) ? String($label.data('remove')) : 'Marked for removal';
    }

    function refreshFileLabel($checkbox, $label){
        if(!$label || !$label.length){
            return;
        }
        if($checkbox && $checkbox.is(':checked')){
            $label.text(getRemovalFileLabel($label));
            return;
        }
        const fileName = $checkbox ? ($checkbox.data('fileName') || '') : '';
        const fileSource = $checkbox ? ($checkbox.data('fileSource') || 'stored') : 'stored';
        if(fileName){
            const prefix = fileSource === 'selected' ? 'Selected' : 'Current';
            $label.text(`${prefix}: ${fileName}`);
        } else {
            $label.text(getDefaultFileLabel($label));
        }
    }

    function clearFieldError($field){
        if(!$field || !$field.length){
            return;
        }
        $field.removeClass('is-invalid').removeAttr('aria-invalid');
        $field.closest('.form-group').find('.form-error').remove();
    }

    function showFieldError($field, message){
        if(!$field || !$field.length){
            return;
        }
        clearFieldError($field);
        $field.addClass('is-invalid').attr('aria-invalid', 'true');
        const $group = $field.closest('.form-group');
        const $error = $('<div class="form-error" role="alert"></div>').text(message);
        const $help = $group.find('.form-help').last();
        if($help.length){
            $error.insertAfter($help);
        } else {
            $group.append($error);
        }
    }

    function clearIntegrationErrors(){
        integrationValidators.forEach(({selector}) => {
            clearFieldError($(selector));
        });
    }

    function validateIntegrations(){
        let firstInvalidField = null;
        let hasError = false;

        integrationValidators.forEach(({selector, pattern, message}) => {
            const $field = $(selector);
            const value = ($field.val() || '').trim();
            if(!value){
                clearFieldError($field);
                return;
            }

            if(!pattern.test(value)){
                showFieldError($field, message);
                if(!firstInvalidField){
                    firstInvalidField = $field;
                }
                hasError = true;
            } else {
                clearFieldError($field);
            }
        });

        if(hasError){
            alertModal('Please fix the highlighted integration fields before saving.');
            if(firstInvalidField){
                firstInvalidField.focus();
            }
        }

        return !hasError;
    }

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
            '#tiktokLink',
            '#pinterestLink',
            '#snapchatLink',
            '#redditLink',
            '#threadsLink',
            '#mastodonLink',
            '#githubLink',
            '#dribbbleLink',
            '#twitchLink',
            '#whatsappLink'
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

    function setSocialPreviewImage(src){
        if(src){
            $socialPreviewImage.attr('src', src).removeAttr('hidden');
            $socialPreviewFallback.attr('hidden', true);
        } else {
            $socialPreviewImage.attr('src', '').attr('hidden', true);
            $socialPreviewFallback.removeAttr('hidden');
        }
    }

    function togglePreview($img, src){
        if(src){
            $img.attr('src', src).removeAttr('hidden');
        } else {
            $img.attr('src', '').attr('hidden', true);
        }
        if($img.is($ogPreview)){
            setSocialPreviewImage(src);
        }
    }

    function setPreviewState($preview, $checkbox, src, fileName = '', source = 'stored'){
        const hasSrc = Boolean(src);
        $checkbox.data('previewSrc', hasSrc ? src : '');
        $checkbox.data('fileName', hasSrc ? fileName : '');
        $checkbox.data('fileSource', hasSrc ? source : '');
        $checkbox.prop('checked', false).prop('disabled', !hasSrc);
        togglePreview($preview, src);
    }

    function bindClearToggle($checkbox, $preview, $label){
        $checkbox.on('change', function(){
            if(this.checked){
                togglePreview($preview, '');
            } else {
                const stored = $checkbox.data('previewSrc') || '';
                togglePreview($preview, stored);
            }
            if($label){
                refreshFileLabel($checkbox, $label);
            }
        });
    }

    bindClearToggle($clearLogo, $logoPreview, $logoFileLabel);
    bindClearToggle($clearFavicon, $faviconPreview, $faviconFileLabel);
    bindClearToggle($clearOgImage, $ogPreview, $ogImageFileLabel);

    function getDefaultOgTitle(settings){
        const siteName = (settings.site_name || '').trim() || 'SparkCMS';
        const tagline = (settings.tagline || '').trim();
        return tagline ? `${siteName} | ${tagline}` : siteName;
    }

    function getDefaultOgDescription(settings){
        const siteName = (settings.site_name || '').trim() || 'SparkCMS';
        return `Stay up to date with the latest updates from ${siteName}.`;
    }

    function resolveOgTitle(){
        const ogTitleInput = $('#ogTitle').val().trim();
        if(ogTitleInput){
            return ogTitleInput;
        }
        return getDefaultOgTitle({
            site_name: $('#site_name').val(),
            tagline: $('#tagline').val()
        });
    }

    function resolveOgDescription(){
        const ogDescriptionInput = $('#ogDescription').val().trim();
        if(ogDescriptionInput){
            return ogDescriptionInput;
        }
        return getDefaultOgDescription({
            site_name: $('#site_name').val()
        });
    }

    function updateSocialPreviewText(){
        $socialPreviewTitle.text(resolveOgTitle());
        $socialPreviewDescription.text(resolveOgDescription());
    }

    function loadSettings(){
        $.getJSON('modules/settings/list_settings.php', function(data){
            data = data || {};
            $('#site_name').val(data.site_name || '');
            $('#tagline').val(data.tagline || '');
            $('#admin_email').val(data.admin_email || '');

            setPreviewState($logoPreview, $clearLogo, data.logo || '', extractFileName(data.logo), 'stored');
            setPreviewState($faviconPreview, $clearFavicon, data.favicon || '', extractFileName(data.favicon), 'stored');

            $('#timezone').val(data.timezone || 'America/Denver');
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
            $('#pinterestLink').val(social.pinterest || '');
            $('#snapchatLink').val(social.snapchat || '');
            $('#redditLink').val(social.reddit || '');
            $('#threadsLink').val(social.threads || '');
            $('#mastodonLink').val(social.mastodon || '');
            $('#githubLink').val(social.github || '');
            $('#dribbbleLink').val(social.dribbble || '');
            $('#twitchLink').val(social.twitch || '');
            $('#whatsappLink').val(social.whatsapp || '');

            const openGraph = data.open_graph || {};
            const ogTitleValue = (openGraph.title || '').trim();
            const ogDescriptionValue = (openGraph.description || '').trim();
            $('#ogTitle').val(ogTitleValue || getDefaultOgTitle(data));
            $('#ogDescription').val(ogDescriptionValue || getDefaultOgDescription(data));
            setPreviewState($ogPreview, $clearOgImage, openGraph.image || '', extractFileName(openGraph.image), 'stored');

            $('#logoFile').val('');
            $('#faviconFile').val('');
            $('#ogImageFile').val('');

            refreshFileLabel($clearLogo, $logoFileLabel);
            refreshFileLabel($clearFavicon, $faviconFileLabel);
            refreshFileLabel($clearOgImage, $ogImageFileLabel);

            const hostname = (window.location && window.location.hostname) ? window.location.hostname : 'yourdomain.com';
            $socialPreviewDomain.text(hostname);
            updateSocialPreviewText();

            clearIntegrationErrors();
            updateHeroMeta(data.last_updated || '');
            updateOverview();
        });
    }

    $('#logoFile').on('change', function(){
        const file = this.files && this.files[0];
        if(file){
            const reader = new FileReader();
            reader.onload = function(e){
                setPreviewState($logoPreview, $clearLogo, e.target.result, file.name, 'selected');
                refreshFileLabel($clearLogo, $logoFileLabel);
                updateOverview();
            };
            reader.readAsDataURL(file);
        } else {
            refreshFileLabel($clearLogo, $logoFileLabel);
        }
    });

    $('#faviconFile').on('change', function(){
        const file = this.files && this.files[0];
        if(file){
            const reader = new FileReader();
            reader.onload = function(e){
                setPreviewState($faviconPreview, $clearFavicon, e.target.result, file.name, 'selected');
                refreshFileLabel($clearFavicon, $faviconFileLabel);
            };
            reader.readAsDataURL(file);
        } else {
            refreshFileLabel($clearFavicon, $faviconFileLabel);
        }
    });

    $('#ogImageFile').on('change', function(){
        const file = this.files && this.files[0];
        if(file){
            const reader = new FileReader();
            reader.onload = function(e){
                setPreviewState($ogPreview, $clearOgImage, e.target.result, file.name, 'selected');
                refreshFileLabel($clearOgImage, $ogImageFileLabel);
            };
            reader.readAsDataURL(file);
        } else {
            refreshFileLabel($clearOgImage, $ogImageFileLabel);
        }
    });

    $('.settings-file-trigger').on('click', function(event){
        event.preventDefault();
        const targetId = $(this).attr('data-input-target');
        if(!targetId){
            return;
        }
        const $targetInput = $(`#${targetId}`);
        if($targetInput.length){
            $targetInput.trigger('click');
        }
    });

    if($tabs.length){
        const $initialTab = $tabs.filter('[aria-selected="true"]').first();
        activateTab($initialTab.length ? $initialTab : $tabs.first());

        $tabs.on('click', function(){
            activateTab($(this));
        });

        $tabs.on('keydown', function(event){
            const key = event.key;
            const index = $tabs.index(this);
            if(key === 'ArrowRight' || key === 'ArrowDown'){
                event.preventDefault();
                focusAdjacentTab(index, 1);
            } else if(key === 'ArrowLeft' || key === 'ArrowUp'){
                event.preventDefault();
                focusAdjacentTab(index, -1);
            } else if(key === 'Home'){
                event.preventDefault();
                activateTab($tabs.first(), { focus: true });
            } else if(key === 'End'){
                event.preventDefault();
                activateTab($tabs.last(), { focus: true });
            }
        });
    }

    $form.on('input change', 'input, textarea, select', function(){
        clearFieldError($(this));
        updateOverview();
    });

    $form.on('input change', '#ogTitle, #ogDescription, #site_name, #tagline', function(){
        updateSocialPreviewText();
    });

    $form.on('submit', function(e){
        e.preventDefault();
        if(!validateIntegrations()){
            return;
        }

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
