<?php
function loadRecipes(string $file): array
{
    $recipes = [];
    if (!is_readable($file)) return $recipes;

    $raw = file_get_contents($file);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    if (!mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }

    $current = null;
    foreach (preg_split('/\R/', $raw) as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if ($line[0] === '#') {
            if ($current) $recipes[] = $current;
            $current = ['name' => trim(substr($line, 1)), 'image' => '', 'steps' => []];
            continue;
        }
        if ($current === null) continue;

        if ($line[0] === '*') { $current['image'] = trim(substr($line, 1)); continue; }
        if (preg_match('/^Step\s*\d+$/i', $line)) continue;

        $current['steps'][] = $line;
    }
    if ($current) $recipes[] = $current;

    // Extra info taken from the recipe text
    foreach ($recipes as &$r) {
        $text = implode(' ', $r['steps']);
        $r['oven'] = null;
        $r['bake'] = null;
        if (preg_match('/(\d+)\s*degrees F\s*\((\d+)\s*degrees C\)/i', $text, $m)) {
            $r['oven'] = $m[2] . ' °C / ' . $m[1] . ' °F';
        }
        if (preg_match('/(?:about\s+|for\s+)?(\d+(?:\s*to\s*\d+)?)\s*minutes/i', $text, $m)) {
            $r['bake'] = $m[1] . ' min';
        }
    }
    unset($r);

    return $recipes;
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function img(array $r): string
{
    if ($r['image'] !== '' && file_exists(__DIR__ . '/' . $r['image'])) {
        return rawurlencode($r['image']);
    }
    return '';
}

$recipes  = loadRecipes(__DIR__ . '/recipes.txt');
$total    = count($recipes);

if (isset($_GET['random']) && $total > 0) {
    $selected = random_int(0, $total - 1);
} else {
    $selected = isset($_GET['cake']) ? (int)$_GET['cake'] : -1;
}
$recipe = $recipes[$selected] ?? null;
$prev   = $selected > 0 ? $selected - 1 : null;
$next   = ($recipe && $selected < $total - 1) ? $selected + 1 : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sweet Slice – Cake Recipe Book</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="hero">
    <button class="theme-btn" id="themeBtn" type="button" title="Toggle dark mode">🌙</button>
    <a href="index.php" class="logo">🍰 Sweet Slice</a>
    <p class="tagline">Handpicked cake recipes, baked with love</p>

    <form method="get" class="picker">
        <label for="cake">Choose your cake</label>
        <div class="picker-row">
            <select name="cake" id="cake" onchange="this.form.submit()">
                <option value="-1" disabled <?= $recipe ? '' : 'selected' ?>>— Select a cake —</option>
                <?php foreach ($recipes as $i => $r): ?>
                    <option value="<?= $i ?>" <?= $i === $selected ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <a class="btn-random" href="?random=1" title="Surprise me">🎲 Surprise me</a>
        </div>
        <noscript><button type="submit">Show</button></noscript>
    </form>
</header>

<main>
<?php if ($recipe): ?>
    <article class="card">
        <div class="photo">
            <?php if (img($recipe) !== ''): ?>
                <img src="<?= e(img($recipe)) ?>" alt="<?= e($recipe['name']) ?>">
            <?php else: ?>
                <div class="no-photo">🍰</div>
            <?php endif; ?>
        </div>

        <div class="recipe">
            <h2><?= e($recipe['name']) ?></h2>

            <div class="badges">
                <span class="badge">📋 <?= count($recipe['steps']) ?> steps</span>
                <?php if ($recipe['oven']): ?><span class="badge">🔥 <?= e($recipe['oven']) ?></span><?php endif; ?>
                <?php if ($recipe['bake']): ?><span class="badge">⏱ <?= e($recipe['bake']) ?></span><?php endif; ?>
            </div>

            <p class="hint">Tip: click a step to tick it off while you bake.</p>
            <ol class="steps">
                <?php foreach ($recipe['steps'] as $step): ?>
                    <li><?= e($step) ?></li>
                <?php endforeach; ?>
            </ol>

            <div class="actions">
                <?php if ($prev !== null): ?><a class="btn" href="?cake=<?= $prev ?>">← Previous</a><?php endif; ?>
                <button class="btn ghost" type="button" onclick="window.print()">🖨 Print</button>
                <?php if ($next !== null): ?><a class="btn" href="?cake=<?= $next ?>">Next →</a><?php endif; ?>
            </div>
        </div>
    </article>
<?php elseif (!$recipes): ?>
    <p class="message">No recipes found. Check that <strong>recipes.txt</strong> is in the same folder.</p>
<?php else: ?>
    <p class="message">Pick a cake from the menu above, or click one below. 🎂</p>
<?php endif; ?>

<?php if ($recipes): ?>
    <section class="gallery">
        <h3><?= $recipe ? 'More cakes' : 'All cakes' ?></h3>
        <div class="grid">
            <?php foreach ($recipes as $i => $r): if ($i === $selected) continue; ?>
                <a class="tile" href="?cake=<?= $i ?>">
                    <?php if (img($r) !== ''): ?>
                        <img src="<?= e(img($r)) ?>" alt="<?= e($r['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="no-photo small">🍰</div>
                    <?php endif; ?>
                    <span><?= e($r['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
</main>

<footer>
    <p><?= $total ?> recipes in the book · Happy baking! 🧁</p>
</footer>

<script>
    // Dark mode (remembered)
    const root = document.documentElement, btn = document.getElementById('themeBtn');
    function setTheme(t) { root.setAttribute('data-theme', t); btn.textContent = t === 'dark' ? '☀️' : '🌙'; }
    setTheme(localStorage.getItem('theme') || 'light');
    btn.addEventListener('click', () => {
        const t = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', t); setTheme(t);
    });

    // Tick off steps
    document.querySelectorAll('.steps li').forEach(li =>
        li.addEventListener('click', () => li.classList.toggle('done')));
</script>
</body>
</html>