<?php

function get_entity_manager()
{
	static $entity_manager = null;

	/* if entity manager has already been loaded */
	if ($entity_manager !== null)
	{
		return $entity_manager;
	}

	$kernel = kernel::getInstance();

	/* get doctrine settings */
	$settings = $kernel->getConfigValue('doctrine', 'sql');
	if (!$settings)
	{
		$settings = $kernel->getConfigValue('doctrine');
	}
	if (!$settings)
	{
		$entity_manager = false;
		return false;
	}

	/* expand possible sqlite file path */
	if (isset($settings['path']))
	{
		$settings['path'] = $kernel->expand($settings['path']);
	}

	/* find doctrine definition directories from modules */
	$directories  = array();
	$modules_path = $kernel->expand('{path:modules}');
	$vendor_path  = $kernel->expand('{path:vendor}');

	/* custom doctrine search paths */
	if (isset($settings['modules']))
	{
		foreach ($settings['modules'] as $module)
		{
			$m_path = $modules_path . '/' . $module . '/doctrine';
			$v_path = $vendor_path . '/' . $module . '/doctrine';
			if (is_dir($m_path))
			{
				$directories[] = $m_path;
			}
			else if (is_dir($v_path))
			{
				$directories[] = $v_path;
			}
		}
	}

	/* all modules are searched as default */
	$modules = $kernel->getConfigValue('modules');
	foreach ($modules as $module)
	{
		$module_file = null;
		if (is_string($module))
		{
			$module_file = $module;
		}
		else if (isset($module['class']))
		{
			$module_file = $module['class'];
		}
		if ($module_file)
		{
			$module_doctrine_path = dirname($modules_path . '/' . $module_file) . '/doctrine';
			if (is_dir($module_doctrine_path))
			{
				$directories[] = $module_doctrine_path;
			}
		}
	}

	/* setup doctrine and return entity manager */
	$config = Doctrine\ORM\Tools\Setup::createYAMLMetadataConfiguration($directories, $kernel->debug());
	$config->setProxyDir($kernel->expand('{path:tmp}') . '/doctrine');
	if ($kernel->debug())
	{
		$config->setAutoGenerateProxyClasses(true);
	}
	/* caching */
	$cache_instance = null;
	$cache_type     = $kernel->getConfigValue('doctrine', 'cache', 'type');
	if ($cache_type == 'memcache')
	{
		$memcache = new Memcache();
		$memcache->connect(
			$kernel->getConfigValue('doctrine', 'cache', 'host'),
			$kernel->getConfigValue('doctrine', 'cache', 'port')
		);
		$cache_instance = new \Doctrine\Common\Cache\MemcacheCache();
		$cache_instance->setMemcache($memcache);
	}
	else if ($cache_type == 'memcached')
	{
		$memcache = new Memcached();
		$memcache->addServer(
			$kernel->getConfigValue('doctrine', 'cache', 'host'),
			$kernel->getConfigValue('doctrine', 'cache', 'port')
		);
		$cache_instance = new \Doctrine\Common\Cache\MemcachedCache();
		$cache_instance->setMemcached($memcache);
	}
	else if ($cache_type == 'xcache')
	{
		$cache_instance = new \Doctrine\Common\Cache\XcacheCache();
	}
	/* set cache instance to be used by doctrine */
	if ($cache_instance)
	{
		$config->setQueryCacheImpl($cache_instance);
		$config->setResultCacheImpl($cache_instance);
		$config->setMetadataCacheImpl($cache_instance);
	}

	$entity_manager = Doctrine\ORM\EntityManager::create($settings, $config);

	return $entity_manager;
}

function get_document_manager()
{
	static $document_manager = null;

	/* if document manager has already been loaded */
	if ($document_manager !== null)
	{
		return $document_manager;
	}

	$kernel = kernel::getInstance();

	/* get doctrine settings */
	$settings = $kernel->getConfigValue('doctrine', 'mongodb');
	if (!$settings)
	{
		$document_manager = false;
		return false;
	}

	/* find doctrine definition directories from modules */
	$directories  = array();
	$modules_path = $kernel->expand('{path:modules}');

	/* expand possible modules that override default directory search behaviour */
	if (isset($settings['modules']))
	{
		foreach ($settings['modules'] as $module)
		{
			$doctrine_path = $modules_path . '/' . $module . '/mongodb';
			if (is_dir($doctrine_path))
			{
				$directories[] = $doctrine_path;
			}
		}
	}
	else
	{
		$modules = $kernel->getConfigValue('modules');
		foreach ($modules as $module)
		{
			$module_file = null;
			if (is_string($module))
			{
				$module_file = $module;
			}
			else
			{
				$module_file = $module['class'];
			}
			$module_doctrine_path = dirname($modules_path . '/' . $module_file) . '/mongodb';
			if (is_dir($module_doctrine_path))
			{
				$directories[] = $module_doctrine_path;
			}
		}
	}

	/* setup doctrine and return document manager */
	$config = new Doctrine\ODM\MongoDB\Configuration();
	$driver = new Doctrine\ODM\MongoDB\Mapping\Driver\YamlDriver($directories);
	// $driver->registerAnnotationClasses();
	$config->setMetadataDriverImpl($driver);
	$config->setProxyDir($kernel->expand('{path:tmp}') . '/mongodb/proxies');
	$config->setProxyNamespace('Proxies');
	$config->setHydratorDir($kernel->expand('{path:tmp}') . '/mongodb/hydrators');
	$config->setHydratorNamespace('Hydrators');

	if ($kernel->debug())
	{
		$config->setAutoGenerateProxyClasses(true);
	}

	$server = 'mongodb://localhost:27017';
	if (isset($settings['server']))
	{
		$server = $settings['server'];
	}

	if (isset($settings['dbname']))
	{
		$config->setDefaultDB($settings['dbname']);
	}

	$connection            = new Doctrine\MongoDB\Connection($server);
	$document_manager = Doctrine\ODM\MongoDB\DocumentManager::create($connection, $config);

	return $document_manager;
}
