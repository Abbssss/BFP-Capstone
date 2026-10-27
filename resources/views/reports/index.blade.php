<x-app-layout>
    <x-slot name="header"><h2>Reports</h2></x-slot>

    <div class="ops-page report-page">
        <div class="report-layout">
            <nav class="ops-card report-tabs" aria-label="Report types">
                @foreach($reportOptions as $key => [$title, $description])
                    <a class="report-tab {{ $report === $key ? 'active' : '' }}" href="{{ route('reports.index', ['report' => $key, 'period' => $period, 'status' => $status]) }}" @if($report === $key) aria-current="page" @endif>
                        <span class="report-tab-icon" aria-hidden="true">{{ ['user-activity' => '♙', 'equipment-registry' => '⬡', 'maintenance-summary' => '⚒', 'inspection-summary' => '▤', 'audit-activity' => '▧'][$key] }}</span>
                        <span><strong>{{ $title }}</strong><small>{{ $description }}</small></span>
                        <span class="report-tab-arrow" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </nav>

            <section class="ops-card report-setup">
                <div class="ops-card-heading">Report setup · {{ $reportTitle }}</div>
                <form method="get" action="{{ route('reports.index') }}" class="report-form">
                    <input type="hidden" name="report" value="{{ $report }}">

                    <div class="report-filters">
                        <label class="report-field">
                            <span>Date range</span>
                            <select class="ops-filter" name="period">
                                <option value="quarter" @selected($period === 'quarter')>Current quarter · {{ now()->firstOfQuarter()->format('M') }}–{{ now()->format('M Y') }}</option>
                                <option value="30-days" @selected($period === '30-days')>Last 30 days</option>
                                <option value="year" @selected($period === 'year')>Year to date · {{ now()->format('Y') }}</option>
                                <option value="all" @selected($period === 'all')>All dates</option>
                            </select>
                        </label>

                        <label class="report-field">
                            <span>Status</span>
                            <select class="ops-filter" name="status">
                                <option value="">All statuses</option>
                                @foreach($statuses as $availableStatus)
                                    <option value="{{ $availableStatus }}" @selected($status === $availableStatus)>{{ $availableStatus }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="report-field">
                            <span>Group by</span>
                            <select class="ops-filter" name="group_by">
                                @foreach($groupOptions as $groupValue => $groupLabel)
                                    <option value="{{ $groupValue }}" @selected($group_by === $groupValue)>{{ $groupLabel }}</option>
                                @endforeach
                            </select>
                        </label>

                        @if(auth()->user()->role === 'super_admin')
                            <label class="report-field">
                                <span>Office code</span>
                                <select class="ops-filter" name="office_code">
                                    <option value="" @selected(blank($office_code))>All offices</option>
                                    @foreach($officeCodes as $availableOfficeCode)
                                        <option value="{{ $availableOfficeCode }}" @selected($office_code === $availableOfficeCode)>{{ $availableOfficeCode }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @else
                            <label class="report-field">
                                <span>Office code</span>
                                <input class="ops-filter" type="text" value="{{ $office_code ?: auth()->user()->office_code ?: 'Not assigned' }}" readonly>
                            </label>
                        @endif
                    </div>

                    <div class="report-actions">
                        <button type="submit" class="ops-button">Generate preview</button>
                        <button type="submit" class="ops-button secondary" formaction="{{ route('reports.export', ['format' => 'pdf']) }}" formmethod="get">
                            <span aria-hidden="true">▤</span> Export PDF
                        </button>
                        <button type="submit" class="ops-button secondary" formaction="{{ route('reports.export', ['format' => 'excel']) }}" formmethod="get">
                            <span aria-hidden="true">▦</span> Export Excel
                        </button>
                    </div>
                </form>

                @if($report === 'audit-activity')
                    <div class="report-preview report-preview-note">
                        <strong>Audit event logging is not configured.</strong>
                        <span>No persisted audit events are available to preview or export.</span>
                    </div>
                @else
                    <div class="report-preview">
                        <div class="report-preview-copy">
                            <span class="report-preview-label">Preview summary</span>
                            <strong>{{ number_format($count) }} {{ \Illuminate\Support\Str::plural('record', $count) }}</strong>
                            <small>{{ $periodLabel }} · Generated {{ now()->format('M j, Y') }}</small>
                        </div>
                        <div class="report-groups" aria-label="Report grouped by {{ $group_by }}">
                            @forelse($grouped->take(4) as $group => $total)
                                <div class="report-group-row">
                                    <span>{{ $group }}</span>
                                    <span class="report-group-track"><i style="width:{{ $count ? max(4, (int) round($total / $count * 100)) : 0 }}%"></i></span>
                                    <strong>{{ number_format($total) }}</strong>
                                </div>
                            @empty
                                <span class="report-no-groups">No matching records.</span>
                            @endforelse
                        </div>
                    </div>

                    @if($records->isNotEmpty())
                        <div class="report-table-wrap">
                            <div class="report-table-heading">Record preview <span>Showing up to 8 of {{ number_format($count) }}</span></div>
                            <div class="ops-table-scroll">
                                <table class="ops-table report-table">
                                    <thead><tr>@foreach($headers as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                                    <tbody>
                                        @foreach($records->take(8) as $record)
                                            <tr>@foreach($record['values'] as $value)<td>{{ $value }}</td>@endforeach</tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
