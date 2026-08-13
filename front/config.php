<?php

declare(strict_types=1);

defined('GLPI_ROOT') or die('No direct access allowed');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

use GlpiPlugin\Brandpulse\BrandAssetStore;
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

function plugin_brandpulse_csrf_token_name(): string
{
    return defined('Session::CSRF_TOKEN') ? Session::CSRF_TOKEN : '_glpi_csrf_token';
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

    return rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/brandpulse';
}

function plugin_brandpulse_config_url(string $tab): string
{
    return plugin_brandpulse_web_base() . '/front/config.php?tab=' . rawurlencode($tab);
}

function plugin_brandpulse_brand_asset_fields(): array
{
    return [
        'favicon' => [
            'section' => 'identity',
            'label' => 'Favicon',
            'column' => 'col-md-6',
            'extensions' => ['svg', 'png', 'ico'],
            'accept' => 'image/svg+xml,image/png,image/x-icon,.ico',
        ],
        'logo_sidebar_expanded_light' => [
            'section' => 'sidebar',
            'label' => 'Expanded sidebar logo, light theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png'],
            'accept' => 'image/svg+xml,image/png',
        ],
        'logo_sidebar_expanded_dark' => [
            'section' => 'sidebar',
            'label' => 'Expanded sidebar logo, dark theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png'],
            'accept' => 'image/svg+xml,image/png',
        ],
        'logo_sidebar_expanded_grey' => [
            'section' => 'sidebar',
            'label' => 'Expanded sidebar logo, neutral theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png'],
            'accept' => 'image/svg+xml,image/png',
        ],
        'logo_sidebar_collapsed_light' => [
            'section' => 'sidebar',
            'label' => 'Collapsed sidebar logo, light theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png'],
            'accept' => 'image/svg+xml,image/png',
        ],
        'logo_sidebar_collapsed_dark' => [
            'section' => 'sidebar',
            'label' => 'Collapsed sidebar logo, dark theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png'],
            'accept' => 'image/svg+xml,image/png',
        ],
        'logo_sidebar_collapsed_grey' => [
            'section' => 'sidebar',
            'label' => 'Collapsed sidebar logo, neutral theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png'],
            'accept' => 'image/svg+xml,image/png',
        ],
        'login_logo_light' => [
            'section' => 'login',
            'label' => 'Login logo, light theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png'],
            'accept' => 'image/svg+xml,image/png',
        ],
        'login_logo_dark' => [
            'section' => 'login',
            'label' => 'Login logo, dark theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png'],
            'accept' => 'image/svg+xml,image/png',
        ],
        'login_logo_grey' => [
            'section' => 'login',
            'label' => 'Login logo, neutral theme',
            'column' => 'col-xl-4 col-md-6',
            'extensions' => ['svg', 'png'],
            'accept' => 'image/svg+xml,image/png',
        ],
        'login_background' => [
            'section' => 'login',
            'label' => 'Login background',
            'column' => 'col-md-12',
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'accept' => 'image/jpeg,image/png,image/webp',
        ],
    ];
}

function plugin_brandpulse_brand_asset_sections(): array
{
    return [
        'identity' => ['title' => 'Identity', 'description' => 'Browser title, favicon and global identity assets.'],
        'sidebar' => ['title' => 'Sidebar logos', 'description' => 'Theme-aware logos for expanded and collapsed GLPI navigation.'],
        'login' => ['title' => 'Login page', 'description' => 'Login logos, background and alert message.'],
        'diagnostic' => ['title' => 'Diagnostic', 'description' => 'Stored file path, generated URLs and asset state.'],
    ];
}

function plugin_brandpulse_clean_upload_name(string $name, string $field, string $extension): string
{
    $base = pathinfo($name, PATHINFO_FILENAME);
    $base = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $base));
    $base = trim($base, '-_');
    if ($base === '') {
        $base = $field;
    }

    return $field . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '-' . substr($base, 0, 60) . '.' . $extension;
}

function plugin_brandpulse_unsupported_image_error(string $label): RuntimeException
{
    return new RuntimeException(sprintf(__('Unsupported image type for "%s".', 'brandpulse'), __($label, 'brandpulse')));
}

