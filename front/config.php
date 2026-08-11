<?php

declare(strict_types=1);

defined('GLPI_ROOT') or die('No direct access allowed');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

use GlpiPlugin\Brandpulse\Config as BrandpulseConfig;

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

function plugin_brandpulse_h(string $value): string
{
    return Html::entities_deep($value);
}

function plugin_brandpulse_saved_search_rows(): array
{
    global $DB;

    $rows = [0 => __('None', 'brandpulse')];

    if (!isset($DB) || !method_exists($DB, 'request') || !class_exists(SavedSearch::class)) {
        return $rows;
    }

    try {
        $iterator = $DB->request([
            'SELECT' => ['id', 'name', 'users_id', 'is_private'],
            'FROM' => SavedSearch::getTable(),
            'WHERE' => [
                'itemtype' => Ticket::class,
                'type' => SavedSearch::SEARCH,
            ],
            'ORDER' => 'name',
        ]);

        $userId = Session::getLoginUserID();
        foreach ($iterator as $row) {
            $isPrivate = (int) ($row['is_private'] ?? 1) === 1;
            if ($isPrivate && (int) ($row['users_id'] ?? 0) !== $userId) {
                continue;
            }

            $rows[(int) $row['id']] = (string) ($row['name'] ?: '#' . $row['id']);
        }
    } catch (Throwable) {
        return $rows;
    }

    return $rows;
}

function plugin_brandpulse_select(string $name, array $options, string|int $selected, bool $translate = true): string
{
    $html = "<select class='form-select form-select-sm' name='" . plugin_brandpulse_h($name) . "'>";

    foreach ($options as $value => $label) {
        $isSelected = (string) $value === (string) $selected ? ' selected' : '';
        $text = $translate ? __((string) $label, 'brandpulse') : (string) $label;
        $html .= "<option value='" . plugin_brandpulse_h((string) $value) . "'" . $isSelected . '>' . plugin_brandpulse_h($text) . '</option>';
    }

    return $html . '</select>';
}

function plugin_brandpulse_web_base(): string
{
    global $CFG_GLPI;

    if (class_exists(Plugin::class) && method_exists(Plugin::class, 'getWebDir')) {
        try {
            $webDir = (string) Plugin::getWebDir('brandpulse');
            if ($webDir !== '') {
                return rtrim($webDir, '/');
            }
        } catch (Throwable) {
        }
    }

    return rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/brandpulse';
}

function plugin_brandpulse_config_url(string $tab): string
{
    $path = parse_url((string) ($_SERVER["REQUEST_URI"] ?? ""), PHP_URL_PATH);
    if (!is_string($path) || $path === "") {
        $path = plugin_brandpulse_web_base() . "/front/config.php";
    }

    return $path . "?tab=" . rawurlencode($tab);
}

function plugin_brandpulse_icon_field(string $name, array $options, string $selected): string
{
    $baseUrl = plugin_brandpulse_web_base() . '/icons/pulse/';
    $isKnown = array_key_exists($selected, $options);
    $value = $selected !== '' ? $selected : 'pulse:Notifications/Bell.svg';
    $path = str_starts_with($value, 'pulse:') ? substr($value, 6) : '';
    $url = $path !== '' ? $baseUrl . implode('/', array_map('rawurlencode', explode('/', $path))) : '';
    $fallbackLabel = $path !== '' ? str_replace('/', ' / ', preg_replace('/\.svg$/', '', $path)) : __('Custom icon', 'brandpulse');
    $label = $isKnown ? (string) $options[$selected] : $fallbackLabel;

    $html = "<div class='brandpulse-icon-field' data-icon-field>";
    $html .= "<input type='hidden' data-icon-value name='" . plugin_brandpulse_h($name) . "' value='" . plugin_brandpulse_h($value) . "'>";
    $html .= "<button type='button' class='btn btn-sm btn-outline-secondary brandpulse-icon-open' data-icon-open>";
    $html .= "<span class='brandpulse-icon-preview' data-icon-preview style='-webkit-mask-image: url(&quot;" . plugin_brandpulse_h($url) . "&quot;); mask-image: url(&quot;" . plugin_brandpulse_h($url) . "&quot;);'></span>";
    $html .= "<span data-icon-label>" . plugin_brandpulse_h($label) . '</span>';
    $html .= '</button>';
    $html .= '</div>';

    return $html;
}

