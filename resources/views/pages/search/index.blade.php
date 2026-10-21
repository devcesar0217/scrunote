@extends('layouts.app')
@section('title', 'Global Search · Scrunote')

@section('content')
    <section class="global-search-page" aria-labelledby="global-search-heading">
        <header class="workspace-section-header">
            <div>
                <h1 id="global-search-heading">Global Search</h1>
                <p class="workspace-section-description">Search Projects, Notes, Markdown Docs, tags, and Vault non-secret metadata.</p>
            </div>
        </header>

        <livewire:global-search-results :initial-query="$query" />
    </section>
@endsection
