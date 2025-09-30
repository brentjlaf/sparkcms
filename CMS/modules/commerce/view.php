<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_login();

$commerceFile = __DIR__ . '/../../data/commerce.json';
$commerceData = read_json_file($commerceFile);
$settings = get_site_settings();

$summary = isset($commerceData['summary']) && is_array($commerceData['summary']) ? $commerceData['summary'] : [];
$alerts = isset($commerceData['alerts']) && is_array($commerceData['alerts']) ? $commerceData['alerts'] : [];
$catalog = isset($commerceData['catalog']) && is_array($commerceData['catalog']) ? $commerceData['catalog'] : [];
$orders = isset($commerceData['orders']) && is_array($commerceData['orders']) ? $commerceData['orders'] : [];
$customers = isset($commerceData['customers']) && is_array($commerceData['customers']) ? $commerceData['customers'] : [];
$reports = isset($commerceData['reports']) && is_array($commerceData['reports']) ? $commerceData['reports'] : [];
$commerceSettings = isset($commerceData['settings']) && is_array($commerceData['settings']) ? $commerceData['settings'] : [];

$currency = isset($commerceSettings['currency']) && is_string($commerceSettings['currency']) ? strtoupper($commerceSettings['currency']) : 'USD';
$currencySymbols = [
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'AUD' => 'A$',
    'CAD' => 'C$',
];
$currencySymbol = isset($currencySymbols[$currency]) ? $currencySymbols[$currency] : '';

function commerce_format_currency($value, $symbol) {
    if (!is_numeric($value)) {
        return $symbol . '0.00';
    }
    $amount = number_format((float) $value, 2);
    return $symbol !== '' ? $symbol . $amount : $amount;
}

function commerce_format_percent($value) {
    if (!is_numeric($value)) {
        return '0%';
    }
    return rtrim(rtrim(number_format((float) $value, 1), '0'), '.') . '%';
}

function commerce_format_number($value) {
    if (!is_numeric($value)) {
        return '0';
    }
    return number_format((float) $value);
}

