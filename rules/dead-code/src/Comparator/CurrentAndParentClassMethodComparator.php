<?php

declare(strict_types=1);

namespace Rector\DeadCode\Comparator;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\Type;
use Rector\Core\Exception\ShouldNotHappenException;
use Rector\Core\PhpParser\Node\Value\ValueResolver;
use Rector\Core\PhpParser\Printer\BetterStandardPrinter;
use Rector\NodeCollector\NodeCollector\NodeRepository;
use Rector\NodeCollector\Reflection\MethodReflectionProvider;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Symplify\PackageBuilder\Reflection\PrivatesCaller;

final class CurrentAndParentClassMethodComparator
{
    /**
     * @var NodeNameResolver
     */
    private $nodeNameResolver;

    /**
     * @var ValueResolver
     */
    private $valueResolver;

    /**
     * @var BetterStandardPrinter
     */
    private $betterStandardPrinter;

    /**
     * @var NodeRepository
     */
    private $nodeRepository;

    /**
     * @var MethodReflectionProvider
     */
    private $methodReflectionProvider;

    /**
     * @var PrivatesCaller
     */
    private $privatesCaller;

    public function __construct(
        PrivatesCaller $privatesCaller,
        NodeNameResolver $nodeNameResolver,
        ValueResolver $valueResolver,
        BetterStandardPrinter $betterStandardPrinter,
        NodeRepository $nodeRepository,
        MethodReflectionProvider $methodReflectionProvider
    ) {
        $this->nodeNameResolver = $nodeNameResolver;
        $this->valueResolver = $valueResolver;
        $this->betterStandardPrinter = $betterStandardPrinter;
        $this->nodeRepository = $nodeRepository;
        $this->methodReflectionProvider = $methodReflectionProvider;
        $this->privatesCaller = $privatesCaller;
    }

    public function isParentCallMatching(ClassMethod $classMethod, ?StaticCall $staticCall): bool
    {
        if ($staticCall === null) {
            return false;
        }

        if (! $this->nodeNameResolver->areNamesEqual($staticCall->name, $classMethod->name)) {
            return false;
        }

        if (! $this->nodeNameResolver->isName($staticCall->class, 'parent')) {
            return false;
        }

        $parentMethodReflection = $this->methodReflectionProvider->provideByStaticCall($staticCall);
        if ($parentMethodReflection === null) {
            return false;
        }

        $parentParameterTypes = $this->methodReflectionProvider->provideParameterTypesFromMethodReflection(
            $parentMethodReflection
        );
        if (! $this->areArgsAndParamsEqual($staticCall->args, $classMethod->params, $parentParameterTypes)) {
            return false;
        }

        return ! $this->isParentClassMethodVisibilityOrDefaultOverride($classMethod, $staticCall);
    }

    /**
     * @param Arg[] $parentStaticCallArgs
     * @param Param[] $currentClassMethodParams
     * @param Type[] $parentParameterTypes
     */
    private function areArgsAndParamsEqual(
        array $parentStaticCallArgs,
        array $currentClassMethodParams,
        array $parentParameterTypes
    ): bool {
        if (count($parentStaticCallArgs) !== count($currentClassMethodParams)) {
            return false;
        }

        if (count($parentStaticCallArgs) === 0) {
            return true;
        }

        foreach ($parentStaticCallArgs as $key => $arg) {
            if (! isset($currentClassMethodParams[$key])) {
                return false;
            }

            $param = $currentClassMethodParams[$key];
            if (! $this->betterStandardPrinter->areNodesEqual($param->var, $arg->value)) {
                return false;
            }
        }

        // are param types equal
//        dump($currentClassMethodParams);
//        dump($parentParameterTypes);

        return true;
    }

    private function isParentClassMethodVisibilityOrDefaultOverride(
        ClassMethod $classMethod,
        StaticCall $staticCall
    ): bool {
        /** @var string $className */
        $className = $staticCall->getAttribute(AttributeKey::CLASS_NAME);

        $parentClassName = get_parent_class($className);
        if (! $parentClassName) {
            throw new ShouldNotHappenException();
        }

        /** @var string $methodName */
        $methodName = $this->nodeNameResolver->getName($staticCall->name);

        $parentClassMethod = $this->nodeRepository->findClassMethod($parentClassName, $methodName);
        if ($parentClassMethod !== null && $parentClassMethod->isProtected() && $classMethod->isPublic()) {
            return true;
        }

        return $this->checkOverrideUsingReflection($classMethod, $parentClassName, $methodName);
    }

    private function checkOverrideUsingReflection(
        ClassMethod $classMethod,
        string $parentClassName,
        string $methodName
    ): bool {
        // @todo use phpstan reflecoitn
        $scope = $classMethod->getAttribute(AttributeKey::SCOPE);

        $parentMethodReflection = $this->methodReflectionProvider->provideByClassAndMethodName(
            $parentClassName,
            $methodName,
            $scope
        );

        // 3rd party code
        if ($parentMethodReflection !== null) {
            if (! $parentMethodReflection->isPrivate() && ! $parentMethodReflection->isPublic() && $classMethod->isPublic()) {
                return true;
            }

            if ($parentMethodReflection->isInternal()->yes()) {
                // we can't know for certain so we assume its a override with purpose
                return true;
            }

            if ($this->areParameterDefaultsDifferent($classMethod, $parentMethodReflection)) {
                return true;
            }
        }

        return false;
    }

    private function areParameterDefaultsDifferent(
        ClassMethod $classMethod,
        MethodReflection $methodReflection
    ): bool {
        $parameterReflections = $this->methodReflectionProvider->getMethodReflectionParameterReflections(
            $methodReflection
        );

        foreach ($parameterReflections as $key => $parameterReflection) {
            if (! isset($classMethod->params[$key])) {
                if ($parameterReflection->getDefaultValue()) {
                    continue;
                }

                return true;
            }

            $methodParam = $classMethod->params[$key];

            if ($this->areDefaultValuesDifferent($parameterReflection, $methodParam)) {
                return true;
            }
        }

        return false;
    }

    private function areDefaultValuesDifferent(ParameterReflection $parameterReflection, Param $methodParam): bool
    {
        if ($parameterReflection->getDefaultValue() === $methodParam->default) {
            return false;
        }

        if ($parameterReflection->getDefaultValue() !== isset($methodParam->default)) {
            return true;
        }

        return $parameterReflection->getDefaultValue() && $methodParam->default !== null &&
            ! $this->valueResolver->isValue($methodParam->default, $parameterReflection->getDefaultValue());
    }
}
