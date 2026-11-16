<?php
use App\Core\Database;
use App\Models\ProviderModel;

$title = 'System status';
$nav = 'status';
$eyebrow = 'Status';
$heading = 'Real status, including the parts that are not configured.';
$copy = 'This page reads live database and provider connection records. It does not display invented uptime percentages.';
require BASE_PATH . '/app/Views/public/_page-hero.php';

$dbOk = true;
$dbError = '';
try {
    Database::scalar('SELECT 1');
} catch (Throwable $e) {
    $dbOk = false;
    $dbError = $e->getMessage();
}
$providers = $dbOk ? ProviderModel::all() : [];
$statusLabels = [
    'connected' => ['Connected', 'emerald'],
    'testing' => ['Testing', 'amber'],
    'failed' => ['Connection failed', 'rose'],
    'disabled' => ['Disabled', 'slate'],
    'not_configured' => ['Not configured', 'slate'],
];
?>
<section class="bg-ink-900 py-16 sm:py-20">
    <div class="container-mf">
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-6 lg:col-span-2">
                <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-slate-400">Platform components</h2>
                <div class="mt-5 space-y-2.5">
                    <?php
                    $components = [
                        ['Database (MySQL)', $dbOk, $dbOk ? 'Reachable — live data is being read.' : 'Unreachable. The application cannot operate.'],
                        ['Application runtime (PHP ' . PHP_VERSION . ')', true, 'Serving this request.'],
                        ['Payment verification service', $dbOk, $dbOk ? 'Enabled. Pending orders are polled by the cron worker.' : 'Waiting for database.'],
                        ['Webhook delivery queue', $dbOk, $dbOk ? 'Enabled. Retries follow the configured backoff schedule.' : 'Waiting for database.'],
                    ];
                    foreach ($components as [$name, $ok, $detail]): ?>
                    <div class="flex flex-col gap-2 rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= $ok ? 'bg-emerald-400 shadow-[0_0_10px_rgba(52,211,153,.8)]' : 'bg-rose-400' ?>"></span>
                            <div><p class="text-[12.5px] font-semibold text-white"><?= e($name) ?></p><p class="mt-0.5 text-[10.5px] text-slate-500"><?= e($detail) ?></p></div>
                        </div>
                        <span class="<?= $ok ? 'badge-success' : 'badge-danger' ?> shrink-0"><?= $ok ? 'Operational' : 'Incident' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!$dbOk): ?>
                <div class="mt-4 rounded-xl border border-rose-400/20 bg-rose-400/[0.06] p-4 text-[11px] leading-5 text-rose-200">Database error: <?= e($dbError) ?></div>
                <?php endif; ?>
            </div>

            <div class="space-y-4">
                <div class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-5">
                    <h2 class="text-[11px] font-bold uppercase tracking-[0.13em] text-slate-500">Current time</h2>
                    <p class="mt-2 font-mono text-[15px] text-white"><?= e(ist(gmdate('Y-m-d H:i:s'), 'd M Y, H:i:s')) ?> IST</p>
                    <p class="mt-1 text-[10px] text-slate-500"><?= e(gmdate('Y-m-d H:i:s')) ?> UTC · <?= e(ist(gmdate('Y-m-d H:i:s'), 'T')) ?></p>
                </div>
                <div class="rounded-2xl border border-white/[0.08] bg-white/[0.025] p-5">
                    <h2 class="text-[11px] font-bold uppercase tracking-[0.13em] text-slate-500">Status meanings</h2>
                    <dl class="mt-3 space-y-2.5 text-[10.5px] leading-5">
                        <div><dt class="font-semibold text-emerald-300">Connected</dt><dd class="text-slate-500">A real authenticated read request succeeded.</dd></div>
                        <div><dt class="font-semibold text-amber-300">Testing</dt><dd class="text-slate-500">A connection check is in progress.</dd></div>
                        <div><dt class="font-semibold text-rose-300">Connection failed</dt><dd class="text-slate-500">The provider rejected or could not answer the request.</dd></div>
                        <div><dt class="font-semibold text-slate-300">Not configured</dt><dd class="text-slate-500">No authorized credentials have been saved.</dd></div>
                    </dl>
                </div>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-white/[0.08] bg-white/[0.025] p-6">
            <h2 class="text-[13px] font-bold uppercase tracking-[0.12em] text-slate-400">Payment providers</h2>
            <p class="mt-1 text-[10.5px] text-slate-500">Provider status is set only by an authenticated connectivity test or a real API call. Platform-wide state can differ from a merchant’s own credential state.</p>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <?php if (!$providers): ?>
                <p class="text-[12px] text-slate-500">No providers installed.</p>
                <?php endif; ?>
                <?php foreach ($providers as $provider):
                    $meta = $statusLabels[$provider['connection_status']] ?? ['Unknown', 'slate'];
                    $toneClass = $meta[1] === 'emerald' ? 'badge-success' : ($meta[1] === 'amber' ? 'badge-warning' : ($meta[1] === 'rose' ? 'badge-danger' : 'badge-muted'));
                ?>
                <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[12.5px] font-semibold text-white"><?= e($provider['name']) ?></p>
                            <p class="mt-0.5 font-mono text-[9.5px] text-slate-500"><?= e($provider['code']) ?></p>
                        </div>
                        <span class="<?= $toneClass ?> shrink-0"><span class="h-1.5 w-1.5 rounded-full <?= $meta[1] === 'emerald' ? 'bg-emerald-400' : ($meta[1] === 'amber' ? 'bg-amber-400' : ($meta[1] === 'rose' ? 'bg-rose-400' : 'bg-slate-400')) ?>"></span> <?= e($meta[0]) ?></span>
                    </div>
                    <p class="mt-3 text-[10.5px] leading-5 text-slate-500">
                        <?php if ($provider['last_test_at']): ?>
                            Last checked <?= e(ist($provider['last_test_at'])) ?><?= $provider['last_error'] ? ' — ' . e(mb_substr((string) $provider['last_error'], 0, 120)) : '' ?>
                        <?php else: ?>
                            No connectivity test has been recorded.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <p class="mt-6 text-[10.5px] leading-5 text-slate-500">This page reports what the application can observe. It is not a third-party uptime monitor and does not claim provider availability beyond the last recorded test. For incident reports, contact <a href="<?= e(url('/contact')) ?>" class="text-brand-300 hover:text-brand-200">support</a>.</p>
    </div>
</section>
