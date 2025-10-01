<!-- File: media.video-embed.php -->
<!-- Template: media.video-embed -->
<?php $videoBlockId = uniqid('video-embed-'); ?>
<templateSetting caption="Video Settings" order="1">
    <dl class="sparkDialog _tpl-box">
        <dt>Source Type</dt>
        <dd class="align-options">
            <label><input type="radio" name="custom_source_type" value="external" checked> External URL</label>
            <label><input type="radio" name="custom_source_type" value="upload"> Uploaded File</label>
        </dd>
    </dl>

    <toggle rel="custom_source_type" value="external">
        <dl class="sparkDialog _tpl-box">
            <dt>External Video URL</dt>
            <dd>
                <input type="url" name="custom_external_url" placeholder="https://example.com/video.mp4">
                <small class="form-text text-muted">Supports HTTPS video sources.</small>
            </dd>
        </dl>
    </toggle>

    <toggle rel="custom_source_type" value="upload">
        <dl class="sparkDialog _tpl-box">
            <dt>Uploaded Video File</dt>
            <dd>
                <input type="text" name="custom_upload_url" id="custom_upload_url" placeholder="/media/video.mp4">
                <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_upload_url')"><i class="fa-solid fa-file-video btn-icon" aria-hidden="true"></i><span class="btn-label">Browse</span></button>
                <small class="form-text text-muted">Select an uploaded MP4, WebM, or similar media file.</small>
            </dd>
        </dl>
    </toggle>

    <dl class="sparkDialog _tpl-box">
        <dt>Poster Image</dt>
        <dd>
            <input type="url" name="custom_poster" id="custom_poster" placeholder="https://example.com/poster.jpg">
            <button type="button" class="btn btn-secondary" onclick="openMediaPicker('custom_poster')"><i class="fa-solid fa-image btn-icon" aria-hidden="true"></i><span class="btn-label">Browse</span></button>
        </dd>
    </dl>

    <dl class="sparkDialog _tpl-box">
        <dt>Aspect Ratio</dt>
        <dd>
            <select name="custom_ratio" class="form-select">
                <option value="ratio-16x9" selected>16:9 (Widescreen)</option>
                <option value="ratio-4x3">4:3 (Standard)</option>
                <option value="ratio-1x1">1:1 (Square)</option>
                <option value="ratio-21x9">21:9 (Cinematic)</option>
            </select>
        </dd>
    </dl>

    <dl class="sparkDialog _tpl-box">
        <dt>Autoplay</dt>
        <dd class="align-options">
            <label><input type="radio" name="custom_autoplay" value="no" checked> Disabled</label>
            <label><input type="radio" name="custom_autoplay" value="yes"> Enabled (muted)</label>
        </dd>
    </dl>

    <dl class="sparkDialog _tpl-box">
        <dt>Loop</dt>
        <dd class="align-options">
            <label><input type="radio" name="custom_loop" value="no" checked> Disabled</label>
            <label><input type="radio" name="custom_loop" value="yes"> Enabled</label>
        </dd>
    </dl>

    <dl class="sparkDialog _tpl-box">
        <dt>Caption</dt>
        <dd>
            <textarea name="custom_caption" rows="2" placeholder="Describe the video for additional context"></textarea>
        </dd>
    </dl>
</templateSetting>
<figure id="<?= $videoBlockId ?>" class="video-wrapper mb-4" data-video-block data-video-source-type="{custom_source_type}" data-video-autoplay="{custom_autoplay}" data-video-loop="{custom_loop}">
    <div class="ratio ratio-16x9 {custom_ratio}" data-video-container>
        <video class="video-player w-100" controls preload="metadata" playsinline>
            Sorry, your browser doesn&#039;t support embedded videos.
        </video>
    </div>
    <figcaption class="video-caption text-muted small mt-2" data-video-caption hidden></figcaption>
    <div class="video-config d-none" aria-hidden="true">
        <span data-video-external>{custom_external_url}</span>
        <span data-video-upload>{custom_upload_url}</span>
        <span data-video-poster>{custom_poster}</span>
        <span data-video-caption-source>{custom_caption}</span>
    </div>
    <p class="video-status text-muted small mt-2 d-none" data-video-status>Provide a valid video source to play this media.</p>