function plugin_brandpulse_validate_svg_upload(string $tmpName, string $label): void
{
    $content = (string) file_get_contents($tmpName);
    if (!preg_match('/<svg[\s>]/i', $content)) {
        throw plugin_brandpulse_unsupported_image_error($label);
    }

    if (preg_match('/<script\b|\bon[a-z]+\s*=|javascript:/i', $content)) {
        throw plugin_brandpulse_unsupported_image_error($label);
    }
}

function plugin_brandpulse_import_brand_asset(string $field, array $fieldConfig): ?string
{
    $file = $_FILES[$field . '_upload'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $label = (string) $fieldConfig['label'];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(sprintf(__('Image upload failed for "%s".', 'brandpulse'), __($label, 'brandpulse')));
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException(sprintf(__('Image upload failed for "%s".', 'brandpulse'), __($label, 'brandpulse')));
    }

    $maxSize = 8 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException(sprintf(__('The image is too large for "%s".', 'brandpulse'), __($label, 'brandpulse')));
    }

    $originalName = (string) ($file['name'] ?? 'brand-image');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = is_array($fieldConfig['extensions'] ?? null) ? $fieldConfig['extensions'] : [];
    if (!in_array($extension, $allowedExtensions, true)) {
        throw plugin_brandpulse_unsupported_image_error($label);
    }

    if ($extension === 'svg') {
        plugin_brandpulse_validate_svg_upload($tmpName, $label);
    } elseif ($extension !== 'ico' && @getimagesize($tmpName) === false) {
        throw plugin_brandpulse_unsupported_image_error($label);
    }

    $uploadDir = BrandAssetStore::brandDirectory();
    if (!BrandAssetStore::ensureBrandDirectory()) {
        throw new RuntimeException(__('Unable to create the Brand upload directory.', 'brandpulse'));
    }

    $filename = plugin_brandpulse_clean_upload_name($originalName, $field, $extension);
    $destination = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpName, $destination)) {
        throw new RuntimeException(sprintf(__('Unable to store the uploaded image for "%s".', 'brandpulse'), __($label, 'brandpulse')));
    }

    @chmod($destination, 0644);

    return BrandAssetStore::assetUrl($filename);
}

function plugin_brandpulse_brand_asset_filename(string $value): ?string
{
    $parts = parse_url($value);
    if (!is_array($parts) || !str_ends_with((string) ($parts['path'] ?? ''), '/plugins/brandpulse/front/asset.php')) {
        return null;
    }

    parse_str((string) ($parts['query'] ?? ''), $query);
    $file = (string) ($query['file'] ?? '');

    return $file !== '' ? basename(str_replace('\\', '/', $file)) : null;
}

function plugin_brandpulse_brand_asset_status(string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return ['class' => 'secondary', 'label' => 'Not configured', 'detail' => ''];
    }

    $filename = plugin_brandpulse_brand_asset_filename($value);
    if ($filename !== null) {
        return BrandAssetStore::assetPath($filename) !== null
            ? ['class' => 'success', 'label' => 'Stored file OK', 'detail' => $filename]
            : ['class' => 'danger', 'label' => 'Stored file missing', 'detail' => $filename];
    }

    return ['class' => 'info', 'label' => 'External URL or path', 'detail' => $value];
}