function plugin_brandpulse_text_input(string $name, string $value, string $type = 'text', string $class = 'form-control'): string
{
    return "<input class='" . plugin_brandpulse_h($class) . "' type='" . plugin_brandpulse_h($type) . "' name='" . plugin_brandpulse_h($name) . "' value='" . plugin_brandpulse_h($value) . "'>";
}

$tab = (string) ($_GET['tab'] ?? $_POST['tab'] ?? 'brand');
$tab = in_array($tab, ['brand', 'pulse'], true) ? $tab : 'brand';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCSRF($_POST);

    if ($tab === 'brand') {
        BrandpulseConfig::saveBranding([
            'enabled' => isset($_POST['brand_enabled']),
            'title' => (string) ($_POST['title'] ?? ''),
            'favicon' => (string) ($_POST['favicon'] ?? ''),
            'login_logo' => (string) ($_POST['login_logo'] ?? ''),
            'menu_logo' => (string) ($_POST['menu_logo'] ?? ''),
            'login_background' => (string) ($_POST['login_background'] ?? ''),
            'login_alert_enabled' => isset($_POST['login_alert_enabled']),
            'login_alert_type' => (string) ($_POST['login_alert_type'] ?? 'info'),
            'login_alert_message' => (string) ($_POST['login_alert_message'] ?? ''),
        ]);

        Session::addMessageAfterRedirect(__('Brand settings updated.', 'brandpulse'));
        Html::redirect(plugin_brandpulse_config_url("brand"));
    }

    $postedCounters = is_array($_POST['counters'] ?? null) ? $_POST['counters'] : [];
    $counters = [];

    foreach ($postedCounters as $row) {
        if (!is_array($row)) {
            continue;
        }

        $sourceType = (string) ($row['source_type'] ?? 'preset');
        $savedSearchId = (int) ($row['savedsearches_id'] ?? 0);
        $key = $sourceType === 'saved_search'
            ? 'saved_search_' . $savedSearchId
            : (string) ($row['preset_key'] ?? '');

        $iconCustom = trim((string) ($row['icon_custom'] ?? ''));
        $icon = $iconCustom !== '' ? $iconCustom : (string) ($row['icon'] ?? 'pulse:Notifications/Bell.svg');

        $counters[] = [
            'key' => $key,
            'label' => (string) ($row['label'] ?? ''),
            'icon' => $icon,
            'color' => (string) ($row['color'] ?? '#3b82f6'),
            'enabled' => isset($row['enabled']),
            'source_type' => $sourceType,
            'savedsearches_id' => $savedSearchId,
            'warning_threshold' => (int) ($row['warning_threshold'] ?? 0),
            'critical_threshold' => (int) ($row['critical_threshold'] ?? 0),
        ];
    }

    BrandpulseConfig::savePulse(
        isset($_POST['enabled']),
        (int) ($_POST['refresh_interval'] ?? 60),
        isset($_POST['compact_search_enabled']),
        $counters
    );

    Session::addMessageAfterRedirect(__('Pulse settings updated.', 'brandpulse'));
    Html::redirect(plugin_brandpulse_config_url("pulse"));
}

$config = BrandpulseConfig::values();
$branding = $config['branding'];
$counters = array_values($config['counters']);
$presetCounters = BrandpulseConfig::presetCounters();
$savedSearches = plugin_brandpulse_saved_search_rows();
$icons = BrandpulseConfig::pulseIcons();
$sourceTypes = [
    'preset' => 'GLPI preset',
    'saved_search' => 'GLPI saved search',
];
$alertTypes = [
    'info' => 'Info',
    'warning' => 'Warning',
    'danger' => 'Danger',
    'success' => 'Success',
];

