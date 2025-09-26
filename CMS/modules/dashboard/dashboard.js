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
            updateText('#statPagesBreakdown', [data.pagesPublished, data.pagesDrafts, data.pagesScheduled], function(values){
                const published = formatNumber(values[0]);
                const drafts = formatNumber(values[1]);
                const scheduled = formatNumber(values[2]);
                return `Published: ${published} • Drafts: ${drafts} • Scheduled: ${scheduled}`;
            });
            updateText('#statMedia', data.media);
            updateText('#statUsers', data.users);
            updateText('#statUsersBreakdown', [data.usersAdmins, data.usersEditors, data.usersInactive], function(values){
                const admins = formatNumber(values[0]);
                const editors = formatNumber(values[1]);
                const inactive = formatNumber(values[2]);
                return `Admins: ${admins} • Editors: ${editors} • Inactive: ${inactive}`;
            });
            updateText('#statViews', data.views);
            updateText('#statViewsAverage', data.averageViews, function(value){
                return `Average per page: ${formatNumber(value)}`;
            });

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

            updateText('#statBlogPosts', data.blogsTotal);
            updateText('#statBlogBreakdown', [data.blogsPublished, data.blogsDrafts, data.blogsScheduled], function(values){
                const published = formatNumber(values[0]);
                const drafts = formatNumber(values[1]);
                const scheduled = formatNumber(values[2]);
                return `Published: ${published} • Drafts: ${drafts} • Scheduled: ${scheduled}`;
            });

            updateText('#statForms', data.formsTotal);
            updateText('#statFormsBreakdown', [data.formsFields, data.formsRequiredFields], function(values){
                const fields = formatNumber(values[0]);
                const required = formatNumber(values[1]);
                return `Fields: ${fields} • Required: ${required}`;
            });

            updateText('#statMenus', data.menusTotal);
            updateText('#statMenusBreakdown', [data.menusItems, data.menusNestedGroups], function(values){
                const items = formatNumber(values[0]);
                const nested = formatNumber(values[1]);
                return `Items: ${items} • Nested groups: ${nested}`;
            });

            updateText('#statSettings', data.settingsCount);
            updateText('#statSettingsBreakdown', data.settingsSocialProfiles, function(value){
                return `Social profiles: ${formatNumber(value)}`;
            });

            updateText('#statSitemap', data.sitemapUrls);
            updateText('#statSitemapBreakdown', data.sitemapLastGenerated, function(value){
                if (!value) {
                    return 'Last generated: Not yet generated';
                }
                return `Last generated: ${value}`;
            });

            updateText('#statLogs', data.logsEntries);
            updateText('#statLogsBreakdown', data.logsLastActivity, function(value){
                if (!value) {
                    return 'Last activity: No history recorded';
                }
                return `Last activity: ${value}`;
            });

            const $topPages = $('#statTopPages');
            if ($topPages.length) {
                $topPages.empty();
                if (Array.isArray(data.topPages) && data.topPages.length) {
                    data.topPages.forEach(function(page){
                        const title = page.title || 'Untitled Page';
                        const views = formatNumber(page.views || 0);
                        const status = page.published ? 'Published' : 'Draft';
                        const $item = $('<li/>');
                        $('<span/>').addClass('stat-list-title').text(title).appendTo($item);
                        $('<span/>').addClass('stat-list-meta').text(`${views} views • ${status}`).appendTo($item);
                        $topPages.append($item);
                    });
                } else {
                    $topPages.append($('<li/>').addClass('stat-list-empty').text('No page analytics available yet.'));
                }
            }
        });
    }
    loadStats();
});
