<?php

namespace OxidCommunity\ModuleInternals\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Module\ModuleList;
use OxidEsales\Eshop\Core\Exception\InputException;
use OxidCommunity\ModuleInternals\Core\ModuleStateFixer;
use OxidProfessionalServices\OxidConsole\Core\ShopConfig;
use Symfony\Component\Console\Logger\ConsoleLogger;
use OxidProfessionalServices\ShopSwitcher\ShopSwitcher;

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

    /** @var ConsoleLogger $logger */
    private $logger;

    /** @var OutputInterface $output; */
    private $output;

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
            ->addArgument('module-id', InputArgument::IS_ARRAY, 'Module id/ids to use');
    }

    /**
     * {@inheritdoc}
     * @return null
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->logger = new ConsoleLogger($output);
        
        if (isset($_POST['shp']) || isset($_GET['shp'])) {
            $this->logger->info("starting for given shop " . $_POST['shp']);
            $this->executeForShop();
            return;
        }
        $this->logger->info("going to fix states in all shops");
        try {
            $this->logger->debug("inside the try block");
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
            
            $shopList = oxNew(\OxidEsales\Eshop\Application\Model\ShopList::class);
            $shopList = $shopList->getAll();
            print_r($shopList);
            $shopSwitcher = new ShopSwitcher();
            $this->logger->info("showswitcher loaded");
        
            $this->logger->debug("test call on showswitcher");
            $shopSwitcher->switchToShopId(1);
            $this->logger->debug("test call on showswitcher done");
           
            foreach ($shopSwitcher as $shopId) {
                $this->logger->debug("in loop for shop $shopId");
                $this->executeForShop();
            }
        } catch (\Error $e) {
            $this->logger->info("oh oh");
            $this->logger->info($e->getMessage());
            throw $e;
        }
    }
    
    /**
     * @param string $shopId
     **/
    public function executeForShop()
    {
        $logger  = $this->logger;
        
        try {
            $aModuleIds = $this->parseModuleIds();
        } catch (InputException $oEx) {
            $logger->error($oEx->getMessage());
            exit(1);
        }

        $config = Registry::getConfig();
        $shopId = $config->getShopId();

        /** @var ModuleStateFixer $oModuleStateFixer */
        $oModuleStateFixer = Registry::get(ModuleStateFixer::class);
        $oModuleStateFixer->setOutput($this->output);

        /** @var Module $oModule */
        $oModule = oxNew(Module::class);

        $moduleCount = count($aModuleIds);
        $logger->info(
            "fixing $moduleCount modules in shop $shopId"
        );
        $oModuleStateFixer->cleanUp();
        foreach ($aModuleIds as $sModuleId) {
            $oModule->setMetaDataVersion(null);
            if (!$oModule->load($sModuleId)) {
                $logger->debug("{$sModuleId} does not exist - skipping");
                continue;
            }

            $logger->debug("Fixing {$sModuleId} module");
            $oModuleStateFixer->fix($oModule);
        }

        $logger->info('Fixed module states successfully');
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
            if (!in_array($moduleId, $availableModuleIds, true)) {
                throw oxNew(
                    InputException::class,
                    "{$moduleId} module does not exist"
                );
            }
        }

        return $requestedModuleIds;
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
}
