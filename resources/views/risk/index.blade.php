<x-app-layout>
    <x-slot name="header"><h2>Predictions</h2></x-slot>

    <div class="ops-page">
        <form method="POST" action="{{ route('risk.retrain') }}" style="margin-bottom:14px">
            @csrf
            <button type="submit" class="ops-button">⌁ Retrain model</button>
        </form>
        @if(session('success'))<div class="ops-flash" role="status">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="ops-note" role="alert" style="margin-bottom:14px;border-color:#efc5c5;background:#fff1f1;color:#8d2428">{{ session('error') }}</div>@endif

        <div class="ops-grid-2">
            <section class="ops-card">
                <div class="ops-card-heading">Model information</div>
                <div class="ops-card-body">
                    <div class="ops-table-scroll"><table class="ops-table"><tbody>
                        <tr><td>Model name</td><td style="text-align:right;color:#183252">Equipment Risk Classifier</td></tr>
                        <tr><td>Algorithm</td><td style="text-align:right;color:#183252">Decision Tree</td></tr>
                        <tr><td>Current version</td><td style="text-align:right;color:#183252">{{ $latestVersion?->version_name ?? 'Not trained' }}</td></tr>
                        <tr><td>Status</td><td style="text-align:right"><span class="ops-pill {{ $modelReady ? '' : 'neutral' }}">{{ $modelReady ? 'Ready' : 'Not trained' }}</span></td></tr>
                        <tr><td>Last trained</td><td style="text-align:right;color:#183252">{{ $latestVersion?->training_date?->format('M d, Y') ?? '—' }}</td></tr>
                    </tbody></table></div>
                    <form method="POST" action="{{ route('risk.run') }}" style="display:flex;gap:8px;margin-top:14px">
                        @csrf
                        <select name="equipment_id" class="ops-input" aria-label="Equipment to assess" required>
                            <option value="">Select equipment to assess</option>
                            @foreach($equipment as $item)<option value="{{ $item->id }}">{{ $item->asset_code }} · {{ $item->equipment_name }}</option>@endforeach
                        </select>
                        <button type="submit" class="ops-button small">Run assessment</button>
                    </form>
                </div>
            </section>

            <section class="ops-card">
                <div class="ops-card-heading">Model performance</div>
                @if($latestVersion && ($modelMetadata['evaluated'] ?? false))
                    <div class="ops-grid-3" style="padding:14px">
                        @foreach(['Accuracy' => $latestVersion->accuracy, 'Precision' => $latestVersion->precision, 'Recall' => $latestVersion->recall, 'F1 score' => $latestVersion->f1_score] as $label => $metric)
                            @if($metric !== null)
                                @php($displayMetric = (float) $metric <= 1 ? (float) $metric * 100 : (float) $metric)
                                <div class="ops-metric"><small>{{ $label }}</small><strong>{{ number_format($displayMetric, 1) }}%</strong></div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="ops-card-body" style="min-height:190px;display:grid;place-content:center;text-align:center">
                        <span style="font-size:24px;color:#9aa7b7">⌁</span><strong style="margin:8px 0;color:#23415f;font-size:11px">No independent evaluation metrics available.</strong><small style="color:#8996a7;font-size:9px">At least two sufficiently represented risk classes are needed for a held-out evaluation.</small>
                    </div>
                @endif
            </section>

            <section class="ops-card">
                <div class="ops-card-heading">Feature importance</div>
                @if($featureImportance->isNotEmpty())
                    <div class="ops-card-body" style="display:grid;gap:10px">
                        @foreach($featureImportance as $feature => $importance)
                            @php($featureLabel = str($feature)->replace('_', ' ')->title())
                            <div style="display:grid;grid-template-columns:minmax(120px, .8fr) minmax(0, 3fr) 40px;align-items:center;gap:9px;color:#536780;font-size:9px">
                                <span>{{ $featureLabel }}</span>
                                <span class="ops-feature-bar"><span style="width:{{ min(100, max(0, (float) $importance * 100)) }}%"></span></span>
                                <strong style="color:#536780;text-align:right">{{ number_format((float) $importance * 100, 0) }}%</strong>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="ops-card-body" style="min-height:132px;display:grid;place-content:center;text-align:center;color:#8996a7;font-size:10px">
                        Train the model to calculate feature-importance scores.
                    </div>
                @endif
            </section>

            <section class="ops-card">
                <div class="ops-card-heading">Saved predictions</div>
                <div class="ops-card-body">
                    <div class="ops-grid-3">
                        <div class="ops-metric"><small>Saved classifications</small><strong>{{ number_format($predictionCount) }}</strong></div>
                        <div class="ops-metric"><small>High risk · latest 20</small><strong>{{ $highRisk }}</strong></div>
                        <div class="ops-metric"><small>Medium · latest 20</small><strong>{{ $mediumRisk }}</strong></div>
                    </div>
                    <div style="margin-top:12px;color:#8996a7;font-size:9px">Low risk in latest 20: {{ $lowRisk }}</div>
                </div>
            </section>

            <section class="ops-card" style="grid-column:1/-1">
                <div class="ops-card-heading">Model versions</div>
                @if($latestVersion)
                    <div class="ops-card-body" style="padding-bottom:0;color:#718197;font-size:10px">
                        Training source: {{ $modelMetadata['training_source'] ?? 'legacy training run' }} · {{ $modelMetadata['training_rows'] ?? '—' }} records.
                        Risk labels derived from operational thresholds are not a substitute for verified historical outcomes.
                    </div>
                @endif
                <div class="ops-table-scroll ops-table-scroll-rows-5"><table class="ops-table">
                    <thead><tr><th>Version</th><th>Training date</th><th>Accuracy</th><th>Precision</th><th>Recall</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($versions as $version)
                        <tr>
                            <td>{{ $version->version_name }}</td><td>{{ $version->training_date?->format('M d, Y') ?? '—' }}</td>
                            <td>{{ $version->accuracy === null ? '—' : number_format((float) $version->accuracy, 3) }}</td>
                            <td>{{ $version->precision === null ? '—' : number_format((float) $version->precision, 3) }}</td>
                            <td>{{ $version->recall === null ? '—' : number_format((float) $version->recall, 3) }}</td>
                            <td><span class="ops-pill {{ $loop->first ? '' : 'neutral' }}">{{ $loop->first ? 'Latest' : 'Previous' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ops-empty">No model versions have been saved.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>

            <section class="ops-card" style="grid-column:1/-1">
                <div class="ops-card-heading">Recent risk classifications</div>
                <div class="ops-table-scroll ops-table-scroll-rows-5"><table class="ops-table">
                    <thead><tr><th>Equipment</th><th>Asset code</th><th>Risk level</th><th>Confidence</th><th>Model version</th><th>Last updated</th></tr></thead>
                    <tbody>
                    @forelse($predictions as $prediction)
                        <tr>
                            <td>{{ $prediction->equipment?->equipment_name ?? 'Deleted equipment' }}</td><td>{{ $prediction->equipment?->asset_code ?? '—' }}</td>
                            <td><span class="ops-pill {{ $prediction->prediction_label === 'High Risk' ? 'danger' : ($prediction->prediction_label === 'Medium Risk' ? 'warning' : '') }}">{{ $prediction->prediction_label }}</span></td>
                            <td>{{ number_format((float) $prediction->confidence * 100, 1) }}%</td><td>{{ $prediction->modelVersion?->version_name ?? '—' }}</td>
                            <td>{{ $prediction->prediction_timestamp?->format('M d, Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ops-empty">No risk classifications are available yet.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>
        </div>
    </div>
</x-app-layout>
