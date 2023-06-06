<?php

namespace Flowpack\NodeTemplates\Tests\Functional;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Utility\Arrays;

trait WithConfigurationTrait
{
    /**
     * Mock the settings of the configuration manager and cleanup afterwards
     *
     * WARNING: If you activate Singletons during this transaction they will later still have a reference to the mocked object manger, so you might need to call
     * {@see ObjectManagerInterface::forgetInstance()}. An alternative would be also to hack the protected $this->settings of the manager.
     *
     * @param array $additionalSettings settings that are merged onto the the current testing configuration
     * @param callable $fn test code that is executed in the modified context
     */
    private function withMockedConfigurationSettings(array $additionalSettings, callable $fn): void
    {
        $configurationManager = $this->objectManager->get(ConfigurationManager::class);
        $configurationManagerMock = $this->getMockBuilder(ConfigurationManager::class)->disableOriginalConstructor()->getMock();
        $mockedSettings = Arrays::arrayMergeRecursiveOverrule($configurationManager->getConfiguration('Settings'), $additionalSettings);
        $configurationManagerMock->expects(self::any())->method('getConfiguration')->willReturnCallback(function (string $configurationType, string $configurationPath = null) use($configurationManager, $mockedSettings) {
            if ($configurationType !== 'Settings') {
                return $configurationManager->getConfiguration($configurationType, $configurationPath);
            }
            return $configurationPath ? Arrays::getValueByPath($mockedSettings, $configurationPath) : $mockedSettings;
        });
        $this->objectManager->setInstance(ConfigurationManager::class, $configurationManagerMock);
        $fn();
        $this->objectManager->setInstance(ConfigurationManager::class, $configurationManager);
    }
}
