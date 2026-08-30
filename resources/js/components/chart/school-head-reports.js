const readChartData = (selector) => {
    const element = document.querySelector(selector);

    return {
        element,
        labels: JSON.parse(element?.dataset.labels || '[]'),
        values: JSON.parse(element?.dataset.values || '[]').map(Number),
    };
};

const baseOptions = {
    chart: {
        height: 280,
        fontFamily: 'Outfit, sans-serif',
        toolbar: { show: false },
    },
    dataLabels: { enabled: false },
    legend: { position: 'bottom', fontFamily: 'Outfit, sans-serif' },
    stroke: { width: 0 },
    tooltip: { y: { formatter: value => `${value} units` } },
};

export const initSchoolHeadReports = () => {
    const categoryData = readChartData('#schoolHeadCategoryChart');
    const statusData = readChartData('#schoolHeadStatusChart');

    if (categoryData.element && categoryData.values.length) {
        new ApexCharts(categoryData.element, {
            ...baseOptions,
            series: categoryData.values,
            labels: categoryData.labels,
            colors: ['#0f766e', '#2563eb', '#d97706', '#be123c', '#475569', '#65a30d'],
            chart: { ...baseOptions.chart, type: 'donut' },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Total units' } } } } },
        }).render();
    }

    if (statusData.element && statusData.values.length) {
        new ApexCharts(statusData.element, {
            ...baseOptions,
            series: [{ name: 'Units', data: statusData.values }],
            xaxis: { categories: statusData.labels, labels: { rotate: -35 } },
            colors: ['#0f766e'],
            chart: { ...baseOptions.chart, type: 'bar' },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '48%', distributed: true } },
            legend: { show: false },
            yaxis: { title: { text: 'Units' } },
        }).render();
    }
};

export const initSchoolHeadOverviewCharts = () => {
    const dashboardData = readChartData('#schoolHeadDashboardCategoryChart');
    const inventoryElement = document.querySelector('#schoolHeadInventoryCategoryChart');

    if (dashboardData.element && dashboardData.values.length) {
        new ApexCharts(dashboardData.element, {
            ...baseOptions,
            series: dashboardData.values,
            labels: dashboardData.labels,
            colors: ['#0f766e', '#2563eb', '#d97706', '#be123c', '#475569', '#65a30d'],
            chart: { ...baseOptions.chart, type: 'donut' },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Total units' } } } } },
        }).render();
    }

    if (inventoryElement) {
        new ApexCharts(inventoryElement, {
            ...baseOptions,
            series: [
                { name: 'Total', data: JSON.parse(inventoryElement.dataset.total || '[]').map(Number) },
                { name: 'Available', data: JSON.parse(inventoryElement.dataset.available || '[]').map(Number) },
                { name: 'Assigned', data: JSON.parse(inventoryElement.dataset.assigned || '[]').map(Number) },
            ],
            xaxis: { categories: JSON.parse(inventoryElement.dataset.labels || '[]'), labels: { rotate: -35 } },
            colors: ['#475569', '#0f766e', '#d97706'],
            chart: { ...baseOptions.chart, type: 'bar' },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
            yaxis: { title: { text: 'Units' } },
        }).render();
    }
};

export default initSchoolHeadReports;