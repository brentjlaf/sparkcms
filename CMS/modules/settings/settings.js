// File: settings.js
$(function(){
    const $form = $('#settingsForm');
    const $dashboard = $('#settingsDashboard');
    const $lastSaved = $('#settingsLastSaved');
    const $saveButton = $('#saveSettingsButton');
    const $logoPreview = $('#logoPreview');
    const $ogPreview = $('#ogImagePreview');
    const socialFieldSelectors = {
        facebook: '#facebookLink',
        twitter: '#twitterLink',
        instagram: '#instagramLink',
        linkedin: '#linkedinLink',
        youtube: '#youtubeLink',
        tiktok: '#tiktokLink'
    };
    const socialFieldLabels = {
        facebook: 'Facebook',
        twitter: 'Twitter',
        instagram: 'Instagram',
        linkedin: 'LinkedIn',
        youtube: 'YouTube',
        tiktok: 'TikTok'
    };

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

    function setSocialFieldValidity(invalidFields){
        invalidFields = invalidFields || [];
        Object.keys(socialFieldSelectors).forEach(function(field){
            const selector = socialFieldSelectors[field];
            const $input = $(selector);
            if(!$input.length){
                return;
            }
            if(invalidFields.includes(field)){
                $input.addClass('is-danger').attr('aria-invalid', 'true');
            } else {
                $input.removeClass('is-danger').removeAttr('aria-invalid');
            }
        });
    }

    function loadSettings(){
        $.getJSON('modules/settings/list_settings.php', function(data){
            data = data || {};
            $('#site_name').val(data.site_name || '');
            $('#tagline').val(data.tagline || '');
            $('#admin_email').val(data.admin_email || '');

            togglePreview($logoPreview, data.logo || '');

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
            togglePreview($ogPreview, openGraph.image || '');

            updateHeroMeta(data.last_updated || '');
            updateOverview();
        });
    }

    $('#logoFile').on('change', function(){
        const file = this.files && this.files[0];
        if(file){
            const reader = new FileReader();
            reader.onload = function(e){
                togglePreview($logoPreview, e.target.result);
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
                togglePreview($ogPreview, e.target.result);
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
                let message = 'Settings saved';
                if(response && response.last_updated){
                    updateHeroMeta(response.last_updated);
                }
                if(response && Array.isArray(response.invalid_social_fields) && response.invalid_social_fields.length){
                    setSocialFieldValidity(response.invalid_social_fields);
                    const invalidNames = response.invalid_social_fields.map(function(field){
                        return socialFieldLabels[field] || field;
                    });
                    message += '.\nSome social links were not saved. Please enter valid HTTPS URLs for: ' + invalidNames.join(', ');
                } else {
                    setSocialFieldValidity([]);
                }
                alertModal(message);
                loadSettings();
            },
            error: function(){
                alertModal('Unable to save settings');
            }
        });
    });

    loadSettings();
});
