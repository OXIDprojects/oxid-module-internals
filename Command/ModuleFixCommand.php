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
        } catch (InputException $oEx) {
            $output->writeln($oEx->getMessage());
            exit(1);
        }

        /** @var ModuleStateFixer $oModuleStateFixer */
        $oModuleStateFixer = Registry::get(ModuleStateFixer::class);
        $oModuleStateFixer->setOutput($output);

        /** @var Module $oModule */
        $oModule = oxNew(Module::class);

            $moduleCount = count($aModuleIds);
            $verboseOutput->writeln(
                "[DEBUG] fixing $moduleCount modules"
            );
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
}
