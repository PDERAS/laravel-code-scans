<?php

namespace Pderas\LaravelCodeScans\Stan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Exit_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Check for the different exit like calls (exit, die, etc)
 */
class DisallowExitLikeCallsRule implements Rule
{
    public function getNodeType(): string
    {
        return Exit_::class;
    }

    /**
     * @param Exit_ $node
     * @return array
     */
    public function processNode(Node $node, Scope $scope): array
    {
        return [
            RuleErrorBuilder::message('Disallowed exit/die usage.')
                ->identifier('pderas.disallow.exit')
                ->build()
        ];
    }
}
