<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <style>
            .auth-body {
                background-color: #FBF6F1;
                font-family: 'DM Sans', sans-serif;
            }
            .auth-input {
                border: 1.5px solid #E5DDD5;
                border-radius: 9999px;
                padding: 0.75rem 1rem 0.75rem 2.75rem;
                font-size: 0.95rem;
                color: #3D3D3D;
                background: white;
                width: 100%;
                outline: none;
                transition: border-color 0.2s;
            }
            .auth-input::placeholder {
                color: #B5ADA5;
            }
            .auth-input:focus {
                border-color: #D4A574;
            }
            .auth-input-wrapper {
                position: relative;
            }
            .auth-input-icon {
                position: absolute;
                left: 1rem;
                top: 50%;
                transform: translateY(-50%);
                color: #B5ADA5;
                font-size: 0.9rem;
            }
            .auth-input-toggle {
                position: absolute;
                right: 1rem;
                top: 50%;
                transform: translateY(-50%);
                color: #B5ADA5;
                cursor: pointer;
                background: none;
                border: none;
                padding: 0;
                font-size: 0.9rem;
            }
            .auth-input-toggle:hover {
                color: #8B7B6B;
            }
            .auth-btn {
                background-color: #D4A574;
                color: white;
                border: none;
                border-radius: 9999px;
                padding: 0.8rem;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                width: 100%;
                transition: background-color 0.2s;
            }
            .auth-btn:hover {
                background-color: #C49464;
            }
            .auth-label {
                position: absolute;
                top: -0.55rem;
                left: 1.5rem;
                background: white;
                padding: 0 0.5rem;
                font-size: 0.75rem;
                color: #8B7B6B;
                font-weight: 500;
                z-index: 1;
            }
            .auth-illustration-bg {
                background: radial-gradient(ellipse at center bottom, #EDDCC8 0%, transparent 70%);
            }
        </style>
    </head>
    <body class="auth-body min-h-screen antialiased">
        <div class="flex min-h-screen">
            {{-- Left Side — Form --}}
            <div class="flex flex-1 flex-col items-center justify-center px-6 py-10 md:px-12 lg:px-20">
                <div class="w-full max-w-sm">
                    {{-- Logo --}}
                    <a href="{{ route('home') }}" class="flex justify-center mb-8" wire:navigate>
                        <div class="flex items-center justify-center size-12 rounded-xl" style="background: linear-gradient(135deg, #A3E635 0%, #84CC16 100%);">
                            <x-app-logo-icon class="size-6 text-[#1E1F2E]" />
                        </div>
                    </a>

                    {{ $slot }}
                </div>
            </div>

            {{-- Right Side — Illustration (hidden on mobile) --}}
            <div class="hidden lg:flex flex-1 items-center justify-center auth-illustration-bg relative overflow-hidden">
                {{-- Decorative arch --}}
                <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[340px] h-[420px] rounded-t-full" style="background: linear-gradient(180deg, #EDDCC8 0%, #E5D0B8 100%);"></div>

                {{-- Tailor Illustration SVG --}}
                <div class="relative z-10 flex flex-col items-center">
                    <svg width="320" height="380" viewBox="0 0 320 380" fill="none" xmlns="http://www.w3.org/2000/svg">
                        {{-- Sewing Machine Body --}}
                        <rect x="70" y="200" width="180" height="90" rx="16" fill="#1E1F2E"/>
                        <rect x="80" y="210" width="160" height="70" rx="12" fill="#252637"/>

                        {{-- Machine Top Arm --}}
                        <path d="M200 200 V150 H240 V170 H220 V200" fill="#1E1F2E"/>
                        <rect x="210" y="145" width="40" height="30" rx="6" fill="#252637"/>

                        {{-- Needle --}}
                        <line x1="225" y1="175" x2="225" y2="220" stroke="#A3E635" stroke-width="2.5"/>
                        <circle cx="225" cy="222" r="3" fill="#A3E635"/>

                        {{-- Thread Spool on top --}}
                        <rect x="195" y="130" width="20" height="25" rx="4" fill="#D4A574"/>
                        <line x1="199" y1="137" x2="211" y2="137" stroke="#C49464" stroke-width="1.5"/>
                        <line x1="199" y1="142" x2="211" y2="142" stroke="#C49464" stroke-width="1.5"/>
                        <line x1="199" y1="147" x2="211" y2="147" stroke="#C49464" stroke-width="1.5"/>

                        {{-- Thread from spool to needle --}}
                        <path d="M205 155 Q210 165, 225 175" stroke="#D4A574" stroke-width="1.5" fill="none" stroke-dasharray="4,3"/>

                        {{-- Fabric under needle --}}
                        <path d="M150 220 H280 Q285 220 285 225 V240 Q285 245 280 245 H145 Q140 245 140 240 V225 Q140 220 145 220 Z" fill="#84CC16" opacity="0.3"/>
                        <path d="M155 228 H275" stroke="#A3E635" stroke-width="1" stroke-dasharray="6,4"/>
                        <path d="M155 236 H275" stroke="#A3E635" stroke-width="1" stroke-dasharray="6,4"/>

                        {{-- Machine Wheel --}}
                        <circle cx="105" cy="250" r="20" fill="#2A2B3D" stroke="#3D3E52" stroke-width="2"/>
                        <circle cx="105" cy="250" r="8" fill="#1E1F2E" stroke="#A3E635" stroke-width="1.5"/>

                        {{-- Machine Base / Table --}}
                        <rect x="50" y="290" width="220" height="12" rx="6" fill="#1E1F2E"/>
                        <rect x="80" y="302" width="12" height="40" rx="3" fill="#252637"/>
                        <rect x="228" y="302" width="12" height="40" rx="3" fill="#252637"/>
                        <rect x="70" y="338" width="40" height="8" rx="4" fill="#1E1F2E"/>
                        <rect x="210" y="338" width="40" height="8" rx="4" fill="#1E1F2E"/>

                        {{-- Scissors on table --}}
                        <g transform="translate(40, 270) scale(0.8)">
                            <circle cx="15" cy="10" r="8" stroke="#D4A574" stroke-width="2" fill="none"/>
                            <circle cx="15" cy="30" r="8" stroke="#D4A574" stroke-width="2" fill="none"/>
                            <line x1="22" y1="13" x2="45" y2="20" stroke="#D4A574" stroke-width="2.5" stroke-linecap="round"/>
                            <line x1="22" y1="27" x2="45" y2="20" stroke="#D4A574" stroke-width="2.5" stroke-linecap="round"/>
                        </g>

                        {{-- Measuring Tape --}}
                        <g transform="translate(255, 250)">
                            <rect x="0" y="0" width="35" height="20" rx="4" fill="#D4A574"/>
                            <rect x="3" y="3" width="29" height="14" rx="3" fill="#E5C4A0"/>
                            <path d="M35 10 Q50 5 55 15 Q58 25 50 28" stroke="#D4A574" stroke-width="2" fill="none"/>
                            <line x1="8" y1="6" x2="8" y2="14" stroke="#C49464" stroke-width="1"/>
                            <line x1="14" y1="6" x2="14" y2="14" stroke="#C49464" stroke-width="1"/>
                            <line x1="20" y1="6" x2="20" y2="14" stroke="#C49464" stroke-width="1"/>
                            <line x1="26" y1="6" x2="26" y2="14" stroke="#C49464" stroke-width="1"/>
                        </g>

                        {{-- Decorative Buttons floating --}}
                        <circle cx="90" cy="140" r="10" fill="none" stroke="#D4A574" stroke-width="1.5" opacity="0.5"/>
                        <circle cx="87" cy="137" r="1.5" fill="#D4A574" opacity="0.5"/>
                        <circle cx="93" cy="137" r="1.5" fill="#D4A574" opacity="0.5"/>
                        <circle cx="87" cy="143" r="1.5" fill="#D4A574" opacity="0.5"/>
                        <circle cx="93" cy="143" r="1.5" fill="#D4A574" opacity="0.5"/>

                        <circle cx="280" cy="160" r="8" fill="none" stroke="#A3E635" stroke-width="1.5" opacity="0.4"/>
                        <circle cx="278" cy="158" r="1.2" fill="#A3E635" opacity="0.4"/>
                        <circle cx="282" cy="158" r="1.2" fill="#A3E635" opacity="0.4"/>
                        <circle cx="278" cy="162" r="1.2" fill="#A3E635" opacity="0.4"/>
                        <circle cx="282" cy="162" r="1.2" fill="#A3E635" opacity="0.4"/>

                        {{-- Thread lines floating --}}
                        <path d="M60 170 Q70 160 80 170 Q90 180 100 170" stroke="#D4A574" stroke-width="1.5" fill="none" opacity="0.3"/>
                        <path d="M250 130 Q260 120 270 130" stroke="#A3E635" stroke-width="1.5" fill="none" opacity="0.3"/>
                    </svg>

                    {{-- Business Name --}}
                    @php($businessName = \App\Models\BusinessSetting::query()->value('business_name') ?: 'Tailex')
                    <p class="mt-4 text-lg font-semibold" style="color: #1E1F2E;">{{ $businessName }}</p>
                    <p class="text-sm" style="color: #8B7B6B;">Tailoring Management System</p>
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
