<?php

require_once __DIR__ . '/../../includes/data.php';

class MediaLibrary
{
    public const DEFAULT_SORT = 'custom';
    public const ALLOWED_SORTS = ['custom', 'name', 'date', 'type', 'size', 'tags', 'dimensions'];

    private string $mediaFile;
    private string $rootDir;
    private string $uploadsDir;
    private ?array $mediaData = null;

    public function __construct(string $mediaFile, string $rootDir)
    {
        $this->mediaFile = $mediaFile;
        $this->rootDir = rtrim($rootDir, '/');
        $this->uploadsDir = $this->rootDir . '/uploads';
        $this->ensureUploadStructure();
    }

    public function listMedia(array $filters = []): array
    {
        $media = $this->loadMedia();

        usort($media, function ($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        $query = strtolower($filters['query'] ?? '');
        $folder = $filters['folder'] ?? '';
        $sort = strtolower($filters['sort'] ?? self::DEFAULT_SORT);
        if (!in_array($sort, self::ALLOWED_SORTS, true)) {
            $sort = self::DEFAULT_SORT;
        }
        $order = strtolower($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $limit = isset($filters['limit']) ? max(0, (int) $filters['limit']) : 0;
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;

        $results = array_filter($media, function ($item) use ($query, $folder) {
            if ($folder !== '' && ($item['folder'] ?? '') !== $folder) {
                return false;
            }
            if ($query !== '') {
                $nameMatch = isset($item['name']) && stripos($item['name'], $query) !== false;
                $tagsMatch = false;
                if (isset($item['tags'])) {
                    $tags = is_array($item['tags']) ? $item['tags'] : [$item['tags']];
                    $tagsMatch = stripos(implode(',', $tags), $query) !== false;
                }
                if (!$nameMatch && !$tagsMatch) {
                    return false;
                }
            }
            return true;
        });

        $results = array_map(function ($item) {
            return $this->enrichMediaItem($item);
        }, array_values($results));

        $results = $this->sortResults($results, $sort);

        if ($order === 'desc') {
            $results = array_reverse($results);
        }

        $results = array_values($results);

        $totalCount = count($results);
        $totalBytes = array_reduce($results, function ($carry, $item) {
            return $carry + (int) ($item['size'] ?? 0);
        }, 0);

        $lastModified = 0;
        foreach ($results as $resultItem) {
            if (isset($resultItem['modified_at']) && $resultItem['modified_at'] > $lastModified) {
                $lastModified = $resultItem['modified_at'];
            }
        }

        $pagedResults = $results;
        if ($limit > 0) {
            $pagedResults = array_slice($results, $offset, $limit);
        } elseif ($offset > 0) {
            $pagedResults = array_slice($results, $offset);
        }

        return [
            'media' => array_values($pagedResults),
            'total' => $totalCount,
            'total_size' => $totalBytes,
            'last_modified' => $lastModified,
        ];
    }

    public function listFolders(): array
    {
        $media = $this->loadMedia();
        $directories = glob($this->uploadsDir . '/*');
        if ($directories === false) {
            $directories = [];
        }
        $directories = array_filter($directories, 'is_dir');
        $folders = [];

        foreach ($directories as $dir) {
            $name = basename($dir);
            $folders[] = [
                'name' => $name,
                'thumbnail' => $this->resolveFolderThumbnail($name, $dir, $media),
            ];
        }

        return $folders;
    }

    private function ensureUploadStructure(): void
    {
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0777, true);
        }
        $defaultDir = $this->uploadsDir . '/general';
        if (!is_dir($defaultDir)) {
            mkdir($defaultDir, 0777, true);
        }
    }

    private function loadMedia(): array
    {
        if ($this->mediaData === null) {
            $this->mediaData = read_json_file($this->mediaFile);
            if (!is_array($this->mediaData)) {
                $this->mediaData = [];
            }
        }
        return $this->mediaData;
    }

    private function enrichMediaItem(array $item): array
    {
        $relativePath = $item['file'] ?? '';
        if ($relativePath === '') {
            return $item;
        }
        $path = $this->rootDir . '/' . ltrim($relativePath, '/');
        if (is_file($path)) {
            $item['modified_at'] = filemtime($path);
            if (($item['type'] ?? '') === 'images') {
                $info = @getimagesize($path);
                if ($info) {
                    $item['width'] = $info[0];
                    $item['height'] = $info[1];
                }
            }
        }
        return $item;
    }

    private function sortResults(array $results, string $sort): array
    {
        switch ($sort) {
            case 'name':
                usort($results, function ($a, $b) {
                    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                });
                break;
            case 'date':
                usort($results, function ($a, $b) {
                    $aTime = $a['modified_at'] ?? ($a['uploaded_at'] ?? 0);
                    $bTime = $b['modified_at'] ?? ($b['uploaded_at'] ?? 0);
                    return $aTime <=> $bTime;
                });
                break;
            case 'type':
                usort($results, function ($a, $b) {
                    return strcasecmp($a['type'] ?? '', $b['type'] ?? '');
                });
                break;
            case 'size':
                usort($results, function ($a, $b) {
                    return ((int) ($a['size'] ?? 0)) <=> ((int) ($b['size'] ?? 0));
                });
                break;
            case 'tags':
                usort($results, function ($a, $b) {
                    $aTags = isset($a['tags']) ? implode(',', (array) $a['tags']) : '';
                    $bTags = isset($b['tags']) ? implode(',', (array) $b['tags']) : '';
                    return strcasecmp($aTags, $bTags);
                });
                break;
            case 'dimensions':
                usort($results, function ($a, $b) {
                    $aDim = ((int) ($a['width'] ?? 0)) * ((int) ($a['height'] ?? 0));
                    $bDim = ((int) ($b['width'] ?? 0)) * ((int) ($b['height'] ?? 0));
                    return $aDim <=> $bDim;
                });
                break;
            default:
                usort($results, function ($a, $b) {
                    return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
                });
        }

        return $results;
    }

    private function resolveFolderThumbnail(string $folderName, string $directory, array $media): ?string
    {
        foreach ($media as $item) {
            if (($item['folder'] ?? '') !== $folderName) {
                continue;
            }
            if (($item['type'] ?? '') !== 'images') {
                continue;
            }
            $thumb = $item['thumbnail'] ?? '';
            if ($thumb === '' && !empty($item['file'])) {
                $thumb = $item['file'];
            }
            if ($thumb !== '') {
                return $thumb;
            }
        }

        $patterns = [
            $directory . '/thumbs/*.{jpg,jpeg,png,gif,webp}',
            $directory . '/*.{jpg,jpeg,png,gif,webp}',
        ];

        foreach ($patterns as $pattern) {
            $matches = glob($pattern, GLOB_BRACE);
            if (!empty($matches)) {
                return $this->relativePath($matches[0]);
            }
        }

        return null;
    }

    private function relativePath(string $absolutePath): string
    {
        $normalizedRoot = rtrim($this->rootDir, '/') . '/';
        if (strpos($absolutePath, $normalizedRoot) === 0) {
            return ltrim(substr($absolutePath, strlen($normalizedRoot)), '/');
        }
        return ltrim($absolutePath, '/');
    }
}
