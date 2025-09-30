<?php
// File: helpers.php

/**
 * Create a URL-friendly slug from a category or status label.
 */
function commerce_slugify(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value ?? '');
    $value = trim($value, '-');
    if ($value === '') {
        return uniqid('category_', false);
    }
    return $value;
}

/**
 * Normalise the categories collection ensuring IDs, names, and slugs are unique.
 * Also ensures categories referenced by products exist in the list.
 *
 * @param array $rawCategories
 * @param array $catalog
 * @return array
 */
function commerce_prepare_categories($rawCategories, $catalog = []) {
    $normalized = [];
    $usedSlugs = [];

    if (is_array($rawCategories)) {
        foreach ($rawCategories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $name = isset($category['name']) ? trim((string) $category['name']) : '';
            if ($name === '') {
                continue;
            }
            $slug = isset($category['slug']) ? (string) $category['slug'] : commerce_slugify($name);
            $slug = $slug === '' ? commerce_slugify($name) : commerce_slugify($slug);
            $id = isset($category['id']) && $category['id'] !== '' ? (string) $category['id'] : $slug;

            $uniqueId = $id;
            $suffix = 1;
            while (isset($normalized[$uniqueId])) {
                $uniqueId = $id . '-' . $suffix;
                $suffix++;
            }

            $uniqueSlug = $slug;
            $slugSuffix = 1;
            while (isset($usedSlugs[$uniqueSlug])) {
                $uniqueSlug = $slug . '-' . $slugSuffix;
                $slugSuffix++;
            }

            $normalized[$uniqueId] = [
                'id' => $uniqueId,
                'name' => $name,
                'slug' => $uniqueSlug,
            ];
            $usedSlugs[$uniqueSlug] = true;
        }
    }

    if (is_array($catalog)) {
        foreach ($catalog as $product) {
            if (!is_array($product)) {
                continue;
            }
            $name = isset($product['category']) ? trim((string) $product['category']) : '';
            if ($name === '') {
                continue;
            }
            $slug = commerce_slugify($name);
            if (isset($usedSlugs[$slug])) {
                continue;
            }
            $idBase = $slug !== '' ? $slug : uniqid('category_', false);
            $uniqueId = $idBase;
            $suffix = 1;
            while (isset($normalized[$uniqueId])) {
                $uniqueId = $idBase . '-' . $suffix;
                $suffix++;
            }
            $normalized[$uniqueId] = [
                'id' => $uniqueId,
                'name' => $name,
                'slug' => $slug !== '' ? $slug : $uniqueId,
            ];
            if ($slug !== '') {
                $usedSlugs[$slug] = true;
            }
        }
    }

    $categories = array_values($normalized);
    usort($categories, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return $categories;
}
