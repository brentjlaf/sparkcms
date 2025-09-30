<?php
// File: modules/ecommerce/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_login();

$ecommerceFile = __DIR__ . '/../../data/ecommerce.json';
$rawData = read_json_file($ecommerceFile);
if (!is_array($rawData)) {
    $rawData = [];
}

$defaultData = [
    'metrics' => [
        'total_products' => 0,
        'orders_today' => 0,
        'revenue_this_month' => 0,
        'average_order_value' => 0,
        'test_mode' => false,
    ],
    'products' => [],
    'orders' => [],
    'customers' => [],
    'cart_settings' => [
        'enabled' => true,
        'display' => 'sidebar',
        'upsell_message' => '',
        'continue_shopping_url' => '/',
    ],
    'checkout_settings' => [
        'required_fields' => ['name', 'email'],
        'allow_guest_checkout' => false,
        'tax_mode' => 'percentage',
        'tax_value' => 0,
        'shipping_rate' => 0,
        'free_shipping_threshold' => null,
        'estimated_delivery_note' => '',
    ],
    'integrations' => [
        'payments' => [
            'paypal' => ['enabled' => false, 'api_key' => ''],
            'stripe' => ['enabled' => false, 'api_key' => ''],
            'square' => ['enabled' => false, 'api_key' => ''],
        ],
        'notifications' => [
            'order_placed' => '',
            'order_shipped' => '',
            'order_refunded' => '',
            'webhook_endpoint' => '',
        ],
    ],
    'reports' => [
        'sales' => [
            'daily' => [],
            'weekly' => [],
            'monthly' => [],
        ],
        'top_products' => [],
    ],
    'roles' => [],
    'rest_hooks' => [
        'products' => '/api/ecommerce/products',
        'orders' => '/api/ecommerce/orders',
        'integrations' => '/api/ecommerce/integrations',
    ],
];

$data = array_replace_recursive($defaultData, $rawData);

$availableViews = ['dashboard', 'products', 'orders', 'customers', 'reports', 'settings'];
$view = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (!is_string($view) || !in_array($view, $availableViews, true)) {
    $view = 'dashboard';
}

$products = array_values(array_filter($data['products'], static function ($product) {
    return is_array($product);
}));

$orders = array_values(array_filter($data['orders'], static function ($order) {
    return is_array($order);
}));

usort($orders, static function ($a, $b) {
    $timeA = isset($a['submitted_at']) ? strtotime((string) $a['submitted_at']) : 0;
    $timeB = isset($b['submitted_at']) ? strtotime((string) $b['submitted_at']) : 0;
    return $timeB <=> $timeA;
});

$recentOrders = array_slice($orders, 0, 5);

$customers = array_values(array_filter($data['customers'], static function ($customer) {
    return is_array($customer);
}));

$formatCurrency = static function ($amount): string {
    if (!is_numeric($amount)) {
        return '$0.00';
    }
    return '$' . number_format((float) $amount, 2);
};

$metrics = $data['metrics'];

$settings = get_site_settings();
$currencySymbol = '$';
if (!empty($settings['store_currency_symbol'])) {
    $currencySymbol = (string) $settings['store_currency_symbol'];
}

$formatCurrencyWithSymbol = static function ($amount) use ($currencySymbol) {
    if (!is_numeric($amount)) {
        $amount = 0;
    }
    $precision = floor($amount) !== (float) $amount ? 2 : 0;
    return $currencySymbol . number_format((float) $amount, $precision);
};

$orderStatuses = [
    'pending' => 'Pending',
    'paid' => 'Paid',
    'shipped' => 'Shipped',
    'refunded' => 'Refunded',
];

$productStatuses = [
    'active' => 'Active',
    'draft' => 'Draft',
    'out_of_stock' => 'Out of Stock',
];

$requiredFields = $data['checkout_settings']['required_fields'];
if (!is_array($requiredFields)) {
    $requiredFields = [];
}

$breadcrumbs = [
    ['label' => 'E-commerce', 'view' => 'dashboard'],
];
if ($view !== 'dashboard') {
    $breadcrumbs[] = ['label' => ucfirst($view), 'view' => $view];
}

