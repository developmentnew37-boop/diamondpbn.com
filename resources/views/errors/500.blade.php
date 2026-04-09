@extends('errors.layout')

@section('title', 'Server error')

@section('code')
    Error 500
@endsection

@section('heading')
    Something went wrong
@endsection

@section('message')
    We hit an unexpected issue on our side. Please try again in a moment. If the problem continues, let the team know.
@endsection

@section('illustration')
<svg viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <defs>
        <linearGradient id="e500-g" x1="120" y1="40" x2="120" y2="165" gradientUnits="userSpaceOnUse">
            <stop stop-color="#ff4a17" stop-opacity="0.35"/>
            <stop offset="1" stop-color="#ff4a17" stop-opacity="0"/>
        </linearGradient>
    </defs>
    <ellipse cx="120" cy="175" rx="68" ry="9" fill="#000" fill-opacity="0.22"/>
    <circle cx="120" cy="95" r="70" fill="url(#e500-g)"/>
    <rect x="70" y="65" width="100" height="72" rx="8" fill="#1e293b" stroke="#475569" stroke-width="2"/>
    <rect x="78" y="74" width="84" height="48" rx="4" fill="#0f172a"/>
    <path d="M88 92h64M88 104h48M88 116h56" stroke="#334155" stroke-width="2.5" stroke-linecap="round"/>
    <path d="M95 130l10 10 18-22" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.35"/>
    <path d="M152 58l12-8 8 12-12 8z" fill="#ff4a17" opacity="0.9"/>
    <path d="M158 62l4-3 3 5-4 3z" fill="#fff" opacity="0.3"/>
    <path d="M78 52c-4-2-6-7-4-11l6-10c2-4 7-6 11-4" stroke="#ef4444" stroke-width="3" stroke-linecap="round"/>
    <path d="M82 50l-6 2" stroke="#f97316" stroke-width="2" stroke-linecap="round"/>
    <circle cx="165" cy="48" r="3" fill="#fbbf24"/>
    <circle cx="72" cy="118" r="2.5" fill="#94a3b8" opacity="0.6"/>
    <circle cx="178" cy="125" r="2" fill="#94a3b8" opacity="0.5"/>
</svg>
@endsection
