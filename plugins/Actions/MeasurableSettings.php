<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Actions;

use Piwik\Piwik;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;
use Piwik\Plugins\SitesManager;
use Piwik\Plugin;

/**
 * Defines Settings for Actions.
 *
 * Usage like this:
 * // require Piwik\Plugin\SettingsProvider via Dependency Injection eg in constructor of your class
 * $settings = $settingsProvider->getMeasurableSettings('Actions', $idSite);
 * $settings->appId->getValue();
 * $settings->contactEmails->getValue();
 */
class MeasurableSettings extends \Piwik\Settings\Measurable\MeasurableSettings
{
    /** @var Setting */
    public $siteSearch;

    /** @var Setting */
    public $useDefaultSiteSearchParams;

    /** @var Setting */
    public $siteSearchKeywords;

    /** @var Setting */
    public $siteSearchCategory;

    /**
     * @var SitesManager\API
     */
    private $sitesManagerApi;

    /**
     * @var Plugin\Manager
     */
    private $pluginManager;

    public function __construct(SitesManager\API $api, Plugin\Manager $pluginManager, $idSite, $idMeasurableType)
    {
        $this->sitesManagerApi = $api;
        $this->pluginManager = $pluginManager;

        parent::__construct($idSite, $idMeasurableType);
    }

    protected function init()
    {

        /**
         * SiteSearch
         */
        $this->siteSearch = $this->makeSiteSearch();
        $this->useDefaultSiteSearchParams = $this->makeUseDefaultSiteSearchParams($this->sitesManagerApi);
        $this->siteSearchKeywords = $this->makeSiteSearchKeywords();

        $siteSearchKeywords = $this->siteSearchKeywords->getValue();
        $this->useDefaultSiteSearchParams->setDefaultValue(empty($siteSearchKeywords));

        $this->siteSearchCategory = $this->makeSiteSearchCategory($this->pluginManager);
        /**
         * SiteSearch End
         */
    }
    private function makeSiteSearch()
    {
        return $this->makeProperty('sitesearch', $default = 1, FieldConfig::TYPE_INT, function (FieldConfig $field) {
            $field->title = Piwik::translate('Actions_SubmenuSitesearch');
            $field->inlineHelp = Piwik::translate('SitesManager_SiteSearchUse');
            $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
            $field->availableValues = array(
                1 => Piwik::translate('SitesManager_EnableSiteSearch'),
                0 => Piwik::translate('SitesManager_DisableSiteSearch')
            );
        });
    }

    private function makeUseDefaultSiteSearchParams(SitesManager\API $sitesManagerApi)
    {
        return $this->makeSetting('use_default_site_search_params', $default = true, FieldConfig::TYPE_BOOL, function (FieldConfig $field) use ($sitesManagerApi) {

            if (Piwik::hasUserSuperUserAccess()) {
                $title = Piwik::translate('SitesManager_SearchUseDefault', array("<a href='#globalSettings'>","</a>"));
            } else {
                $title = Piwik::translate('SitesManager_SearchUseDefault', array('', ''));
            }

            $field->title = $title;
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;

            $searchKeywordsGlobal = $sitesManagerApi->getSearchKeywordParametersGlobal();

            $hasParams = (int) !empty($searchKeywordsGlobal);
            $field->condition = $hasParams . ' && sitesearch';

            $searchKeywordsGlobal = $sitesManagerApi->getSearchKeywordParametersGlobal();
            $searchCategoryGlobal = $sitesManagerApi->getSearchCategoryParametersGlobal();

            $field->description  = Piwik::translate('SitesManager_SearchKeywordLabel');
            $field->description .= ' (' . Piwik::translate('General_Default') . ')';
            $field->description .= ': ';
            $field->description .= $searchKeywordsGlobal;
            $field->description .= ' & ';
            $field->description .= Piwik::translate('SitesManager_SearchCategoryLabel');
            $field->description .= ': ';
            $field->description .= $searchCategoryGlobal;
            $field->transform = function () {
                return null;// never actually save a value for this
            };
        });
    }

    private function makeSiteSearchKeywords()
    {
        return $this->makeProperty('sitesearch_keyword_parameters', $default = array(), FieldConfig::TYPE_ARRAY, function (FieldConfig $field) {
            $field->title = Piwik::translate('SitesManager_SearchKeywordLabel');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->inlineHelp = Piwik::translate('SitesManager_SearchKeywordParametersDesc');
            $field->condition = Piwik::translate('sitesearch && !use_default_site_search_params');
        });
    }

    private function makeSiteSearchCategory(Plugin\Manager $pluginManager)
    {
        return $this->makeProperty('sitesearch_category_parameters', $default = array(), FieldConfig::TYPE_ARRAY, function (FieldConfig $field) use ($pluginManager) {
            $field->title = Piwik::translate('SitesManager_SearchCategoryLabel');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->inlineHelp = Piwik::translate('Goals_Optional')
                . '<br /><br />'
                . Piwik::translate('SitesManager_SearchCategoryParametersDesc');

            $hasCustomVars = (int) $pluginManager->isPluginActivated('CustomVariables');
            $field->condition = $hasCustomVars . ' && sitesearch && !use_default_site_search_params';
        });
    }


}
