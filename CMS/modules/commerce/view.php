<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/CommerceService.php';
require_login();

$service = new CommerceService();
$context = $service->buildDashboardContext();
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
                <?php foreach ($context['heroMetrics'] as $metric): ?>
                <article class="commerce-metric-card" role="listitem">
                    <div class="commerce-metric-label"><?php echo htmlspecialchars($metric['label']); ?></div>
                    <div class="commerce-metric-value" data-metric-value="<?php echo htmlspecialchars($metric['label']); ?>"><?php echo htmlspecialchars($metric['value']); ?></div>
                    <div class="commerce-metric-meta"><?php echo htmlspecialchars($metric['meta']); ?></div>
                </article>
                <?php endforeach; ?>
            </div>
        </header>

        <nav class="commerce-workspace-nav" aria-label="Commerce workspaces" role="tablist">
            <?php foreach ($context['workspaces'] as $workspace): ?>
            <button type="button"
                class="commerce-workspace-tab<?php echo $workspace['isActive'] ? ' active' : ''; ?>"
                role="tab"
                id="commerce-tab-<?php echo htmlspecialchars($workspace['key']); ?>"
                aria-selected="<?php echo $workspace['isActive'] ? 'true' : 'false'; ?>"
                aria-controls="commerce-panel-<?php echo htmlspecialchars($workspace['key']); ?>"
                data-commerce-workspace="<?php echo htmlspecialchars($workspace['key']); ?>">
                <?php echo htmlspecialchars($workspace['label']); ?>
            </button>
            <?php endforeach; ?>
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
                            <?php foreach ($context['dashboard']['channels'] as $channel): ?>
                            <div class="commerce-channel-item" data-channel="<?php echo htmlspecialchars($channel['slug']); ?>">
                                <div class="commerce-channel-label">
                                    <span><?php echo htmlspecialchars($channel['label']); ?></span>
                                    <strong><?php echo htmlspecialchars($channel['percentageLabel']); ?></strong>
                                </div>
                                <div class="commerce-channel-meter" role="presentation">
                                    <span class="commerce-channel-bar" style="width: <?php echo (int) $channel['barWidth']; ?>%"></span>
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
                            <?php if (empty($context['dashboard']['alerts'])): ?>
                            <li class="commerce-alert-item is-empty">All systems are operating normally.</li>
                            <?php else: ?>
                                <?php foreach ($context['dashboard']['alerts'] as $alert): ?>
                                <li class="commerce-alert-item <?php echo htmlspecialchars($alert['class']); ?>" data-alert-type="<?php echo htmlspecialchars($alert['type']); ?>">
                                    <span class="commerce-alert-indicator" aria-hidden="true"></span>
                                    <div>
                                        <p class="commerce-alert-message"><?php echo htmlspecialchars($alert['message']); ?></p>
                                        <span class="commerce-alert-meta">Severity: <?php echo htmlspecialchars($alert['severityLabel']); ?></span>
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
                            <?php foreach ($context['dashboard']['salesTrend'] as $point): ?>
                            <div class="commerce-trend-bar" aria-label="<?php echo htmlspecialchars($point['ariaLabel']); ?>">
                                <span class="commerce-trend-value"><?php echo htmlspecialchars($point['valueLabel']); ?></span>
                                <span class="commerce-trend-indicator" style="height: <?php echo (int) $point['height']; ?>%"></span>
                                <span class="commerce-trend-label"><?php echo htmlspecialchars($point['label']); ?></span>
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
                            <?php foreach ($context['catalog']['categories']['list'] as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['slug']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
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
                <div class="commerce-management-grid">
                    <article class="commerce-card">
                        <header class="commerce-card-header">
                            <div>
                                <h4 class="commerce-card-title">Category management</h4>
                                <p class="commerce-card-subtitle">Add new catalogue categories or rename existing ones.</p>
                            </div>
                        </header>
                        <div class="commerce-card-body">
                            <p class="commerce-card-copy">Your catalogue spans <?php echo htmlspecialchars($context['catalog']['categories']['countFormatted']); ?> <?php echo htmlspecialchars($context['catalog']['categories']['countLabel']); ?>.</p>
                            <ul class="commerce-chip-list" id="commerceCategorySummary">
                                <?php if ($context['catalog']['categories']['hasCategories']): ?>
                                    <?php foreach ($context['catalog']['categories']['list'] as $category): ?>
                                    <li class="commerce-chip" data-category-id="<?php echo htmlspecialchars($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="commerce-chip commerce-chip--empty">No categories yet</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="commerce-card-footer">
                            <button type="button" class="a11y-btn a11y-btn--primary" data-commerce-open-modal="commerceCategoryModal">
                                <i class="fa-solid fa-tags" aria-hidden="true"></i>
                                <span>Manage categories</span>
                            </button>
                        </div>
                    </article>
                    <article class="commerce-card">
                        <header class="commerce-card-header">
                            <div>
                                <h4 class="commerce-card-title">Product quick actions</h4>
                                <p class="commerce-card-subtitle">Launch new items or update existing SKUs from a focused modal.</p>
                            </div>
                        </header>
                        <div class="commerce-card-body">
                            <p class="commerce-card-copy">Keep your catalogue current by adding new products or refreshing existing listings.</p>
                            <ul class="commerce-stat-list">
                                <li class="commerce-stat">
                                    <span class="commerce-stat-label">Total products</span>
                                    <span class="commerce-stat-value"><?php echo htmlspecialchars($context['catalog']['stats']['totalProducts']); ?></span>
                                </li>
                                <li class="commerce-stat">
                                    <span class="commerce-stat-label">Active SKUs</span>
                                    <span class="commerce-stat-value"><?php echo htmlspecialchars($context['catalog']['stats']['activeProducts']); ?></span>
                                </li>
                                <?php if ($context['catalog']['stats']['showLowInventory']): ?>
                                <li class="commerce-stat">
                                    <span class="commerce-stat-label">Low inventory (≤ <?php echo htmlspecialchars($context['catalog']['stats']['lowInventoryThreshold']); ?>)</span>
                                    <span class="commerce-stat-value"><?php echo htmlspecialchars($context['catalog']['stats']['lowInventoryCount']); ?></span>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="commerce-card-footer">
                            <button type="button" class="a11y-btn a11y-btn--primary" data-commerce-open-modal="commerceProductModal">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                <span>Add product</span>
                            </button>
                        </div>
                    </article>
                </div>
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
                                <th scope="col" class="commerce-table-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($context['catalog']['products'] as $product): ?>
                            <tr data-commerce-item
                                data-sku="<?php echo htmlspecialchars($product['sku']); ?>"
                                data-category="<?php echo htmlspecialchars($product['categorySlug']); ?>"
                                data-status="<?php echo htmlspecialchars($product['statusKey']); ?>"
                                data-visibility="<?php echo htmlspecialchars($product['visibilityKey']); ?>"
                                data-updated="<?php echo htmlspecialchars($product['updated']); ?>">
                                <td>
                                    <div class="commerce-product-cell">
                                        <?php if ($product['hasFeaturedImage']): ?>
                                        <img src="<?php echo htmlspecialchars($product['featuredImage']); ?>" alt="Featured image for <?php echo htmlspecialchars($product['name']); ?>" class="commerce-product-thumb">
                                        <?php else: ?>
                                        <div class="commerce-product-thumb-placeholder" role="img" aria-label="No featured image">
                                            <i class="fa-solid fa-image" aria-hidden="true"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div class="commerce-product-details">
                                            <div class="commerce-table-primary">
                                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                <span class="commerce-table-meta">SKU: <?php echo htmlspecialchars($product['sku']); ?></span>
                                            </div>
                                            <span class="commerce-product-gallery-meta"><?php echo htmlspecialchars($product['galleryLabel']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                                <td><?php echo htmlspecialchars($product['price']); ?></td>
                                <td>
                                    <span class="commerce-inventory" data-inventory="<?php echo htmlspecialchars((string) $product['inventory']); ?>"><?php echo htmlspecialchars((string) $product['inventory']); ?></span>
                                </td>
                                <td><span class="<?php echo htmlspecialchars($product['statusClass']); ?>"><?php echo htmlspecialchars($product['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($product['visibility']); ?></td>
                                <td><?php echo htmlspecialchars($product['updated']); ?></td>
                                <td>
                                    <button type="button" class="commerce-inline-action" data-action="edit-product" data-sku="<?php echo htmlspecialchars($product['sku']); ?>">
                                        <span>Edit</span>
                                    </button>
                                </td>
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
                            <?php foreach ($context['orders']['rows'] as $order): ?>
                            <tr data-commerce-order
                                data-status="<?php echo htmlspecialchars($order['filters']['status']); ?>"
                                data-channel="<?php echo htmlspecialchars($order['filters']['channel']); ?>"
                                data-customer="<?php echo htmlspecialchars($order['filters']['customer']); ?>"
                                data-order-id="<?php echo htmlspecialchars($order['filters']['order']); ?>">
                                <td>
                                    <div class="commerce-table-primary">
                                        <strong><?php echo htmlspecialchars($order['id']); ?></strong>
                                        <span class="commerce-table-meta">Channel: <?php echo htmlspecialchars($order['channel']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($order['customer']); ?></td>
                                <td><?php echo htmlspecialchars($order['channel']); ?></td>
                                <td><span class="<?php echo htmlspecialchars($order['statusClass']); ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($order['total']); ?></td>
                                <td><?php echo htmlspecialchars($order['placed']); ?></td>
                                <td><?php echo htmlspecialchars($order['fulfillment']); ?></td>
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
                            <?php foreach ($context['customers']['segments'] as $segment): ?>
                            <option value="<?php echo htmlspecialchars($segment['value']); ?>"><?php echo htmlspecialchars($segment['label']); ?></option>
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
                            <?php foreach ($context['customers']['rows'] as $customer): ?>
                            <tr data-commerce-customer
                                data-segment="<?php echo htmlspecialchars($customer['filters']['segment']); ?>"
                                data-status="<?php echo htmlspecialchars($customer['filters']['status']); ?>"
                                data-name="<?php echo htmlspecialchars($customer['filters']['name']); ?>"
                                data-email="<?php echo htmlspecialchars($customer['filters']['email']); ?>">
                                <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                <td><a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="commerce-link"><?php echo htmlspecialchars($customer['email']); ?></a></td>
                                <td><?php echo htmlspecialchars($customer['segment']); ?></td>
                                <td>
                                    <button type="button"
                                        class="commerce-link commerce-link-button"
                                        data-commerce-customer-orders="<?php echo htmlspecialchars($customer['filters']['name']); ?>"
                                        data-customer-name="<?php echo htmlspecialchars($customer['name']); ?>">
                                        <?php echo htmlspecialchars((string) $customer['orders']); ?>
                                        <span class="sr-only"> orders for <?php echo htmlspecialchars($customer['name']); ?></span>
                                    </button>
                                </td>
                                <td><?php echo htmlspecialchars($customer['lifetimeValue']); ?></td>
                                <td><?php echo htmlspecialchars($customer['lastOrder']); ?></td>
                                <td><span class="<?php echo htmlspecialchars($customer['statusClass']); ?>"><?php echo htmlspecialchars($customer['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="commerce-empty" id="commerceCustomerEmpty" hidden>No customers match the selected filters.</div>
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
                            <?php foreach ($context['settings']['storefront'] as $item): ?>
                            <li>
                                <span class="commerce-settings-label"><?php echo htmlspecialchars($item['label']); ?></span>
                                <?php if (!empty($item['isBadge'])): ?>
                                <span class="commerce-settings-value <?php echo htmlspecialchars($item['badgeClass']); ?>"><?php echo htmlspecialchars($item['value']); ?></span>
                                <?php else: ?>
                                <span class="commerce-settings-value"><?php echo htmlspecialchars($item['value']); ?></span>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                    <section class="commerce-settings-group">
                        <h4 class="commerce-settings-heading">Checkout controls</h4>
                        <ul class="commerce-toggle-list">
                            <?php foreach ($context['settings']['toggles'] as $toggle): ?>
                            <li>
                                <label class="commerce-toggle">
                                    <input type="checkbox" <?php echo $toggle['checked'] ? 'checked' : ''; ?> data-commerce-toggle="<?php echo htmlspecialchars($toggle['key']); ?>">
                                    <span class="commerce-toggle-indicator" aria-hidden="true"></span>
                                    <span class="commerce-toggle-label"><?php echo htmlspecialchars($toggle['label']); ?></span>
                                </label>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                    <section class="commerce-settings-group">
                        <h4 class="commerce-settings-heading">Payment methods</h4>
                        <ul class="commerce-settings-list">
                            <?php foreach ($context['settings']['paymentMethods'] as $method): ?>
                            <li>
                                <span class="commerce-settings-label"><?php echo htmlspecialchars($method['label']); ?></span>
                                <span class="commerce-settings-value <?php echo htmlspecialchars($method['statusClass']); ?>"><?php echo htmlspecialchars($method['status']); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                    <section class="commerce-settings-group">
                        <h4 class="commerce-settings-heading">Fulfilment windows</h4>
                        <ul class="commerce-settings-list">
                            <?php foreach ($context['settings']['fulfillmentWindows'] as $window): ?>
                            <li>
                                <span class="commerce-settings-label"><?php echo htmlspecialchars($window['name']); ?></span>
                                <span class="commerce-settings-value"><?php echo htmlspecialchars($window['sla']); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                    <section class="commerce-settings-group">
                        <h4 class="commerce-settings-heading">Shipping partners</h4>
                        <ul class="commerce-settings-list">
                            <?php foreach ($context['settings']['shippingPartners'] as $partner): ?>
                            <li>
                                <span class="commerce-settings-label"><?php echo htmlspecialchars($partner['name']); ?></span>
                                <?php if (!empty($partner['statusClass'])): ?>
                                <span class="commerce-settings-value <?php echo htmlspecialchars($partner['statusClass']); ?>"><?php echo htmlspecialchars($partner['status']); ?></span>
                                <?php else: ?>
                                <span class="commerce-settings-value"><?php echo htmlspecialchars($partner['status']); ?></span>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                    <section class="commerce-settings-group">
                        <h4 class="commerce-settings-heading">Return policy</h4>
                        <ul class="commerce-settings-list">
                            <?php foreach ($context['settings']['returnPolicy'] as $policy): ?>
                            <li>
                                <span class="commerce-settings-label"><?php echo htmlspecialchars($policy['label']); ?></span>
                                <span class="commerce-settings-value"><?php echo htmlspecialchars($policy['value']); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                </div>
            </section>
        </div>
    </div>
    <div class="modal commerce-modal" id="commerceCategoryModal" role="dialog" aria-modal="true" aria-labelledby="commerceCategoryModalTitle" aria-describedby="commerceCategoryModalDescription" aria-hidden="true">
        <div class="commerce-modal__surface" role="document">
            <button type="button" class="commerce-modal__close" data-commerce-close-modal="commerceCategoryModal" aria-label="Close category modal">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="commerce-modal__header">
                <span class="commerce-modal__eyebrow">Catalogue</span>
                <h2 class="commerce-modal__title" id="commerceCategoryModalTitle">Manage categories</h2>
                <p class="commerce-modal__description" id="commerceCategoryModalDescription">Create new catalogue groupings or rename existing categories to keep your storefront organised.</p>
            </header>
            <div class="commerce-modal__body">
                <form id="commerceCategoryForm" class="commerce-form" method="post" action="modules/commerce/save_category.php">
                    <input type="hidden" id="commerceCategoryId" name="id">
                    <div class="commerce-form-group">
                        <label class="commerce-form-label" for="commerceCategorySelect">Edit existing category</label>
                        <select id="commerceCategorySelect" class="commerce-form-input" data-commerce-initial-focus>
                            <option value="">Create new category</option>
                            <?php foreach ($context['catalog']['categories']['list'] as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="commerce-form-help">Select a category to update or choose “Create new category”.</p>
                    </div>
                    <div class="commerce-form-group">
                        <label class="commerce-form-label" for="commerceCategoryName">Category name</label>
                        <input type="text" id="commerceCategoryName" class="commerce-form-input" name="name" required autocomplete="off" placeholder="e.g. Lighting">
                    </div>
                    <div class="commerce-form-actions">
                        <button type="submit" class="a11y-btn a11y-btn--primary" id="commerceCategorySubmit">Save category</button>
                        <button type="button" class="a11y-btn a11y-btn--ghost" id="commerceCategoryReset">Clear</button>
                    </div>
                </form>
                <div class="commerce-form-meta">
                    <h5 class="commerce-form-meta-title">Existing categories</h5>
                    <ul class="commerce-chip-list" id="commerceCategoryList">
                        <?php if ($context['catalog']['categories']['hasCategories']): ?>
                            <?php foreach ($context['catalog']['categories']['list'] as $category): ?>
                            <li class="commerce-chip" data-category-id="<?php echo htmlspecialchars($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="commerce-chip commerce-chip--empty">No categories yet</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="modal commerce-modal" id="commerceProductModal" role="dialog" aria-modal="true" aria-labelledby="commerceProductModalTitle" aria-describedby="commerceProductModalDescription" aria-hidden="true">
        <div class="commerce-modal__surface" role="document">
            <button type="button" class="commerce-modal__close" data-commerce-close-modal="commerceProductModal" aria-label="Close product modal">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="commerce-modal__header">
                <span class="commerce-modal__eyebrow">Product catalogue</span>
                <h2 class="commerce-modal__title" id="commerceProductModalTitle">Add new product</h2>
                <p class="commerce-modal__description" id="commerceProductModalDescription">Launch a new product listing or update an existing SKU with pricing and inventory details.</p>
            </header>
            <div class="commerce-modal__body">
                <form id="commerceProductForm" class="commerce-form" method="post" action="modules/commerce/save_product.php">
                    <input type="hidden" id="commerceProductOriginalSku" name="original_sku">
                    <div class="commerce-form-grid">
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="commerceProductSku">SKU</label>
                            <input type="text" id="commerceProductSku" class="commerce-form-input" name="sku" required autocomplete="off" placeholder="SP-1001" data-commerce-initial-focus>
                        </div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="commerceProductName">Product name</label>
                            <input type="text" id="commerceProductName" class="commerce-form-input" name="name" required placeholder="Luminous Desk Lamp">
                        </div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="commerceProductCategory">Category</label>
                            <input type="text" id="commerceProductCategory" class="commerce-form-input" name="category" list="commerceProductCategoryOptions" required placeholder="Lighting">
                            <datalist id="commerceProductCategoryOptions">
                                <?php foreach ($context['catalog']['categories']['list'] as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="commerceProductPrice">Price</label>
                            <input type="number" id="commerceProductPrice" class="commerce-form-input" name="price" min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="commerceProductInventory">Inventory</label>
                            <input type="number" id="commerceProductInventory" class="commerce-form-input" name="inventory" min="0" step="1" placeholder="0">
                        </div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="commerceProductStatus">Status</label>
                            <select id="commerceProductStatus" class="commerce-form-input" name="status">
                                <option value="Active">Active</option>
                                <option value="Preorder">Preorder</option>
                                <option value="Backorder">Backorder</option>
                                <option value="Restock">Restock</option>
                                <option value="Hidden">Hidden</option>
                            </select>
                        </div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="commerceProductVisibility">Visibility</label>
                            <select id="commerceProductVisibility" class="commerce-form-input" name="visibility">
                                <option value="Published">Published</option>
                                <option value="Hidden">Hidden</option>
                            </select>
                        </div>
                        <div class="commerce-form-group commerce-form-group--full" data-commerce-media-field="featured">
                            <label class="commerce-form-label" for="commerceProductFeaturedImage">Featured image URL</label>
                            <div class="commerce-media-control">
                                <input type="url" id="commerceProductFeaturedImage" class="commerce-form-input" name="featured_image" placeholder="https://example.com/images/product.jpg">
                                <div class="commerce-media-actions">
                                    <button type="button" class="a11y-btn a11y-btn--secondary" data-commerce-media-browse="featured">
                                        <i class="fa-solid fa-image" aria-hidden="true"></i>
                                        <span>Select from library</span>
                                    </button>
                                    <button type="button" class="a11y-btn a11y-btn--ghost" data-commerce-media-clear="featured">
                                        <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                        <span>Clear</span>
                                    </button>
                                </div>
                            </div>
                            <div class="commerce-media-preview" data-commerce-media-preview="featured" hidden></div>
                            <p class="commerce-form-help">Used on collection pages and highlights. Leave blank to fall back to the first gallery image.</p>
                        </div>
                        <div class="commerce-form-group commerce-form-group--full" data-commerce-media-field="gallery">
                            <label class="commerce-form-label" for="commerceProductImages">Product gallery images</label>
                            <div class="commerce-media-control commerce-media-control--stacked">
                                <textarea id="commerceProductImages" class="commerce-form-input" name="images" rows="4" placeholder="Add one image URL per line"></textarea>
                                <div class="commerce-media-actions">
                                    <button type="button" class="a11y-btn a11y-btn--secondary" data-commerce-media-browse="gallery">
                                        <i class="fa-solid fa-images" aria-hidden="true"></i>
                                        <span>Add from library</span>
                                    </button>
                                    <button type="button" class="a11y-btn a11y-btn--ghost" data-commerce-media-clear="gallery">
                                        <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                        <span>Clear</span>
                                    </button>
                                </div>
                            </div>
                            <div class="commerce-media-preview commerce-media-preview--grid" data-commerce-media-preview="gallery" hidden></div>
                            <p class="commerce-form-help">These images power the product gallery and merchandising blocks. Use the media library to quickly insert hosted assets.</p>
                        </div>
                        <div class="commerce-form-group">
                            <label class="commerce-form-label" for="commerceProductUpdated">Last updated</label>
                            <input type="date" id="commerceProductUpdated" class="commerce-form-input" name="updated">
                        </div>
                    </div>
                    <div class="commerce-form-actions">
                        <button type="submit" class="a11y-btn a11y-btn--primary" id="commerceProductSubmit">Add product</button>
                        <button type="button" class="a11y-btn a11y-btn--ghost" id="commerceProductReset">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal commerce-modal commerce-media-modal" id="commerceMediaModal" role="dialog" aria-modal="true" aria-labelledby="commerceMediaModalTitle" aria-describedby="commerceMediaModalDescription" aria-hidden="true">
        <div class="commerce-modal__surface commerce-modal__surface--wide" role="document">
            <button type="button" class="commerce-modal__close" data-commerce-close-modal="commerceMediaModal" aria-label="Close media library">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="commerce-modal__header">
                <span class="commerce-modal__eyebrow">Media library</span>
                <h2 class="commerce-modal__title" id="commerceMediaModalTitle">Choose product imagery</h2>
                <p class="commerce-modal__description" id="commerceMediaModalDescription">Browse uploaded assets and select images to feature on your products.</p>
            </header>
            <div class="commerce-modal__body">
                <div class="commerce-media-picker">
                    <div class="commerce-media-toolbar">
                        <label class="commerce-media-search" for="commerceMediaSearch">
                            <i class="fa-solid fa-search" aria-hidden="true"></i>
                            <span class="sr-only">Search media library</span>
                            <input type="search" id="commerceMediaSearch" placeholder="Search media library" data-commerce-initial-focus>
                        </label>
                        <p class="commerce-media-hint" id="commerceMediaSelectionHint">Select an image to insert it.</p>
                    </div>
                    <div class="commerce-media-grid" id="commerceMediaGrid" role="listbox" aria-live="polite" aria-busy="false"></div>
                </div>
            </div>
        </div>
    </div>
    <script type="application/json" id="commerceDataset"><?php echo $context['datasetJson']; ?></script>
</div>
