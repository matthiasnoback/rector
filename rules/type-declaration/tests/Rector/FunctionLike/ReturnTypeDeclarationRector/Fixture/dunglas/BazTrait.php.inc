<?php

namespace Rector\TypeDeclaration\Tests\Rector\FunctionLike\ReturnTypeDeclarationRector\Fixture\Dunglas;

trait BazTrait
{
    /**
     * @return \DateTime
     */
    protected function inTrait($a)
    {
        return $a;
    }
}

?>
-----
<?php

namespace Rector\TypeDeclaration\Tests\Rector\FunctionLike\ReturnTypeDeclarationRector\Fixture\Dunglas;

trait BazTrait
{
    protected function inTrait($a): \DateTime
    {
        return $a;
    }
}

?>
