<?php

namespace OxidCommunity\ModuleInternals\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\NullOutput;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Module\ModuleList;
use OxidEsales\Eshop\Core\Exception\InputException;
use OxidCommunity\ModuleInternals\Core\ModuleStateFixer;
use OxidProfessionalServices\OxidConsole\Core\ShopConfig;

/**
 * Fix States command
 */
class ModuleFixCommand extends Command
{

    /**
     * @var array<string>|null Available module ids
     */
    protected $availableModuleIds = null;

    /** @var InputInterface */
    private $input;

    /**
     * {@inheritdoc}
     * @return void
     */
    public function configure()
    {
        $this
            ->setName('module:fix')
            ->setAliases(['fix:states'])
            ->setDescription('Fixes modules metadata states')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Includes all modules')
            ->addOption('base-shop', 'b', InputOption::VALUE_NONE, 'Apply changes to base shop only')
            ->addOption('shop', 's', InputOption::VALUE_REQUIRED, 'Apply changes to given shop only')
            ->addArgument('module-id', InputArgument::IS_ARRAY, 'Module id/ids to use');
    }

    /**
     * {@inheritdoc}
     * @return null
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;

        $verboseOutput = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE
            ? $output
            : new NullOutput();

        try {
            $aModuleIds = $this->parseModuleIds();
            $aShopConfigs = $this->parseShopConfigs();
        } catch (InputException $oEx) {
            $output->writeln($oEx->getMessage());
            exit(1);
        }

        /** @var ModuleStateFixer $oModuleStateFixer */
        $oModuleStateFixer = Registry::get(ModuleStateFixer::class);
        $oModuleStateFixer->setOutput($output);
        $oModuleStateFixer->setDebugOutput($verboseOutput);

        /** @var Module $oModule */
        $oModule = oxNew(Module::class);

        foreach ($aShopConfigs as $oConfig) {
            $moduleCount = count($aModuleIds);
            $verboseOutput->writeln(
                '[DEBUG] Working on shop id ' . $oConfig->getShopId() . " fixing $moduleCount modules"
            );
            $oModuleStateFixer->setConfig($oConfig);
            $oModuleStateFixer->cleanUp();
            foreach ($aModuleIds as $sModuleId) {
                $oModule->setMetaDataVersion(null);
                if (!$oModule->load($sModuleId)) {
                    $verboseOutput->writeln("[DEBUG] {$sModuleId} does not exist - skipping");
                    continue;
                }

                $verboseOutput->writeln("[DEBUG] Fixing {$sModuleId} module");
                $oModuleStateFixer->fix($oModule);
            }

            $verboseOutput->writeln('');
        }

        $output->writeln('Fixed module states successfully');
        return null;
    }

    /**
     * Parse and return module ids from input
     *
     * @return array<string>
     *
     * @throws InputException
     */
    protected function parseModuleIds()
    {
        if ($this->input->getOption('all')) {
            return $this->getAvailableModuleIds();
        }

        if (count($this->input->getArguments()['module-id']) === 0) {
            throw oxNew(
                InputException::class,
                'Please specify at least one module if as argument or use --all (-a) option'
            );
        }

        $requestedModuleIds = $this->input->getArguments()['module-id'];
        $availableModuleIds = $this->getAvailableModuleIds();

        // Checking if all provided module ids exist
        foreach ($requestedModuleIds as $moduleId) {
            if (!in_array($moduleId, $availableModuleIds)) {
                throw oxNew(
                    InputException::class,
                    "{$moduleId} module does not exist"
                );
            }
        }

        return $requestedModuleIds;
    }

    /**
     * Parse and return shop config objects from input
     *
     * @return array<int, Config>
     *
     * @throws InputException
     */
    protected function parseShopConfigs()
    {
        if ($this->input->getOption('base-shop')) {
            return array(Registry::getConfig());
        }

        if ($shopId = $this->input->getOption('shop')) {
            if ($oConfig = ShopConfig::get($shopId)) {
                return array($oConfig);
            }

            throw oxNew(
                InputException::class,
                'Shop id does not exist'
            );
        }

        return ShopConfig::getAll();
    }

    /**
     * Get all available module ids
     *
     * @return array<string>
     */
    protected function getAvailableModuleIds()
    {
        if ($this->availableModuleIds === null) {
            $oConfig = Registry::getConfig();

            // We are calling getModulesFromDir() because we want to refresh
            // the list of available modules. This is a workaround for OXID
            // bug.
            oxNew(ModuleList::class)->getModulesFromDir($oConfig->getModulesDir());
            $this->availableModuleIds = array_keys($oConfig->getConfigParam('aModulePaths'));
        }

        return $this->availableModuleIds;
    }

    /**
     * important method for oxrun to bootstrap oxid
     * @return bool
     */
    public function isEnabled()
    {
        $app = $this->getApplication();
        if (method_exists($app, 'bootstrapOxid')) {
                return $app->bootstrapOxid(true);
        }
        return true;
    }
}
