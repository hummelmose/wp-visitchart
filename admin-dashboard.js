(function () {
    'use strict';

    var app = document.getElementById('lstats-app');
    var chartInstance = null;
    var previousTotal = null;

    function formatNumber(n) {
        try {
            return new Intl.NumberFormat(lstatsAdmin.locale || 'da-DK').format(n);
        } catch (e) {
            return n;
        }
    }

    function buildLayout() {
        var i18n = lstatsAdmin.i18n;
        app.innerHTML =
            '<div class="lstats-row">' +
                '<div class="lstats-card lstats-chart-card lstats-chart-full">' +
                    '<div class="lstats-label">' + escapeHtml(i18n.visitorsToday) + '</div>' +
                    '<div class="lstats-chart-wrap">' +
                        '<canvas id="lstats-chart"></canvas>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="lstats-row lstats-two-col">' +
                '<div class="lstats-col">' +
                    '<div class="lstats-card">' +
                        '<div class="lstats-label">' + escapeHtml(i18n.mostActivePagesNow) + '</div>' +
                        '<ul id="lstats-pages-list"></ul>' +
                    '</div>' +
                    '<div class="lstats-card">' +
                        '<div class="lstats-label">' + escapeHtml(i18n.trafficSourcesToday) + '</div>' +
                        '<ul class="lstats-bot-list" id="lstats-referrer-categories-list"></ul>' +
                    '</div>' +
                    '<div class="lstats-card">' +
                        '<div class="lstats-label">' + escapeHtml(i18n.devicesToday) + '</div>' +
                        '<ul class="lstats-bot-list" id="lstats-devices-list"></ul>' +
                    '</div>' +
                    '<div class="lstats-card">' +
                        '<div class="lstats-label">' + escapeHtml(i18n.botsRegistered) + '</div>' +
                        '<ul class="lstats-bot-list" id="lstats-bot-list"></ul>' +
                    '</div>' +
                '</div>' +
                '<div class="lstats-col">' +
                    '<div class="lstats-card">' +
                        '<div class="lstats-label">' + escapeHtml(i18n.mostVisitedToday) + '</div>' +
                        '<ul id="lstats-top-pages-list"></ul>' +
                    '</div>' +
                    '<div class="lstats-card">' +
                        '<div class="lstats-label">' + escapeHtml(i18n.topReferrers) + '</div>' +
                        '<ul id="lstats-referrer-domains-list"></ul>' +
                    '</div>' +
                '</div>' +
            '</div>';
    }

    function fetchJson(endpoint) {
        return fetch(lstatsAdmin.restUrl + endpoint, {
            headers: { 'X-WP-Nonce': lstatsAdmin.nonce },
        }).then(function (res) {
            return res.json();
        });
    }

    function updateLiveCount() {
        fetchJson('live-count').then(function (data) {
            var totalEl = document.getElementById('lstats-sticky-total');
            totalEl.textContent = formatNumber(data.total);

            if (previousTotal !== null && data.total !== previousTotal) {
                totalEl.classList.remove('lstats-flash');
                void totalEl.offsetWidth;
                totalEl.classList.add('lstats-flash');
                setTimeout(function () {
                    totalEl.classList.remove('lstats-flash');
                }, 600);
            }
            previousTotal = data.total;

            var botList = document.getElementById('lstats-bot-list');
            botList.innerHTML = '';
            if (data.bots && data.bots.length > 0) {
                data.bots.forEach(function (bot) {
                    var li = document.createElement('li');
                    li.innerHTML = '<span class="lstats-bot-name">' + escapeHtml(bot.name) + '</span>' +
                                    '<span class="lstats-bot-count-num">' + formatNumber(bot.count) + '</span>';
                    botList.appendChild(li);
                });
            }

            var list = document.getElementById('lstats-pages-list');
            list.innerHTML = '';
            if (data.pages.length === 0) {
                list.innerHTML = '<li class="lstats-empty">' + escapeHtml(lstatsAdmin.i18n.noActivePages) + '</li>';
            }
            data.pages.forEach(function (page, index) {
                var li = document.createElement('li');
                var num = String(index + 1).padStart(2, '0');
                var badge = page.trending ? ' <span class="lstats-trending-badge" title="' + escapeHtml(lstatsAdmin.i18n.trending) + '"><i class="fa-solid fa-fire"></i></span>' : '';
                var featuredBadge = page.featured ? ' <span class="lstats-featured-badge" title="' + escapeHtml(lstatsAdmin.i18n.featured) + '">⭐</span>' : '';
                li.innerHTML = '<span class="lstats-page-title"><span class="lstats-num">' + num + ':</span> ' +
                                '<a href="' + escapeHtml(page.url) + '" target="_blank" rel="noopener">' + escapeHtml(page.title) + '</a>' + badge + '</span>' +
                                (page.featured ? '<span class="lstats-featured-badge">⭐</span>' : '') +
                                '<span class="lstats-page-count">' + formatNumber(page.live) + '</span>';
                list.appendChild(li);
            });
        });
    }

    function formatTimeRange(label) {
        var parts = label.split(':');
        var h = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) + 5;
        if (m >= 60) {
            m -= 60;
            h = (h + 1) % 24;
        }
        var endLabel = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        return label + ' - ' + endLabel;
    }

    var verticalLinePlugin = {
        id: 'lstatsVerticalLine',
        afterDraw: function (chart) {
            if (chart.tooltip && chart.tooltip._active && chart.tooltip._active.length) {
                var ctx = chart.ctx;
                var x = chart.tooltip._active[0].element.x;
                var topY = chart.scales.y.top;
                var bottomY = chart.scales.y.bottom;
                ctx.save();
                ctx.beginPath();
                ctx.moveTo(x, topY);
                ctx.lineTo(x, bottomY);
                ctx.lineWidth = 1;
                ctx.setLineDash([4, 4]);
                ctx.strokeStyle = 'rgba(34, 113, 177, 0.7)';
                ctx.stroke();
                ctx.restore();
            }
        },
    };

    function updateHistoryChart() {
        fetchJson('today-history').then(function (data) {
            var labels = data.map(function (d) { return d.time; });
            var counts = data.map(function (d) { return d.count; });
            var pageviews = data.map(function (d) { return d.pageviews; });
            var prevCounts = data.map(function (d) { return d.prevCount; });
            var prevPageviews = data.map(function (d) { return d.prevPageviews; });

            var ctx = document.getElementById('lstats-chart').getContext('2d');

            if (chartInstance) {
                chartInstance.data.labels = labels;
                chartInstance.data.datasets[0].data = counts;
                chartInstance.data.datasets[1].data = pageviews;
                chartInstance.data.datasets[2].data = prevCounts;
                chartInstance.data.datasets[3].data = prevPageviews;
                chartInstance.update();
                return;
            }

            chartInstance = new Chart(ctx, {
                type: 'line',
                plugins: [verticalLinePlugin],
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: lstatsAdmin.i18n.uniqueVisitors,
                            data: counts,
                            borderColor: '#2271b1',
                            backgroundColor: 'rgba(34, 113, 177, 0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 0,
                            borderWidth: 2,
                            order: 2,
                        },
                        {
                            label: lstatsAdmin.i18n.pageviews,
                            data: pageviews,
                            borderColor: '#d63638',
                            backgroundColor: 'rgba(214, 54, 56, 0.05)',
                            fill: false,
                            tension: 0.3,
                            pointRadius: 0,
                            borderWidth: 2,
                            borderDash: [4, 4],
                            order: 2,
                        },
                        {
                            label: lstatsAdmin.i18n.uniqueVisitors + ' (' + lstatsAdmin.i18n.yesterday + ')',
                            data: prevCounts,
                            borderColor: 'rgba(150, 150, 150, 0.5)',
                            backgroundColor: 'rgba(150, 150, 150, 0.08)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 0,
                            borderWidth: 1,
                            order: 1,
                        },
                        {
                            label: lstatsAdmin.i18n.pageviews + ' (' + lstatsAdmin.i18n.yesterday + ')',
                            data: prevPageviews,
                            borderColor: 'rgba(150, 150, 150, 0.35)',
                            backgroundColor: 'rgba(0, 0, 0, 0)',
                            fill: false,
                            tension: 0.3,
                            pointRadius: 0,
                            borderWidth: 1,
                            borderDash: [3, 3],
                            order: 1,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: true, position: 'top', align: 'end' },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            itemSort: function(a, b) {
                                return a.datasetIndex - b.datasetIndex;
                            },
                            callbacks: {
                                title: function(tooltipItems) {
                                    return formatTimeRange(tooltipItems[0].label);
                                },
                            },
                        },
                    },
                    hover: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        x: { ticks: { maxTicksLimit: 12 } },
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                    },
                },
            });
        });
    }

    function updateTopPages() {
        fetchJson('top-pages').then(function (data) {
            var list = document.getElementById('lstats-top-pages-list');
            list.innerHTML = '';
            if (data.length === 0) {
                list.innerHTML = '<li class="lstats-empty">' + escapeHtml(lstatsAdmin.i18n.noDataToday) + '</li>';
                return;
            }
            data.forEach(function (page, index) {
                var li = document.createElement('li');
                var num = String(index + 1).padStart(2, '0');
                li.innerHTML = '<span class="lstats-page-title"><span class="lstats-num">' + num + ':</span> ' +
                                '<a href="' + escapeHtml(page.url) + '" target="_blank" rel="noopener">' + escapeHtml(page.title) + '</a></span>' +
                                (page.featured ? '<span class="lstats-featured-badge">⭐</span>' : '') +
                                '<span class="lstats-page-count">' + formatNumber(page.visitors) + '</span>';
                list.appendChild(li);
            });
        });
    }

    function updateReferrers() {
        fetchJson('referrers').then(function (data) {
            var i18n = lstatsAdmin.i18n;
            var categoryLabels = {
                direct: i18n.direct,
                search: i18n.search,
                social: i18n.social,
                other: i18n.other,
            };

            var catList = document.getElementById('lstats-referrer-categories-list');
            catList.innerHTML = '';
            var catTotal = ( data.categories.direct || 0 ) + ( data.categories.search || 0 ) +
                            ( data.categories.social || 0 ) + ( data.categories.other || 0 );
            ['direct', 'search', 'social', 'other'].forEach(function (key) {
                var li = document.createElement('li');
                var count = data.categories[key] || 0;
                var pct = catTotal > 0 ? ( ( count / catTotal ) * 100 ).toFixed(1) : 0;
                li.innerHTML = '<span class="lstats-page-title">' + escapeHtml(categoryLabels[key]) + ':</span>' +
                                '<span class="lstats-value-wrap"><span class="lstats-page-count">' + formatNumber(count) + '</span>' +
                                '<span class="lstats-page-pct">(' + pct + '%)</span></span>';
                catList.appendChild(li);
            });

            var domainList = document.getElementById('lstats-referrer-domains-list');
            domainList.innerHTML = '';
            if (!data.domains || data.domains.length === 0) {
                domainList.innerHTML = '<li class="lstats-empty">' + escapeHtml(i18n.noReferrers) + '</li>';
                return;
            }
            data.domains.forEach(function (ref, index) {
                var li = document.createElement('li');
                var num = String(index + 1).padStart(2, '0');
                li.innerHTML = '<span class="lstats-page-title"><span class="lstats-num">' + num + ':</span>' +
                                '<span class="lstats-page-domain">' + escapeHtml(ref.domain) + ':</span></span>' +
                                '<span class="lstats-page-count"> ' + formatNumber(ref.count) + '</span>';
                domainList.appendChild(li);
            });
        });
    }


    function updateInsights() {
        fetchJson('insights').then(function (data) {
            var i18n = lstatsAdmin.i18n;
            var deviceLabels = { mobile: i18n.mobile, tablet: i18n.tablet, desktop: i18n.desktop };

            var deviceList = document.getElementById('lstats-devices-list');
            deviceList.innerHTML = '';
            var deviceTotal = ( data.devices.mobile || 0 ) + ( data.devices.tablet || 0 ) + ( data.devices.desktop || 0 );
            ['mobile', 'tablet', 'desktop'].forEach(function (key) {
                var li = document.createElement('li');
                var count = data.devices[key] || 0;
                var pct = deviceTotal > 0 ? ( ( count / deviceTotal ) * 100 ).toFixed(1) : 0;
                li.innerHTML = '<span class="lstats-page-title">' + escapeHtml(deviceLabels[key]) + ':</span>' +
                                '<span class="lstats-value-wrap"><span class="lstats-page-count">' + formatNumber(count) + '</span>' +
                                '<span class="lstats-page-pct">(' + pct + '%)</span></span>';
                deviceList.appendChild(li);
            });

        });
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function refreshAll() {
        updateLiveCount();
        updateHistoryChart();
        updateTopPages();
        updateReferrers();
        updateInsights();
    }

    buildLayout();
    refreshAll();

    setInterval(updateLiveCount, 10000);
    setInterval(updateHistoryChart, 60000);
    setInterval(updateTopPages, 60000);
    setInterval(updateReferrers, 60000);
    setInterval(updateInsights, 60000);
})();

// 2.0.6
// 2.1.0
// 2.1.1
// 2.1.2
// 2.1.3
// 2.1.4
// 2.1.5
// 2.1.6
// 2.1.7
// 2.1.8
// 2.1.9
// 2.2.0
// 2.2.1
// 2.2.2
// 2.3.0
// 2.3.1
// 2.3.2
// 2.3.3
// 2.3.4
// 2.3.5
// 2.3.6
// 2.3.7
// 2.3.8
// 2.3.9
// 2.4.0
// 2.4.1
// 2.4.2
// 2.4.3
// 2.4.5
// 2.4.6
// 2.4.7
