(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.DashboardChartPages = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';

    const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();

    function pageMetric(metric, { query = '', page = 0, pageSize = 8 } = {}) {
        const size = Math.max(1, Math.floor(Number(pageSize) || 8));
        const search = normalize(query);
        // Index-based selection keeps duplicate labels and every series aligned.
        const indices = metric.labels.reduce((matches, label, index) => {
            if (normalize(label).includes(search)) matches.push(index);
            return matches;
        }, []);
        const total = indices.length;
        const pageCount = Math.max(1, Math.ceil(total / size));
        const current = Math.max(0, Math.min(pageCount - 1, Math.floor(Number(page) || 0)));
        const selected = indices.slice(current * size, (current + 1) * size);
        return {
            metric: {
                ...metric,
                labels: selected.map(index => metric.labels[index]),
                series: metric.series.map(series => ({ ...series, values: selected.map(index => series.values[index]) })),
            },
            page: current,
            pageCount,
            total,
            start: total ? current * size + 1 : 0,
            end: Math.min((current + 1) * size, total),
        };
    }

    function mount(root, metric, render) {
        const controls = root.querySelector('[data-chart-controls]');
        const search = root.querySelector('[data-chart-search]');
        const previous = root.querySelector('[data-chart-previous]');
        const next = root.querySelector('[data-chart-next]');
        const status = root.querySelector('[data-chart-range]');
        const chart = root.querySelector('[data-chart-canvas]');
        let source = metric;
        let page = 0;

        const refresh = (nextMetric = source) => {
            source = nextMetric;
            const result = pageMetric(source, { query: search.value, page });
            page = result.page;
            controls.hidden = source.labels.length <= 8;
            previous.disabled = page === 0;
            next.disabled = page + 1 >= result.pageCount;
            status.textContent = result.total
                ? `${result.start}–${result.end} of ${result.total} municipalities${search.value.trim() ? ' matching your search' : ''}`
                : 'No municipalities match. Clear the search to see all municipalities.';
            chart.hidden = result.total === 0;
            render(result.metric);
        };
        search.addEventListener('input', () => { page = 0; refresh(); });
        previous.addEventListener('click', () => { page -= 1; refresh(); });
        next.addEventListener('click', () => { page += 1; refresh(); });
        refresh();
        return refresh;
    }

    return { pageMetric, mount };
});
