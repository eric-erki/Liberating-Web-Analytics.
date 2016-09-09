<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\Diagnostics\Diagnostic;

use Piwik\Db;
use Piwik\SettingsPiwik;
use Piwik\Translation\Translator;

/**
 * Check the database version
 */
class DatabaseVersionCheck implements Diagnostic
{
    const REQUIRED_MYSQL_VERSION = '5.5.0';

    /**
     * @var Translator
     */
    private $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function execute()
    {
        if (!SettingsPiwik::isPiwikInstalled()) {
            return;
        }

        try {
            $dbVersion = Db::get()->getServerVersion();
        } catch (\Exception $e) {
            // in case not yet installed etc
            return;
        }

        $label = $this->translator->translate('Installation_DatabaseVersionCheck');

        if (version_compare($dbVersion, self::REQUIRED_MYSQL_VERSION) >= 0) {
            return array(DiagnosticResult::singleResult($label, DiagnosticResult::STATUS_OK));
        }

        $comment = $this->translator->translate('Installation_DatabaseVersionTooLow', self::REQUIRED_MYSQL_VERSION);

        return array(DiagnosticResult::singleResult($label, DiagnosticResult::STATUS_WARNING, $comment));
    }
}
