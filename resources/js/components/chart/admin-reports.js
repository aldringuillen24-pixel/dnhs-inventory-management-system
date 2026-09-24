const readChartData = (selector) => {
    const element = document.querySelector(selector);

    return {
        element,
        labels: JSON.parse(element?.dataset.labels || '[]'),
        values: JSON.parse(element?.dataset.values || '[]').map(Number),
    };
};

const baseOptions = {
    chart: { height: 280, fontFamily: 'Outfit, sans-serif', toolbar: { show: false } },
    dataLabels: { enabled: false },
    legend: { position: 'bottom', fontFamily: 'Outfit, sans-serif' },
    stroke: { width: 0 },
};

const renderDonut = (data, colors, totalLabel) => {
    if (!data.element || !data.values.length) return;

    new ApexCharts(data.element, {
        ...baseOptions,
        series: data.values,
        labels: data.labels,
        colors,
        chart: { ...baseOptions.chart, type: 'donut' },
        plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: totalLabel } } } } },
        tooltip: { y: { formatter: value => value.toLocaleString() } },
    }).render();
};

const renderBar = (data, color, label) => {
    if (!data.element || !data.values.length) return;

    new ApexCharts(data.element, {
        ...baseOptions,
        series: [{ name: label, data: data.values }],
        colors: [color],
        chart: { ...baseOptions.chart, type: 'bar' },
        xaxis: { categories: data.labels, labels: { rotate: -30 } },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '52%' } },
        legend: { show: false },
        yaxis: { title: { text: label } },
        tooltip: { y: { formatter: value => value.toLocaleString() } },
    }).render();
};

export const initAdminReports = () => {
    renderDonut(readChartData('#adminSystemCategoryChart'), ['#0f766e', '#2563eb', '#d97706', '#be123c', '#475569', '#65a30d'], 'Inventory units');
    renderBar(readChartData('#adminSystemStatusChart'), '#0f766e', 'Units');
    renderDonut(readChartData('#adminSystemRoleChart'), ['#2563eb', '#0f766e', '#d97706', '#be123c', '#475569'], 'User accounts');
    renderBar(readChartData('#adminSystemRequestChart'), '#d97706', 'Requests');
    renderBar(readChartData('#adminSystemMaintenanceChart'), '#be123c', 'Records');
};

export default initAdminReports;