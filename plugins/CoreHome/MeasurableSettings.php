<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CoreHome;

use Piwik\IP;
use Piwik\Network\IPUtils;
use Piwik\Piwik;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;
use Exception;

/**
 * Defines Settings for CoreHome.
 *
 * Usage like this:
 * // require Piwik\Plugin\SettingsProvider via Dependency Injection eg in constructor of your class
 * $settings = $settingsProvider->getMeasurableSettings('CoreHome', $idSite);
 * $settings->appId->getValue();
 * $settings->contactEmails->getValue();
 */
class MeasurableSettings extends \Piwik\Settings\Measurable\MeasurableSettings
{
    /** @var Setting */
    public $excludedUserAgents;

    /** @var Setting */
    public $excludedIps;

    protected function init()
    {
        $this->excludedIps = $this->makeExcludeIps();
        $this->excludedUserAgents = $this->makeExcludedUserAgents();
    }

    private function makeExcludeIps()
    {
        return $this->makeProperty('excluded_ips', $default = array(), FieldConfig::TYPE_ARRAY, function (FieldConfig $field) {
            $ip = IP::getIpFromHeader();

            $field->title = Piwik::translate('SitesManager_ExcludedIps');
            $field->inlineHelp = Piwik::translate('SitesManager_HelpExcludedIps', array('1.2.3.*', '1.2.*.*'))
                . '<br /><br />'
                . Piwik::translate('SitesManager_YourCurrentIpAddressIs', array('<i>' . $ip . '</i>'));
            $field->uiControl = FieldConfig::UI_CONTROL_TEXTAREA;
            $field->uiControlAttributes = array('cols' => '20', 'rows' => '4');

            $field->validate = function ($value) {
                if (!empty($value)) {
                    $ips = array_map('trim', $value);
                    $ips = array_filter($ips, 'strlen');

                    foreach ($ips as $ip) {
                        if (IPUtils::getIPRangeBounds($ip) === null) {
                            throw new Exception(Piwik::translate('SitesManager_ExceptionInvalidIPFormat', array($ip, "1.2.3.4, 1.2.3.*, or 1.2.3.4/5")));
                        }
                    }
                }
            };
            $field->transform = function ($value) {
                if (empty($value)) {
                    return array();
                }

                $ips = array_map('trim', $value);
                $ips = array_filter($ips, 'strlen');
                return $ips;
            };
        });
    }

    private function makeExcludedUserAgents()
    {
        $self = $this;
        return $this->makeProperty('excluded_user_agents', $default = array(), FieldConfig::TYPE_ARRAY, function (FieldConfig $field) use ($self) {
            $field->title = Piwik::translate('SitesManager_ExcludedUserAgents');
            $field->inlineHelp = Piwik::translate('SitesManager_GlobalExcludedUserAgentHelp1')
                . '<br /><br />'
                . Piwik::translate('SitesManager_GlobalListExcludedUserAgents_Desc')
                . '<br />'
                . Piwik::translate('SitesManager_GlobalExcludedUserAgentHelp2');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXTAREA;
            $field->uiControlAttributes = array('cols' => '20', 'rows' => '4');
            $field->transform = function ($value) use ($self) {
                return $self->checkAndReturnCommaSeparatedStringList($value);
            };
        });
    }

    public function checkAndReturnCommaSeparatedStringList($parameters)
    {
        if (empty($parameters)) {
            return array();
        }

        $parameters = array_map('trim', $parameters);
        $parameters = array_filter($parameters, 'strlen');
        $parameters = array_unique($parameters);
        return $parameters;
    }


}
