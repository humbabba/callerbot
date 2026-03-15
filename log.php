<?php
$config = require __DIR__ . '/config/config.php';
require __DIR__ . '/db/database.php';

$entries = getLogEntries(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Callerbot — Query Log</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="dist/css/style.css?v=<?= $config['version'] ?>">
    <style>
        .log-table { opacity: 0; }
        .log-table.crt-on { animation: crt-flicker 0.8s ease-out forwards; }
    </style>
</head>
<body class="min-h-screen font-mono text-terminal-green antialiased">
    <div class="scanline"></div>

    <main class="w-full max-w-5xl mx-auto px-6 py-8">
        <div class="border border-terminal-dim/40 rounded bg-terminal-panel/60 p-8 backdrop-blur-sm">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-2 text-terminal-dim text-sm select-none">
                    <span class="inline-block w-2 h-2 rounded-full bg-terminal-green animate-pulse"></span>
                    <span>CALLERBOT QUERY LOG &mdash; All times in Arizona (MST/UTC-7, no DST)</span>
                </div>
                <a href="index.php" class="text-terminal-dim hover:text-terminal-green text-sm transition-colors">&larr; Back</a>
            </div>

            <div class="log-table text-sm overflow-x-auto">
                <?php if (empty($entries)): ?>
                    <p class="text-terminal-dim/60">No queries logged yet.</p>
                <?php else: ?>
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-terminal-dim/80 border-b border-terminal-dim/30">
                                <th class="py-2 pr-4 whitespace-nowrap">Time (AZ)</th>
                                <th class="py-2 pr-4">Input</th>
                                <th class="py-2 pr-4 whitespace-nowrap">Model</th>
                                <th class="py-2 pr-4 whitespace-nowrap">Tool</th>
                                <th class="py-2">Output</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $row): ?>
                                <tr class="border-b border-terminal-dim/15 hover:bg-terminal-dim/5">
                                    <?php
                                        $parts = explode(' ', $row['timestamp'], 2);
                                        $date = $parts[0] ?? '';
                                        $time = $parts[1] ?? '';
                                    ?>
                                    <td class="py-2 pr-4 text-terminal-dim/60 whitespace-nowrap align-top leading-tight"><?= htmlspecialchars($date) ?><br><?= htmlspecialchars($time) ?></td>
                                    <td class="py-2 pr-4 text-terminal-green/90 align-top"><?= htmlspecialchars($row['input']) ?></td>
                                    <td class="py-2 pr-4 text-terminal-dim/60 whitespace-nowrap align-top"><?= htmlspecialchars($row['model'] ?? '—') ?></td>
                                    <td class="py-2 pr-4 text-terminal-dim/80 whitespace-nowrap align-top"><?= htmlspecialchars($row['tool'] ?? '—') ?></td>
                                    <td class="py-2 text-terminal-dim/80 align-top max-w-md"><div class="max-h-20 overflow-y-auto"><?= htmlspecialchars($row['output']) ?></div></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-terminal-dim/40 text-xs mt-4 select-none">Showing <?= count($entries) ?> of max 500 entries</p>
                <?php endif; ?>
            </div>
        </div>

        <p class="text-terminal-dim/60 text-sm text-center mt-4 select-none">
            &copy; <?= date('Y') ?> <a href="https://sublogicalendeavors.com/" target="_blank">Sublogical Endeavors</a>
        </p>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                document.querySelector('.log-table')?.classList.add('crt-on');
            }, 300);
        });
    </script>
</body>
</html>