while (count($counters) < 8) {
    $counters[] = [
        'key' => '',
        'label' => '',
        'icon' => 'pulse:Notifications/Bell.svg',
        'color' => '#3b82f6',
        'enabled' => false,
        'source_type' => 'saved_search',
        'savedsearches_id' => 0,
        'warning_threshold' => 0,
        'critical_threshold' => 0,
    ];
}

Html::header(__("GLPI BrandPulse", "brandpulse"), plugin_brandpulse_config_url($tab), "config", "plugins");

echo "<div class='brandpulse-config'>";
echo '<h1>' . __s('GLPI BrandPulse', 'brandpulse') . '</h1>';
echo '<p>' . __s('Settings categories: Brand for visual identity, Pulse for header counters.', 'brandpulse') . '</p>';
echo "<p class='text-muted'>" . __s('Pulse is only displayed in the GLPI central interface, not in the helpdesk/self-service portal.', 'brandpulse') . '</p>';
echo "<p class='text-muted'>" . sprintf(
    __s('Installed BrandPulse schema: %s', 'brandpulse'),
    Html::entities_deep(BrandpulseConfig::schemaVersion() ?: __('not initialized', 'brandpulse'))
) . '</p>';

echo "<ul class='nav nav-tabs mb-3'>";
echo "<li class='nav-item'><a class='nav-link" . ($tab === 'brand' ? ' active' : '') . "' href='?tab=brand'>" . __s('Brand', 'brandpulse') . '</a></li>';
echo "<li class='nav-item'><a class='nav-link" . ($tab === 'pulse' ? ' active' : '') . "' href='?tab=pulse'>" . __s('Pulse', 'brandpulse') . '</a></li>';
echo '</ul>';

echo "<form method=\"post\" action=\"" . plugin_brandpulse_h(plugin_brandpulse_config_url($tab)) . "\">";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo Html::hidden('tab', ['value' => $tab]);

