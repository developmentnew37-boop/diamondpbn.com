@php
    $includeJs = $includeJs ?? true;
    $includeCss = $includeCss ?? true;
    $manifestPath = public_path('build/manifest.json');
    $manifest = file_exists($manifestPath)
        ? json_decode(file_get_contents($manifestPath), true)
        : [];
    $cssFile = $manifest['resources/css/app.css']['file'] ?? null;
    $jsFile = $manifest['resources/js/app.js']['file'] ?? null;
@endphp
@if ($includeCss && $cssFile)
    <link rel="stylesheet" href="{{ asset('build/'.$cssFile) }}">
@endif
@if ($includeJs && $jsFile)
    <script src="{{ asset('build/'.$jsFile) }}" defer></script>
@endif
