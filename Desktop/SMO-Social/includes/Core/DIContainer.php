<?php
/**
 * Dependency Injection Container
 * 
 * Modern DI container for improved testability and modularity
 */

namespace SMO_Social\Core;

class DIContainer
{
    private static ?DIContainer $instance = null;
    private array $bindings = [];
    private array $singletons = [];
    private array $factories = [];

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Bind an abstract to a concrete implementation
     */
    public function bind(string $abstract, callable $factory, bool $singleton = false): void
    {
        if ($singleton) {
            $this->singletons[$abstract] = null;
        } else {
            $this->factories[$abstract] = $factory;
        }
    }

    /**
     * Register a singleton instance
     */
    public function singleton(string $abstract, $instance): void
    {
        $this->singletons[$abstract] = $instance;
    }

    /**
     * Resolve an instance from the container
     * 
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function resolve(string $abstract, array $parameters = [])
    {
        // Check for singleton instance
        if (array_key_exists($abstract, $this->singletons)) {
            if ($this->singletons[$abstract] === null) {
                // Lazy initialization of singleton
                $this->singletons[$abstract] = $this->createInstance($abstract, $parameters);
            }
            return $this->singletons[$abstract];
        }

        // Check for factory binding
        if (isset($this->factories[$abstract])) {
            return $this->factories[$abstract](...$parameters);
        }

        // Check if it's a class that can be instantiated
        if (class_exists($abstract)) {
            return $this->createInstance($abstract, $parameters);
        }

        throw new \InvalidArgumentException("Cannot resolve abstract '{$abstract}': no binding found");
    }

    /**
     * Check if an abstract is bound
     */
    public function has(string $abstract): bool
    {
        return array_key_exists($abstract, $this->singletons) || 
               isset($this->factories[$abstract]) || 
               class_exists($abstract);
    }

    /**
     * Clear all bindings
     */
    public function clear(): void
    {
        $this->bindings = [];
        $this->singletons = [];
        $this->factories = [];
    }

    /**
     * Create an instance using reflection
     */
    private function createInstance(string $class, array $parameters = [])
    {
        $reflection = new \ReflectionClass($class);
        
        if (!$reflection->isInstantiable()) {
            throw new \InvalidArgumentException("Cannot instantiate abstract class '{$class}'");
        }

        $constructor = $reflection->getConstructor();
        
        if ($constructor === null) {
            return new $class();
        }

        $constructor_parameters = $constructor->getParameters();
        
        if (empty($constructor_parameters)) {
            return new $class();
        }

        // Try to resolve constructor parameters
        $resolved_parameters = [];

        foreach ($constructor_parameters as $parameter) {
            $parameter_name = $parameter->getName();
            $parameter_type = $parameter->getType();

            if ($parameter_type && !$parameter_type->isBuiltin()) {
                $type_name = $parameter_type->getName();

                // Special handling for common AI dependencies
                if ($type_name === 'SMO_Social\AI\Models\UniversalManager') {
                    // Try to resolve UniversalManager with default provider
                    $resolved_parameters[] = $this->resolveUniversalManager();
                }
                elseif ($type_name === 'SMO_Social\AI\CacheManager') {
                    // Try to resolve CacheManager
                    $resolved_parameters[] = $this->resolveCacheManager();
                }
                elseif ($this->has($type_name)) {
                    $resolved_parameters[] = $this->resolve($type_name);
                } elseif (isset($parameters[$parameter_name])) {
                    $resolved_parameters[] = $parameters[$parameter_name];
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $resolved_parameters[] = $parameter->getDefaultValue();
                } else {
                    throw new \InvalidArgumentException(
                        "Cannot resolve parameter '\$parameter_name' of type '{$type_name}' for class '{$class}'"
                    );
                }
            } elseif (isset($parameters[$parameter_name])) {
                $resolved_parameters[] = $parameters[$parameter_name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $resolved_parameters[] = $parameter->getDefaultValue();
            } else {
                throw new \InvalidArgumentException(
                    "Cannot resolve parameter '\$parameter_name' for class '{$class}'"
                );
            }
        }

        return new $class(...$resolved_parameters);
    }

    /**
     * Resolve UniversalManager instance
     */
    private function resolveUniversalManager() {
        // Check if we have a cached UniversalManager instance
        if (!isset($this->singletons['SMO_Social\AI\Models\UniversalManager'])) {
            try {
                // Try to get primary provider from settings or use default
                $primary_provider = 'huggingface'; // Default fallback

                // Check if we can get from WordPress options
                if (function_exists('get_option')) {
                    $ai_settings = get_option('smo_social_ai_settings', []);
                    $primary_provider = $ai_settings['primary_provider'] ?? $primary_provider;
                }

                // Create and cache the UniversalManager instance
                $manager = new \SMO_Social\AI\Models\UniversalManager($primary_provider);
                $this->singletons['SMO_Social\AI\Models\UniversalManager'] = $manager;
            } catch (\Exception $e) {
                error_log("DIContainer: Failed to create UniversalManager: " . $e->getMessage());
                throw new \InvalidArgumentException("Cannot create UniversalManager: " . $e->getMessage());
            }
        }

        return $this->singletons['SMO_Social\AI\Models\UniversalManager'];
    }

    /**
     * Resolve CacheManager instance
     */
    private function resolveCacheManager() {
        // Check if we have a cached CacheManager instance
        if (!isset($this->singletons['SMO_Social\AI\CacheManager'])) {
            try {
                $manager = new \SMO_Social\AI\CacheManager();
                $this->singletons['SMO_Social\AI\CacheManager'] = $manager;
            } catch (\Exception $e) {
                error_log("DIContainer: Failed to create CacheManager: " . $e->getMessage());
                throw new \InvalidArgumentException("Cannot create CacheManager: " . $e->getMessage());
            }
        }

        return $this->singletons['SMO_Social\AI\CacheManager'];
    }
}