<?php

namespace Pderas\LaravelCodeScans\Stan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Check for debug helper functions that didn't get removed
 */
class DisallowDebugHelpersRule implements Rule
{
    /** @var array */
    private array $debug_helpers = [
        'dd'   => true,
        'dump' => true,
    ];

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @param FuncCall $node
     * @return array
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $name = strtolower($node->name->toString());
        if (!isset($this->debug_helpers[$name])) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf('Disallowed debug helper %s() used.', $name))
                ->identifier('pderas.disallow.debug')
                ->build()
        ];
    }
}
