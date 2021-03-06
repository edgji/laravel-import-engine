<?php namespace Edgji\LaravelImportEngine;

use Illuminate\Support\ServiceProvider;

use Mathielen\ImportEngine\Import\Workflow\DefaultWorkflowFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Finder\Finder;

use Mathielen\ImportEngine\Storage\Format\Discovery\MimeTypeDiscoverStrategy;
use Mathielen\ImportEngine\Importer\ImporterRepository;
use Mathielen\ImportEngine\Storage\StorageLocator;
use Mathielen\ImportEngine\Import\ImportBuilder;
use Mathielen\ImportEngine\Import\Run\ImportRunner;

class LaravelImportEngineServiceProvider extends ServiceProvider {

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('edgji/laravel-import-engine', 'edgji/lie', __DIR__);
    }

    /**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
        $this->registerFormatDiscoverer();
        $this->registerRepository();
        $this->registerStorageLocator();
        $this->registerBuilder();
        $this->registerEventDispatcher();
        $this->registerRunner();
	}

    protected function registerFormatDiscoverer()
    {
        $this->app['importengine.importer.format_discoverer'] = $this->app->share(function($app)
        {
            return new MimeTypeDiscoverStrategy();
        });
    }

    protected function registerRepository()
    {
        $this->app['importengine.importer.repository'] = $this->app->share(function($app)
        {
            $importerRepository = new ImporterRepository();

            foreach ($app['config']['edgji/lie::importers'] as $name => $importConfig) {
                $finder = null;
                if (array_key_exists('preconditions', $importConfig)) {
                    $finder = $this->generateFinder($importConfig['preconditions']);
                }

                $objectFactory = null;
                if (array_key_exists('object_factory', $importConfig)) {
                    $objectFactory = $this->generateObjectFactory($importConfig['object_factory']);
                }

                $importer = $this->generateImporter($importConfig, $objectFactory);
                $importerRepository->register($name, $importer, $finder);
            }

            return $importerRepository;
        });
    }

    protected function registerStorageLocator()
    {
        $this->app['importengine.import.storagelocator'] = $this->app->share(function($app)
        {
            $storageLocator = new StorageLocator();

            if (isset($app['config']['edgji/lie::storageprovider']))
            {
                foreach ($app['config']['edgji/lie::storageprovider'] as $sourceConfig) {
                    $this->addStorageProvider($storageLocator, $sourceConfig);
                }
            }

            return $storageLocator;
        });
    }

    protected function registerBuilder()
    {
        $this->app['importengine.import.builder'] = $this->app->share(function($app)
        {
            $importerRepository = $app['importengine.importer.repository'];
            $storageLocator = $app['importengine.import.storagelocator'];
            $eventDispatcher = $app['importengine.import.eventdispatcher'];
            return new ImportBuilder($importerRepository, $storageLocator, $eventDispatcher);
        });
    }

    protected function registerEventDispatcher()
    {
        $this->app['importengine.import.eventdispatcher'] = $this->app->share(function($app)
        {
            return new EventDispatcher();
        });
    }

    protected function registerRunner()
    {
        $this->app['importengine.import.runner'] = $this->app->share(function($app)
        {
            $eventDispatcher = $app['importengine.import.eventdispatcher'];
            return new ImportRunner(new DefaultWorkflowFactory($eventDispatcher));
        });
    }

    private function generateFinder(array $finderConfig)
    {
        $finder = $this->app->make('Mathielen\ImportEngine\Importer\ImporterPrecondition');

        if (array_key_exists('filename', $finderConfig)) {
            if ( ! is_array($finderConfig['filename']))
                $finderConfig['filename'] = array($finderConfig['filename']);
            foreach ($finderConfig['filename'] as $conf) {
                $finder->filename($conf);
            }
        }

        if (array_key_exists('format', $finderConfig)) {
            if ( ! is_array($finderConfig['format']))
                $finderConfig['format'] = array($finderConfig['format']);
            foreach ($finderConfig['format'] as $conf) {
                $finder->format($conf);
            }
        }

        if (array_key_exists('fieldcount', $finderConfig)) {
            if ( ! is_array($finderConfig['fieldcount']))
                $finderConfig['fieldcount'] = array($finderConfig['fieldcount']);
            $finder->fieldcount($finderConfig['fieldcount']);
        }

        if (array_key_exists('fields', $finderConfig)) {
            foreach ($finderConfig['fields'] as $conf) {
                $finder->field($conf);
            }
        }

        if (array_key_exists('fieldset', $finderConfig)) {
            $finder->fieldset($finderConfig['fieldset']);
        }

        return $finder;
    }

    private function generateObjectFactory(array $config)
    {
        if (isset($config['type']) && $config['type'] == 'jms_serializer') {
            //return $this->app->make('Mathielen\DataImport\Writer\ObjectWriter\JmsSerializerObjectFactory', array(
            //    $config['class'],
            //    new Reference('jms_serializer')));
        }

        return $this->app->make('Mathielen\DataImport\Writer\ObjectWriter\DefaultObjectFactory', array($config['class']));
    }

    private function generateImporter(array $importConfig, $objectFactory=null)
    {
        $importer = $this->app->make('Mathielen\ImportEngine\Importer\Importer', array(
            $this->getStorage($importConfig['target'], $objectFactory)
        ));

        if (array_key_exists('source', $importConfig)) {
            $this->setSourceStorage($importConfig['source'], $importer);
        }

        //enable validation?
        //if (array_key_exists('validation', $importConfig)) {
        //    $this->generateValidation($importConfig['validation'], $importer, $objectFactory);
        //}

        return $importer;
    }

    //private function generateValidation(array $validationConfig, $importer, $objectFactory=null)
    //{
    //    $validation = $this->app->make('Mathielen\ImportEngine\Validation\ValidatorValidation', array(
    //        new Reference('validator')
    //    ));
    //    $importer->setValidation($validation);
    //
    //    if (@$validationConfig['source']) {
    //        $validatorFilter = $this->generateValidator();
    //
    //        $validation->setSourceValidatorFilter($validatorFilter);
    //
    //        foreach ($validationConfig['source']['constraints'] as $field=>$constraint) {
    //            $validation->addSourceConstraint($field,
    //                new Definition($constraint)
    //            );
    //        }
    //    }
    //
    //    //automatically apply class validation
    //    if (@$validationConfig['target']) {
    //
    //        //using objects as result
    //        if ($objectFactory) {
    //
    //            //set eventdispatcher aware target CLASS-validatorfilter
    //            $validatorFilter = $this->app->make('Mathielen\DataImport\Filter\ClassValidatorFilter', array(
    //                new Reference('validator'),
    //                $objectFactory,
    //                new Reference('event_dispatcher')
    //            ));
    //
    //        } else {
    //            $validatorFilter = $this->generateValidator();
    //
    //            foreach ($validationConfig['target']['constraints'] as $field=>$constraint) {
    //                $validation->addTargetConstraint($field,
    //                    new Definition($constraint)
    //                );
    //            }
    //        }
    //
    //        $validation->setTargetValidatorFilter($validatorFilter);
    //    }
    //
    //    return $validation;
    //}

    private function setSourceStorage(array $sourceConfig, $importer)
    {
        $s = $this->getStorage($sourceConfig, $importer);
        $importer->setSourceStorage($s);
    }

    private function addStorageProvider($storageLocator, $config, $id = 'default')
    {
        switch ($config['type']) {
            case 'directory':
                $spFinder = Finder::create();
                $path = $this->cleanUpStoragePath($config['path']);
                $spFinder->in($path);
                $sp = $this->app->make('Mathielen\ImportEngine\Storage\Provider\FinderFileStorageProvider', array(
                    $spFinder
                ));
                break;
            case 'upload':
                $path = $this->cleanUpStoragePath($config['path']);
                $sp = $this->app->make('Mathielen\ImportEngine\Storage\Provider\UploadFileStorageProvider', array(
                    $path
                ));
                break;
            case 'doctrine':
                $sp = null;
                //TODO
                break;
            case 'service':
                $sp = $this->app->make('Mathielen\ImportEngine\Storage\Provider\ServiceStorageProvider', array(
                    $config['class'],
                    $config['method']
                ));
                break;
            default:
                throw new \InvalidArgumentException('Unknown type: '.$config['type']);
        }

        $storageLocator->register($id, $sp);
    }

    private function cleanUpStoragePath($path)
    {
        return str_replace("{app_storage}", $this->app['path.storage'], $path);
    }

    private function getStorage(array $config, $objectFactory=null)
    {
        switch ($config['type']) {
            case 'file':
                $file = $this->app->make('SplFileInfo', array(
                    $config['uri']
                ));

                $storage = $this->app->make('Mathielen\ImportEngine\Storage\LocalFileStorage', array(
                    $file,
                    $this->app->make("Mathielen\\ImportEngine\\Storage\\Format\\".ucfirst($config['format'])."Format")
                ));

                break;
            case 'doctrine':
                // $qb = new Definition('Doctrine\ORM\QueryBuilder');
                // $qb->setFactoryService('doctrine.orm.entity_manager');
                // $qb->setFactoryMethod('createQueryBuilder');

                $storage = $this->app->make('Mathielen\ImportEngine\Storage\DoctrineStorage', array(
                    null,
                    //TODO laravel doctrine em
                    //new Reference('doctrine.orm.entity_manager'),
                    $config
                ));

                break;
            case 'service':
                $service = @$this->app[$config['service']] ?: $this->app->make($config['service']);
                $storage = $this->app->make('Mathielen\ImportEngine\Storage\ServiceStorage', array(
                    array($service, $config['method']), //callable
                    $objectFactory //from parameter array
                ));

                break;
            default:
                throw new \InvalidArgumentException('Unknown type: '.$config['type']);
        }

        return $storage;
    }

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array(
            'importengine.importer.format_discoverer',
            'importengine.importer.repository',
            'importengine.import.storagelocator',
            'importengine.import.builder',
            'importengine.import.eventdispatcher',
            'importengine.import.runner',
        );
	}

}
