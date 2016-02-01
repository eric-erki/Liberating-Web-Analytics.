<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugin;

use Composer\DependencyResolver\DefaultPolicy;
use Composer\DependencyResolver\Pool;
use Composer\DependencyResolver\Request;
use Composer\DependencyResolver\Solver;
use Composer\DependencyResolver\SolverProblemsException;
use Composer\Json\JsonFile;
use Composer\Package\Package;
use Composer\Repository\ArrayRepository;
use Composer\Repository\FilesystemRepository;
use Composer\Repository\WritableArrayRepository;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\VersionParser;
use DI\DependencyException;
use Piwik\Plugin;
use Piwik\Plugin\Manager as PluginManager;
use Piwik\Version;

/**
 *
 */
class Dependency
{
    private $piwikVersion;

    public function __construct()
    {
        $this->setPiwikVersion(Version::VERSION);
    }

    public function solve()
    {
        $policy = new DefaultPolicy($preferStable = false, $preferLowest = false);
        $pool   = new Pool($minimumStability = 'dev');
        $packages = array();

        // here we define our available packages.
        foreach (Plugin\Manager::getInstance()->getLoadedPlugins() as $name => $plugin) {
            $version = $plugin->getVersion();
            $package = new Package($name, $version, $prettyVersion = $version);
            $package->setRequires($plugin->getDependencies());

            $packages[] = $package;
        }

        // todo we also need to add plugins from marketplace into repository

        $repository = new ArrayRepository($packages);
        $solver = new Solver($policy, $pool, $repository);

        $request = new Request();

        // here we define what we want to do! eg install a fixed version, update a plugin with a certain constraint etc
        $versionParser = new VersionParser();

        $request->install('plugin1', $versionParser->parseConstraints('>=34'));
        $request->update('plugin2', $versionParser->parseConstraints('2.15.0'));
        $request->fix('CoreHome', $versionParser->parseConstraints(Version::VERSION));

        try {
            $operations = $solver->solve($request, false);
        } catch (SolverProblemsException $e) {
            $promlems = $e->getProblems(); // each individual problem
            $message = $e->getMessage(); // human readable version but it may contain links to google user groups etc
            throw new DependencyException(); // todo make it human readable
        }

        return $operations;
    }

    public function getMissingDependencies($requires)
    {
        $missingRequirements = array();

        if (empty($requires)) {
            return $missingRequirements;
        }

        foreach ($requires as $name => $requiredVersion) {
            $currentVersion  = $this->getCurrentVersion($name);
            $missingVersions = $this->getMissingVersions($currentVersion, $requiredVersion);

            if (!empty($missingVersions)) {
                $missingRequirements[] = array(
                    'requirement'     => $name,
                    'actualVersion'   => $currentVersion,
                    'requiredVersion' => $requiredVersion,
                    'causedBy'        => implode(', ', $missingVersions)
                );
            }
        }

        return $missingRequirements;
    }

    public function getMissingVersions($currentVersion, $requiredVersion)
    {
        $currentVersion   = trim($currentVersion);
        $requiredVersions = explode(',', (string) $requiredVersion);

        $missingVersions = array();

        foreach ($requiredVersions as $required) {
            $comparison = '>=';
            $required   = trim($required);

            if (preg_match('{^(<>|!=|>=?|<=?|==?)\s*(.*)}', $required, $matches)) {
                $required   = $matches[2];
                $comparison = trim($matches[1]);
            }

            if (false === version_compare($currentVersion, $required, $comparison)) {
                $missingVersions[] = $comparison . $required;
            }
        }

        return $missingVersions;
    }

    public function setPiwikVersion($piwikVersion)
    {
        $this->piwikVersion = $piwikVersion;
    }

    private function getCurrentVersion($name)
    {
        switch (strtolower($name)) {
            case 'piwik':
                return $this->piwikVersion;
            case 'php':
                return PHP_VERSION;
            default:
                try {
                    $pluginNames = PluginManager::getAllPluginsNames();

                    if (!in_array($name, $pluginNames) || !PluginManager::getInstance()->isPluginLoaded($name)) {
                        return '';
                    }

                    $plugin = PluginManager::getInstance()->loadPlugin(ucfirst($name));

                    if (!empty($plugin)) {
                        return $plugin->getVersion();
                    }
                } catch (\Exception $e) {
                }
        }

        return '';
    }
}
