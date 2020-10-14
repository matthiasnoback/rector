<?php

declare(strict_types=1);

namespace Rector\NodeCollector\Reflection;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\Php\PhpParameterReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Type;
use PHPStan\Type\TypeUtils;
use Rector\Core\Exception\ShouldNotHappenException;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Symplify\PackageBuilder\Reflection\PrivatesCaller;

final class MethodReflectionProvider
{
    /**
     * @var NodeTypeResolver
     */
    private $nodeTypeResolver;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    /**
     * @var NodeNameResolver
     */
    private $nodeNameResolver;

    /**
     * @var PrivatesCaller
     */
    private $privatesCaller;

    public function __construct(
        NodeTypeResolver $nodeTypeResolver,
        NodeNameResolver $nodeNameResolver,
        ReflectionProvider $reflectionProvider,
        PrivatesCaller $privatesCaller
    ) {
        $this->nodeTypeResolver = $nodeTypeResolver;
        $this->reflectionProvider = $reflectionProvider;
        $this->nodeNameResolver = $nodeNameResolver;
        $this->privatesCaller = $privatesCaller;
    }

    /**
     * @return PhpParameterReflection[]
     */
    public function getMethodReflectionParameterReflections(MethodReflection $methodReflection): array
    {
        /** @var PhpParameterReflection[] $phpParameterReflections */
        $phpParameterReflections = $this->privatesCaller->callPrivateMethod($methodReflection, 'getParameters');

        return $phpParameterReflections;
    }

    /**
     * @return Type[]
     */
    public function provideParameterTypesFromMethodReflection(MethodReflection $methodReflection): array
    {
        $parameterTypes = [];

        $phpParameterReflections = $this->getMethodReflectionParameterReflections($methodReflection);
        foreach ($phpParameterReflections as $key => $phpParameterReflection) {
            $parameterTypes[] = $phpParameterReflection->getType();
        }

        return $parameterTypes;
    }

    public function provideByClassAndMethodName(string $class, string $method, Scope $scope): ?MethodReflection
    {
        $reflectionClass = $this->reflectionProvider->getClass($class);
        if (! $reflectionClass->hasMethod($method)) {
            return null;
        }

        return $reflectionClass->getMethod($method, $scope);
    }

    public function provideByStaticCall(StaticCall $staticCall): ?MethodReflection
    {
        $objectType = $this->nodeTypeResolver->resolve($staticCall->class);
        $classes = TypeUtils::getDirectClassNames($objectType);

        $methodName = $this->nodeNameResolver->getName($staticCall->name);

        /** @var Scope|null $scope */
        $scope = $staticCall->getAttribute(AttributeKey::SCOPE);
        if ($scope === null) {
            throw new ShouldNotHappenException();
        }

        foreach ($classes as $class) {
            $methodReflection = $this->provideByClassAndMethodName($class, $methodName, $scope);
            if ($methodReflection instanceof MethodReflection) {
                return $methodReflection;
            }
        }

        return null;
    }
}