</figure>
<script>
(function () {
    'use strict';
    const scriptEl = document.currentScript;
    const wrapper = scriptEl ? scriptEl.previousElementSibling : null;
    if (!wrapper || !wrapper.matches('[data-video-block]')) {
        return;
    }

    const normalizeFlag = (value) => {
        if (!value) return false;
        const val = String(value).toLowerCase();
        return val === 'yes' || val === 'true' || val === '1';
    };

    const sanitizeUrl = (value) => {
        if (!value) return '';
        const raw = String(value).trim();
        if (!raw || /^javascript:/i.test(raw)) {
            return '';
        }
        try {
            const parsed = new URL(raw, window.location.origin);
            const scheme = (parsed.protocol || '').replace(':', '').toLowerCase();
            if (scheme === 'http' || scheme === 'https') {
                return parsed.href;
            }
            if (parsed.origin === 'null') {
                // Relative URL, ensure it contains safe characters
                if (/^[./A-Za-z0-9@:%_+~#?&=\-]+(\/[./A-Za-z0-9@:%_+~#?&=\-]+)*$/.test(raw)) {
                    return raw;
                }
            }
        } catch (err) {
            if (/^[./A-Za-z0-9@:%_+~#?&=\-]+(\/[./A-Za-z0-9@:%_+~#?&=\-]+)*$/.test(raw)) {
                return raw;
            }
        }
        return '';
    };

    const getConfigText = (selector) => {
        const host = wrapper.querySelector('.video-config');
        if (!host) return '';
        const el = host.querySelector(selector);
        return el ? el.textContent || '' : '';
    };

    const videoEl = wrapper.querySelector('video');
    const statusEl = wrapper.querySelector('[data-video-status]');
    const captionEl = wrapper.querySelector('[data-video-caption]');

    const sourceType = (wrapper.dataset.videoSourceType || 'external').toLowerCase();
    const autoplayEnabled = normalizeFlag(wrapper.dataset.videoAutoplay);
    const loopEnabled = normalizeFlag(wrapper.dataset.videoLoop);
    const externalUrl = getConfigText('[data-video-external]');
    const uploadUrl = getConfigText('[data-video-upload]');
    const posterUrl = getConfigText('[data-video-poster]');
    const captionText = (getConfigText('[data-video-caption-source]') || '').trim();

    const sourceUrl = sanitizeUrl(sourceType === 'upload' ? uploadUrl : externalUrl);
    const safePoster = sanitizeUrl(posterUrl);

    if (!videoEl) {
        return;
    }

    // Reset previous state
    videoEl.pause();
    videoEl.removeAttribute('src');
    videoEl.querySelectorAll('source').forEach((src) => src.remove());
    videoEl.removeAttribute('poster');
    videoEl.removeAttribute('autoplay');
    videoEl.removeAttribute('muted');
    videoEl.removeAttribute('loop');

    if (captionEl) {
        if (captionText) {
            captionEl.textContent = captionText;
            captionEl.hidden = false;
        } else {
            captionEl.textContent = '';
            captionEl.hidden = true;
        }
    }

    if (sourceUrl) {
        const source = document.createElement('source');
        source.setAttribute('src', sourceUrl);
        videoEl.appendChild(source);
        if (safePoster) {
            videoEl.setAttribute('poster', safePoster);
        }
        if (autoplayEnabled) {
            videoEl.setAttribute('autoplay', '');
            videoEl.setAttribute('muted', '');
            videoEl.setAttribute('playsinline', '');
        } else {
            videoEl.removeAttribute('playsinline');
        }
        if (loopEnabled) {
            videoEl.setAttribute('loop', '');
        }
        if (statusEl) {
            statusEl.classList.add('d-none');
        }
        wrapper.classList.remove('video-wrapper--invalid');
        videoEl.load();
    } else {
        if (statusEl) {
            statusEl.classList.remove('d-none');
        }
        wrapper.classList.add('video-wrapper--invalid');
    }
})();
</script>
