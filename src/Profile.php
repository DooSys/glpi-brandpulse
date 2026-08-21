<?php

declare(strict_types=1);

namespace GlpiPlugin\Brandpulse;

use CommonGLPI;
use ProfileRight;
use Session;

final class Profile extends \Profile
{
    public const RIGHT_VIEW_GENERAL_PULSE = 'plugin_brandpulse_view_general_pulse';

    public static $rightname = 'profile';

    public static function getAllRights(): array
    {
        return [[
            'itemtype' => Menu::class,
            'label' => __('Display General Pulse', 'brandpulse'),
            'field' => self::RIGHT_VIEW_GENERAL_PULSE,
            'rights' => [READ => __('Display', 'brandpulse')],
        ]];
    }

    public static function canViewGeneralPulse(): bool
    {
        return class_exists(Session::class)
            && Session::haveRight(self::RIGHT_VIEW_GENERAL_PULSE, READ);
    }

    public static function installRights(): void
    {
        global $DB;

        if (!isset($DB) || !class_exists(ProfileRight::class)) {
            return;
        }

        $profiles = $DB->request([
            'SELECT' => ['id', 'interface'],
            'FROM' => \Profile::getTable(),
        ]);

        $profileRight = new ProfileRight();
        foreach ($profiles as $profile) {
            self::ensureRightForProfile(
                $profileRight,
                (int) $profile['id'],
                (string) ($profile['interface'] ?? 'central')
            );
        }

        ProfileRight::cleanAllPossibleRights();

        if (isset($_SESSION['glpiactiveprofile']['id'])) {
            Session::reloadCurrentProfile();
        }
    }

    public static function uninstallRights(): void
    {
        if (class_exists(ProfileRight::class)) {
            ProfileRight::deleteProfileRights([self::RIGHT_VIEW_GENERAL_PULSE]);
        }
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if (!$item instanceof \Profile) {
            return '';
        }

        return self::createTabEntry(__('BrandPulse', 'brandpulse'), 0, $item::getType(), 'ti ti-activity');
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if (!$item instanceof \Profile) {
            return false;
        }

        $profile = new self();
        $profile->showRightsForm((int) $item->getID());

        return true;
    }

    private static function ensureRightForProfile(ProfileRight $profileRight, int $profileId, string $interface): void
    {
        if (
            $profileId <= 0
            || countElementsInTable(ProfileRight::getTable(), [
                'profiles_id' => $profileId,
                'name' => self::RIGHT_VIEW_GENERAL_PULSE,
            ]) > 0
        ) {
            return;
        }

        $profileRight->add([
            'profiles_id' => $profileId,
            'name' => self::RIGHT_VIEW_GENERAL_PULSE,
            'rights' => $interface === 'central' ? READ : 0,
        ]);
    }

    private function showRightsForm(int $profileId): void
    {
        $nativeProfile = new \Profile();
        if (!$nativeProfile->getFromDB($profileId)) {
            return;
        }

        self::ensureRightForProfile(
            new ProfileRight(),
            $profileId,
            (string) ($nativeProfile->fields['interface'] ?? 'central')
        );
        $nativeProfile->getFromDB($profileId);

        $canEdit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        echo "<div class='firstbloc'>";
        if ($canEdit) {
            echo "<form method='post' action='" . $nativeProfile->getFormURL() . "'>";
        }

        $nativeProfile->displayRightsChoiceMatrix(self::getAllRights(), [
            'canedit' => $canEdit,
            'default_class' => 'tab_bg_2',
            'title' => __('Pulse permissions', 'brandpulse'),
        ]);

        if ($canEdit) {
            echo "<div class='center'>";
            echo \Html::hidden('id', ['value' => $profileId]);
            echo \Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo '</div>';
            \Html::closeForm();
        }
        echo '</div>';
    }
}
