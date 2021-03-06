<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Amf
 */

namespace ZendAmf\Parser;

use Zend\Loader\PluginClassLocator;

/**
 * Loads a local class and executes the instantiation of that class.
 *
 * @todo       PHP 5.3 can drastically change this class w/ namespace and the new call_user_func w/ namespace
 * @package    Zend_Amf
 * @subpackage Parser
 */
final class TypeLoader
{
    /**
     * @var string callback class
     */
    public static $callbackClass;

    /**
     * @var array AMF class map
     */
    public static $classMap = array (
        'flex.messaging.messages.AcknowledgeMessage' => 'ZendAmf\\Value\\Messaging\\AcknowledgeMessage',
        'flex.messaging.messages.AsyncMessage'       => 'ZendAmf\\Value\\Messaging\\AsyncMessage',
        'flex.messaging.messages.CommandMessage'     => 'ZendAmf\\Value\\Messaging\\CommandMessage',
        'flex.messaging.messages.ErrorMessage'       => 'ZendAmf\\Value\\Messaging\\ErrorMessage',
        'flex.messaging.messages.RemotingMessage'    => 'ZendAmf\\Value\\Messaging\\RemotingMessage',
        'flex.messaging.io.ArrayCollection'          => 'ZendAmf\\Value\\Messaging\\ArrayCollection',
    );

    /**
     * @var array Default class map
     */
    protected static $_defaultClassMap = array(
        'flex.messaging.messages.AcknowledgeMessage' => 'ZendAmf\\Value\\Messaging\\AcknowledgeMessage',
        'flex.messaging.messages.AsyncMessage'       => 'ZendAmf\\Value\\Messaging\\AsyncMessage',
        'flex.messaging.messages.CommandMessage'     => 'ZendAmf\\Value\\Messaging\\CommandMessage',
        'flex.messaging.messages.ErrorMessage'       => 'ZendAmf\\Value\\Messaging\\ErrorMessage',
        'flex.messaging.messages.RemotingMessage'    => 'ZendAmf\\Value\\Messaging\\RemotingMessage',
        'flex.messaging.io.ArrayCollection'          => 'ZendAmf\\Value\\Messaging\\ArrayCollection',
    );

    /**
     * @var \Zend\Loader\PluginClassLocator
     */
    protected static $_resourceLoader = null;

    /**
     * Load the mapped class type into a callback.
     *
     * @param  string $className
     * @return object|bool
     */
    public static function loadType($className)
    {
        $class = static::getMappedClassName($className);
        if(!$class) {
            $class = str_replace('.', '\\', $className);
        }
        if (!class_exists($class)) {
            return "stdClass";
        }
        return $class;
    }

    /**
     * Looks up the supplied call name to its mapped class name
     *
     * @param  string $className
     * @return string
     */
    public static function getMappedClassName($className)
    {
        $mappedName = array_search($className, static::$classMap);

        if ($mappedName) {
            return $mappedName;
        }

        $mappedName = array_search($className, array_flip(static::$classMap));

        if ($mappedName) {
            return $mappedName;
        }

        return false;
    }

    /**
     * Map PHP class names to ActionScript class names
     *
     * Allows users to map the class names of there action script classes
     * to the equivelent php class name. Used in deserialization to load a class
     * and serialiation to set the class name of the returned object.
     *
     * @param  string $asClassName
     * @param  string $phpClassName
     * @return void
     */
    public static function setMapping($asClassName, $phpClassName)
    {
        static::$classMap[$asClassName] = $phpClassName;
    }

    /**
     * Reset type map
     *
     * @return void
     */
    public static function resetMap()
    {
        static::$classMap = static::$_defaultClassMap;
    }

    /**
     * Get loader for resource type handlers.
     * 
     * @return \Zend\Loader\PluginClassLocator
     */
    public static function getResourceLoader()
    {
        return static::$_resourceLoader;
    }

    /**
     * Set loader for resource type handlers
     *
     * @param \Zend\Loader\PluginClassLocator $loader
     */
    public static function setResourceLoader(PluginClassLocator $loader)
    {
        static::$_resourceLoader = $loader;
    }

    /**
     * Get plugin class that handles this resource
     *
     * @param resource $resource Resource type
     * @return object Resource class
     */
    public static function getResourceParser($resource)
    {
        if (static::$_resourceLoader) {
            $type = preg_replace("/[^A-Za-z0-9_]/", " ", get_resource_type($resource));
            $type = str_replace(" ","", ucwords($type));
            return static::$_resourceLoader->load($type);
        }
        return false;
    }

    /**
     * Convert resource to a serializable object
     *
     * @param resource $resource
     * @return mixed
     * @throws Exception\ExceptionInterface
     */
    public static function handleResource($resource)
    {
        if (!static::$_resourceLoader) {
            throw new Exception\InvalidArgumentException('Unable to handle resources - resource plugin loader not set');
        }
        try {
            while (is_resource($resource)) {
                $resclass = static::getResourceParser($resource);
                if (!$resclass) {
                    throw new Exception\RuntimeException('Can not serialize resource type: '. get_resource_type($resource));
                }
                $parser = new $resclass;
                if (!is_callable(array($parser, 'parse'))) {
                    throw new Exception\RuntimeException("Could not call parse() method on class $resclass");
                }
                $resource = $parser->parse($resource);
            }
            return $resource;
        } catch(Exception\ExceptionInterface $e) {
            throw new Exception\InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        } catch(\Exception $e) {
            throw new Exception\RuntimeException('Can not serialize resource type: '. get_resource_type($resource), 0, $e);
        }
    }
}
