<?php

namespace Rector\TypeDeclaration\Tests\Rector\FunctionLike\ReturnTypeDeclarationRector\Fixture\Dunglas;

interface BarInterface
{
    /**
     * @return float
     */
    public function baz(array $a, $b);
}
?>
-----
<?php

namespace Rector\TypeDeclaration\Tests\Rector\FunctionLike\ReturnTypeDeclarationRector\Fixture\Dunglas;

interface BarInterface
{
    public function baz(array $a, $b): float;
}
?>
