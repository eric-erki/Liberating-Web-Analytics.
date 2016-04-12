<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Ecommerce;

use Piwik\Piwik;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;

/**
 * Defines Settings for Ecommerce.
 *
 * Usage like this:
 * // require Piwik\Plugin\SettingsProvider via Dependency Injection eg in constructor of your class
 * $settings = $settingsProvider->getMeasurableSettings('Ecommerce', $idSite);
 * $settings->appId->getValue();
 * $settings->contactEmails->getValue();
 */
class MeasurableSettings extends \Piwik\Settings\Measurable\MeasurableSettings
{
    /** @var Setting */
    public $ecommerce;

    protected function init()
    {
        $this->ecommerce = $this->makeEcommerce();
    }

    private function makeEcommerce()
    {
        return $this->makeProperty('ecommerce', $default = 0, FieldConfig::TYPE_INT, function (FieldConfig $field) {
            $field->title = Piwik::translate('Goals_Ecommerce');
            $field->inlineHelp = Piwik::translate('SitesManager_EcommerceHelp')
                . '<br />'
                . Piwik::translate('SitesManager_PiwikOffersEcommerceAnalytics',
                    array("<a href='http://piwik.org/docs/ecommerce-analytics/' target='_blank'>", '</a>'));
            $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
            $field->availableValues = array(
                0 => Piwik::translate('SitesManager_NotAnEcommerceSite'),
                1 => Piwik::translate('SitesManager_EnableEcommerce')
            );
        });
    }


}