$restHooks = $data['rest_hooks'];
?>
<div class="content-section" id="ecommerce" data-active-view="<?php echo htmlspecialchars($view); ?>" data-currency="<?php echo htmlspecialchars($currencySymbol); ?>">
    <div class="module-breadcrumbs" aria-label="Breadcrumb">
        <ol class="module-breadcrumbs-list">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <li class="module-breadcrumbs-item">
                    <?php if ($index === count($breadcrumbs) - 1): ?>
                        <span aria-current="page"><?php echo htmlspecialchars($crumb['label']); ?></span>
                    <?php else: ?>
                        <a href="#" class="ecommerce-breadcrumb" data-view="<?php echo htmlspecialchars($crumb['view']); ?>">
                            <?php echo htmlspecialchars($crumb['label']); ?>
                        </a>
                        <span class="module-breadcrumbs-separator" aria-hidden="true">/</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>

    <div class="ecommerce-shell">
        <header class="ecommerce-hero">
            <div class="ecommerce-hero-body">
                <div>
                    <span class="hero-eyebrow ecommerce-eyebrow">Commerce Control Center</span>
                    <h2 class="ecommerce-title">E-commerce Module</h2>
                    <p class="ecommerce-subtitle">
                        Oversee products, transactions, and customer experiences with a comprehensive operational console.
                    </p>
                </div>
                <div class="ecommerce-hero-meta">
                    <div class="ecommerce-rest-endpoints" role="list" aria-label="REST hooks">
                        <?php foreach ($restHooks as $resource => $endpoint): ?>
                            <span class="ecommerce-rest-endpoint" role="listitem">
                                <strong><?php echo htmlspecialchars(ucfirst($resource)); ?>:</strong>
                                <code><?php echo htmlspecialchars((string) $endpoint); ?></code>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($metrics['test_mode'])): ?>
                        <span class="ecommerce-test-mode" role="status" aria-live="polite">
                            <i class="fa-solid fa-flask" aria-hidden="true"></i>
                            Test mode active
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <nav class="ecommerce-subnav" aria-label="E-commerce sections">
                <ul class="ecommerce-subnav-list">
                    <?php foreach ($availableViews as $subView): ?>
                        <li class="ecommerce-subnav-item">
                            <button type="button" class="ecommerce-subnav-button" data-view="<?php echo htmlspecialchars($subView); ?>"
                                    <?php if ($subView === $view): ?>aria-current="page"<?php endif; ?>>
                                <span><?php echo htmlspecialchars(ucfirst($subView)); ?></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </header>

        <section class="ecommerce-panel" data-panel="dashboard" <?php if ($view !== 'dashboard'): ?>hidden<?php endif; ?>>
            <header class="ecommerce-panel-header">
                <div>
                    <h3>Store pulse</h3>
                    <p>Monitor sales velocity and recent order activity at a glance.</p>
                </div>
                <div class="ecommerce-quick-actions">
                    <button type="button" class="a11y-btn a11y-btn--primary" data-action="add-product">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>Add product</span>
                    </button>
                    <button type="button" class="a11y-btn a11y-btn--secondary" data-action="view-orders">
                        <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                        <span>View orders</span>
                    </button>
                </div>
            </header>
            <div class="ecommerce-kpi-grid" aria-live="polite">
                <article class="ecommerce-kpi-card">
                    <h4>Total products</h4>
                    <div class="ecommerce-kpi-value"><?php echo (int) $metrics['total_products']; ?></div>
                </article>
                <article class="ecommerce-kpi-card">
                    <h4>Orders today</h4>
                    <div class="ecommerce-kpi-value"><?php echo (int) $metrics['orders_today']; ?></div>
                </article>
                <article class="ecommerce-kpi-card">
                    <h4>Revenue this month</h4>
                    <div class="ecommerce-kpi-value"><?php echo htmlspecialchars($formatCurrencyWithSymbol($metrics['revenue_this_month'])); ?></div>
                </article>
                <article class="ecommerce-kpi-card">
                    <h4>Average order value</h4>
                    <div class="ecommerce-kpi-value"><?php echo htmlspecialchars($formatCurrencyWithSymbol($metrics['average_order_value'])); ?></div>
                </article>
            </div>
            <div class="ecommerce-dashboard-grid">
                <section class="ecommerce-dashboard-card">
                    <header>
                        <h4>Recent orders</h4>
                        <p>Latest five transactions with fulfillment status.</p>
                    </header>
                    <ol class="ecommerce-recent-orders" aria-live="polite">
                        <?php if (empty($recentOrders)): ?>
                            <li class="ecommerce-empty-state">No orders have been received yet.</li>
                        <?php endif; ?>
                        <?php foreach ($recentOrders as $order): ?>
                            <li class="ecommerce-recent-order">
                                <div class="recent-order-primary">
                                    <strong><?php echo htmlspecialchars($order['customer'] ?? 'Unknown customer'); ?></strong>
                                    <span class="recent-order-total"><?php echo htmlspecialchars($formatCurrencyWithSymbol($order['total'] ?? 0)); ?></span>
                                </div>
                                <div class="recent-order-meta">
                                    <span class="recent-order-id"><?php echo htmlspecialchars($order['id'] ?? ''); ?></span>
                                    <span class="recent-order-status status-<?php echo htmlspecialchars($order['status'] ?? 'pending'); ?>">
                                        <?php echo htmlspecialchars($orderStatuses[$order['status']] ?? ucfirst((string) ($order['status'] ?? 'pending'))); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
                <section class="ecommerce-dashboard-card">
                    <header>
                        <h4>Operational quick notes</h4>
                        <p>Flag items that need attention for the operations team.</p>
                    </header>
                    <ul class="ecommerce-ops-list">
                        <li>Inventory sync connected to <code><?php echo htmlspecialchars($restHooks['products']); ?></code></li>
                        <li>Webhook health: <strong><?php echo !empty($data['integrations']['notifications']['webhook_endpoint']) ? 'Active' : 'Not configured'; ?></strong></li>
                        <li>Test transactions visible because test mode is <?php echo !empty($metrics['test_mode']) ? 'enabled' : 'disabled'; ?>.</li>
                    </ul>
                </section>
            </div>
        </section>

        <section class="ecommerce-panel" data-panel="products" <?php if ($view !== 'products'): ?>hidden<?php endif; ?>>
            <header class="ecommerce-panel-header">
                <div>
                    <h3>Product catalog</h3>
                    <p>Manage product lifecycle, pricing, and merchandising.</p>
                </div>
                <div class="ecommerce-panel-toolbar">
                    <div class="ecommerce-bulk-actions" role="group" aria-label="Bulk product actions">
                        <button type="button" class="a11y-btn a11y-btn--ghost" data-bulk="publish">Publish</button>
                        <button type="button" class="a11y-btn a11y-btn--ghost" data-bulk="unpublish">Unpublish</button>
                        <button type="button" class="a11y-btn a11y-btn--danger" data-bulk="delete">Delete</button>
                    </div>
                    <button type="button" class="a11y-btn a11y-btn--primary" data-action="open-product-drawer">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>New product</span>
                    </button>
                </div>
            </header>

            <div class="ecommerce-table-wrapper">
                <table class="ecommerce-table" aria-describedby="productsTableCaption" data-endpoint="<?php echo htmlspecialchars($restHooks['products']); ?>">
                    <caption id="productsTableCaption" class="sr-only">Product catalog overview</caption>
                    <thead>
                        <tr>
                            <th scope="col">
                                <input type="checkbox" class="ecommerce-checkbox" id="productSelectAll" aria-label="Select all products">
                            </th>
                            <th scope="col">Product Name</th>
                            <th scope="col">SKU</th>
                            <th scope="col">Price</th>
                            <th scope="col">Stock</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" class="ecommerce-empty-state">No products available. Create a new product to get started.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($products as $product): ?>
                            <tr data-product-id="<?php echo (int) ($product['id'] ?? 0); ?>">
                                <td><input type="checkbox" class="ecommerce-checkbox ecommerce-row-select" aria-label="Select <?php echo htmlspecialchars($product['name'] ?? 'product'); ?>"></td>
                                <th scope="row">
                                    <div class="ecommerce-product-name">
                                        <span class="product-title"><?php echo htmlspecialchars($product['name'] ?? 'Untitled product'); ?></span>
                                        <span class="product-updated">Updated <?php echo htmlspecialchars(date('M j, Y', strtotime((string) ($product['updated_at'] ?? 'now')))); ?></span>
                                    </div>
                                </th>
                                <td><?php echo htmlspecialchars($product['sku'] ?? '—'); ?></td>
                                <td>
                                    <?php
                                    $price = $product['price'] ?? 0;
                                    $sale = $product['sale_price'] ?? null;
                                    if (is_numeric($sale) && $sale > 0 && $sale < $price) {
                                        echo '<span class="price-sale">' . htmlspecialchars($formatCurrencyWithSymbol($sale)) . '</span>';
                                        echo '<span class="price-original">' . htmlspecialchars($formatCurrencyWithSymbol($price)) . '</span>';
                                    } else {
                                        echo htmlspecialchars($formatCurrencyWithSymbol($price));
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="stock-count"><?php echo (int) ($product['stock'] ?? 0); ?></span>
                                </td>
                                <td>
                                    <span class="status-pill status-<?php echo htmlspecialchars($product['status'] ?? 'draft'); ?>">
                                        <?php echo htmlspecialchars($productStatuses[$product['status']] ?? ucfirst((string) ($product['status'] ?? 'draft'))); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="ecommerce-row-actions" role="group" aria-label="Product actions">
                                        <button type="button" class="a11y-btn a11y-btn--ghost" data-action="edit">Edit</button>
                                        <button type="button" class="a11y-btn a11y-btn--ghost" data-action="duplicate">Duplicate</button>
                                        <button type="button" class="a11y-btn a11y-btn--ghost" data-action="view">View</button>
                                        <button type="button" class="a11y-btn a11y-btn--danger" data-action="delete">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <aside class="ecommerce-product-drawer" id="productDrawer" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="productDrawerTitle" aria-describedby="productDrawerDescription">
                <div class="ecommerce-drawer-content">
                    <header class="ecommerce-drawer-header">
                        <div>
                            <h4 id="productDrawerTitle">Create product</h4>
                            <p id="productDrawerDescription">Complete the sections below to publish a new product to your storefront.</p>
                        </div>
                        <button type="button" class="ecommerce-drawer-close" data-action="close-product-drawer" aria-label="Close product drawer">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </header>
                    <form class="ecommerce-form" id="productForm" data-endpoint="<?php echo htmlspecialchars($restHooks['products']); ?>">
                        <section class="ecommerce-form-section">
                            <h5>Basic information</h5>
                            <div class="ecommerce-field">
                                <label for="productName">Product name</label>
                                <input type="text" id="productName" name="name" required>
                            </div>
                            <div class="ecommerce-field">
                                <label for="productSku">SKU <span class="field-meta">Auto-generated, editable</span></label>
                                <div class="sku-input-group">
                                    <input type="text" id="productSku" name="sku" data-autogenerate="true" placeholder="AUTO-GEN-0001">
                                    <button type="button" class="a11y-btn a11y-btn--ghost" id="regenerateSku">Regenerate</button>
                                </div>
                            </div>
                            <div class="ecommerce-field">
                                <label for="productShortDescription">Short description</label>
                                <textarea id="productShortDescription" name="short_description" rows="3"></textarea>
                            </div>
                            <div class="ecommerce-field">
                                <label for="productLongDescription">Long description</label>
                                <div class="ecommerce-wysiwyg">
                                    <div class="ecommerce-wysiwyg-toolbar" role="toolbar" aria-label="Formatting controls">
                                        <button type="button" data-format="bold" aria-label="Bold"><i class="fa-solid fa-bold" aria-hidden="true"></i></button>
                                        <button type="button" data-format="italic" aria-label="Italic"><i class="fa-solid fa-italic" aria-hidden="true"></i></button>
                                        <button type="button" data-format="underline" aria-label="Underline"><i class="fa-solid fa-underline" aria-hidden="true"></i></button>
                                        <button type="button" data-format="insertUnorderedList" aria-label="Bullet list"><i class="fa-solid fa-list" aria-hidden="true"></i></button>
                                    </div>
                                    <div id="productLongDescription" class="ecommerce-wysiwyg-editor" contenteditable="true" role="textbox" aria-multiline="true"></div>
                                    <textarea name="long_description" id="productLongDescriptionInput" hidden></textarea>
                                </div>
                            </div>
                            <div class="ecommerce-field">
                                <label>Product imagery</label>
                                <div class="ecommerce-upload-dropzone" id="productImageDropzone">
                                    <input type="file" id="productImage" name="image" accept="image/*" aria-describedby="productImageHelp">
                                    <div class="dropzone-instructions">
                                        <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                                        <p>Drag &amp; drop an image or click to browse.</p>
                                        <small id="productImageHelp">PNG, JPG up to 5MB.</small>
                                    </div>
                                </div>
                                <div class="dropzone-file-info" id="productImageInfo" hidden></div>
                            </div>
                        </section>

                        <section class="ecommerce-form-section">
                            <h5>Pricing &amp; inventory</h5>
                            <div class="ecommerce-field-grid">
                                <div class="ecommerce-field">
                                    <label for="productPrice">Regular price</label>
                                    <input type="number" id="productPrice" name="price" min="0" step="0.01" required>
                                </div>
                                <div class="ecommerce-field">
                                    <label for="productSalePrice">Sale price <span class="field-meta">Optional</span></label>
                                    <input type="number" id="productSalePrice" name="sale_price" min="0" step="0.01">
                                </div>
                                <div class="ecommerce-field">
                                    <label for="productStock">Stock quantity</label>
                                    <input type="number" id="productStock" name="stock" min="0" step="1">
                                </div>
                                <div class="ecommerce-field">
                                    <label for="productLowStock">Low-stock alert threshold</label>
                                    <input type="number" id="productLowStock" name="low_stock_threshold" min="0" step="1">
                                </div>
                            </div>
                            <div class="ecommerce-toggle-field">
                                <input type="checkbox" id="productTrackInventory" name="track_inventory" checked>
                                <label for="productTrackInventory">Track inventory for this product</label>
                            </div>
                        </section>

                        <section class="ecommerce-form-section">
                            <h5>Categories &amp; tags</h5>
                            <div class="ecommerce-field">
                                <label for="productCategories">Categories</label>
                                <select id="productCategories" name="categories[]" multiple>
                                    <option value="Apparel">Apparel</option>
                                    <option value="Featured">Featured</option>
                                    <option value="Accessories">Accessories</option>
                                    <option value="Outerwear">Outerwear</option>
                                    <option value="Gifts">Gifts</option>
                                </select>
                                <small>Select multiple categories to control merchandising placements.</small>
                            </div>
                            <div class="ecommerce-field">
                                <label for="productTags">Tags</label>
                                <input type="text" id="productTags" name="tags" data-autocomplete='["cotton","summer","unisex","eco","hydration","denim","travel"]' placeholder="Type to add tags">
                                <small>Press enter to add tags. Suggestions will appear as you type.</small>
                            </div>
                        </section>

                        <section class="ecommerce-form-section">
                            <h5>Status &amp; publishing</h5>
                            <fieldset class="ecommerce-fieldset">
                                <legend>Visibility</legend>
                                <label class="ecommerce-radio">
                                    <input type="radio" name="status" value="draft" checked>
                                    <span>Draft</span>
                                </label>
                                <label class="ecommerce-radio">
                                    <input type="radio" name="status" value="active">
                                    <span>Publish immediately</span>
                                </label>
                                <label class="ecommerce-radio">
                                    <input type="radio" name="status" value="scheduled">
                                    <span>Schedule publish</span>
                                </label>
                                <div class="ecommerce-field schedule-field" hidden>
                                    <label for="productSchedule">Publish on</label>
                                    <input type="datetime-local" id="productSchedule" name="scheduled_for">
                                </div>
                            </fieldset>
                        </section>

                        <footer class="ecommerce-form-footer">
                            <button type="button" class="a11y-btn a11y-btn--ghost" data-action="close-product-drawer">Cancel</button>
                            <button type="submit" class="a11y-btn a11y-btn--primary">Save product</button>
                        </footer>
                    </form>
                </div>
            </aside>
        </section>

        <section class="ecommerce-panel" data-panel="orders" <?php if ($view !== 'orders'): ?>hidden<?php endif; ?>>
            <header class="ecommerce-panel-header">
                <div>
                    <h3>Orders management</h3>
                    <p>Track, fulfill, and export customer purchases.</p>
                </div>
                <div class="ecommerce-panel-toolbar">
                    <div class="ecommerce-bulk-actions" role="group" aria-label="Bulk order actions">
                        <button type="button" class="a11y-btn a11y-btn--ghost" data-bulk="status">Update status</button>
                        <button type="button" class="a11y-btn a11y-btn--ghost" data-bulk="export">Export selected</button>
                    </div>
                    <button type="button" class="a11y-btn a11y-btn--secondary" data-action="export-all">
                        <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                        <span>Export all (CSV/Excel)</span>
                    </button>
                </div>
            </header>
            <div class="ecommerce-filters" role="search">
                <div class="ecommerce-field">
                    <label for="orderStatusFilter">Status</label>
                    <select id="orderStatusFilter" data-filter="status">
                        <option value="">All</option>
                        <?php foreach ($orderStatuses as $statusValue => $statusLabel): ?>
                            <option value="<?php echo htmlspecialchars($statusValue); ?>"><?php echo htmlspecialchars($statusLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ecommerce-field">
                    <label for="orderPaymentFilter">Payment method</label>
                    <select id="orderPaymentFilter" data-filter="payment">
                        <option value="">All</option>
                        <?php
                        $paymentMethods = array_unique(array_map(static function ($order) {
                            return $order['payment_method'] ?? '';
                        }, $orders));
                        foreach ($paymentMethods as $method):
                            if ($method === '') {
                                continue;
                            }
                            ?>
                            <option value="<?php echo htmlspecialchars($method); ?>"><?php echo htmlspecialchars($method); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ecommerce-field">
                    <label for="orderDateStart">Start date</label>
                    <input type="date" id="orderDateStart" data-filter="date-start">
                </div>
                <div class="ecommerce-field">
                    <label for="orderDateEnd">End date</label>
                    <input type="date" id="orderDateEnd" data-filter="date-end">
                </div>
            </div>
            <div class="ecommerce-orders-layout">
                <div class="ecommerce-table-wrapper orders-table-wrapper">
                    <table class="ecommerce-table" id="ordersTable" aria-describedby="ordersTableCaption" data-endpoint="<?php echo htmlspecialchars($restHooks['orders']); ?>">
                        <caption id="ordersTableCaption" class="sr-only">Orders listing with filters</caption>
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" class="ecommerce-checkbox" id="orderSelectAll" aria-label="Select all orders"></th>
                                <th scope="col">Order ID</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Total</th>
                                <th scope="col">Status</th>
                                <th scope="col">Payment</th>
                                <th scope="col">Placed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" class="ecommerce-empty-state">No orders found for the selected filters.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($orders as $order): ?>
                                <tr data-order='<?php echo json_encode($order, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>
                                    <td><input type="checkbox" class="ecommerce-checkbox ecommerce-row-select"></td>
                                    <th scope="row"><?php echo htmlspecialchars($order['id'] ?? ''); ?></th>
                                    <td><?php echo htmlspecialchars($order['customer'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($formatCurrencyWithSymbol($order['total'] ?? 0)); ?></td>
                                    <td>
                                        <span class="status-pill status-<?php echo htmlspecialchars($order['status'] ?? 'pending'); ?>">
                                            <?php echo htmlspecialchars($orderStatuses[$order['status']] ?? ucfirst((string) ($order['status'] ?? 'pending'))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['payment_method'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars(!empty($order['submitted_at']) ? date('M j, Y g:i A', strtotime((string) $order['submitted_at'])) : '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <aside class="ecommerce-order-detail" id="orderDetail" aria-live="polite">
                    <div class="ecommerce-order-placeholder" id="orderPlaceholder">
                        <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                        <p>Select an order to view details.</p>
                    </div>
                    <div class="ecommerce-order-content" id="orderContent" hidden>
                        <header class="ecommerce-order-header">
                            <div>
                                <h4 id="orderDetailId">Order</h4>
                                <p id="orderDetailStatus">Status</p>
                            </div>
                            <div class="ecommerce-order-actions">
                                <button type="button" class="a11y-btn a11y-btn--secondary" data-action="mark-shipped">Mark as shipped</button>
                                <button type="button" class="a11y-btn a11y-btn--ghost" data-action="resend-invoice">Resend invoice</button>
                                <button type="button" class="a11y-btn a11y-btn--ghost" data-action="print-invoice">Print invoice</button>
                                <button type="button" class="a11y-btn a11y-btn--danger" data-action="refund-order">Refund</button>
                            </div>
                        </header>
                        <section class="ecommerce-order-section">
                            <h5>Customer</h5>
                            <address id="orderDetailCustomer"></address>
                        </section>
                        <section class="ecommerce-order-section">
                            <h5>Items</h5>
                            <table class="ecommerce-table order-items-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Product</th>
                                        <th scope="col">SKU</th>
                                        <th scope="col">Qty</th>
                                        <th scope="col">Price</th>
                                        <th scope="col">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="orderDetailItems"></tbody>
                            </table>
                        </section>
                        <section class="ecommerce-order-section">
                            <h5>Payment</h5>
                            <dl class="ecommerce-order-metadata">
                                <div>
                                    <dt>Total</dt>
                                    <dd id="orderDetailTotal"></dd>
                                </div>
                                <div>
                                    <dt>Tax</dt>
                                    <dd id="orderDetailTax"></dd>
                                </div>
                                <div>
                                    <dt>Shipping</dt>
                                    <dd id="orderDetailShipping"></dd>
                                </div>
                                <div>
                                    <dt>Transaction</dt>
                                    <dd id="orderDetailTransaction"></dd>
                                </div>
                            </dl>
                        </section>
                        <section class="ecommerce-order-section">
                            <h5>Shipping status</h5>
                            <ol class="ecommerce-shipping-timeline" id="orderDetailTimeline"></ol>
                        </section>
                    </div>
                </aside>
            </div>
        </section>

        <section class="ecommerce-panel" data-panel="customers" <?php if ($view !== 'customers'): ?>hidden<?php endif; ?>>
            <header class="ecommerce-panel-header">
                <div>
                    <h3>Customer insights</h3>
                    <p>Understand who is buying and how to personalise follow-up.</p>
                </div>
            </header>
            <div class="ecommerce-table-wrapper">
                <table class="ecommerce-table">
                    <thead>
                        <tr>
                            <th scope="col">Customer</th>
                            <th scope="col">Email</th>
                            <th scope="col">Lifetime value</th>
                            <th scope="col">Orders</th>
                            <th scope="col">Last order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="5" class="ecommerce-empty-state">No customers recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <th scope="row"><?php echo htmlspecialchars($customer['name'] ?? ''); ?></th>
                                <td><a href="mailto:<?php echo htmlspecialchars($customer['email'] ?? ''); ?>"><?php echo htmlspecialchars($customer['email'] ?? ''); ?></a></td>
                                <td><?php echo htmlspecialchars($formatCurrencyWithSymbol($customer['lifetime_value'] ?? 0)); ?></td>
                                <td><?php echo (int) ($customer['orders'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars(!empty($customer['last_order']) ? date('M j, Y g:i A', strtotime((string) $customer['last_order'])) : '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ecommerce-panel" data-panel="reports" <?php if ($view !== 'reports'): ?>hidden<?php endif; ?>>
            <header class="ecommerce-panel-header">
                <div>
                    <h3>Sales intelligence</h3>
                    <p>Track how your business is trending across time ranges.</p>
                </div>
                <div class="ecommerce-panel-toolbar">
                    <button type="button" class="a11y-btn a11y-btn--ghost" data-action="export-sales">Export dataset (CSV)</button>
                </div>
            </header>
            <div class="ecommerce-reports-grid">
                <section class="ecommerce-report-card" data-report="daily">
                    <header>
                        <h4>Daily sales</h4>
                        <p>Rolling seven-day revenue performance.</p>
                    </header>
                    <div class="ecommerce-chart" data-points='<?php echo json_encode($data['reports']['sales']['daily'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>
                        <canvas aria-label="Daily sales chart" role="img"></canvas>
                    </div>
                </section>
                <section class="ecommerce-report-card" data-report="weekly">
                    <header>
                        <h4>Weekly sales</h4>
                        <p>Quarter-to-date weekly totals.</p>
                    </header>
                    <div class="ecommerce-chart" data-points='<?php echo json_encode($data['reports']['sales']['weekly'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>
                        <canvas aria-label="Weekly sales chart" role="img"></canvas>
                    </div>
                </section>
                <section class="ecommerce-report-card" data-report="monthly">
                    <header>
                        <h4>Monthly sales</h4>
                        <p>Year-to-date revenue across each month.</p>
                    </header>
                    <div class="ecommerce-chart" data-points='<?php echo json_encode($data['reports']['sales']['monthly'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>
                        <canvas aria-label="Monthly sales chart" role="img"></canvas>
                    </div>
                </section>
                <section class="ecommerce-report-card" data-report="top-products">
                    <header>
                        <h4>Top-selling products</h4>
                        <p>Identify leading SKUs by units sold.</p>
                    </header>
                    <ol class="ecommerce-leaderboard">
                        <?php if (empty($data['reports']['top_products'])): ?>
                            <li class="ecommerce-empty-state">No product sales have been recorded.</li>
                        <?php endif; ?>
                        <?php foreach ($data['reports']['top_products'] as $product): ?>
                            <li>
                                <span class="leaderboard-product"><?php echo htmlspecialchars($product['name'] ?? ''); ?></span>
                                <span class="leaderboard-metric"><?php echo (int) ($product['units'] ?? 0); ?> units</span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            </div>
        </section>

        <section class="ecommerce-panel" data-panel="settings" <?php if ($view !== 'settings'): ?>hidden<?php endif; ?>>
            <header class="ecommerce-panel-header">
                <div>
                    <h3>Commerce configuration</h3>
                    <p>Control checkout, notifications, and access policies.</p>
                </div>
            </header>
            <div class="ecommerce-settings-grid">
                <section class="ecommerce-settings-card">
                    <header>
                        <h4>Cart settings</h4>
                        <p>Toggle cart availability and configure upsell messaging.</p>
                    </header>
                    <form class="ecommerce-form" id="cartSettingsForm">
                        <div class="ecommerce-toggle-field">
                            <input type="checkbox" id="cartEnabled" name="enabled" <?php echo !empty($data['cart_settings']['enabled']) ? 'checked' : ''; ?>>
                            <label for="cartEnabled">Enable cart</label>
                        </div>
                        <div class="ecommerce-field">
                            <label for="cartDisplay">Cart display</label>
                            <select id="cartDisplay" name="display">
                                <option value="sidebar" <?php echo ($data['cart_settings']['display'] ?? '') === 'sidebar' ? 'selected' : ''; ?>>Sidebar cart</option>
                                <option value="full" <?php echo ($data['cart_settings']['display'] ?? '') === 'full' ? 'selected' : ''; ?>>Full-page cart</option>
                            </select>
                        </div>
                        <div class="ecommerce-field">
                            <label for="cartUpsell">Upsell message</label>
                            <textarea id="cartUpsell" name="upsell_message" rows="3"><?php echo htmlspecialchars($data['cart_settings']['upsell_message'] ?? ''); ?></textarea>
                        </div>
                        <div class="ecommerce-field">
                            <label for="continueShopping">Continue shopping link</label>
                            <input type="url" id="continueShopping" name="continue_shopping_url" value="<?php echo htmlspecialchars($data['cart_settings']['continue_shopping_url'] ?? ''); ?>">
                        </div>
                        <footer class="ecommerce-form-footer">
                            <button type="submit" class="a11y-btn a11y-btn--primary">Save cart settings</button>
                        </footer>
                    </form>
                </section>

                <section class="ecommerce-settings-card">
                    <header>
                        <h4>Checkout settings</h4>
                        <p>Define data capture requirements and transactional rules.</p>
                    </header>
                    <form class="ecommerce-form" id="checkoutSettingsForm">
                        <fieldset class="ecommerce-fieldset">
                            <legend>Required customer fields</legend>
                            <?php
                            $fieldOptions = [
                                'name' => 'Full name',
                                'email' => 'Email address',
                                'shipping_address' => 'Shipping address',
                                'payment_method' => 'Payment method',
                                'phone' => 'Phone number',
                                'company' => 'Company name',
                            ];
                            foreach ($fieldOptions as $field => $label):
                                $checked = in_array($field, $requiredFields, true) ? 'checked' : '';
                                ?>
                                <label class="ecommerce-checkbox-field">
                                    <input type="checkbox" name="required_fields[]" value="<?php echo htmlspecialchars($field); ?>" <?php echo $checked; ?>>
                                    <span><?php echo htmlspecialchars($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <div class="ecommerce-toggle-field">
                            <input type="checkbox" id="guestCheckout" name="allow_guest_checkout" <?php echo !empty($data['checkout_settings']['allow_guest_checkout']) ? 'checked' : ''; ?>>
                            <label for="guestCheckout">Allow guest checkout</label>
                        </div>
                        <div class="ecommerce-field-grid">
                            <div class="ecommerce-field">
                                <label for="taxMode">Tax mode</label>
                                <select id="taxMode" name="tax_mode">
                                    <option value="percentage" <?php echo ($data['checkout_settings']['tax_mode'] ?? '') === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                                    <option value="flat" <?php echo ($data['checkout_settings']['tax_mode'] ?? '') === 'flat' ? 'selected' : ''; ?>>Flat amount</option>
                                </select>
                            </div>
                            <div class="ecommerce-field">
                                <label for="taxValue">Tax value</label>
                                <input type="number" id="taxValue" name="tax_value" step="0.01" value="<?php echo htmlspecialchars((string) ($data['checkout_settings']['tax_value'] ?? 0)); ?>">
                            </div>
                            <div class="ecommerce-field">
                                <label for="shippingRate">Shipping rate</label>
                                <input type="number" id="shippingRate" name="shipping_rate" step="0.01" value="<?php echo htmlspecialchars((string) ($data['checkout_settings']['shipping_rate'] ?? 0)); ?>">
                            </div>
                            <div class="ecommerce-field">
                                <label for="freeShippingThreshold">Free shipping threshold</label>
                                <input type="number" id="freeShippingThreshold" name="free_shipping_threshold" step="0.01" value="<?php echo htmlspecialchars((string) ($data['checkout_settings']['free_shipping_threshold'] ?? 0)); ?>">
                            </div>
                        </div>
                        <div class="ecommerce-field">
                            <label for="deliveryNote">Estimated delivery note</label>
                            <textarea id="deliveryNote" name="estimated_delivery_note" rows="2"><?php echo htmlspecialchars($data['checkout_settings']['estimated_delivery_note'] ?? ''); ?></textarea>
                        </div>
                        <footer class="ecommerce-form-footer">
                            <button type="submit" class="a11y-btn a11y-btn--primary">Save checkout settings</button>
                        </footer>
                    </form>
                </section>

                <section class="ecommerce-settings-card integrations-card">
                    <header>
                        <h4>Payments &amp; notifications</h4>
                        <p>Connect gateways and manage transactional messaging.</p>
                    </header>
                    <div class="ecommerce-integrations">
                        <div class="ecommerce-payment-grid">
                            <?php foreach ($data['integrations']['payments'] as $provider => $config): ?>
                                <div class="ecommerce-payment-card" data-provider="<?php echo htmlspecialchars($provider); ?>">
                                    <div class="payment-card-header">
                                        <h5><?php echo htmlspecialchars(ucfirst($provider)); ?></h5>
                                        <label class="switch">
                                            <input type="checkbox" <?php echo !empty($config['enabled']) ? 'checked' : ''; ?> data-provider-toggle="<?php echo htmlspecialchars($provider); ?>">
                                            <span class="slider" aria-hidden="true"></span>
                                            <span class="sr-only">Enable <?php echo htmlspecialchars($provider); ?></span>
                                        </label>
                                    </div>
                                    <div class="ecommerce-field">
                                        <label for="apiKey-<?php echo htmlspecialchars($provider); ?>">API key</label>
                                        <input type="password" id="apiKey-<?php echo htmlspecialchars($provider); ?>" value="<?php echo htmlspecialchars((string) ($config['api_key'] ?? '')); ?>" autocomplete="off">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="ecommerce-notification-editor">
                            <h5>Email notifications</h5>
                            <div class="notification-tabs" role="tablist">
                                <?php
                                $notificationTemplates = [
                                    'order_placed' => 'Order placed',
                                    'order_shipped' => 'Order shipped',
                                    'order_refunded' => 'Order refunded',
                                ];
                                $firstKey = array_key_first($notificationTemplates);
                                foreach ($notificationTemplates as $key => $label):
                                    $selected = $key === $firstKey ? 'true' : 'false';
                                    ?>
                                    <button type="button" role="tab" class="notification-tab" data-template="<?php echo htmlspecialchars($key); ?>" aria-selected="<?php echo $selected; ?>">
                                        <?php echo htmlspecialchars($label); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div class="notification-editor" role="tabpanel">
                                <?php foreach ($notificationTemplates as $key => $label): ?>
                                    <textarea data-template-editor="<?php echo htmlspecialchars($key); ?>" rows="4" <?php echo $key !== $firstKey ? 'hidden' : ''; ?>><?php echo htmlspecialchars((string) ($data['integrations']['notifications'][$key] ?? '')); ?></textarea>
                                <?php endforeach; ?>
                            </div>
                            <div class="notification-actions">
                                <button type="button" class="a11y-btn a11y-btn--secondary" data-action="preview-template">Preview</button>
                                <button type="button" class="a11y-btn a11y-btn--ghost" data-action="send-test">Send test</button>
                            </div>
                            <div class="ecommerce-field">
                                <label for="webhookEndpoint">Webhook endpoint</label>
                                <input type="url" id="webhookEndpoint" value="<?php echo htmlspecialchars((string) ($data['integrations']['notifications']['webhook_endpoint'] ?? '')); ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="ecommerce-settings-card roles-card">
                    <header>
                        <h4>User roles &amp; permissions</h4>
                        <p>Define how team members access commerce operations.</p>
                    </header>
                    <table class="ecommerce-table roles-table">
                        <thead>
                            <tr>
                                <th scope="col">Role</th>
                                <th scope="col">Description</th>
                                <th scope="col">Permissions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data['roles'])): ?>
                                <tr>
                                    <td colspan="3" class="ecommerce-empty-state">No roles defined.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($data['roles'] as $role): ?>
                                <tr>
                                    <th scope="row"><?php echo htmlspecialchars($role['name'] ?? ''); ?></th>
                                    <td><?php echo htmlspecialchars($role['description'] ?? ''); ?></td>
                                    <td>
                                        <?php if (!empty($role['permissions']) && is_array($role['permissions'])): ?>
                                            <ul class="role-permissions">
                                                <?php foreach ($role['permissions'] as $permission): ?>
                                                    <li><?php echo htmlspecialchars($permission); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <span>—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            </div>
        </section>
    </div>
</div>
