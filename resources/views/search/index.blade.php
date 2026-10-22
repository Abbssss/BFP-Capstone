<x-app-layout>
    <x-slot name="header"><h2>Search</h2></x-slot>

    <div class="ops-page">
        <section class="ops-card">
            <div class="ops-card-heading">Search results for “{{ $term }}”</div>
            <div class="ops-card-body">
                @forelse($results as $result)
                    <a class="app-search-result" href="{{ $result['url'] }}">
                        <span class="app-search-result-type">{{ $result['type'] }}</span>
                        <strong>{{ $result['title'] }}</strong>
                        <span>{{ $result['detail'] }}</span>
                        <time datetime="{{ $result['date']?->toISOString() }}">{{ $result['date']?->format('M d, Y') }}</time>
                    </a>
                @empty
                    <p class="app-search-empty">No matching records were found in the modules available to your account.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
