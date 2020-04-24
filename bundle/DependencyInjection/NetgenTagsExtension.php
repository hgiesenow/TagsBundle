<?php

declare(strict_types=1);

namespace Netgen\TagsBundle\DependencyInjection;

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\SiteAccessAware\ConfigurationProcessor;
use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\SiteAccessAware\ContextualizerInterface;
use RuntimeException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;
use function array_keys;
use function file_get_contents;
use function in_array;

final class NetgenTagsExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $activatedBundles = array_keys($container->getParameter('kernel.bundles'));

        if (!in_array('EzCoreExtraBundle', $activatedBundles, true)) {
            throw new RuntimeException('Netgen Tags Bundle requires EzCoreExtraBundle (lolautruche/ez-core-extra-bundle) to be activated to work properly.');
        }

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $loader->load('services.yaml');
        $loader->load('rest/services.yaml');
        $loader->load('fieldtypes.yaml');
        $loader->load('persistence.yaml');
        $loader->load('papi.yaml');
        $loader->load('default_settings.yaml');
        $loader->load('pagerfanta.yaml');
        $loader->load('templating.yaml');
        $loader->load('view.yaml');
        $loader->load('limitations.yaml');
        $loader->load('storage/doctrine.yaml');
        $loader->load('admin/controllers.yaml');
        $loader->load('admin/templating.yaml');
        $loader->load('forms.yaml');
        $loader->load('validators.yaml');
        $loader->load('param_converters.yaml');
        $loader->load('installer.yaml');
        $loader->load('search/related_content.yaml');
        $loader->load('ezadminui/default_settings.yaml');
        $loader->load('ezadminui/services.yaml');

        $persistenceCache = 'disabled';
        if ($container->getParameter('eztags.enable_persistence_cache') === true) {
            $persistenceCache = 'psr6';
        }

        $loader->load('storage/cache_' . $persistenceCache . '.yaml');

        if (in_array('EzSystemsEzPlatformSolrSearchEngineBundle', $activatedBundles, true)) {
            $loader->load('search/solr.yaml');
        }

        if (in_array('EzPublishLegacySearchEngineBundle', $activatedBundles, true)) {
            $loader->load('search/legacy.yaml');
        }

        $this->processSemanticConfig($container, $config);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $configs = [
            'netgen_tags.yaml' => 'netgen_tags',
            'ezplatform.yaml' => 'ezpublish',
            'framework/twig.yaml' => 'twig',
            'ezadminui/twig.yaml' => 'twig',
        ];

        foreach ($configs as $fileName => $extensionName) {
            $configFile = __DIR__ . '/../Resources/config/' . $fileName;
            $config = Yaml::parse((string) file_get_contents($configFile));
            $container->prependExtensionConfig($extensionName, $config);
            $container->addResource(new FileResource($configFile));
        }
    }

    /**
     * Processes semantic config and translates it to container parameters.
     */
    private function processSemanticConfig(ContainerBuilder $container, array $config): void
    {
        $processor = new ConfigurationProcessor($container, 'eztags');
        $processor->mapConfig(
            $config,
            static function ($config, $scope, ContextualizerInterface $c): void {
                $c->setContextualParameter('tag_view.cache', $scope, $config['tag_view']['cache']);
                $c->setContextualParameter('tag_view.ttl_cache', $scope, $config['tag_view']['ttl_cache']);
                $c->setContextualParameter('tag_view.default_ttl', $scope, $config['tag_view']['default_ttl']);
                $c->setContextualParameter('tag_view.template', $scope, $config['tag_view']['template']);
                $c->setContextualParameter('tag_view.path_prefix', $scope, $config['tag_view']['path_prefix']);

                $c->setContextualParameter('tag_view.related_content_list.limit', $scope, $config['tag_view']['related_content_list']['limit']);
                $c->setContextualParameter('tag_view.related_content_list.return_content_info', $scope, $config['tag_view']['related_content_list']['return_content_info']);

                $c->setContextualParameter('admin.pagelayout', $scope, $config['admin']['pagelayout']);
                $c->setContextualParameter('admin.children_limit', $scope, $config['admin']['children_limit']);
                $c->setContextualParameter('admin.related_content_limit', $scope, $config['admin']['related_content_limit']);
                $c->setContextualParameter('field.autocomplete_limit', $scope, $config['field']['autocomplete_limit']);
            }
        );

        $processor->mapConfigArray('tag_view_match', $config, ContextualizerInterface::MERGE_FROM_SECOND_LEVEL);
        $processor->mapConfigArray('edit_views', $config, ContextualizerInterface::MERGE_FROM_SECOND_LEVEL);
    }
}
