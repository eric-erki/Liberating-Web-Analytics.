<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\WebsiteMeasurable;
use Piwik\IP;
use Piwik\Network\IPUtils;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugins\WebsiteMeasurable\Settings\Urls;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;
use Piwik\Plugins\SitesManager;
use Exception;

/**
 * Defines Settings for ExampleSettingsPlugin.
 *
 * Usage like this:
 * $settings = new MeasurableSettings($idSite);
 * $settings->autoRefresh->getValue();
 * $settings->metric->getValue();
 */
class MeasurableSettings extends \Piwik\Settings\Measurable\MeasurableSettings
{
    /** @var Setting */
    public $urls;

    /** @var Setting */
    public $onlyTrackWhitelstedUrls;

    /** @var Setting */
    public $keepPageUrlFragments;

    /** @var Setting */
    public $excludeKnownUrls;

    /** @var Setting */
    public $ecommerce;

    /** @var Setting */
    public $excludedParameters;

    /**
     * @var SitesManager\API
     */
    private $sitesManagerApi;

    public function __construct(SitesManager\API $api, $idSite, $idMeasurableType)
    {
        $this->sitesManagerApi = $api;

        parent::__construct($idSite, $idMeasurableType);
    }

    protected function init()
    {
        $this->urls = new Urls($this->idSite);
        $this->addSetting($this->urls);

        $this->excludeKnownUrls = $this->makeExcludeUnknownUrls();
        $this->keepPageUrlFragments = $this->makeKeepUrlFragments($this->sitesManagerApi);
        $this->excludedParameters = $this->makeExcludedParameters();
    }

    private function makeExcludeUnknownUrls()
    {
        return $this->makeProperty('exclude_unknown_urls', $default = false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('SitesManager_OnlyMatchedUrlsAllowed');
            $field->inlineHelp = Piwik::translate('SitesManager_OnlyMatchedUrlsAllowedHelp')
                . '<br />'
                . Piwik::translate('SitesManager_OnlyMatchedUrlsAllowedHelpExamples');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }

    private function makeKeepUrlFragments(SitesManager\API $sitesManagerApi)
    {
        return $this->makeProperty('keep_url_fragment', $default = '0', FieldConfig::TYPE_STRING, function (FieldConfig $field) use ($sitesManagerApi) {
            $field->title = Piwik::translate('SitesManager_KeepURLFragmentsLong');
            $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;

            if ($sitesManagerApi->getKeepURLFragmentsGlobal()) {
                $default = Piwik::translate('General_Yes');
            } else {
                $default = Piwik::translate('General_No');
            }

            $field->availableValues = array(
                '0' => $default . ' (' . Piwik::translate('General_Default') . ')',
                '1' => Piwik::translate('General_Yes'),
                '2' => Piwik::translate('General_No')
            );
        });
    }

    private function makeExcludedParameters()
    {
        $self = $this;
        return $this->makeProperty('excluded_parameters', $default = array(), FieldConfig::TYPE_ARRAY, function (FieldConfig $field) use ($self) {
            $field->title = Piwik::translate('SitesManager_ExcludedParameters');
            $field->inlineHelp = Piwik::translate('SitesManager_ListOfQueryParametersToExclude')
                . '<br /><br />'
                . Piwik::translate('SitesManager_PiwikWillAutomaticallyExcludeCommonSessionParameters', array('phpsessid, sessionid, ...'));
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
