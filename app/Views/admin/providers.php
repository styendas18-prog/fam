<?php
$providers = $providers ?? [];
$platformCredentials = $platformCredentials ?? [];
?>
<div class="mb-6 rounded-2xl border border-sky-200 bg-sky-50/70 p-4 dark:border-sky-500/20 dark:bg-sky-500/10">
    <div class="flex items-start gap-3">
        <i data-lucide="info" class="mt-0.5 h-5 w-5 shrink-0 text-sky-600 dark:text-sky-400"></i>
        <div class="text-[12px] leading-relaxed text-sky-900 dark:text-sky-200">
            <span class="font-semibold">Honest Gateway Connectivity:</span>
            Connection statuses reflect actual, authoritative server-to-server tests against provider endpoints.
            Secrets are encrypted at rest with AES-256-GCM.
            If a provider API key is not configured or rejected by the third party, status shows <span class="font-mono font-bold">Not Configured</span> or <span class="font-mono font-bold">Connection Failed</span>.
        </div>
    </div>
</div>

<div class="space-y-6">
    <?php foreach ($providers as $p): ?>
        <?php
        $id = (int) $p['id'];
        $cred = $platformCredentials[$id] ?? null;
        $status = $cred['connection_status'] ?? 'not_configured';
        if ((int) $p['is_enabled'] !== 1) {
            $status = 'disabled';
        }
        $badgeClass = match ($status) {
            'connected' => 'badge-success',
            'testing' => 'badge-warning',
            'connection_failed' => 'badge-danger',
            'disabled' => 'badge-neutral',
            default => 'badge-neutral',
        };
        $statusLabel = match ($status) {
            'connected' => 'Connected',
            'testing' => 'Testing',
            'connection_failed' => 'Connection Failed',
            'disabled' => 'Disabled',
            default => 'Not Configured',
        };
        ?>
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/50 p-5 dark:border-white/[0.06] dark:bg-white/[0.02]">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-500/10 text-brand-600 dark:text-brand-400">
                        <i data-lucide="plug-zap" class="h-5 w-5"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-[15px] font-bold text-slate-900 dark:text-white"><?= e($p['name']) ?></h2>
                            <span class="font-mono text-[10px] uppercase text-slate-400"><?= e($p['code']) ?></span>
                        </div>
                        <p class="text-[11px] text-slate-500">Driver: <?= e($p['driver']) ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="<?= $badgeClass ?>">
                        <?= $statusLabel ?>
                    </span>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="action" value="toggle_enable">
                        <button type="submit" class="btn-secondary btn-sm">
                            <i data-lucide="<?= (int) $p['is_enabled'] === 1 ? 'toggle-right' : 'toggle-left' ?>" class="h-4 w-4"></i>
                            <?= (int) $p['is_enabled'] === 1 ? 'Disable Provider' : 'Enable Provider' ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="grid gap-6 p-6 lg:grid-cols-2">
                <!-- Provider Details -->
                <div class="space-y-4">
                    <div>
                        <h3 class="text-[12px] font-semibold text-slate-900 dark:text-white">Provider Description</h3>
                        <p class="mt-1 text-[11.5px] leading-relaxed text-slate-500 dark:text-slate-400"><?= e($p['description']) ?></p>
                    </div>

                    <div class="space-y-2 rounded-xl border border-slate-100 bg-slate-50 p-4 text-[11px] dark:border-white/5 dark:bg-white/[0.02]">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Base URL:</span>
                            <span class="font-mono text-slate-700 dark:text-slate-300"><?= e($p['base_url']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Documentation:</span>
                            <a href="<?= e($p['docs_url']) ?>" target="_blank" class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                                Official Docs <i data-lucide="external-link" class="inline h-3 w-3"></i>
                            </a>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Last Test:</span>
                            <span class="text-slate-700 dark:text-slate-300">
                                <?= !empty($cred['last_test_at']) ? e(ist($cred['last_test_at'], 'd M Y, H:i')) : 'Never' ?>
                            </span>
                        </div>
                        <?php if (!empty($cred['last_test_message'])): ?>
                            <div class="border-t border-slate-200/60 pt-2 text-slate-600 dark:border-white/5 dark:text-slate-400">
                                <span class="font-medium">Test Result:</span> <?= e($cred['last_test_message']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Platform Fallback Credentials Form -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-ink-900/60">
                    <h3 class="text-[12px] font-semibold text-slate-900 dark:text-white">Platform Default Credential</h3>
                    <p class="mt-1 text-[11px] text-slate-500">
                        Used only if platform default credentials are permitted in Payment Settings. Individual merchants configure their own API keys under their merchant settings.
                    </p>

                    <form method="post" class="mt-4 space-y-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="action" value="save_credentials">

                        <div>
                            <label class="label" for="api_key_<?= $id ?>">
                                Provider API Key
                                <?php if (!empty($cred['key_last4'])): ?>
                                    <span class="font-normal text-slate-400">(currently ending in ••••<?= e($cred['key_last4']) ?>)</span>
                                <?php endif; ?>
                            </label>
                            <input type="password" id="api_key_<?= $id ?>" name="api_key" placeholder="Enter provider API key" class="input" autocomplete="new-password">
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="btn-primary btn-sm">
                                <i data-lucide="save" class="h-3.5 w-3.5"></i> Save API Key
                            </button>
                        </div>
                    </form>

                    <?php if ($cred): ?>
                        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-white/5">
                            <form method="post" data-loading>
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="action" value="test_connection">
                                <button type="submit" class="btn-secondary btn-sm">
                                    <i data-lucide="activity" class="h-3.5 w-3.5"></i> Test Real Connection
                                </button>
                            </form>

                            <form method="post" data-confirm="Delete stored platform credentials for this provider?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="action" value="delete_credentials">
                                <button type="submit" class="btn-danger btn-sm">
                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete Key
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
