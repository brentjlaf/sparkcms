<?php
require_once __DIR__ . '/../../includes/sanitize.php';

class MenuBuilder
{
    /**
     * Normalize raw menu items into the persisted structure.
     *
     * @param mixed $rawItems
     * @param array $pages
     * @return array
     */
    public function normalizeItems($rawItems, array $pages): array
    {
        $pageIndex = $this->indexPages($pages);
        return $this->normalizeLevel($rawItems, $pageIndex);
    }

    private function normalizeLevel($rawItems, array $pageIndex): array
    {
        if (!is_array($rawItems)) {
            return [];
        }

        $items = [];
        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                continue;
            }

            $type = strtolower(sanitize_text($rawItem['type'] ?? 'custom'));
            $label = sanitize_text($rawItem['label'] ?? '');
            $newTab = !empty($rawItem['new_tab']);

            if ($type === 'page') {
                $pageId = isset($rawItem['page']) ? (int)$rawItem['page'] : 0;
                if ($pageId === 0 || !isset($pageIndex[$pageId]['slug'])) {
                    continue;
                }

                $slug = (string)$pageIndex[$pageId]['slug'];
                if ($slug === '') {
                    continue;
                }

                $item = [
                    'label' => $label !== '' ? $label : $slug,
                    'type' => 'page',
                    'page' => $pageId,
                    'link' => '/' . ltrim($slug, '/'),
                    'new_tab' => $newTab,
                ];
            } else {
                $link = isset($rawItem['link']) ? sanitize_url($rawItem['link']) : '';
                if ($link === '') {
                    continue;
                }

                $item = [
                    'label' => $label !== '' ? $label : $link,
                    'type' => 'custom',
                    'link' => $link,
                    'new_tab' => $newTab,
                ];
            }

            if (!empty($rawItem['children'])) {
                $children = $this->normalizeLevel($rawItem['children'], $pageIndex);
                if (!empty($children)) {
                    $item['children'] = $children;
                }
            }

            $items[] = $item;
        }

        return $items;
    }

    private function indexPages(array $pages): array
    {
        $index = [];
        foreach ($pages as $page) {
            if (is_array($page) && isset($page['id'])) {
                $index[(int)$page['id']] = $page;
            }
        }

        return $index;
    }
}
