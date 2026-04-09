@extends('errors.layout')

@section('title', 'Access denied')

@section('code')
    Error 403
@endsection

@section('heading')
    Access denied
@endsection

@section('message')
    You do not have permission to view this resource. If you believe this is a mistake, contact an administrator.
@endsection

@section('illustration')
<svg viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <defs>
        <linearGradient id="e403-sh" x1="120" y1="30" x2="120" y2="170" gradientUnits="userSpaceOnUse">
            <stop stop-color="#ff4a17" stop-opacity="0.2"/>
            <stop offset="1" stop-color="#ff4a17" stop-opacity="0"/>
        </linearGradient>
        <linearGradient id="e403-metal" x1="80" y1="70" x2="160" y2="150" gradientUnits="userSpaceOnUse">
            <stop stop-color="#475569"/>
            <stop offset="1" stop-color="#1e293b"/>
        </linearGradient>
    </defs>
    <ellipse cx="120" cy="175" rx="72" ry="10" fill="#000" fill-opacity="0.2"/>
    <circle cx="120" cy="95" r="68" fill="url(#e403-sh)"/>
    <rect x="95" y="55" width="50" height="85" rx="10" fill="url(#e403-metal)" stroke="#64748b" stroke-width="2"/>
    <path d="M110 55v-12c0-6 6-11 13-11h6c7 0 13 5 13 11v12" stroke="#64748b" stroke-width="3" stroke-linecap="round"/>
    <circle cx="120" cy="95" r="14" fill="#0f172a" stroke="#ff4a17" stroke-width="3"/>
    <path d="M116 95l3 3 7-8" stroke="#ff4a17" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <rect x="105" y="118" width="30" height="22" rx="3" fill="#0f172a" stroke="#334155" stroke-width="2"/>
    <path d="M75 125h-12c-4 0-7-3-7-7V95c0-4 3-7 7-7h12" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/>
    <path d="M165 125h12c4 0 7-3 7-7V95c0-4-3-7-7-7h-12" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/>
    <path d="M88 140h64" stroke="#ff4a17" stroke-width="2" stroke-linecap="round" stroke-opacity="0.4"/>
</svg>
@endsection