if ($tab === 'brand') {
    echo "<div class='card mb-3'>";
    echo "<div class='card-header'><strong>" . __s('Brand', 'brandpulse') . '</strong></div>';
    echo "<div class='card-body'>";
    echo "<div class='form-check mb-3'>";
    echo "<input class='form-check-input' id='brand_enabled' type='checkbox' name='brand_enabled' value='1'" . ($branding['enabled'] ? ' checked' : '') . '> ';
    echo "<label class='form-check-label' for='brand_enabled'>" . __s('Enable branding customizations', 'brandpulse') . '</label>';
    echo '</div>';

    echo "<div class='row g-3'>";
    echo "<div class='col-md-6'><label class='form-label'>" . __s('Browser title', 'brandpulse') . '</label>' . plugin_brandpulse_text_input('title', (string) $branding['title']) . '</div>';
    echo "<div class='col-md-6'><label class='form-label'>" . __s('Favicon URL or path', 'brandpulse') . '</label>' . plugin_brandpulse_text_input('favicon', (string) $branding['favicon']) . '</div>';
    echo "<div class='col-md-6'><label class='form-label'>" . __s('Login logo URL or path', 'brandpulse') . '</label>' . plugin_brandpulse_text_input('login_logo', (string) $branding['login_logo']) . '</div>';
    echo "<div class='col-md-6'><label class='form-label'>" . __s('Left menu logo URL or path', 'brandpulse') . '</label>' . plugin_brandpulse_text_input('menu_logo', (string) $branding['menu_logo']) . '</div>';
    echo "<div class='col-md-12'><label class='form-label'>" . __s('Login background URL or path', 'brandpulse') . '</label>' . plugin_brandpulse_text_input('login_background', (string) $branding['login_background']) . '</div>';
    echo '</div>';

    echo "<hr>";
    echo "<div class='form-check mb-3'>";
    echo "<input class='form-check-input' id='login_alert_enabled' type='checkbox' name='login_alert_enabled' value='1'" . ($branding['login_alert_enabled'] ? ' checked' : '') . '> ';
    echo "<label class='form-check-label' for='login_alert_enabled'>" . __s('Show a login page alert message', 'brandpulse') . '</label>';
    echo '</div>';
    echo "<div class='row g-3'>";
    echo "<div class='col-md-3'><label class='form-label'>" . __s('Alert type', 'brandpulse') . '</label>' . plugin_brandpulse_select('login_alert_type', $alertTypes, (string) $branding['login_alert_type']) . '</div>';
    echo "<div class='col-md-9'><label class='form-label'>" . __s('Login alert message', 'brandpulse') . "</label><textarea class='form-control' name='login_alert_message' rows='3'>" . plugin_brandpulse_h((string) $branding['login_alert_message']) . '</textarea></div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
} else {
    echo "<div class='card mb-3'>";
    echo "<div class='card-header d-flex align-items-center justify-content-between gap-2'><strong>" . __s('Pulse', 'brandpulse') . "</strong><button class='btn btn-primary btn-sm' type='submit'>" . __s('Save', 'brandpulse') . "</button></div>";
    echo "<div class='card-body'>";
    echo "<div class='row g-3 align-items-end'>";
    echo "<div class='col-md-3'><div class='form-check'>";
    echo "<input class='form-check-input' id='enabled' type='checkbox' name='enabled' value='1'" . ($config['enabled'] ? ' checked' : '') . '> ';
    echo "<label class='form-check-label' for='enabled'>" . __s('Display Pulse counters in the header', 'brandpulse') . '</label>';
    echo '</div></div>';
    echo "<div class='col-md-3'><label class='form-label'>" . __s('Counter refresh interval, in seconds', 'brandpulse') . "</label><input class='form-control' type='number' min='15' name='refresh_interval' value='" . (int) $config['refresh_interval'] . "'></div>";
    echo "<div class='col-md-4'><div class='form-check'>";
    echo "<input class='form-check-input' id='compact_search_enabled' type='checkbox' name='compact_search_enabled' value='1'" . ($config['compact_search_enabled'] ? ' checked' : '') . '> ';
    echo "<label class='form-check-label' for='compact_search_enabled'>" . __s('Minimize the global search field to a magnifier icon', 'brandpulse') . '</label>';
    echo '</div></div>';
    echo '</div>';
    echo '</div>';

    echo "<div class='table-responsive'>";
    echo "<table class='table table-sm align-middle mb-0 brandpulse-pulse-table'>";
    echo '<thead><tr>';
    echo '<th>' . __s('Enabled', 'brandpulse') . '</th>';
    echo '<th>' . __s('Label', 'brandpulse') . '</th>';
    echo '<th>' . __s('Source', 'brandpulse') . '</th>';
    echo '<th>' . __s('Target', 'brandpulse') . '</th>';
    echo '<th>' . __s('Icon', 'brandpulse') . '</th>';
    echo '<th>' . __s('Color', 'brandpulse') . '</th>';
    echo '<th>' . __s('Warning', 'brandpulse') . '</th>';
    echo '<th>' . __s('Critical', 'brandpulse') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($counters as $index => $counter) {
        $sourceType = (string) ($counter['source_type'] ?? 'preset');
        $savedSearchId = (int) ($counter['savedsearches_id'] ?? 0);
        $presetKey = array_key_exists((string) ($counter['key'] ?? ''), $presetCounters) ? (string) $counter['key'] : 'my_tasks';

        echo '<tr>';
        echo "<td><input class='form-check-input' type='checkbox' name='counters[{$index}][enabled]' value='1'" . (!empty($counter['enabled']) ? ' checked' : '') . '></td>';
        echo "<td>" . plugin_brandpulse_text_input("counters[{$index}][label]", (string) ($counter['label'] ?? ''), 'text', 'form-control form-control-sm') . plugin_brandpulse_text_input("counters[{$index}][key]", (string) ($counter['key'] ?? ''), 'hidden') . '</td>';
        echo "<td>" . str_replace("<select ", "<select data-pulse-source ", plugin_brandpulse_select("counters[{$index}][source_type]", $sourceTypes, $sourceType)) . '</td>';
        echo "<td class='brandpulse-target-cell'>";
        echo "<div data-pulse-target='preset'>" . plugin_brandpulse_select("counters[{$index}][preset_key]", $presetCounters, $presetKey) . "</div>";
        echo "<div data-pulse-target='saved_search'>" . plugin_brandpulse_select("counters[{$index}][savedsearches_id]", $savedSearches, $savedSearchId, false) . "</div>";
        echo "</td>";
        $iconValue = (string) ($counter['icon'] ?? 'pulse:Notifications/Bell.svg');
        $customIcon = !array_key_exists($iconValue, $icons) && !str_starts_with($iconValue, 'pulse:') ? $iconValue : '';
        echo "<td>" . plugin_brandpulse_icon_field("counters[{$index}][icon]", $icons, $iconValue);
        echo plugin_brandpulse_text_input("counters[{$index}][icon_custom]", $customIcon, 'text', 'form-control form-control-sm brandpulse-icon-custom');
        echo "<div class='form-text'>" . __s('Custom SVG URL or path', 'brandpulse') . '</div></td>';
        echo "<td><input class='form-control form-control-sm form-control-color' type='color' name='counters[{$index}][color]' value='" . plugin_brandpulse_h((string) ($counter['color'] ?? '#3b82f6')) . "'></td>";
        echo "<td><input class='form-control form-control-sm' type='number' min='0' name='counters[{$index}][warning_threshold]' value='" . (int) ($counter['warning_threshold'] ?? 0) . "'></td>";
        echo "<td><input class='form-control form-control-sm' type='number' min='0' name='counters[{$index}][critical_threshold]' value='" . (int) ($counter['critical_threshold'] ?? 0) . "'></td>";
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
    echo "<div class='card-footer text-muted'>" . __s('Use GLPI presets or GLPI saved searches. Saved searches keep the native AND/OR criteria builder.', 'brandpulse') . '</div>';
    echo '</div>';
}

echo "<div class='brandpulse-action-bar'><button class='btn btn-primary' type='submit'>" . __s('Save', 'brandpulse') . '</button></div>';
echo '</form>';
if ($tab === 'pulse') {
    echo "<div class='brandpulse-icon-modal' data-icon-modal hidden>";
    echo "<div class='brandpulse-icon-modal-backdrop' data-icon-close></div>";
    echo "<div class='brandpulse-icon-dialog' role='dialog' aria-modal='true' aria-label='" . __s('Choose an icon', 'brandpulse') . "'>";
    echo "<div class='brandpulse-icon-dialog-header'>";
    echo "<strong>" . __s('Choose an icon', 'brandpulse') . '</strong>';
    echo "<button type='button' class='btn-close' data-icon-close aria-label='" . __s('Close', 'brandpulse') . "'></button>";
    echo '</div>';
    echo "<div class='brandpulse-icon-dialog-tools'>";
    echo "<input class='form-control form-control-sm' type='search' data-icon-search placeholder='" . __s('Search', 'brandpulse') . "'>";
    echo '</div>';
    echo "<div class='brandpulse-icon-results' data-icon-results></div>";
    echo "<div class='brandpulse-icon-dialog-footer'>";
    echo "<button type='button' class='btn btn-sm btn-outline-secondary' data-icon-prev>" . __s('Previous', 'brandpulse') . '</button>';
    echo "<span class='text-muted' data-icon-page></span>";
    echo "<button type='button' class='btn btn-sm btn-outline-secondary' data-icon-next>" . __s('Next', 'brandpulse') . '</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
echo '</div>';

Html::footer();
