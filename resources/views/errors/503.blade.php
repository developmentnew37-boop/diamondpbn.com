@extends('errors.layout')

@section('title', 'Service unavailable')

@section('code')
    Error 503
@endsection

@section('heading')
    Be right back
@endsection

@section('message')
    We are applying updates or handling heavy traffic. Refresh the page shortly, or return home and try again in a few minutes.
@endsection

@section('illustration')
<svg viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <defs>
        <linearGradient id="e503-ring" x1="120" y1="35" x2="120" y2="165" gradientUnits="userSpaceOnUse">
            <stop stop-color="#ff4a17" stop-opacity="0.15"/>
            <stop offset="1" stop-color="#ff4a17" stop-opacity="0"/>
        </linearGradient>
        <linearGradient id="e503-w" x1="120" y1="85" x2="120" y2="125" gradientUnits="userSpaceOnUse">
            <stop stop-color="#64748b"/>
            <stop offset="1" stop-color="#334155"/>
        </linearGradient>
    </defs>
    <ellipse cx="120" cy="178" rx="56" ry="8" fill="#000" fill-opacity="0.18"/>
    <circle cx="120" cy="100" r="72" fill="url(#e503-ring)"/>
    <circle cx="120" cy="100" r="38" stroke="#334155" stroke-width="3" fill="none" stroke-dasharray="10 8" opacity="0.8"/>
    <circle cx="120" cy="100" r="24" stroke="#ff4a17" stroke-width="2.5" fill="none" stroke-opacity="0.5" stroke-dasharray="6 10"/>
    <g transform="translate(120 100)">
        <path d="M-2-18v36c0 4 4 7 8 5l14-8c4-2 4-9 0-11l-14-8c-4-2-8 1-8 5z" fill="url(#e503-w)" stroke="#475569" stroke-width="1.5"/>
        <path d="M6-8v16" stroke="#ff4a17" stroke-width="2.5" stroke-linecap="round"/>
    </g>
    <rect x="88" y="38" width="64" height="8" rx="4" fill="#1e293b" stroke="#334155"/>
    <rect x="96" y="40" width="20" height="4" rx="1" fill="#ff4a17" opacity="0.6"/>
    <circle cx="168" cy="42" r="3" fill="#22c55e" opacity="0.8"/>
</svg>
@endsection
