<x-app-layout>
	<x-slot name="header"><h2>Approvals</h2></x-slot>

	<div class="ops-page">
		@if(session('success'))<div class="ops-flash" role="status">{{ session('success') }}</div>@endif
		<section class="ops-card">
			<div class="ops-card-heading">Completed work awaiting review</div>
			<div class="ops-table-scroll">
				<table class="ops-table">
					<thead><tr><th>Reference</th><th>Work</th><th>Equipment</th><th>Assigned to</th><th>Priority</th><th>Completed</th><th>Status</th><th>Action</th></tr></thead>
					<tbody>
						@forelse($approvalRecords as $record)
							<tr>
								<td>{{ $record['reference'] }}</td>
								<td>{{ $record['type'] }}</td>
								<td>{{ $record['equipment'] }}</td>
								<td>{{ $record['owner'] }}</td>
								<td>{{ $record['priority'] }}</td>
								<td>{{ $record['date'] }}</td>
								<td><span class="ops-pill warning">{{ $record['status'] }}</span></td>
								<td><button type="button" class="equipment-action" data-modal-open="approval-review-{{ $record['kind'] }}-{{ $record['id'] }}">Review</button></td>
							</tr>
						@empty
							<tr><td colspan="8" class="ops-empty">No completed work is waiting for approval.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</section>
	</div>

	@foreach($approvalRecords as $record)
		<dialog id="approval-review-{{ $record['kind'] }}-{{ $record['id'] }}" class="app-modal" aria-labelledby="approval-review-title-{{ $record['kind'] }}-{{ $record['id'] }}">
			<div class="app-modal-panel">
				<header class="app-modal-header">
					<div><h2 id="approval-review-title-{{ $record['kind'] }}-{{ $record['id'] }}">Review {{ strtolower($record['type']) }}</h2><p>{{ $record['reference'] }} · {{ $record['equipment'] }}</p></div>
					<button type="button" class="app-modal-close" aria-label="Close review dialog" data-modal-close>&times;</button>
				</header>
				<div class="app-modal-body">
					<dl class="equipment-detail-grid">
						<div><dt>Assigned to</dt><dd>{{ $record['owner'] }}</dd></div>
						<div><dt>Priority</dt><dd>{{ $record['priority'] }}</dd></div>
						<div><dt>Completed</dt><dd>{{ $record['date'] }}</dd></div>
						<div class="equipment-detail-full"><dt>Work notes</dt><dd>{{ $record['notes'] ?: 'No notes recorded.' }}</dd></div>
					</dl>
					<form method="POST" action="{{ route('approvals.update', [$record['kind'], $record['id']]) }}">
						@csrf
						@method('PATCH')
						<div class="app-modal-field">
							<label for="approval-decision-{{ $record['kind'] }}-{{ $record['id'] }}">Decision</label>
							<select id="approval-decision-{{ $record['kind'] }}-{{ $record['id'] }}" name="decision" required>
								<option value="Passed">Passed</option>
								<option value="Requires Further Assessment">Requires Further Assessment</option>
							</select>
						</div>
						<div class="app-modal-field">
							<label for="approval-notes-{{ $record['kind'] }}-{{ $record['id'] }}">Review comment</label>
							<textarea id="approval-notes-{{ $record['kind'] }}-{{ $record['id'] }}" name="approval_notes" rows="3"></textarea>
						</div>
						<footer class="app-modal-footer">
							<button type="button" class="app-modal-button" data-modal-close>Cancel</button>
							<button type="submit" class="app-modal-button primary">Save review</button>
						</footer>
					</form>
				</div>
			</div>
		</dialog>
	@endforeach
</x-app-layout>
