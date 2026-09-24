const colors = ['#0f766e', '#2563eb', '#d97706', '#be123c', '#475569', '#65a30d'];

const parse = (element, key) => JSON.parse(element?.dataset[key] || '[]');

const baseChart = {
    height: 300,
    fontFamily: 'Outfit, sans-serif',
    toolbar: { show: false },
};

const render = (element, options) => {
    if (element) new ApexCharts(element, options).render();
};

export const initCustodianReports = () => {
    const status = document.querySelector('#custodianReportStatusChart');
    const category = document.querySelector('#custodianReportCategoryChart');
    const lifecycle = document.querySelector('#custodianReportLifecycleChart');
    const movement = document.querySelector('#custodianReportMovementChart');

    if (status && parse(status, 'values').length) {
        render(status, {
            chart: { ...baseChart, type: 'donut' },
            series: parse(status, 'values').map(Number),
            labels: parse(status, 'labels'),
            colors,
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontFamily: 'Outfit, sans-serif' },
            stroke: { width: 0 },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Total units' } } } } },
            tooltip: { y: { formatter: value => `${value} units` } },
        });
    }

    if (category && parse(category, 'values').length) {
        render(category, {
            chart: { ...baseChart, type: 'bar' },
            series: [{ name: 'Units', data: parse(category, 'values').map(Number) }],
            colors: ['#0f766e'],
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: parse(category, 'labels') },
            yaxis: { labels: { maxWidth: 150 } },
            tooltip: { y: { formatter: value => `${value} units` } },
        });
    }

    if (lifecycle && parse(lifecycle, 'values').length) {
        render(lifecycle, {
            chart: { ...baseChart, type: 'bar' },
            series: [{ name: 'Units', data: parse(lifecycle, 'values').map(Number) }],
            colors: ['#16a34a', '#d97706', '#dc2626'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '48%', distributed: true } },
            dataLabels: { enabled: false },
            xaxis: { categories: parse(lifecycle, 'labels') },
            yaxis: { min: 0, forceNiceScale: true },
            tooltip: { y: { formatter: value => `${value} units` } },
        });
    }

    if (movement && parse(movement, 'labels').length) {
        render(movement, {
            chart: { ...baseChart, type: 'area' },
            series: [
                { name: 'Stock in / returns', data: parse(movement, 'stockIn').map(Number) },
                { name: 'Assignments / transfers', data: parse(movement, 'stockOut').map(Number) },
                { name: 'Disposals', data: parse(movement, 'disposals').map(Number) },
            ],
            colors: ['#0f766e', '#2563eb', '#be123c'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.32, opacityTo: 0.04 } },
            xaxis: { categories: parse(movement, 'labels'), labels: { rotate: -35 } },
            yaxis: { min: 0, forceNiceScale: true },
            tooltip: { y: { formatter: value => `${value} units` } },
        });
    }
};

export default initCustodianReports;