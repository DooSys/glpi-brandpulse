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
        $errors[] = __('Brand JSON is invalid.', 'brandpulse');
    }

    if (!BrandpulseConfig::isValidJsonArray($countersJson)) {
        $errors[] = __('Pulse counters JSON is invalid.', 'brandpulse');
    }

    if ($errors === []) {
        BrandpulseConfig::save([
            'enabled' => $enabled,
            'refresh_interval' => (string) $refreshInterval,
            'branding_json' => $brandingJson,
            'counters_json' => $countersJson,
        ]);

        Session::addMessageAfterRedirect(__('BrandPulse configuration updated.', 'brandpulse'));
        Html::redirect($_SERVER['PHP_SELF']);
    }
}

$config = BrandpulseConfig::values();
$rawConfig = BrandpulseConfig::rawValues();

Html::header(__('GLPI BrandPulse', 'brandpulse'), $_SERVER['PHP_SELF'], 'config', 'plugins');

echo "<div class='brandpulse-config'>";
echo '<h1>' . __s('GLPI BrandPulse', 'brandpulse') . '</h1>';
echo '<p>' . __s('Settings categories: Brand for visual identity, Pulse for header counters.', 'brandpulse') . '</p>';
echo "<p class='text-muted'>" . sprintf(
    __s('Installed BrandPulse schema: %s', 'brandpulse'),
    Html::entities_deep(BrandpulseConfig::schemaVersion() ?: __('not initialized', 'brandpulse'))
) . '</p>';

foreach ($errors as $error) {
    echo "<div class='alert alert-danger'>" . Html::entities_deep($error) . '</div>';
}

echo "<form method='post' action='" . Html::entities_deep($_SERVER['PHP_SELF']) . "'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

echo "<div class='card mb-3'>";
echo "<div class='card-header'><strong>" . __s('Activation', 'brandpulse') . '</strong></div>';
echo "<div class='card-body'>";
echo "<label class='form-check'>";
echo "<input class='form-check-input' type='checkbox' name='enabled' value='1'" . ($config['enabled'] ? ' checked' : '') . '> ';
echo "<span class='form-check-label'>" . __s('Display BrandPulse in the header', 'brandpulse') . '</span>';
echo '</label>';
echo "<label class='form-label mt-3' for='refresh_interval'>" . __s('Counter refresh interval, in seconds', 'brandpulse') . '</label>';
echo "<input class='form-control' id='refresh_interval' type='number' min='15' name='refresh_interval' value='" . (int) $config['refresh_interval'] . "'>";
echo '</div>';
echo '</div>';

echo "<div class='card mb-3'>";
echo "<div class='card-header'><strong>" . __s('Brand', 'brandpulse') . '</strong></div>';
echo "<div class='card-body'>";
echo "<textarea class='form-control font-monospace' name='branding_json' rows='8'>" . Html::entities_deep((string) $rawConfig['branding_json']) . '</textarea>';
echo '</div>';
echo '</div>';

echo "<div class='card mb-3'>";
echo "<div class='card-header'><strong>" . __s('Pulse', 'brandpulse') . '</strong></div>';
echo "<div class='card-body'>";
echo "<textarea class='form-control font-monospace' name='counters_json' rows='22'>" . Html::entities_deep((string) $rawConfig['counters_json']) . '</textarea>';
echo '</div>';
echo "<div class='table-responsive'>";
echo "<table class='table table-sm mb-0'>";
echo '<thead><tr>';
echo '<th>' . __s('Order', 'brandpulse') . '</th>';
echo '<th>' . __s('Key', 'brandpulse') . '</th>';
echo '<th>' . __s('Label', 'brandpulse') . '</th>';
echo '<th>' . __s('Icon', 'brandpulse') . '</th>';
echo '<th>' . __s('Color', 'brandpulse') . '</th>';
echo '<th>' . __s('Enabled', 'brandpulse') . '</th>';
echo '</tr></thead><tbody>';

foreach (array_values($config['counters']) as $index => $counter) {
    $enabled = !empty($counter['enabled']) ? __('yes', 'brandpulse') : __('no', 'brandpulse');
    $color = Html::entities_deep((string) ($counter['color'] ?? ''));

    echo '<tr>';
    echo '<td>' . ((int) $index + 1) . '</td>';
    echo '<td><code>' . Html::entities_deep((string) ($counter['key'] ?? '')) . '</code></td>';
    echo '<td>' . Html::entities_deep(__((string) ($counter['label'] ?? ''), 'brandpulse')) . '</td>';
    echo '<td><code>' . Html::entities_deep((string) ($counter['icon'] ?? '')) . '</code></td>';
    echo '<td><span class="brandpulse-color-preview" style="background:' . $color . '"></span><code>' . $color . '</code></td>';
    echo '<td>' . Html::entities_deep($enabled) . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo '</div>';
echo '<div class="card-footer text-muted">' . __s('Version 0.1 exposes raw JSON editing for Brand and Pulse categories. The full graphical screen will come next.', 'brandpulse') . '</div>';
echo '</div>';

echo "<button class='btn btn-primary' type='submit'>" . __s('Save', 'brandpulse') . '</button>';
echo '</form>';
echo '</div>';

Html::footer();
