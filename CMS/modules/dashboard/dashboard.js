// File: dashboard.js
$(function(){
    function formatNumber(value) {
        if (typeof value === 'number') {
            return value.toLocaleString();
        }
        const numeric = parseInt(value, 10);
        if (Number.isNaN(numeric)) {
            return '0';
        }
        return numeric.toLocaleString();
    }

    function formatPercent(value) {
        if (typeof value !== 'number') {
            value = parseFloat(value);
        }
        if (!Number.isFinite(value)) {
            value = 0;
        }
        return `${Math.round(value)}%`;
    }

    function updateText(selector, value, formatter) {
        const $el = $(selector);
        if (!$el.length) {
            return;
        }
        const output = typeof formatter === 'function' ? formatter(value) : formatNumber(value);
        $el.text(output);
    }

    function loadStats(){
        $.getJSON('modules/dashboard/dashboard_data.php', function(data){
            updateText('#statPages', data.pages);
            updateText('#statMedia', data.media);
            updateText('#statUsers', data.users);
            updateText('#statViews', data.views);

            updateText('#statSeoScore', data.seoScore, formatPercent);
            updateText('#statSeoBreakdown', [data.seoOptimized, data.seoNeedsAttention], function(values){
                const optimized = formatNumber(values[0]);
                const attention = formatNumber(values[1]);
                return `Optimized: ${optimized} • Needs attention: ${attention}`;
            });
            updateText('#statSeoMetadata', data.seoMetadataGaps, function(value){
                return `Metadata gaps: ${formatNumber(value)}`;
            });

            updateText('#statAccessibilityScore', data.accessibilityScore, formatPercent);
            updateText('#statAccessibilityBreakdown', [data.accessibilityCompliant, data.accessibilityNeedsReview], function(values){
                const compliant = formatNumber(values[0]);
                const review = formatNumber(values[1]);
                return `Compliant: ${compliant} • Needs review: ${review}`;
            });
            updateText('#statAccessibilityAlt', data.accessibilityMissingAlt, function(value){
                return `Alt text issues: ${formatNumber(value)}`;
            });

            updateText('#statAlerts', data.openAlerts);
            updateText('#statAlertsBreakdown', [data.alertsSeo, data.alertsAccessibility], function(values){
                const seo = formatNumber(values[0]);
                const accessibility = formatNumber(values[1]);
                return `SEO: ${seo} • Accessibility: ${accessibility}`;
            });
        });
    }
    loadStats();
});