function plugin_brandpulse_icon_field(string $name, array $options, string $selected): string
{
    $baseUrl = plugin_brandpulse_web_base() . '/icons/pulse/';
    $isKnown = array_key_exists($selected, $options);
    $value = $selected !== '' ? $selected : BrandpulseConfig::DEFAULT_PULSE_ICON;
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

function plugin_brandpulse_brand_asset_input(string $name, array $fieldConfig, string $value): string
{
    $label = (string) $fieldConfig['label'];
    $columnClass = (string) $fieldConfig['column'];
    $id = 'brandpulse_' . preg_replace('/[^a-z0-9_]+/', '_', $name);
    $fileId = $id . '_upload';
    $accept = (string) ($fieldConfig['accept'] ?? 'image/*');
    $extensions = implode(', ', array_map(static fn ($extension): string => '.' . $extension, $fieldConfig['extensions'] ?? []));
    $previewUrl = trim($value);
    $status = plugin_brandpulse_brand_asset_status($value);

    $html = "<div class='" . plugin_brandpulse_h($columnClass) . "'>";
    $html .= "<div class='brandpulse-brand-field'>";
    $html .= "<div class='brandpulse-brand-field-head'>";
    $html .= "<label class='form-label' for='" . plugin_brandpulse_h($id) . "'>" . __s($label, 'brandpulse') . '</label>';
    $html .= "<span class='badge bg-" . plugin_brandpulse_h($status['class']) . "'>" . __s($status['label'], 'brandpulse') . '</span>';
    $html .= '</div>';
    $html .= "<input class='form-control' id='" . plugin_brandpulse_h($id) . "' type='text' name='" . plugin_brandpulse_h($name) . "' value='" . plugin_brandpulse_h($value) . "'>";
    $html .= "<input class='form-control form-control-sm mt-2 brandpulse-brand-upload' id='" . plugin_brandpulse_h($fileId) . "' type='file' name='" . plugin_brandpulse_h($name . '_upload') . "' accept='" . plugin_brandpulse_h($accept) . "'>";
    $html .= "<div class='form-text'>" . __s('Enter a URL, or choose a local image and save to fill this URL automatically.', 'brandpulse') . ' ';
    $html .= sprintf(__s('Accepted formats: %s', 'brandpulse'), plugin_brandpulse_h($extensions)) . '</div>';
    if ($previewUrl !== '') {
        $html .= "<div class='brandpulse-brand-preview mt-2'>";
        $html .= "<img src='" . plugin_brandpulse_h($previewUrl) . "' alt='" . __s($label, 'brandpulse') . "'>";
        $html .= "<code title='" . plugin_brandpulse_h($previewUrl) . "'>" . plugin_brandpulse_h($previewUrl) . '</code>';
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

function plugin_brandpulse_brand_section(string $title, string $description, string $content): string
{
    return "<section class='brandpulse-brand-section'>"
        . "<div class='brandpulse-brand-section-head'><strong>" . __s($title, 'brandpulse') . "</strong><span>" . __s($description, 'brandpulse') . "</span></div>"
        . $content
        . '</section>';
}

function plugin_brandpulse_brand_diagnostic(array $fields, array $branding): string
{
    $html = "<div class='table-responsive'><table class='table table-sm align-middle brandpulse-brand-diagnostic mb-0'>";
    $html .= '<thead><tr><th>' . __s('Asset', 'brandpulse') . '</th><th>' . __s('Status', 'brandpulse') . '</th><th>' . __s('URL or path', 'brandpulse') . '</th></tr></thead><tbody>';

    foreach ($fields as $field => $fieldConfig) {
        $value = (string) ($branding[$field] ?? '');
        $status = plugin_brandpulse_brand_asset_status($value);
        $detail = $status['detail'] !== '' ? $status['detail'] : $value;
        $html .= '<tr>';
        $html .= '<td>' . __s((string) $fieldConfig['label'], 'brandpulse') . '</td>';
        $html .= "<td><span class='badge bg-" . plugin_brandpulse_h($status['class']) . "'>" . __s($status['label'], 'brandpulse') . '</span></td>';
        $html .= '<td><code>' . plugin_brandpulse_h($detail) . '</code></td>';
        $html .= '</tr>';
    }

    return $html . '</tbody></table></div>';
}


$tab = (string) ($_GET['tab'] ?? $_POST['tab'] ?? 'brand');
$tab = in_array($tab, ['brand', 'pulse'], true) ? $tab : 'brand';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($tab === 'brand') {
        $assetFields = plugin_brandpulse_brand_asset_fields();
        $postedBranding = [
            'enabled' => isset($_POST['brand_enabled']),
            'title' => (string) ($_POST['title'] ?? ''),
            'login_alert_enabled' => isset($_POST['login_alert_enabled']),
            'login_alert_type' => (string) ($_POST['login_alert_type'] ?? 'info'),
            'login_alert_message' => (string) ($_POST['login_alert_message'] ?? ''),
        ];

        foreach ($assetFields as $field => $fieldConfig) {
            $postedBranding[(string) $field] = (string) ($_POST[(string) $field] ?? '');

            try {
                $uploadedUrl = plugin_brandpulse_import_brand_asset((string) $field, $fieldConfig);
                if ($uploadedUrl !== null) {
                    $postedBranding[(string) $field] = $uploadedUrl;
                }
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        $postedBranding['menu_logo'] = (string) ($postedBranding['logo_sidebar_expanded_light'] ?? '');
        $postedBranding['login_logo'] = (string) ($postedBranding['login_logo_light'] ?? '');

        if ($errors === []) {
            BrandpulseConfig::saveBranding($postedBranding);
            Session::addMessageAfterRedirect(__('Brand settings updated.', 'brandpulse'));
            Html::redirect(plugin_brandpulse_config_url('brand'));
        }
    }

    if ($tab === 'pulse') {
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
            $icon = (string) ($row['icon'] ?? BrandpulseConfig::DEFAULT_PULSE_ICON);

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
        Html::redirect(plugin_brandpulse_config_url('pulse'));
    }
}

$config = BrandpulseConfig::values();
$branding = $config['branding'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'brand' && $errors !== [] && isset($postedBranding)) {
    $branding = BrandpulseConfig::normalizeBranding($postedBranding);
}
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
        'icon' => BrandpulseConfig::DEFAULT_PULSE_ICON,
        'color' => '#3b82f6',
        'enabled' => false,
        'source_type' => 'saved_search',
        'savedsearches_id' => 0,
        'warning_threshold' => 0,
        'critical_threshold' => 0,
    ];
}

Html::header(__('GLPI BrandPulse', 'brandpulse'), plugin_brandpulse_config_url($tab), 'config', 'plugins');

echo "<div class='brandpulse-config'>";
echo '<h1>' . __s('GLPI BrandPulse', 'brandpulse') . '</h1>';
echo '<p>' . __s('Settings categories: Brand for visual identity, Pulse for header counters.', 'brandpulse') . '</p>';
echo "<p class='text-muted'>" . __s('Pulse is only displayed in the GLPI central interface, not in the helpdesk/self-service portal.', 'brandpulse') . '</p>';
echo "<p class='text-muted'>" . sprintf(
    __s('Installed BrandPulse schema: %s', 'brandpulse'),
    Html::entities_deep(BrandpulseConfig::schemaVersion() ?: __('not initialized', 'brandpulse'))
) . '</p>';

echo "<ul class='nav nav-tabs mb-3'>";
echo "<li class='nav-item'><a class='nav-link" . ($tab === 'brand' ? ' active' : '') . "' href='" . plugin_brandpulse_h(plugin_brandpulse_config_url('brand')) . "'>" . __s('Brand', 'brandpulse') . '</a></li>';
echo "<li class='nav-item'><a class='nav-link" . ($tab === 'pulse' ? ' active' : '') . "' href='" . plugin_brandpulse_h(plugin_brandpulse_config_url('pulse')) . "'>" . __s('Pulse', 'brandpulse') . '</a></li>';
echo '</ul>';

echo '<form method="post" enctype="multipart/form-data" action="' . plugin_brandpulse_h(plugin_brandpulse_config_url($tab)) . '">';
echo Html::hidden(plugin_brandpulse_csrf_token_name(), ['value' => Session::getNewCSRFToken()]);
echo Html::hidden('tab', ['value' => $tab]);
echo "<input type='hidden' name='MAX_FILE_SIZE' value='8388608'>";

if ($errors !== []) {
    echo "<div class='alert alert-danger'>";
    foreach ($errors as $error) {
        echo '<div>' . plugin_brandpulse_h($error) . '</div>';
    }
    echo '</div>';
}

if ($tab === 'brand') {
    $assetFields = plugin_brandpulse_brand_asset_fields();
    $sections = plugin_brandpulse_brand_asset_sections();

    echo "<div class='brandpulse-brand-layout'>";

    $identityContent = "<div class='row g-3'>";
    $identityContent .= "<div class='col-md-6'><div class='brandpulse-brand-field'><div class='brandpulse-brand-field-head'><label class='form-label'>" . __s('Browser title', 'brandpulse') . '</label></div>' . plugin_brandpulse_text_input('title', (string) $branding['title']) . '</div></div>';
    $identityContent .= plugin_brandpulse_brand_asset_input('favicon', $assetFields['favicon'], (string) $branding['favicon']);
    $identityContent .= '</div>';
    $identityContent .= "<div class='form-check mt-3'>";
    $identityContent .= "<input class='form-check-input' id='brand_enabled' type='checkbox' name='brand_enabled' value='1'" . ($branding['enabled'] ? ' checked' : '') . '> ';
    $identityContent .= "<label class='form-check-label' for='brand_enabled'>" . __s('Enable branding customizations', 'brandpulse') . '</label>';
    $identityContent .= '</div>';
    echo plugin_brandpulse_brand_section($sections['identity']['title'], $sections['identity']['description'], $identityContent);

    $sidebarContent = "<div class='row g-3'>";
    foreach ($assetFields as $field => $fieldConfig) {
        if (($fieldConfig['section'] ?? '') === 'sidebar') {
            $sidebarContent .= plugin_brandpulse_brand_asset_input((string) $field, $fieldConfig, (string) $branding[$field]);
        }
    }
    $sidebarContent .= '</div>';
    echo plugin_brandpulse_brand_section($sections['sidebar']['title'], $sections['sidebar']['description'], $sidebarContent);

    $loginContent = "<div class='row g-3'>";
    foreach ($assetFields as $field => $fieldConfig) {
        if (($fieldConfig['section'] ?? '') === 'login') {
            $loginContent .= plugin_brandpulse_brand_asset_input((string) $field, $fieldConfig, (string) $branding[$field]);
        }
    }
    $loginContent .= '</div>';
    $loginContent .= "<div class='brandpulse-brand-alert mt-3'>";
    $loginContent .= "<div class='form-check mb-3'>";
    $loginContent .= "<input class='form-check-input' id='login_alert_enabled' type='checkbox' name='login_alert_enabled' value='1'" . ($branding['login_alert_enabled'] ? ' checked' : '') . '> ';
    $loginContent .= "<label class='form-check-label' for='login_alert_enabled'>" . __s('Show a login page alert message', 'brandpulse') . '</label>';
    $loginContent .= '</div>';
    $loginContent .= "<div class='row g-3'>";
    $loginContent .= "<div class='col-md-3'><label class='form-label'>" . __s('Alert type', 'brandpulse') . '</label>' . plugin_brandpulse_select('login_alert_type', $alertTypes, (string) $branding['login_alert_type']) . '</div>';
    $loginContent .= "<div class='col-md-9'><label class='form-label'>" . __s('Login alert message', 'brandpulse') . "</label><textarea class='form-control' name='login_alert_message' rows='3'>" . plugin_brandpulse_h((string) $branding['login_alert_message']) . '</textarea></div>';
    $loginContent .= '</div></div>';
    echo plugin_brandpulse_brand_section($sections['login']['title'], $sections['login']['description'], $loginContent);

    $diagnosticContent = "<div class='brandpulse-brand-storage mb-3'>";
    $diagnosticContent .= "<strong>" . __s('Stored files', 'brandpulse') . '</strong>';
    $diagnosticContent .= '<span>' . plugin_brandpulse_h(BrandAssetStore::brandDirectory()) . '</span>';
    $diagnosticContent .= '</div>';
    $diagnosticContent .= plugin_brandpulse_brand_diagnostic($assetFields, $branding);
    echo plugin_brandpulse_brand_section($sections['diagnostic']['title'], $sections['diagnostic']['description'], $diagnosticContent);

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
        echo '<td>' . plugin_brandpulse_text_input("counters[{$index}][label]", (string) ($counter['label'] ?? ''), 'text', 'form-control form-control-sm') . plugin_brandpulse_text_input("counters[{$index}][key]", (string) ($counter['key'] ?? ''), 'hidden') . '</td>';
        echo '<td>' . str_replace('<select ', '<select data-pulse-source ', plugin_brandpulse_select("counters[{$index}][source_type]", $sourceTypes, $sourceType)) . '</td>';
        echo "<td class='brandpulse-target-cell'>";
        echo "<div data-pulse-target='preset'>" . plugin_brandpulse_select("counters[{$index}][preset_key]", $presetCounters, $presetKey) . '</div>';
        echo "<div data-pulse-target='saved_search'>" . plugin_brandpulse_select("counters[{$index}][savedsearches_id]", $savedSearches, $savedSearchId, false) . '</div>';
        echo '</td>';
        $iconValue = (string) ($counter['icon'] ?? BrandpulseConfig::DEFAULT_PULSE_ICON);
        $customIcon = !array_key_exists($iconValue, $icons) && !str_starts_with($iconValue, 'pulse:') ? $iconValue : '';
        echo '<td>' . plugin_brandpulse_icon_field("counters[{$index}][icon]", $icons, $iconValue) . '</td>';
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
    echo '<strong>' . __s('Choose an icon', 'brandpulse') . '</strong>';
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
