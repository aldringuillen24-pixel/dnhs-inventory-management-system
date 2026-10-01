@extends('layouts.app')

@section('content')
	<div class="space-y-5 pb-6">
		@php
			$summaryMetrics = [
				['title' => 'Total Inventory Items', 'value' => number_format($metrics['totalUnits']), 'subtitle' => 'Non-disposed inventory units', 'icon' => 'package', 'iconClass' => 'text-brand-600 bg-brand-50 dark:bg-brand-500/10 dark:text-brand-400', 'route' => 'schoolHead.inventory.overview'],
				['title' => 'Available Items', 'value' => number_format($metrics['availableUnits']), 'subtitle' => 'Ready for assignment', 'icon' => 'package-check', 'iconClass' => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400', 'route' => 'schoolHead.inventory.overview'],
				['title' => 'Assigned Items', 'value' => number_format($metrics['assignedUnits']), 'subtitle' => 'Currently in use', 'icon' => 'user-check', 'iconClass' => 'text-blue-600 bg-blue-50 dark:bg-blue-500/10 dark:text-blue-400', 'route' => 'schoolHead.inventory.overview'],
				['title' => 'Low-Stock Items', 'value' => number_format($metrics['lowStockItems']), 'subtitle' => 'Categories at three units or fewer', 'icon' => 'triangle-alert', 'iconClass' => 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-400', 'route' => 'schoolHead.inventory.overview'],
				['title' => 'Total Inventory Value', 'value' => 'PHP ' . number_format($metrics['totalValue'], 2), 'subtitle' => 'Recorded asset value', 'icon' => 'coins', 'iconClass' => 'text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10 dark:text-indigo-400', 'route' => 'schoolHead.reports'],
			];
			$healthItems = [
				['value' => number_format($metrics['approachingLifespan']), 'title' => 'Items Nearing End of Life', 'subtitle' => 'Expected within the next 12 months', 'icon' => 'clock-3', 'iconClass' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400', 'route' => 'schoolHead.reports'],
				['value' => number_format($metrics['expiredLifespan']), 'title' => 'Items Past Expected End Date', 'subtitle' => 'Require review and planning', 'icon' => 'calendar-x-2', 'iconClass' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400', 'route' => 'schoolHead.reports'],
				['value' => number_format($metrics['attentionItems']), 'title' => 'Condition Items', 'subtitle' => 'Damaged, inspected, or under repair', 'icon' => 'shield-alert', 'iconClass' => 'bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400', 'route' => 'schoolHead.inventory.overview'],
			];
			$alertItems = collect([
				['value' => $metrics['lowStockItems'], 'title' => 'Low-stock or out-of-stock items', 'subtitle' => 'Categories needing stock review', 'icon' => 'package-search', 'iconClass' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400', 'route' => 'schoolHead.inventory.overview'],
				['value' => $metrics['pendingRequests'], 'title' => 'Pending returns or approvals', 'subtitle' => 'Requests awaiting review', 'icon' => 'clipboard-clock', 'iconClass' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400'],
				['value' => $metrics['attentionItems'], 'title' => 'Damaged, lost, or under-repair items', 'subtitle' => 'Condition records requiring attention', 'icon' => 'alert-triangle', 'iconClass' => 'bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400', 'route' => 'schoolHead.inventory.overview'],
				['value' => $metrics['expiredLifespan'], 'title' => 'Items past expected end date', 'subtitle' => 'Lifecycle review required', 'icon' => 'calendar-x-2', 'iconClass' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400', 'route' => 'schoolHead.reports'],
			])->filter(fn ($item) => $item['value'] > 0)->values();
		@endphp

		<x-dashboard.dashboard-header
			title="School Head Dashboard"
			description="Read-only overview of inventory position, condition, and decisions requiring attention."
			breadcrumb-title="School Head Dashboard"
			:updated-at="'Updated ' . now()->format('h:i A')"
			:user-name="auth()->user()?->full_name ?? 'School Head'"
			role-label="School Head"
			:show-refresh="true"
		>
			<x-slot:actions>
				<div class="flex flex-wrap items-center gap-2">
					<a href="{{ route('schoolHead.inventory.overview') }}" class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition hover:border-brand-300 hover:text-brand-600 dark:border-gray-700 dark:text-gray-300 dark:hover:border-brand-500 dark:hover:text-brand-400">
						<i data-lucide="package-search" class="h-4 w-4"></i>
						Inventory Overview
					</a>
					<a href="{{ route('schoolHead.reports') }}" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3 py-2 text-xs font-semibold text-white transition hover:bg-brand-600">
						<i data-lucide="file-chart-column" class="h-4 w-4"></i>
						Reports
					</a>
				</div>
			</x-slot:actions>
		</x-dashboard.dashboard-header>

		<x-cards.kpi-summary :metrics="$summaryMetrics" />

		<x-dashboard.inventory-condition-chart
			title="Inventory Condition Summary"
			subtitle="Items requiring inspection, maintenance, or review"
			chart-id="schoolHeadInventoryConditionChart"
			details-route="schoolHead.inventory.overview"
			details-label="View inventory"
			empty-message="No damaged, under-repair, lost, or disposal-review items."
			:labels="$conditionData->pluck('label')->values()"
			:values="$conditionData->pluck('value')->values()"
		/>

		<x-dashboard.pending-request-chart
			title="Pending Requests Overview"
			subtitle="Requests awaiting review by workflow type"
			chart-id="schoolHeadPendingRequestChart"
			details-route="schoolHead.reports"
			details-label="View reports"
			empty-message="No pending item, transfer, or return requests."
			:request-data="$pendingRequestData"
		/>

		<div class="grid gap-5 xl:grid-cols-2">
			<x-dashboard.chart-card
				title="Inventory by Category"
				subtitle="Current non-disposed units."
				chart-id="schoolHeadDashboardCategoryChart"
				badge="By Category"
				empty-message="No inventory data available."
				:chart-data="['labels' => $categoryData->pluck('label')->values(), 'values' => $categoryData->pluck('quantity')->values()]"
			/>
			<x-dashboard.chart-card
				title="Inventory Condition Summary"
				subtitle="Current inventory distribution by status."
				chart-id="schoolHeadDashboardStatusChart"
				badge="By Condition"
				empty-message="No condition data available."
				:chart-data="['labels' => $statusData->pluck('label')->values(), 'values' => $statusData->pluck('quantity')->values()]"
			/>
		</div>

		<div class="grid gap-5 xl:grid-cols-2">
			<x-dashboard.inventory-health
				title="Inventory Health"
				subtitle="Lifecycle and condition summaries for planning."
				:items="$healthItems"
			/>
			<x-dashboard.inventory-health
				title="Alerts Requiring Attention"
				subtitle="Read-only alerts for School Head review."
				empty-message="No active alerts requiring attention."
				:items="$alertItems"
			/>
		</div>

		<x-dashboard.recent-activity-table
			title="Recent Inventory Activity"
			subtitle="Latest stock-in, stock-out, assignments, transfers, and returns."
			:activities="$recentActivity"
			empty-message="No recent inventory activity."
		/>
	</div>
@endsection
