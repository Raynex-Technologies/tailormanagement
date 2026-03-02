<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="install-shell min-h-screen antialiased text-zinc-900">
        <div class="install-orb install-orb-a"></div>
        <div class="install-orb install-orb-b"></div>

        <main class="relative mx-auto flex min-h-screen w-full max-w-7xl items-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="grid w-full gap-8 lg:grid-cols-[1.02fr_1.18fr]">
                <section class="install-hero overflow-hidden rounded-[2rem] border border-white/40 p-8 shadow-[0_30px_80px_rgba(15,23,42,0.12)] lg:p-10">
                    <div class="inline-flex items-center gap-3 rounded-full border border-white/50 bg-white/60 px-4 py-2 text-xs font-semibold uppercase tracking-[0.3em] text-slate-600">
                        First Run Setup
                    </div>

                    <div class="mt-8 space-y-5">
                        <p class="text-sm font-semibold uppercase tracking-[0.28em] text-emerald-700">Tailor Management</p>
                        <h1 class="max-w-xl text-4xl font-semibold leading-tight text-slate-950 sm:text-5xl">
                            Install once, then hand the app to operations.
                        </h1>
                        <p class="max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
                            This wizard writes the runtime configuration, generates the application key, runs migrations,
                            seeds the required defaults, and creates the first branch, business profile, and superadmin account.
                        </p>
                    </div>

                    <div class="mt-10 grid gap-4 sm:grid-cols-2">
                        <article class="install-stat-card">
                            <p class="install-stat-label">Generated During Setup</p>
                            <p class="install-stat-value">Application key</p>
                            <p class="install-stat-copy">Created after validation so encrypted sessions and cookies become valid immediately.</p>
                        </article>
                        <article class="install-stat-card">
                            <p class="install-stat-label">Bootstrapped Automatically</p>
                            <p class="install-stat-value">Schema + defaults</p>
                            <p class="install-stat-copy">Runs migrations, roles, permissions, payment defaults, SMS defaults, and starter inventory data.</p>
                        </article>
                    </div>

                    <div class="mt-10 rounded-[1.5rem] border border-slate-200/70 bg-white/70 p-6">
                        <h2 class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-500">What You Need</h2>
                        <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                            <li>A reachable database and credentials with permission to create tables.</li>
                            <li>The public application URL you want generated links to use.</li>
                            <li>Your business profile, first branch details, and the superadmin credentials.</li>
                        </ul>
                    </div>
                </section>

                <section class="install-card rounded-[2rem] border border-white/50 bg-white/85 p-6 shadow-[0_30px_80px_rgba(15,23,42,0.14)] backdrop-blur xl:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-slate-500">Installation Wizard</p>
                            <h2 class="mt-2 text-2xl font-semibold text-slate-950">Configure the first deployment</h2>
                        </div>
                        <div class="rounded-full bg-slate-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-white">
                            Step 1 of 1
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">
                            <p class="font-semibold">The installer could not continue.</p>
                            <p class="mt-1">{{ $errors->first('installation') ?? 'Review the highlighted fields and submit again.' }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('install.store') }}" class="mt-8 space-y-8">
                        @csrf

                        <div>
                            <div class="install-section-head">
                                <div>
                                    <p class="install-eyebrow">Database</p>
                                    <h3 class="install-section-title">Connection settings</h3>
                                </div>
                                <p class="install-section-copy">Use SQLite only when the application should manage its own database file.</p>
                            </div>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <label class="install-field md:col-span-2">
                                    <span class="install-label">Connection</span>
                                    <select name="db_connection" class="install-input">
                                        @foreach ($connections as $key => $label)
                                            <option value="{{ $key }}" @selected(old('db_connection', $defaults['db_connection']) === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('db_connection') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field">
                                    <span class="install-label">Host</span>
                                    <input name="db_host" type="text" class="install-input" value="{{ $defaults['db_host'] }}" placeholder="127.0.0.1">
                                    @error('db_host') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field">
                                    <span class="install-label">Port</span>
                                    <input name="db_port" type="number" class="install-input" value="{{ $defaults['db_port'] }}" placeholder="3306">
                                    @error('db_port') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field">
                                    <span class="install-label">Database</span>
                                    <input name="db_database" type="text" class="install-input" value="{{ $defaults['db_database'] }}" placeholder="tailormanagement">
                                    @error('db_database') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field">
                                    <span class="install-label">Username</span>
                                    <input name="db_username" type="text" class="install-input" value="{{ $defaults['db_username'] }}" placeholder="root">
                                    @error('db_username') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field md:col-span-2">
                                    <span class="install-label">Password</span>
                                    <input name="db_password" type="password" class="install-input" placeholder="Database password">
                                    @error('db_password') <span class="install-error">{{ $message }}</span> @enderror
                                </label>
                            </div>
                        </div>

                        <div>
                            <div class="install-section-head">
                                <div>
                                    <p class="install-eyebrow">Application</p>
                                    <h3 class="install-section-title">Runtime profile</h3>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <label class="install-field md:col-span-2">
                                    <span class="install-label">Application URL</span>
                                    <input name="app_url" type="url" class="install-input" value="{{ $defaults['app_url'] }}" placeholder="https://example.com">
                                    @error('app_url') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field">
                                    <span class="install-label">Business name</span>
                                    <input name="business_name" type="text" class="install-input" value="{{ $defaults['business_name'] }}" required>
                                    @error('business_name') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field">
                                    <span class="install-label">Business phone</span>
                                    <input name="business_phone" type="text" class="install-input" value="{{ $defaults['business_phone'] }}" required>
                                    @error('business_phone') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field">
                                    <span class="install-label">TIN</span>
                                    <input name="business_tin" type="text" class="install-input" value="{{ $defaults['business_tin'] }}" placeholder="Optional">
                                    @error('business_tin') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field">
                                    <span class="install-label">Business email</span>
                                    <input name="business_email" type="email" class="install-input" value="{{ $defaults['business_email'] }}" placeholder="Optional">
                                    @error('business_email') <span class="install-error">{{ $message }}</span> @enderror
                                </label>
                            </div>
                        </div>

                        <div>
                            <div class="install-section-head">
                                <div>
                                    <p class="install-eyebrow">Branch</p>
                                    <h3 class="install-section-title">First branch details</h3>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <label class="install-field">
                                    <span class="install-label">Branch name</span>
                                    <input name="branch_name" type="text" class="install-input" value="{{ $defaults['branch_name'] }}" required>
                                    @error('branch_name') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field">
                                    <span class="install-label">Branch phone</span>
                                    <input name="branch_phone" type="text" class="install-input" value="{{ $defaults['branch_phone'] }}" placeholder="Optional">
                                    @error('branch_phone') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field md:col-span-2">
                                    <span class="install-label">Branch address</span>
                                    <textarea name="branch_address" class="install-input min-h-28" placeholder="Optional">{{ $defaults['branch_address'] }}</textarea>
                                    @error('branch_address') <span class="install-error">{{ $message }}</span> @enderror
                                </label>
                            </div>
                        </div>

                        <div>
                            <div class="install-section-head">
                                <div>
                                    <p class="install-eyebrow">Superadmin</p>
                                    <h3 class="install-section-title">Primary account</h3>
                                </div>
                                <p class="install-section-copy">This account is created as a global superadmin.</p>
                            </div>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <label class="install-field">
                                    <span class="install-label">Full name</span>
                                    <input name="admin_name" type="text" class="install-input" value="{{ $defaults['admin_name'] }}" required>
                                    @error('admin_name') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field">
                                    <span class="install-label">Email</span>
                                    <input name="admin_email" type="email" class="install-input" value="{{ $defaults['admin_email'] }}" required>
                                    @error('admin_email') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field">
                                    <span class="install-label">Password</span>
                                    <input name="admin_password" type="password" class="install-input" required>
                                    @error('admin_password') <span class="install-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="install-field">
                                    <span class="install-label">Confirm password</span>
                                    <input name="admin_password_confirmation" type="password" class="install-input" required>
                                </label>
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 rounded-[1.75rem] border border-slate-200/80 bg-slate-950 px-6 py-5 text-white sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-emerald-300">Installer action</p>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                                    Submitting this form writes the environment, generates the application key, runs migrations, seeds defaults, and creates the first operational records.
                                </p>
                            </div>

                            <button type="submit" class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-emerald-400 via-lime-300 to-amber-300 px-7 py-3 text-sm font-semibold uppercase tracking-[0.22em] text-slate-950 transition hover:scale-[1.01] hover:shadow-[0_18px_40px_rgba(163,230,53,0.28)]">
                                Run installation
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </body>
</html>
