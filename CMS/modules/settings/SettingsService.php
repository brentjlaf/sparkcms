<?php
// File: CMS/modules/settings/SettingsService.php

require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/UploadHandler.php';

class SettingsService
{
    /** @var string */
    private $settingsFile;

    /** @var UploadHandler */
    private $uploadHandler;

    /** @var array<int, string> */
    private $allowedImageTypes = [
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** @var array<int, string> */
    private $allowedFaviconTypes;

    /** @var int */
    private $maxUploadSize;

    public function __construct(string $settingsFile, UploadHandler $uploadHandler, ?int $maxUploadSize = null)
    {
        $this->settingsFile = $settingsFile;
        $this->uploadHandler = $uploadHandler;
        $this->allowedFaviconTypes = array_merge(
            $this->allowedImageTypes,
            [
                'image/x-icon',
                'image/vnd.microsoft.icon',
                'image/svg+xml',
            ]
        );
        $this->maxUploadSize = $maxUploadSize ?? (5 * 1024 * 1024);
    }

    /**
     * Persist settings based on the provided form payload and files.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $files
     *
     * @return array<string, mixed>
     */
    public function save(array $form, array $files): array
    {
        $settings = read_json_file($this->settingsFile);
        if (!is_array($settings)) {
            $settings = [];
        }

        $settings['site_name'] = sanitize_text($form['site_name'] ?? ($settings['site_name'] ?? ''));
        $settings['tagline'] = sanitize_text($form['tagline'] ?? ($settings['tagline'] ?? ''));
        $settings['admin_email'] = sanitize_text($form['admin_email'] ?? ($settings['admin_email'] ?? ''));
        $settings['timezone'] = sanitize_text($form['timezone'] ?? ($settings['timezone'] ?? 'America/Denver'));
        if ($settings['timezone'] === '') {
            $settings['timezone'] = 'America/Denver';
        }
        $settings['googleAnalytics'] = sanitize_text($form['googleAnalytics'] ?? ($settings['googleAnalytics'] ?? ''));
        $settings['googleSearchConsole'] = sanitize_text($form['googleSearchConsole'] ?? ($settings['googleSearchConsole'] ?? ''));
        $settings['facebookPixel'] = sanitize_text($form['facebookPixel'] ?? ($settings['facebookPixel'] ?? ''));
        $settings['generateSitemap'] = !empty($form['generateSitemap']);
        $settings['allowIndexing'] = !empty($form['allowIndexing']);

        $this->uploadHandler->validateImageUpload($files['logo'] ?? [], $this->allowedImageTypes, $this->maxUploadSize, 'Logo');
        $this->uploadHandler->validateImageUpload($files['favicon'] ?? [], $this->allowedFaviconTypes, $this->maxUploadSize, 'Favicon');
        $this->uploadHandler->validateImageUpload($files['ogImage'] ?? [], $this->allowedImageTypes, $this->maxUploadSize, 'Open graph image');

        $settings = $this->syncSocialLinks($settings, $form);
        $settings = $this->syncOpenGraph($settings, $form, $files);
        $settings = $this->syncBrandAssets($settings, $form, $files);

        $settings['last_updated'] = date('c');

        if (!write_json_file($this->settingsFile, $settings)) {
            throw new RuntimeException('Unable to write settings file.');
        }

        set_site_settings_cache($settings);

        $openGraph = $settings['open_graph'] ?? [];

        return [
            'last_updated' => $settings['last_updated'],
            'logo' => $settings['logo'] ?? null,
            'favicon' => $settings['favicon'] ?? null,
            'open_graph' => [
                'image' => $openGraph['image'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $form
     * @param array<string, mixed> $files
     *
     * @return array<string, mixed>
     */
    private function syncBrandAssets(array $settings, array $form, array $files): array
    {
        $logoResult = $this->processAsset(
            $settings['logo'] ?? null,
            $files['logo'] ?? [],
            'logo',
            !empty($form['clear_logo'])
        );
        if ($logoResult['path'] !== null) {
            $settings['logo'] = $logoResult['path'];
        } else {
            unset($settings['logo']);
        }

        $faviconResult = $this->processAsset(
            $settings['favicon'] ?? null,
            $files['favicon'] ?? [],
            'favicon',
            !empty($form['clear_favicon']),
            ['gif', 'jpg', 'jpeg', 'png', 'webp', 'ico', 'svg'],
            'png'
        );
        if ($faviconResult['path'] !== null) {
            $settings['favicon'] = $faviconResult['path'];
        } else {
            unset($settings['favicon']);
        }

        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $form
     * @param array<string, mixed> $files
     *
     * @return array<string, mixed>
     */
    private function syncOpenGraph(array $settings, array $form, array $files): array
    {
        $openGraph = is_array($settings['open_graph'] ?? null) ? $settings['open_graph'] : [];
        $openGraph['title'] = sanitize_text($form['ogTitle'] ?? ($openGraph['title'] ?? ''));
        $openGraph['description'] = sanitize_text($form['ogDescription'] ?? ($openGraph['description'] ?? ''));

        $ogResult = $this->processAsset(
            $openGraph['image'] ?? null,
            $files['ogImage'] ?? [],
            'og',
            !empty($form['clear_og_image'])
        );
        if ($ogResult['path'] !== null) {
            $openGraph['image'] = $ogResult['path'];
        } else {
            unset($openGraph['image']);
        }

        $settings['open_graph'] = $openGraph;
        return $settings;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    private function syncSocialLinks(array $settings, array $form): array
    {
        $social = is_array($settings['social'] ?? null) ? $settings['social'] : [];
        $fields = [
            'facebook',
            'twitter',
            'instagram',
            'linkedin',
            'youtube',
            'tiktok',
            'pinterest',
            'snapchat',
            'reddit',
            'threads',
            'mastodon',
            'github',
            'dribbble',
            'twitch',
            'whatsapp',
        ];

        foreach ($fields as $field) {
            $social[$field] = sanitize_url($form[$field] ?? ($social[$field] ?? ''));
        }

        $settings['social'] = $social;
        return $settings;
    }

    /**
     * @param string|null $previous
     * @param array<string, mixed> $file
     *
     * @return array{path: string|null, uploaded: bool}
     */
    private function processAsset(
        ?string $previous,
        array $file,
        string $prefix,
        bool $cleared,
        array $allowedExtensions = [],
        ?string $fallbackExtension = null
    ): array {
        $path = $previous;
        $uploaded = false;

        $stored = $this->uploadHandler->storeUploadedFile($file, $prefix, $allowedExtensions, $fallbackExtension);
        if ($stored !== null) {
            if ($previous && $previous !== $stored) {
                $this->uploadHandler->deleteUploadFile($previous);
            }
            $path = $stored;
            $uploaded = true;
        } elseif ($cleared) {
            if ($previous) {
                $this->uploadHandler->deleteUploadFile($previous);
            }
            $path = null;
        }

        return [
            'path' => $path,
            'uploaded' => $uploaded,
        ];
    }
}
