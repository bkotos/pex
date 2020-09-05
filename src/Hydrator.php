<?php

namespace Pex;

class Hydrator
{
    const PHP_DOC_PROPERTY_TYPE_EXPRESSION = '/@var +([\\a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)[ \n*]/';

    const PHP_USE_STATEMENT_EXPRESSION = '/(?:;|&&|&|\|\||\|)[ \n\r]+use[ \n\r]+((?:\x92?[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*\x92?)+)/';

    const PHP_NAMESPACE_STATEMENT_EXPRESSION = '/^<\?php[ \n\r]+namespace[ \n\r]+(.*);/';

    /** @var string[][] */
    private static $propertyTypeCache = [];

    /** @var \ReflectionClass[] */
    private static $reflectionCache = [];

    public function hydrateEntity(string $className, array $data)
    {
        $raisedData = $this->raiseData($data);

        return $this->hydrateEntityFromNestedData($className, $raisedData);
    }

    public function hydrateEntities(string $className, array $dataCollection): array
    {
        $entities = [];
        foreach ($dataCollection as $data) {
            $entities[] = $this->hydrateEntity($className, $data);
        }

        return $entities;
    }

    private function hydrateEntityFromNestedData(string $className, array $nestedData)
    {
        if (!class_exists($className)) {
            throw new \Exception("Entity '$className' not found.");
        }

        $entity = new $className();

        // TODO find a better solution than this
        $hasAtLeastOneNotNullValue = false;
        foreach ($nestedData as $propertyName => $value) {
            if (property_exists($className, $propertyName)) {
                $propertyType = $this->getPropertyType($className, $propertyName);
                $isCastable = in_array($propertyType, ['int', 'integer', 'float', 'double', 'string', 'bool', 'boolean']);

                if ($isCastable) {
                    if (!is_null($value)) {
                        settype($value, $propertyType);
                    }
                } else {
                    $namespacePath = $this->getNamespacePathForAlias($className, $propertyType);
                    $isInstantiatable = class_exists($namespacePath) && is_array($value);
                    if ($isInstantiatable) {
                        $value = $this->hydrateEntityFromNestedData($namespacePath, $value);
                    }
                }

                if (!is_null($value)) {
                    $hasAtLeastOneNotNullValue = true;
                }

                $entity->{$propertyName} = $value;
            }
        }

        // TODO why is null thing still not working
        return $hasAtLeastOneNotNullValue ? $entity : null;
    }

    private function getPropertyType(string $className, string $propertyName)
    {
        // TODO use new syntax here
        $areOtherPropertiesCached = isset(self::$propertyTypeCache[$className]);
        $isPropertyTypeCached = $areOtherPropertiesCached && isset(self::$propertyTypeCache[$className][$propertyName]);
        if ($isPropertyTypeCached) {
            return self::$propertyTypeCache[$className][$propertyName];
        }

        $reflection = $this->getReflectionClass($className);
        $property = $reflection->getProperty($propertyName);
        $docBlock = $property->getDocComment();
        $hasDocBlock = $docBlock !== false;

        $propertyType = null;
        if ($hasDocBlock) {
            preg_match_all(self::PHP_DOC_PROPERTY_TYPE_EXPRESSION, $docBlock, $matches);
            [$fullMatches, $capturedMatches] = $matches;
            [$propertyType] = $capturedMatches;
        }

        if (!$areOtherPropertiesCached) {
            self::$propertyTypeCache[$className] = [];
        }
        self::$propertyTypeCache[$className][$propertyName] = $propertyType;

        return $propertyType;
    }

    private function raiseData(array $flattData, $delimiter = '.')
    {
        $tree = [];
        foreach ($flattData as $joinedKey => $leafValue) {
            $keyBranches = explode($delimiter, $joinedKey);
            $this->nest($tree, $keyBranches, $leafValue);
        }

        return $tree;
    }

    private function nest(array &$tree, array $keyBranches, $leafValue)
    {
        $currentKeyBranch = array_shift($keyBranches);
        $isLeaf = count($keyBranches) === 0;

        if ($isLeaf) {
            $tree[$currentKeyBranch] = $leafValue;
        } else {
            $isBranchInitialized = isset($tree[$currentKeyBranch]);
            if (!$isBranchInitialized) {
                $tree[$currentKeyBranch] = [];
            }

            $this->nest($tree[$currentKeyBranch], $keyBranches, $leafValue);
        }
    }

    // TODO rename method and $subject
    private function getNamespacePathForAlias(string $className, string $aliasName)
    {
        $reflection = $this->getReflectionClass($className);
        $filePath = $reflection->getFileName();
        $phpCode = file_get_contents($filePath);

        // see if the class is located in the same namespace
        $namespace = $this->getNamespaceFromPhpCode($phpCode);
        if ($namespace) {
            $namespacePath = "$namespace\\$aliasName";
            if (class_exists($namespacePath)) {
                return $namespacePath;
            }
        }

        preg_match_all(self::PHP_USE_STATEMENT_EXPRESSION, $phpCode, $matches);
        [$fullMatches, $capturedMatches] = $matches;

        foreach ($capturedMatches as $fullNamespacePath) {
            if ($fullNamespacePath === $aliasName && class_exists($fullNamespacePath)) {
                return $fullNamespacePath;
            }

            $splitNamespacePath = explode('\\', $fullNamespacePath);
            $head = end($splitNamespacePath);
            if ($head === $aliasName && class_exists($fullNamespacePath)) {
                return $fullNamespacePath;
            }
        }

        throw new \Exception("Namespace path for '$aliasName' not found.");
    }

    /**
     * @param string $phpCode
     * @return string|null
     */
    private function getNamespaceFromPhpCode(string $phpCode)
    {
        preg_match_all(self::PHP_NAMESPACE_STATEMENT_EXPRESSION, $phpCode, $matches);
        [$fullMatches, $capturedMatches] = $matches;
        [$namespace] = $capturedMatches;

        return $namespace;
    }

    /**
     * @param string $className
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    private function getReflectionClass(string $className): \ReflectionClass
    {
        $isReflectionCached = isset(self::$reflectionCache[$className]);
        if (!$isReflectionCached) {
            // TODO maybe create method from this, to read more like prose
            self::$reflectionCache[$className] = new \ReflectionClass($className);
        }

        return self::$reflectionCache[$className];
    }
}
