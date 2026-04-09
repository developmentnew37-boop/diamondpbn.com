@extends('errors.layout')

@section('title', 'Page not found')

@section('code')
    Error 404
@endsection

@section('heading')
    This page drifted away
@endsection

@section('message')
    The link may be outdated or the page was moved. Double-check the URL or return to the dashboard and try again.
@endsection

@section('illustration')
<svg viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <defs>
        <linearGradient id="e404-a" x1="40" y1="20" x2="200" y2="180" gradientUnits="userSpaceOnUse">
            <stop stop-color="#ff4a17" stop-opacity="0.25"/>
            <stop offset="1" stop-color="#ff4a17" stop-opacity="0"/>
        </linearGradient>
        <linearGradient id="e404-b" x1="120" y1="60" x2="120" y2="150" gradientUnits="userSpaceOnUse">
            <stop stop-color="#334155"/>
            <stop offset="1" stop-color="#1e293b"/>
        </linearGradient>
    </defs>
    <circle cx="120" cy="100" r="78" fill="url(#e404-a)"/>
    <path d="M85 145c-8-18-5-40 8-56 14-18 38-26 60-20" stroke="#ff4a17" stroke-width="2.5" stroke-linecap="round" stroke-opacity="0.5"/>
    <path d="M155 70c10 12 14 28 10 44" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-dasharray="4 6"/>
    <rect x="75" y="55" width="90" height="70" rx="8" fill="url(#e404-b)" stroke="#475569" stroke-width="2"/>
    <path d="M88 72h64M88 88h44M88 104h54" stroke="#64748b" stroke-width="3" stroke-linecap="round"/>
    <circle cx="175" cy="48" r="22" fill="#1e293b" stroke="#ff4a17" stroke-width="2"/>
    <path d="M168 48l5 5 10-12" stroke="#ff4a17" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
    <ellipse cx="120" cy="168" rx="40" ry="6" fill="#000" fill-opacity="0.25"/>
</svg>
@endsection
