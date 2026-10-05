import './bootstrap';
import './media-play-guard';

import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import { createIcons, icons } from 'lucide';

// flatpickr
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
// FullCalendar
import { Calendar } from '@fullcalendar/core';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

marked.setOptions({
    gfm: true,
    breaks: true,
});

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.renderAiComparisonChart = function (element, chartData, metricLabel) {
    if (!element || !chartData || !window.ApexCharts
        || !Array.isArray(chartData.categories) || chartData.categories.length !== 2
        || !Array.isArray(chartData.series) || chartData.series.length !== 1
        || !Array.isArray(chartData.statuses) || chartData.statuses.length !== 2
        || chartData.statuses.some(status => !['verified zero activity', 'activity recorded', 'history unavailable'].includes(status))
        || typeof chartData.unit !== 'string'
        || chartData.series.some(series => !Array.isArray(series.data)
            || series.data.length !== 2
            || series.data.some(value => value !== null && !Number.isFinite(value)))) {
        return null;
    }

    const chart = new ApexCharts(element, {
        chart: { type: 'bar', height: 240, toolbar: { show: false } },
        plotOptions: { bar: { columnWidth: '48%', borderRadius: 2 } },
        dataLabels: { enabled: false },
        series: chartData.series,
        xaxis: { categories: chartData.categories },
        yaxis: { title: { text: `${metricLabel} (${chartData.unit})` } },
        tooltip: {
            y: {
                formatter: (value, { dataPointIndex }) => chartData.statuses[dataPointIndex] === 'history unavailable'
                    ? 'history unavailable'
                    : `${value} (${chartData.statuses[dataPointIndex]})`,
            },
        },
        noData: { text: 'No verified activity to chart' },
        legend: { position: 'bottom' },
        colors: ['#059669', '#d97706', '#0284c7', '#be123c', '#65a30d', '#9333ea'],
    });
    chart.render();

    return chart;
};
window.flatpickr = flatpickr;
window.FullCalendar = Calendar;
window.lucide = { createIcons, icons };
window.renderMarkdown = function (text) {
    if (!text) return '';
    try {
        const rawHtml = marked.parse(text);
        return DOMPurify.sanitize(rawHtml);
    } catch (e) {
        return text;
    }
};

Alpine.start();

// Initialize components on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Initialize any client-side Lucide icon tags (<i data-lucide="..."></i>)
    createIcons({ icons });
    // Map imports
    if (document.querySelector('#mapOne')) {
        import('./components/map').then(module => module.initMap());
    }

    // Chart imports
    if (document.querySelector('#chartOne')) {
        import('./components/chart/chart-1').then(module => module.initChartOne());
    }
    if (document.querySelector('#chartTwo')) {
        import('./components/chart/chart-2').then(module => module.initChartTwo());
    }
    if (document.querySelector('#chartThree')) {
        import('./components/chart/chart-3').then(module => module.initChartThree());
    }
    if (document.querySelector('#chartSix')) {
        import('./components/chart/chart-6').then(module => module.initChartSix());
    }
    if (document.querySelector('#chartEight')) {
        import('./components/chart/chart-8').then(module => module.initChartEight());
    }
    if (document.querySelector('#chartThirteen')) {
        import('./components/chart/chart-13').then(module => module.initChartThirteen());
    }
    if (document.querySelector('#adminInventoryChart, #adminUserRoleChart, #adminAccountStatusChart, #adminMaintenanceStatusChart, #adminInventoryStatusChart, #adminAuditActivityChart, #adminPendingRequestChart, #adminInventoryConditionChart')) {
        import('./components/chart/admin-dashboard').then(module => module.initAdminDashboard());
    }
    if (document.querySelector('#adminSystemRoleChart, #adminSystemRequestChart, #adminSystemMaintenanceChart')) {
        import('./components/chart/admin-reports').then(module => module.initAdminReports());
    }
    if (document.querySelector('#custodianCategoryChart')) {
        import('./components/chart/custodian-dashboard').then(module => module.initCustodianDashboard());
    }
    if (document.querySelector('#custodianReportStatusChart, #custodianReportCategoryChart, #custodianReportMovementChart, #custodianReportLifecycleChart')) {
        import('./components/chart/custodian-reports').then(module => module.initCustodianReports());
    }
    if (document.querySelector('#schoolHeadCategoryChart')) {
        import('./components/chart/school-head-reports').then(module => module.initSchoolHeadReports());
    }
    if (document.querySelector('#schoolHeadDashboardCategoryChart, #schoolHeadInventoryCategoryChart')) {
        import('./components/chart/school-head-reports').then(module => module.initSchoolHeadOverviewCharts());
    }

    // Calendar init
    if (document.querySelector('#calendar')) {
        import('./components/calendar-init').then(module => module.calendarInit());
    }
});

// QR camera scanner (inventory page). Bundled locally so the camera works
// without reaching the html5-qrcode CDN. Exposed on window because the
// Blade/Alpine scanner calls it as a global. Loaded at module evaluation
// (not on DOMContentLoaded) so it is ready before the user opens the modal.
if (document.querySelector('#qr-camera-viewport') && !window.Html5Qrcode) {
    window.__html5QrcodeReady = import('html5-qrcode').then(module => {
        window.Html5Qrcode = module.Html5Qrcode ?? module.default;
        return window.Html5Qrcode;
    }).catch(() => null);
}
