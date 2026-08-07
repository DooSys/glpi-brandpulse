<?php

declare(strict_types=1);

include('../../../inc/includes.php');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

use GlpiPlugin\Brandpulse\Config as BrandpulseConfig;

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCSRF($_POST);

    $enabled = isset($_POST['enabled']) ? '1' : '0';
    $refreshInterval = max(15, (int) ($_POST['refresh_interval'] ?? 60));
    $brandingJson = trim((string) ($_POST['branding_json'] ?? ''));
    $countersJson = trim((string) ($_POST['counters_json'] ?? ''));

    if (!BrandpulseConfig::isValidJsonArray($brandingJson)) {
        $errors[] = 'Le JSON branding est invalide.';
    }

    if (!BrandpulseConfig::isValidJsonArray($countersJson)) {
        $errors[] = 'Le JSON des compteurs est invalide.';
    }

    if ($errors === []) {
        BrandpulseConfig::save([
            'enabled' => $enabled,
            'refresh_interval' => (string) $refreshInterval,
            'branding_json' => $brandingJson,
            'counters_json' => $countersJson,
        ]);

        Session::addMessageAfterRedirect('Configuration BrandPulse mise à jour.');
        Html::redirect($_SERVER['PHP_SELF']);
    }
}

$config = BrandpulseConfig::values();
$rawConfig = BrandpulseConfig::rawValues();

Html::header('GLPI BrandPulse', $_SERVER['PHP_SELF'], 'config', 'plugins');

echo "<div class='brandpulse-config'>";
echo "<h1>GLPI BrandPulse</h1>";
echo "<p>Catégories de paramétrage : Brand pour l’identité visuelle, Pulse pour les compteurs du header.</p>";
echo "<p class='text-muted'>Schéma BrandPulse installé : " . Html::entities_deep(BrandpulseConfig::schemaVersion() ?: 'non initialisé') . "</p>";

foreach ($errors as $error) {
    echo "<div class='alert alert-danger'>" . Html::entities_deep($error) . "</div>";
}

echo "<form method='post' action='" . Html::entities_deep($_SERVER['PHP_SELF']) . "'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

echo "<div class='card mb-3'>";
echo "<div class='card-header'><strong>Activation</strong></div>";
echo "<div class='card-body'>";
echo "<label class='form-check'>";
echo "<input class='form-check-input' type='checkbox' name='enabled' value='1'" . ($config['enabled'] ? ' checked' : '') . "> ";
echo "<span class='form-check-label'>Afficher BrandPulse dans le header</span>";
echo "</label>";
echo "<label class='form-label mt-3' for='refresh_interval'>Rafraîchissement des compteurs, en secondes</label>";
echo "<input class='form-control' id='refresh_interval' type='number' min='15' name='refresh_interval' value='" . (int) $config['refresh_interval'] . "'>";
echo "</div>";
echo "</div>";

echo "<div class='card mb-3'>";
echo "<div class='card-header'><strong>Brand</strong></div>";
echo "<div class='card-body'>";
echo "<textarea class='form-control font-monospace' name='branding_json' rows='8'>" . Html::entities_deep((string) $rawConfig['branding_json']) . "</textarea>";
echo "</div>";
echo "</div>";

echo "<div class='card mb-3'>";
echo "<div class='card-header'><strong>Pulse</strong></div>";
echo "<div class='card-body'>";
echo "<textarea class='form-control font-monospace' name='counters_json' rows='22'>" . Html::entities_deep((string) $rawConfig['counters_json']) . "</textarea>";
echo "</div>";
echo "<div class='table-responsive'>";
echo "<table class='table table-sm mb-0'>";
echo "<thead><tr><th>Ordre</th><th>Clé</th><th>Libellé</th><th>Icône</th><th>Couleur</th><th>Actif</th></tr></thead><tbody>";

foreach (array_values($config['counters']) as $index => $counter) {
    $enabled = !empty($counter['enabled']) ? 'oui' : 'non';
    $color = Html::entities_deep((string) ($counter['color'] ?? ''));

    echo '<tr>';
    echo '<td>' . ((int) $index + 1) . '</td>';
    echo '<td><code>' . Html::entities_deep((string) ($counter['key'] ?? '')) . '</code></td>';
    echo '<td>' . Html::entities_deep((string) ($counter['label'] ?? '')) . '</td>';
    echo '<td><code>' . Html::entities_deep((string) ($counter['icon'] ?? '')) . '</code></td>';
    echo '<td><span class="brandpulse-color-preview" style="background:' . $color . '"></span><code>' . $color . '</code></td>';
    echo '<td>' . $enabled . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo '</div>';
echo '<div class="card-footer text-muted">La v0.1 expose une édition JSON brute pour les catégories Brand et Pulse. L écran graphique complet viendra ensuite.</div>';
echo '</div>';

echo "<button class='btn btn-primary' type='submit'>Enregistrer</button>";
echo "</form>";
echo "</div>";

Html::footer();
