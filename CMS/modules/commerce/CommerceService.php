<?php
// File: CommerceService.php

require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/helpers.php';

class CommerceService
{
    private string $dataFile;

    public function __construct(?string $dataFile = null)
    {
        $this->dataFile = $dataFile ?? __DIR__ . '/../../data/commerce.json';
    }

    /**
     * Build the context array used by the commerce dashboard template.
     *
     * @return array<string,mixed>
     */
    public function buildDashboardContext(): array
    {
        $rawData = $this->loadData();

        $summary = $this->expectArray($rawData['summary'] ?? []);
        $alerts = $this->expectArray($rawData['alerts'] ?? []);
        $catalog = $this->expectArray($rawData['catalog'] ?? []);
        $orders = $this->expectArray($rawData['orders'] ?? []);
        $customers = $this->expectArray($rawData['customers'] ?? []);
        $reports = $this->expectArray($rawData['reports'] ?? []);
        $commerceSettings = $this->expectArray($rawData['settings'] ?? []);

        $currency = $this->resolveCurrency($commerceSettings);
        $currencySymbol = $this->getCurrencySymbol($currency);

        $catalogContext = $this->prepareCatalog($catalog, $commerceSettings, $currencySymbol);
        $categories = commerce_prepare_categories($rawData['categories'] ?? [], $catalogContext['productsForCategories']);
        $categoryContext = $this->prepareCategories($categories);
        $ordersContext = $this->prepareOrders($orders, $currencySymbol);
        $customersContext = $this->prepareCustomers($customers, $currencySymbol);
        $settingsContext = $this->prepareSettings($commerceSettings, $currency, $currencySymbol);
        $channels = $this->prepareChannels($reports['top_channels'] ?? []);
        $salesTrend = $this->prepareSalesTrend($reports['sales_trend'] ?? [], $currencySymbol);

        $dataset = [
            'summary' => $summary,
            'alerts' => $alerts,
            'catalog' => $catalogContext['rawCatalog'],
            'categories' => $categories,
            'orders' => $orders,
            'customers' => $customers,
            'reports' => $reports,
            'settings' => $commerceSettings,
            'currency' => $currency,
        ];

        $datasetJson = json_encode($dataset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($datasetJson === false) {
            $datasetJson = '[]';
        }

        return [
            'currency' => $currency,
            'currencySymbol' => $currencySymbol,
            'heroMetrics' => $this->prepareHeroMetrics($summary, $currencySymbol),
            'workspaces' => $this->prepareWorkspaces(),
            'dashboard' => [
                'channels' => $channels,
                'alerts' => $this->prepareAlerts($alerts),
                'salesTrend' => $salesTrend,
            ],
            'catalog' => [
                'categories' => $categoryContext,
                'stats' => $catalogContext['stats'],
                'products' => $catalogContext['rows'],
            ],
            'orders' => $ordersContext,
            'customers' => $customersContext,
            'settings' => $settingsContext,
            'datasetJson' => $datasetJson,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function loadData(): array
    {
        $data = read_json_file($this->dataFile);
        return is_array($data) ? $data : [];
    }

    /**
     * @param mixed $value
     * @return array<int|string,mixed>
     */
    private function expectArray($value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string,mixed> $commerceSettings
     */
    private function resolveCurrency(array $commerceSettings): string
    {
        $currency = isset($commerceSettings['currency']) && is_string($commerceSettings['currency'])
            ? strtoupper($commerceSettings['currency'])
            : 'USD';

        return $currency !== '' ? $currency : 'USD';
    }

    private function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AUD' => 'A$',
            'CAD' => 'C$',
        ];

        return $symbols[$currency] ?? '';
    }

    /**
     * @param array<string,mixed> $summary
     * @return array<int,array<string,string>>
     */
    private function prepareHeroMetrics(array $summary, string $currencySymbol): array
    {
        return [
            [
                'label' => 'Total revenue',
                'value' => $this->formatCurrency($summary['total_revenue'] ?? 0, $currencySymbol),
                'meta' => '30-day view',
            ],
            [
                'label' => 'Orders',
                'value' => $this->formatNumber($summary['orders'] ?? 0),
                'meta' => 'Open in fulfilment',
            ],
            [
                'label' => 'Average order value',
                'value' => $this->formatCurrency($summary['average_order_value'] ?? 0, $currencySymbol),
                'meta' => 'Blended across channels',
            ],
            [
                'label' => 'Conversion rate',
                'value' => $this->formatPercent($summary['conversion_rate'] ?? 0),
                'meta' => 'Storefront sessions',
            ],
            [
                'label' => 'Repeat purchase',
                'value' => $this->formatPercent($summary['repeat_purchase_rate'] ?? 0),
                'meta' => 'Returning customers',
            ],
            [
                'label' => 'Refund rate',
                'value' => $this->formatPercent($summary['refund_rate'] ?? 0),
                'meta' => '30-day average',
            ],
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function prepareWorkspaces(): array
    {
        $workspaces = [
            'dashboard' => 'Commerce Dashboard',
            'catalog' => 'Product Catalog',
            'orders' => 'Orders',
            'customers' => 'Customers',
            'settings' => 'Commerce Settings',
        ];

        $prepared = [];
        $isFirst = true;
        foreach ($workspaces as $key => $label) {
            $prepared[] = [
                'key' => $key,
                'label' => $label,
                'isActive' => $isFirst,
            ];
            $isFirst = false;
        }

        return $prepared;
    }

    /**
     * @param array<int,array<string,mixed>> $alerts
     * @return array<int,array<string,string>>
     */
    private function prepareAlerts(array $alerts): array
    {
        $prepared = [];
        foreach ($alerts as $alert) {
            if (!is_array($alert)) {
                continue;
            }

            $type = isset($alert['type']) ? (string) $alert['type'] : 'general';
            $severity = strtolower((string) ($alert['severity'] ?? 'info'));
            $message = isset($alert['message']) ? (string) $alert['message'] : '';

            $prepared[] = [
                'type' => $type,
                'severity' => $severity,
                'severityLabel' => ucfirst($severity),
                'message' => $message,
                'class' => 'commerce-alert-' . $severity,
            ];
        }

        return $prepared;
    }

    /**
     * @param array<int,array<string,mixed>> $channels
     * @return array<int,array<string,string|int>>
     */
    private function prepareChannels(array $channels): array
    {
        $prepared = [];
        $total = 0.0;

        foreach ($channels as $channel) {
            if (!is_array($channel)) {
                continue;
            }

            $percentage = isset($channel['percentage']) && is_numeric($channel['percentage'])
                ? (float) $channel['percentage']
                : 0.0;
            $total += $percentage;
        }

        if ($total <= 0) {
            $total = 100.0;
        }

        foreach ($channels as $channel) {
            if (!is_array($channel)) {
                continue;
            }

            $label = isset($channel['channel']) ? (string) $channel['channel'] : 'Unknown';
            $percentage = isset($channel['percentage']) && is_numeric($channel['percentage'])
                ? (float) $channel['percentage']
                : 0.0;
            $width = (int) round(min(100, max(0, ($percentage / $total) * 100)));

            $prepared[] = [
                'label' => $label,
                'slug' => commerce_slugify($label),
                'percentageLabel' => $this->formatPercent($percentage),
                'barWidth' => $width,
            ];
        }

        return $prepared;
    }

    /**
     * @param array<int,array<string,mixed>> $trend
     * @return array<int,array<string,string|int>>
     */
    private function prepareSalesTrend(array $trend, string $currencySymbol): array
    {
        $prepared = [];
        $maxRevenue = 0.0;
        foreach ($trend as $point) {
            if (!is_array($point)) {
                continue;
            }

            if (isset($point['revenue']) && is_numeric($point['revenue'])) {
                $maxRevenue = max($maxRevenue, (float) $point['revenue']);
            }
        }

        if ($maxRevenue <= 0) {
            $maxRevenue = 1.0;
        }

        foreach ($trend as $point) {
            if (!is_array($point)) {
                continue;
            }

            $label = isset($point['label']) ? (string) $point['label'] : '';
            $revenue = isset($point['revenue']) && is_numeric($point['revenue'])
                ? (float) $point['revenue']
                : 0.0;
            $height = (int) round(min(100, max(0, ($revenue / $maxRevenue) * 100)));

            $prepared[] = [
                'label' => $label,
                'valueLabel' => $this->formatCurrency($revenue, $currencySymbol),
                'height' => $height,
                'ariaLabel' => trim($label . ' revenue ' . $this->formatCurrency($revenue, $currencySymbol)),
            ];
        }

        return $prepared;
    }

    /**
     * @param array<int,array<string,mixed>> $catalog
     * @param array<string,mixed> $commerceSettings
     * @return array{
     *     rows: array<int,array<string,mixed>>,
     *     stats: array<string,mixed>,
     *     productsForCategories: array<int,array<string,mixed>>,
     *     rawCatalog: array<int,array<string,mixed>>
     * }
     */
    private function prepareCatalog(array $catalog, array $commerceSettings, string $currencySymbol): array
    {
        $rows = [];
        $rawCatalog = [];
        $productsForCategories = [];
        $totalProducts = 0;
        $activeProducts = 0;
        $lowInventoryCount = 0;
        $threshold = isset($commerceSettings['low_inventory_threshold']) && is_numeric($commerceSettings['low_inventory_threshold'])
            ? (int) $commerceSettings['low_inventory_threshold']
            : 0;

        foreach ($catalog as $product) {
            if (!is_array($product)) {
                continue;
            }

            $sku = (string) ($product['sku'] ?? '');
            $name = (string) ($product['name'] ?? 'Untitled product');
            $category = (string) ($product['category'] ?? 'Uncategorised');
            $price = $this->formatCurrency($product['price'] ?? 0, $currencySymbol);
            $inventoryRaw = isset($product['inventory']) && is_numeric($product['inventory'])
                ? (int) $product['inventory']
                : 0;
            $inventory = $inventoryRaw;
            $status = (string) ($product['status'] ?? 'Unknown');
            $visibility = (string) ($product['visibility'] ?? 'Unknown');
            $updated = (string) ($product['updated'] ?? '');
            $featuredImage = (string) ($product['featured_image'] ?? '');

            $galleryList = [];
            if (isset($product['images'])) {
                if (is_array($product['images'])) {
                    foreach ($product['images'] as $imageUrl) {
                        if (is_string($imageUrl)) {
                            $imageUrl = trim($imageUrl);
                            if ($imageUrl !== '') {
                                $galleryList[] = $imageUrl;
                            }
                        }
                    }
                } elseif (is_string($product['images'])) {
                    $lines = preg_split('/\r\n|\r|\n/', $product['images']);
                    if ($lines !== false) {
                        foreach ($lines as $imageUrl) {
                            $imageUrl = trim((string) $imageUrl);
                            if ($imageUrl !== '') {
                                $galleryList[] = $imageUrl;
                            }
                        }
                    }
                }
            }

            if ($featuredImage === '' && !empty($galleryList)) {
                $featuredImage = $galleryList[0];
            }

            $galleryCount = count($galleryList);
            if ($galleryCount === 1) {
                $galleryLabel = '1 gallery image';
            } elseif ($galleryCount > 1) {
                $galleryLabel = $galleryCount . ' gallery images';
            } else {
                $galleryLabel = 'No gallery images';
            }

            $statusClass = $this->badgeClass($status);

            $rows[] = [
                'sku' => $sku,
                'name' => $name,
                'category' => $category,
                'categorySlug' => commerce_slugify($category),
                'price' => $price,
                'inventory' => $inventory,
                'status' => $status,
                'statusClass' => $statusClass,
                'statusKey' => strtolower($status),
                'visibility' => $visibility,
                'visibilityKey' => strtolower($visibility),
                'updated' => $updated,
                'featuredImage' => $featuredImage,
                'hasFeaturedImage' => $featuredImage !== '',
                'galleryLabel' => $galleryLabel,
            ];

            $rawCatalog[] = $product;
            $productsForCategories[] = ['category' => $category];
            $totalProducts++;

            if (strtolower($status) === 'active') {
                $activeProducts++;
            }

            if ($threshold > 0 && $inventoryRaw <= $threshold) {
                $lowInventoryCount++;
            }
        }

        $stats = [
            'totalProducts' => $this->formatNumber($totalProducts),
            'activeProducts' => $this->formatNumber($activeProducts),
            'lowInventoryThreshold' => $threshold,
            'showLowInventory' => $threshold > 0,
            'lowInventoryCount' => $this->formatNumber($lowInventoryCount),
        ];

        return [
            'rows' => $rows,
            'stats' => $stats,
            'productsForCategories' => $productsForCategories,
            'rawCatalog' => $rawCatalog,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $categories
     * @return array<string,mixed>
     */
    private function prepareCategories(array $categories): array
    {
        $count = count($categories);
        $label = $count === 1 ? 'category' : 'categories';

        $options = [];
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }

            $options[] = [
                'id' => (string) ($category['id'] ?? ''),
                'name' => (string) ($category['name'] ?? ''),
                'slug' => (string) ($category['slug'] ?? ''),
            ];
        }

        return [
            'countLabel' => $label,
            'countFormatted' => $this->formatNumber($count),
            'list' => $options,
            'hasCategories' => $count > 0,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $orders
     * @return array<string,mixed>
     */
    private function prepareOrders(array $orders, string $currencySymbol): array
    {
        $rows = [];
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $id = (string) ($order['id'] ?? '');
            $customer = (string) ($order['customer'] ?? '');
            $channel = (string) ($order['channel'] ?? '');
            $status = (string) ($order['status'] ?? '');
            $total = $this->formatCurrency($order['total'] ?? 0, $currencySymbol);
            $placed = (string) ($order['placed'] ?? '');
            $fulfillment = (string) ($order['fulfillment'] ?? '');

            $rows[] = [
                'id' => $id,
                'customer' => $customer,
                'channel' => $channel,
                'status' => $status,
                'statusClass' => $this->badgeClass($status),
                'total' => $total,
                'placed' => $placed,
                'fulfillment' => $fulfillment,
                'filters' => [
                    'status' => strtolower($status),
                    'channel' => strtolower($channel),
                    'customer' => strtolower($customer),
                    'order' => strtolower($id),
                ],
            ];
        }

        return ['rows' => $rows];
    }

    /**
     * @param array<int,array<string,mixed>> $customers
     * @return array<string,mixed>
     */
    private function prepareCustomers(array $customers, string $currencySymbol): array
    {
        $rows = [];
        $segments = [];
        foreach ($customers as $customer) {
            if (!is_array($customer)) {
                continue;
            }

            $name = (string) ($customer['name'] ?? '');
            $email = (string) ($customer['email'] ?? '');
            $segment = (string) ($customer['segment'] ?? '');
            $orders = isset($customer['orders']) && is_numeric($customer['orders'])
                ? (int) $customer['orders']
                : 0;
            $ltv = $this->formatCurrency($customer['lifetime_value'] ?? 0, $currencySymbol);
            $lastOrder = (string) ($customer['last_order'] ?? '');
            $status = (string) ($customer['status'] ?? '');

            if ($segment !== '') {
                $segments[] = $segment;
            }

            $rows[] = [
                'name' => $name,
                'email' => $email,
                'segment' => $segment,
                'orders' => $orders,
                'lifetimeValue' => $ltv,
                'lastOrder' => $lastOrder,
                'status' => $status,
                'statusClass' => $this->badgeClass($status),
                'filters' => [
                    'segment' => strtolower($segment),
                    'status' => strtolower($status),
                    'name' => strtolower($name),
                    'email' => strtolower($email),
                ],
            ];
        }

        $segments = array_values(array_unique($segments));
        sort($segments, SORT_NATURAL | SORT_FLAG_CASE);

        $segmentOptions = [];
        foreach ($segments as $segment) {
            $segmentOptions[] = [
                'value' => strtolower($segment),
                'label' => $segment,
            ];
        }

        return [
            'rows' => $rows,
            'segments' => $segmentOptions,
        ];
    }

    /**
     * @param array<string,mixed> $commerceSettings
     * @return array<string,mixed>
     */
    private function prepareSettings(array $commerceSettings, string $currency, string $currencySymbol): array
    {
        $storefront = [
            [
                'label' => 'Status',
                'value' => (string) ($commerceSettings['storefront_status'] ?? 'Offline'),
                'isBadge' => true,
                'badgeClass' => 'status-badge status-good',
            ],
            [
                'label' => 'Currency',
                'value' => $currency,
            ],
            [
                'label' => 'Theme',
                'value' => (string) ($commerceSettings['storefront_theme'] ?? 'Default'),
            ],
            [
                'label' => 'Primary language',
                'value' => (string) ($commerceSettings['default_language'] ?? 'Not set'),
            ],
            [
                'label' => 'Low inventory threshold',
                'value' => (string) ($commerceSettings['low_inventory_threshold'] ?? 0) . ' units',
            ],
            [
                'label' => 'Default tax rate',
                'value' => $this->formatPercent($commerceSettings['default_tax_rate'] ?? 0),
            ],
        ];

        $toggleMap = [
            'express_checkout' => 'Express checkout',
            'allow_guest_checkout' => 'Allow guest checkout',
            'fraud_protection' => 'Fraud protection',
            'auto_apply_discounts' => 'Auto-apply discounts',
            'address_verification' => 'Address verification',
        ];

        $toggles = [];
        foreach ($toggleMap as $key => $label) {
            $toggles[] = [
                'key' => $key,
                'label' => $label,
                'checked' => !empty($commerceSettings[$key]),
            ];
        }

        $paymentMethods = [];
        $rawPaymentMethods = $this->expectArray($commerceSettings['payment_methods'] ?? []);
        foreach ($rawPaymentMethods as $method) {
            if (!is_array($method)) {
                continue;
            }

            $label = (string) ($method['label'] ?? '');
            $enabled = !empty($method['enabled']);
            $paymentMethods[] = [
                'label' => $label,
                'status' => $enabled ? 'Enabled' : 'Disabled',
                'statusClass' => $enabled ? 'status-badge status-good' : 'status-badge status-warning',
            ];
        }

        $fulfillmentWindows = [];
        $rawWindows = $this->expectArray($commerceSettings['fulfillment_windows'] ?? []);
        foreach ($rawWindows as $window) {
            if (!is_array($window)) {
                continue;
            }

            $fulfillmentWindows[] = [
                'name' => (string) ($window['name'] ?? ''),
                'sla' => (string) ($window['sla'] ?? ''),
            ];
        }

        $shippingPartners = [];
        $rawPartners = $this->expectArray($commerceSettings['shipping_partners'] ?? []);
        if (empty($rawPartners)) {
            $shippingPartners[] = [
                'name' => 'Partners',
                'status' => 'No shipping partners configured',
                'statusClass' => '',
                'isPlaceholder' => true,
            ];
        }

        foreach ($rawPartners as $partner) {
            if (!is_array($partner)) {
                continue;
            }

            $name = (string) ($partner['name'] ?? '');
            $status = (string) ($partner['status'] ?? 'Unavailable');
            $statusClass = $this->resolvePartnerBadgeClass($status);

            $shippingPartners[] = [
                'name' => $name,
                'status' => $status !== '' ? $status : 'Unavailable',
                'statusClass' => $statusClass,
                'isPlaceholder' => false,
            ];
        }

        $returnPolicy = $this->expectArray($commerceSettings['return_policy'] ?? []);
        $windowDays = isset($returnPolicy['window_days']) && is_numeric($returnPolicy['window_days'])
            ? (int) $returnPolicy['window_days']
            : null;
        $restockingFee = (string) ($returnPolicy['restocking_fee'] ?? '');
        $returnShipping = (string) ($returnPolicy['return_shipping'] ?? '');

        $returnPolicyList = [
            [
                'label' => 'Return window',
                'value' => $windowDays !== null ? $windowDays . ' days' : 'Not specified',
            ],
            [
                'label' => 'Restocking fee',
                'value' => $restockingFee !== '' ? $restockingFee : 'None',
            ],
            [
                'label' => 'Return shipping',
                'value' => $returnShipping !== '' ? $returnShipping : 'Not specified',
            ],
        ];

        return [
            'storefront' => $storefront,
            'toggles' => $toggles,
            'paymentMethods' => $paymentMethods,
            'fulfillmentWindows' => $fulfillmentWindows,
            'shippingPartners' => $shippingPartners,
            'returnPolicy' => $returnPolicyList,
        ];
    }

    private function resolvePartnerBadgeClass(string $status): string
    {
        $normalized = strtolower($status);
        if ($normalized === 'primary' || $normalized === 'active') {
            return 'status-badge status-good';
        }
        if ($normalized === 'backup' || $normalized === 'limited') {
            return 'status-badge status-warning';
        }
        if ($normalized === 'paused' || $normalized === 'suspended') {
            return 'status-badge status-critical';
        }
        if ($normalized === 'international') {
            return 'status-badge status-info';
        }

        return 'status-badge';
    }

    private function formatCurrency($value, string $symbol): string
    {
        if (!is_numeric($value)) {
            return $symbol . '0.00';
        }

        $amount = number_format((float) $value, 2, '.', ',');
        return $symbol !== '' ? $symbol . $amount : $amount;
    }

    private function formatPercent($value): string
    {
        if (!is_numeric($value)) {
            return '0%';
        }

        $formatted = number_format((float) $value, 1, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        if ($formatted === '') {
            $formatted = '0';
        }

        return $formatted . '%';
    }

    private function formatNumber($value): string
    {
        if (!is_numeric($value)) {
            return '0';
        }

        return number_format((float) $value, 0, '.', ',');
    }

    private function badgeClass(string $status): string
    {
        $normalized = strtolower($status);
        switch ($normalized) {
            case 'active':
            case 'fulfilled':
            case 'vip':
                return 'status-badge status-good';
            case 'processing':
            case 'ready for pickup':
            case 'support':
            case 'new':
            case 'loyal':
                return 'status-badge status-warning';
            case 'refund requested':
            case 'pending payment':
            case 'dormant':
            case 'attention':
            case 'backorder':
            case 'restock':
                return 'status-badge status-critical';
            case 'preorder':
                return 'status-badge status-info';
            default:
                return 'status-badge';
        }
    }
}
