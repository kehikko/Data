<?php

function em()
{
    static $entity_manager = false;

    /* if entity manager has already been loaded */
    if ($entity_manager !== false) {
        return $entity_manager;
    }

    /* get doctrine settings */
    $settings = cfg(['doctrine', 'sql']);
    if (!$settings) {
        $settings = cfg(['doctrine']);
    }
    if (!$settings) {
        $entity_manager = null;
        return null;
    }

    /* find doctrine definition paths from modules/vendor only */
    $paths = tool_system_find_files(['sql'], [cfg(['path', 'vendor']), cfg(['path', 'modules'])], 2, true);

    /* setup doctrine and return entity manager */
    $config = Doctrine\ORM\Tools\Setup::createYAMLMetadataConfiguration($paths, cfg_debug());
    $config->setProxyDir(cfg(['path', 'cache']) . '/doctrine');
    if (cfg_debug()) {
        $config->setAutoGenerateProxyClasses(true);
    }
    /* caching */
    $cache_instance = null;
    $cache_type     = cfg(['doctrine', 'cache', 'type']);
    if ($cache_type == 'memcache') {
        $memcache = new Memcache();
        $memcache->connect(
            cfg(['doctrine', 'cache', 'host']),
            cfg(['doctrine', 'cache', 'port'])
        );
        $cache_instance = new \Doctrine\Common\Cache\MemcacheCache();
        $cache_instance->setMemcache($memcache);
    } else if ($cache_type == 'memcached') {
        $memcache = new Memcached();
        $memcache->addServer(
            cfg(['doctrine', 'cache', 'host']),
            cfg(['doctrine', 'cache', 'port'])
        );
        $cache_instance = new \Doctrine\Common\Cache\MemcachedCache();
        $cache_instance->setMemcached($memcache);
    } else if ($cache_type == 'xcache') {
        $cache_instance = new \Doctrine\Common\Cache\XcacheCache();
    }
    /* set cache instance to be used by doctrine */
    if ($cache_instance) {
        $config->setQueryCacheImpl($cache_instance);
        $config->setResultCacheImpl($cache_instance);
        $config->setMetadataCacheImpl($cache_instance);
    }

    $entity_manager = Doctrine\ORM\EntityManager::create($settings, $config);

    return $entity_manager;
}