function commerce_badge_class($status) {
    $normalized = strtolower((string) $status);
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

$workspaces = [
    'dashboard' => 'Commerce Dashboard',
    'catalog' => 'Product Catalog',
    'orders' => 'Orders',
    'customers' => 'Customers',
    'reports' => 'Reports',
    'settings' => 'Commerce Settings',
];

$encodedDataset = json_encode([
    'summary' => $summary,
    'alerts' => $alerts,
    'catalog' => $catalog,
    'orders' => $orders,
    'customers' => $customers,
    'reports' => $reports,
    'settings' => $commerceSettings,
    'currency' => $currency,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<div class="content-section" id="commerce">
    <div class="commerce-shell a11y-dashboard">
        <header class="a11y-hero commerce-hero">
            <div class="a11y-hero-content commerce-hero-content">
                <div>
                    <span class="hero-eyebrow commerce-hero-eyebrow">Revenue Operations</span>
                    <h2 class="a11y-hero-title commerce-hero-title">Commerce Control Center</h2>
                    <p class="a11y-hero-subtitle commerce-hero-subtitle">
                        Track orders, catalogue health, and customer performance from one streamlined workspace.
                    </p>
                </div>
                <div class="a11y-hero-actions commerce-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--primary" id="commerceRefresh" data-commerce-action="refresh">
                        <i class="fa-solid fa-arrow-rotate-right" aria-hidden="true"></i>
                        <span>Refresh commerce data</span>
                    </button>
                    <button type="button" class="a11y-btn a11y-btn--ghost" id="commerceExport" data-commerce-action="export">
                        <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                        <span>Export summary</span>
                    </button>
                    <span class="a11y-hero-meta commerce-hero-meta" id="commerceLastUpdated" aria-live="polite">
                        Updated 12 minutes ago
                    </span>
                </div>
            </div>
            <div class="commerce-metric-grid" role="list">
<?php
$metricMap = [
    ['label' => 'Total revenue', 'value' => isset($summary['total_revenue']) ? commerce_format_currency($summary['total_revenue'], $currencySymbol) : commerce_format_currency(0, $currencySymbol), 'meta' => '30-day view'],
    ['label' => 'Orders', 'value' => commerce_format_number($summary['orders'] ?? 0), 'meta' => 'Open in fulfilment'],
    ['label' => 'Average order value', 'value' => isset($summary['average_order_value']) ? commerce_format_currency($summary['average_order_value'], $currencySymbol) : commerce_format_currency(0, $currencySymbol), 'meta' => 'Blended across channels'],
    ['label' => 'Conversion rate', 'value' => commerce_format_percent($summary['conversion_rate'] ?? 0), 'meta' => 'Storefront sessions'],
    ['label' => 'Repeat purchase', 'value' => commerce_format_percent($summary['repeat_purchase_rate'] ?? 0), 'meta' => 'Returning customers'],
    ['label' => 'Refund rate', 'value' => commerce_format_percent($summary['refund_rate'] ?? 0), 'meta' => '30-day average'],
];
foreach ($metricMap as $metric):
?>
                <article class="commerce-metric-card" role="listitem">
                    <div class="commerce-metric-label"><?php echo htmlspecialchars($metric['label']); ?></div>
                    <div class="commerce-metric-value" data-metric-value="<?php echo htmlspecialchars($metric['label']); ?>"><?php echo htmlspecialchars($metric['value']); ?></div>
                    <div class="commerce-metric-meta"><?php echo htmlspecialchars($metric['meta']); ?></div>
                </article>
<?php endforeach; ?>
            </div>
        </header>

        <nav class="commerce-workspace-nav" aria-label="Commerce workspaces" role="tablist">
<?php $isFirst = true; foreach ($workspaces as $workspaceKey => $workspaceLabel): ?>
            <button type="button"
                class="commerce-workspace-tab<?php echo $isFirst ? ' active' : ''; ?>"
                role="tab"
                id="commerce-tab-<?php echo htmlspecialchars($workspaceKey); ?>"
                aria-selected="<?php echo $isFirst ? 'true' : 'false'; ?>"
                aria-controls="commerce-panel-<?php echo htmlspecialchars($workspaceKey); ?>"
                data-commerce-workspace="<?php echo htmlspecialchars($workspaceKey); ?>">
                <?php echo htmlspecialchars($workspaceLabel); ?>
            </button>
<?php $isFirst = false; endforeach; ?>
        </nav>

        <div class="commerce-workspaces">
            <section class="commerce-panel" role="tabpanel" id="commerce-panel-dashboard" data-commerce-panel="dashboard">
                <div class="commerce-panel-grid">
                    <article class="commerce-card commerce-card--primary">
                        <header class="commerce-card-header">
                            <div>
                                <h3 class="commerce-card-title">Live sales mix</h3>
                                <p class="commerce-card-subtitle">Channel contribution to the latest 30 days of revenue.</p>
                            </div>
                            <span class="commerce-card-meta">Auto-refresh every 15 minutes</span>
                        </header>
                        <div class="commerce-channel-list">
<?php
$channels = isset($reports['top_channels']) && is_array($reports['top_channels']) ? $reports['top_channels'] : [];
$totalChannels = array_sum(array_map(function ($channel) {
    return isset($channel['percentage']) && is_numeric($channel['percentage']) ? (float) $channel['percentage'] : 0;
}, $channels));
foreach ($channels as $channel):
    $label = isset($channel['channel']) ? (string) $channel['channel'] : 'Unknown';
    $percentage = isset($channel['percentage']) ? (float) $channel['percentage'] : 0.0;
    $width = $totalChannels > 0 ? min(100, max(0, round(($percentage / $totalChannels) * 100))) : 0;
?>
                            <div class="commerce-channel-item" data-channel="<?php echo htmlspecialchars(strtolower($label)); ?>">
                                <div class="commerce-channel-label">
                                    <span><?php echo htmlspecialchars($label); ?></span>
                                    <strong><?php echo commerce_format_percent($percentage); ?></strong>
                                </div>
                                <div class="commerce-channel-meter" role="presentation">
                                    <span class="commerce-channel-bar" style="width: <?php echo $width; ?>%"></span>
                                </div>
                            </div>
<?php endforeach; ?>
                        </div>
                    </article>
                    <article class="commerce-card">
                        <header class="commerce-card-header">
                            <div>
                                <h3 class="commerce-card-title">Operational alerts</h3>
                                <p class="commerce-card-subtitle">Stay ahead of backorders, payouts, and customer escalations.</p>
                            </div>
                            <button type="button" class="a11y-btn a11y-btn--link" data-commerce-action="resolve-alerts">
                                Mark resolved
                            </button>
                        </header>
                        <ul class="commerce-alert-list" id="commerceAlerts">
<?php if (!$alerts): ?>
                            <li class="commerce-alert-item is-empty">All systems are operating normally.</li>
<?php else: ?>
<?php foreach ($alerts as $alert):
    $type = isset($alert['type']) ? (string) $alert['type'] : 'general';
    $severity = isset($alert['severity']) ? strtolower((string) $alert['severity']) : 'info';
    $message = isset($alert['message']) ? (string) $alert['message'] : '';
?>
                            <li class="commerce-alert-item commerce-alert-<?php echo htmlspecialchars($severity); ?>" data-alert-type="<?php echo htmlspecialchars($type); ?>">
                                <span class="commerce-alert-indicator" aria-hidden="true"></span>
                                <div>
                                    <p class="commerce-alert-message"><?php echo htmlspecialchars($message); ?></p>
                                    <span class="commerce-alert-meta">Severity: <?php echo ucfirst($severity); ?></span>
                                </div>
                            </li>
<?php endforeach; ?>
<?php endif; ?>
                        </ul>
                    </article>
                    <article class="commerce-card">
                        <header class="commerce-card-header">
                            <div>
                                <h3 class="commerce-card-title">Sales momentum</h3>
                                <p class="commerce-card-subtitle">Month-over-month revenue trends.</p>
                            </div>
                        </header>
                        <div class="commerce-trend-chart" role="img" aria-label="Revenue trend over the last five months">
<?php
$trend = isset($reports['sales_trend']) && is_array($reports['sales_trend']) ? $reports['sales_trend'] : [];
$maxRevenue = 0;
foreach ($trend as $point) {
    if (isset($point['revenue']) && is_numeric($point['revenue'])) {
        $maxRevenue = max($maxRevenue, (float) $point['revenue']);
    }
}
foreach ($trend as $point):
    $label = isset($point['label']) ? (string) $point['label'] : '';
    $revenue = isset($point['revenue']) ? (float) $point['revenue'] : 0.0;
    $height = $maxRevenue > 0 ? round(($revenue / $maxRevenue) * 100) : 0;
?>
                            <div class="commerce-trend-bar" aria-label="<?php echo htmlspecialchars($label); ?> revenue <?php echo commerce_format_currency($revenue, $currencySymbol); ?>">
                                <span class="commerce-trend-value"><?php echo commerce_format_currency($revenue, $currencySymbol); ?></span>
                                <span class="commerce-trend-indicator" style="height: <?php echo $height; ?>%"></span>
                                <span class="commerce-trend-label"><?php echo htmlspecialchars($label); ?></span>
                            </div>
<?php endforeach; ?>
                        </div>
                    </article>
                </div>
            </section>

            <section class="commerce-panel" role="tabpanel" id="commerce-panel-catalog" data-commerce-panel="catalog" hidden>
                <header class="commerce-panel-header">
                    <div>
                        <h3 class="commerce-panel-title">Catalogue inventory</h3>
                        <p class="commerce-panel-description">Search, filter, and review product velocity.</p>
                    </div>
                    <div class="commerce-panel-actions">
                        <label class="commerce-search" for="commerceCatalogSearch">
                            <i class="fa-solid fa-search" aria-hidden="true"></i>
                            <input type="search" id="commerceCatalogSearch" placeholder="Search products" aria-controls="commerceCatalogTable">
                        </label>
                        <select id="commerceCatalogCategory" class="commerce-select" aria-label="Filter products by category">
                            <option value="all">All categories</option>
<?php
$categories = [];
foreach ($catalog as $product) {
    if (isset($product['category'])) {
        $categories[] = (string) $product['category'];
    }
}
$categories = array_values(array_unique($categories));
sort($categories);
foreach ($categories as $category):
?>
                            <option value="<?php echo htmlspecialchars(strtolower($category)); ?>"><?php echo htmlspecialchars($category); ?></option>
<?php endforeach; ?>
                        </select>
                        <select id="commerceCatalogStatus" class="commerce-select" aria-label="Filter products by status">
                            <option value="all">All statuses</option>
                            <option value="active">Active</option>
                            <option value="preorder">Preorder</option>
                            <option value="backorder">Backorder</option>
                            <option value="restock">Restock</option>
                        </select>
                    </div>
                </header>
                <div class="commerce-table-wrapper" role="region" aria-live="polite">
                    <table class="commerce-table" id="commerceCatalogTable">
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col">Category</th>
                                <th scope="col">Price</th>
                                <th scope="col">Inventory</th>
                                <th scope="col">Status</th>
                                <th scope="col">Visibility</th>
                                <th scope="col">Updated</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($catalog as $product):
    $name = isset($product['name']) ? (string) $product['name'] : 'Untitled product';
    $sku = isset($product['sku']) ? (string) $product['sku'] : '';
    $category = isset($product['category']) ? (string) $product['category'] : 'Uncategorised';
    $price = isset($product['price']) ? commerce_format_currency($product['price'], $currencySymbol) : commerce_format_currency(0, $currencySymbol);
    $inventory = isset($product['inventory']) ? (int) $product['inventory'] : 0;
    $status = isset($product['status']) ? (string) $product['status'] : 'Unknown';
    $visibility = isset($product['visibility']) ? (string) $product['visibility'] : 'Unknown';
    $updated = isset($product['updated']) ? (string) $product['updated'] : '';
?>
                            <tr data-commerce-item
                                data-sku="<?php echo htmlspecialchars($sku); ?>"
                                data-category="<?php echo htmlspecialchars(strtolower($category)); ?>"
                                data-status="<?php echo htmlspecialchars(strtolower($status)); ?>"
                                data-visibility="<?php echo htmlspecialchars(strtolower($visibility)); ?>">
                                <td>
                                    <div class="commerce-table-primary">
                                        <strong><?php echo htmlspecialchars($name); ?></strong>
                                        <span class="commerce-table-meta">SKU: <?php echo htmlspecialchars($sku); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($category); ?></td>
                                <td><?php echo htmlspecialchars($price); ?></td>
                                <td>
                                    <span class="commerce-inventory" data-inventory="<?php echo $inventory; ?>"><?php echo $inventory; ?></span>
                                </td>
                                <td><span class="<?php echo commerce_badge_class($status); ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                <td><?php echo htmlspecialchars($visibility); ?></td>
                                <td><?php echo htmlspecialchars($updated); ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="commerce-empty" id="commerceCatalogEmpty" hidden>No products match the current filters.</div>
                </div>
            </section>

            <section class="commerce-panel" role="tabpanel" id="commerce-panel-orders" data-commerce-panel="orders" hidden>
                <header class="commerce-panel-header">
                    <div>
                        <h3 class="commerce-panel-title">Order pipeline</h3>
                        <p class="commerce-panel-description">Monitor fulfilment progress and payment status.</p>
                    </div>
                    <div class="commerce-panel-actions">
                        <select id="commerceOrderStatus" class="commerce-select" aria-label="Filter orders by status">
                            <option value="all">All statuses</option>
                            <option value="processing">Processing</option>
                            <option value="pending payment">Pending payment</option>
                            <option value="ready for pickup">Ready for pickup</option>
                            <option value="fulfilled">Fulfilled</option>
                            <option value="refund requested">Refund requested</option>
                        </select>
                        <label class="commerce-search" for="commerceOrderSearch">
                            <i class="fa-solid fa-search" aria-hidden="true"></i>
                            <input type="search" id="commerceOrderSearch" placeholder="Search orders" aria-controls="commerceOrderTable">
                        </label>
                    </div>
                </header>
                <div class="commerce-table-wrapper" role="region" aria-live="polite">
                    <table class="commerce-table" id="commerceOrderTable">
                        <thead>
                            <tr>
                                <th scope="col">Order</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Channel</th>
                                <th scope="col">Status</th>
                                <th scope="col">Total</th>
                                <th scope="col">Placed</th>
                                <th scope="col">Fulfilment</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($orders as $order):
    $orderId = isset($order['id']) ? (string) $order['id'] : '';
    $customer = isset($order['customer']) ? (string) $order['customer'] : '';
    $channel = isset($order['channel']) ? (string) $order['channel'] : '';
    $status = isset($order['status']) ? (string) $order['status'] : '';
    $total = isset($order['total']) ? commerce_format_currency($order['total'], $currencySymbol) : commerce_format_currency(0, $currencySymbol);
    $placed = isset($order['placed']) ? (string) $order['placed'] : '';
    $fulfilment = isset($order['fulfillment']) ? (string) $order['fulfillment'] : '';
?>
                            <tr data-commerce-order
                                data-status="<?php echo htmlspecialchars(strtolower($status)); ?>"
                                data-channel="<?php echo htmlspecialchars(strtolower($channel)); ?>"
                                data-customer="<?php echo htmlspecialchars(strtolower($customer)); ?>"
                                data-order-id="<?php echo htmlspecialchars(strtolower($orderId)); ?>">
                                <td>
                                    <div class="commerce-table-primary">
                                        <strong><?php echo htmlspecialchars($orderId); ?></strong>
                                        <span class="commerce-table-meta">Channel: <?php echo htmlspecialchars($channel); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($customer); ?></td>
                                <td><?php echo htmlspecialchars($channel); ?></td>
                                <td><span class="<?php echo commerce_badge_class($status); ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                <td><?php echo htmlspecialchars($total); ?></td>
                                <td><?php echo htmlspecialchars($placed); ?></td>
                                <td><?php echo htmlspecialchars($fulfilment); ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="commerce-empty" id="commerceOrderEmpty" hidden>No orders match the current filters.</div>
                </div>
            </section>

            <section class="commerce-panel" role="tabpanel" id="commerce-panel-customers" data-commerce-panel="customers" hidden>
                <header class="commerce-panel-header">
                    <div>
                        <h3 class="commerce-panel-title">Customer segments</h3>
                        <p class="commerce-panel-description">Prioritise outreach to protect retention and lifetime value.</p>
                    </div>
                    <div class="commerce-panel-actions">
                        <select id="commerceCustomerSegment" class="commerce-select" aria-label="Filter customers by segment">
                            <option value="all">All segments</option>
<?php
$segments = [];
foreach ($customers as $customer) {
    if (isset($customer['segment'])) {
        $segments[] = (string) $customer['segment'];
    }
}
$segments = array_values(array_unique($segments));
sort($segments);
foreach ($segments as $segment):
?>
                            <option value="<?php echo htmlspecialchars(strtolower($segment)); ?>"><?php echo htmlspecialchars($segment); ?></option>
<?php endforeach; ?>
                        </select>
                        <select id="commerceCustomerStatus" class="commerce-select" aria-label="Filter customers by status">
                            <option value="all">All statuses</option>
                            <option value="active">Active</option>
                            <option value="attention">Attention</option>
                            <option value="dormant">Dormant</option>
                        </select>
                    </div>
                </header>
                <div class="commerce-table-wrapper" role="region" aria-live="polite">
                    <table class="commerce-table" id="commerceCustomerTable">
                        <thead>
                            <tr>
                                <th scope="col">Customer</th>
                                <th scope="col">Email</th>
                                <th scope="col">Segment</th>
                                <th scope="col">Orders</th>
                                <th scope="col">Lifetime value</th>
                                <th scope="col">Last order</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($customers as $customer):
    $name = isset($customer['name']) ? (string) $customer['name'] : '';
    $email = isset($customer['email']) ? (string) $customer['email'] : '';
    $segment = isset($customer['segment']) ? (string) $customer['segment'] : '';
    $ordersCount = isset($customer['orders']) ? (int) $customer['orders'] : 0;
    $ltv = isset($customer['lifetime_value']) ? commerce_format_currency($customer['lifetime_value'], $currencySymbol) : commerce_format_currency(0, $currencySymbol);
    $lastOrder = isset($customer['last_order']) ? (string) $customer['last_order'] : '';
    $status = isset($customer['status']) ? (string) $customer['status'] : '';
?>
                            <tr data-commerce-customer
                                data-segment="<?php echo htmlspecialchars(strtolower($segment)); ?>"
                                data-status="<?php echo htmlspecialchars(strtolower($status)); ?>"
                                data-name="<?php echo htmlspecialchars(strtolower($name)); ?>"
                                data-email="<?php echo htmlspecialchars(strtolower($email)); ?>">
                                <td><?php echo htmlspecialchars($name); ?></td>
                                <td><a href="mailto:<?php echo htmlspecialchars($email); ?>" class="commerce-link"><?php echo htmlspecialchars($email); ?></a></td>
                                <td><?php echo htmlspecialchars($segment); ?></td>
                                <td><?php echo $ordersCount; ?></td>
                                <td><?php echo htmlspecialchars($ltv); ?></td>
                                <td><?php echo htmlspecialchars($lastOrder); ?></td>
                                <td><span class="<?php echo commerce_badge_class($status); ?>"><?php echo htmlspecialchars($status); ?></span></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="commerce-empty" id="commerceCustomerEmpty" hidden>No customers match the selected filters.</div>
                </div>
            </section>

            <section class="commerce-panel" role="tabpanel" id="commerce-panel-reports" data-commerce-panel="reports" hidden>
                <div class="commerce-panel-grid">
                    <article class="commerce-card">
                        <header class="commerce-card-header">
                            <div>
                                <h3 class="commerce-card-title">Key ratios</h3>
                                <p class="commerce-card-subtitle">Highlight conversion, abandonment, and retention signals.</p>
                            </div>
                        </header>
                        <dl class="commerce-stat-list">
                            <div class="commerce-stat">
                                <dt>Storefront conversion</dt>
                                <dd><?php echo commerce_format_percent($reports['conversion_rate'] ?? 0); ?></dd>
                            </div>
                            <div class="commerce-stat">
                                <dt>Cart abandonment</dt>
                                <dd><?php echo commerce_format_percent($reports['cart_abandonment'] ?? 0); ?></dd>
                            </div>
                            <div class="commerce-stat">
                                <dt>Net promoter score</dt>
                                <dd><?php echo commerce_format_number($reports['net_promoter_score'] ?? 0); ?></dd>
                            </div>
                            <div class="commerce-stat">
                                <dt>Return rate</dt>
                                <dd><?php echo commerce_format_percent($reports['return_rate'] ?? 0); ?></dd>
                            </div>
                        </dl>
                    </article>
                    <article class="commerce-card">
                        <header class="commerce-card-header">
                            <div>
                                <h3 class="commerce-card-title">Team priorities</h3>
                                <p class="commerce-card-subtitle">Auto-generated tasks based on this week's trading data.</p>
                            </div>
                            <span class="commerce-card-meta">Generated using trading insights</span>
                        </header>
                        <ul class="commerce-priority-list" id="commercePriorities">
                            <li>Contact wholesale partners regarding replenishment for Traveller Backpack.</li>
                            <li>Launch a cart recovery email for dormant VIP customers.</li>
                            <li>Review refund queue for Sofia Oliveira to prevent churn.</li>
                        </ul>
                    </article>
                    <article class="commerce-card">
                        <header class="commerce-card-header">
                            <div>
                                <h3 class="commerce-card-title">Performance benchmarks</h3>
                                <p class="commerce-card-subtitle">Compare against target KPIs to anticipate risks.</p>
                            </div>
                        </header>
                        <ul class="commerce-benchmark-list">
                            <li data-benchmark="conversion" data-target="3.2">Conversion goal: 3.2% (currently <?php echo commerce_format_percent($reports['conversion_rate'] ?? 0); ?>)</li>
                            <li data-benchmark="refunds" data-target="1.0">Refund tolerance: &lt;1.0% (currently <?php echo commerce_format_percent($summary['refund_rate'] ?? 0); ?>)</li>
                            <li data-benchmark="repeat" data-target="40">Repeat purchase: 40% target (currently <?php echo commerce_format_percent($summary['repeat_purchase_rate'] ?? 0); ?>)</li>
                        </ul>
                    </article>
                </div>
            </section>

            <section class="commerce-panel" role="tabpanel" id="commerce-panel-settings" data-commerce-panel="settings" hidden>
                <header class="commerce-panel-header">
                    <div>
                        <h3 class="commerce-panel-title">Commerce configuration</h3>
                        <p class="commerce-panel-description">Adjust storefront controls and fulfilment preferences.</p>
                    </div>
                </header>
                <div class="commerce-settings-grid">
                    <section class="commerce-settings-group">
                        <h4 class="commerce-settings-heading">Storefront</h4>
                        <ul class="commerce-settings-list">
                            <li>
                                <span class="commerce-settings-label">Status</span>
                                <span class="commerce-settings-value status-badge status-good"><?php echo htmlspecialchars($commerceSettings['storefront_status'] ?? 'Offline'); ?></span>
                            </li>
                            <li>
                                <span class="commerce-settings-label">Currency</span>
                                <span class="commerce-settings-value"><?php echo htmlspecialchars($currency); ?></span>
                            </li>
                            <li>
                                <span class="commerce-settings-label">Low inventory threshold</span>
                                <span class="commerce-settings-value"><?php echo htmlspecialchars((string) ($commerceSettings['low_inventory_threshold'] ?? 0)); ?> units</span>
                            </li>
                            <li>
                                <span class="commerce-settings-label">Default tax rate</span>
                                <span class="commerce-settings-value"><?php echo commerce_format_percent($commerceSettings['default_tax_rate'] ?? 0); ?></span>
                            </li>
                        </ul>
                    </section>
                    <section class="commerce-settings-group">
                        <h4 class="commerce-settings-heading">Checkout controls</h4>
                        <ul class="commerce-toggle-list">
<?php
$toggleMap = [
    'express_checkout' => 'Express checkout',
    'allow_guest_checkout' => 'Allow guest checkout',
    'fraud_protection' => 'Fraud protection'
];
foreach ($toggleMap as $key => $label):
    $enabled = !empty($commerceSettings[$key]);
?>
                            <li>
                                <label class="commerce-toggle">
                                    <input type="checkbox" <?php echo $enabled ? 'checked' : ''; ?> data-commerce-toggle="<?php echo htmlspecialchars($key); ?>">
                                    <span class="commerce-toggle-indicator" aria-hidden="true"></span>
                                    <span class="commerce-toggle-label"><?php echo htmlspecialchars($label); ?></span>
                                </label>
                            </li>
<?php endforeach; ?>
                        </ul>
                    </section>
                    <section class="commerce-settings-group">
                        <h4 class="commerce-settings-heading">Payment methods</h4>
                        <ul class="commerce-settings-list">
<?php
$paymentMethods = isset($commerceSettings['payment_methods']) && is_array($commerceSettings['payment_methods']) ? $commerceSettings['payment_methods'] : [];
foreach ($paymentMethods as $method):
    $label = isset($method['label']) ? (string) $method['label'] : '';
    $enabled = !empty($method['enabled']);
?>
                            <li>
                                <span class="commerce-settings-label"><?php echo htmlspecialchars($label); ?></span>
                                <span class="commerce-settings-value <?php echo $enabled ? 'status-badge status-good' : 'status-badge status-warning'; ?>"><?php echo $enabled ? 'Enabled' : 'Disabled'; ?></span>
                            </li>
<?php endforeach; ?>
                        </ul>
                    </section>
                    <section class="commerce-settings-group">
                        <h4 class="commerce-settings-heading">Fulfilment windows</h4>
                        <ul class="commerce-settings-list">
<?php
$windows = isset($commerceSettings['fulfillment_windows']) && is_array($commerceSettings['fulfillment_windows']) ? $commerceSettings['fulfillment_windows'] : [];
foreach ($windows as $window):
    $name = isset($window['name']) ? (string) $window['name'] : '';
    $sla = isset($window['sla']) ? (string) $window['sla'] : '';
?>
                            <li>
                                <span class="commerce-settings-label"><?php echo htmlspecialchars($name); ?></span>
                                <span class="commerce-settings-value"><?php echo htmlspecialchars($sla); ?></span>
                            </li>
<?php endforeach; ?>
                        </ul>
                    </section>
                </div>
            </section>
        </div>
    </div>
    <script type="application/json" id="commerceDataset"><?php echo $encodedDataset; ?></script>
</div>
