<?php

namespace Pderas\LaravelCodeScans\Stan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Check for disallowed function calls. Defined in the phpstan.neon config.
 */
class DisallowFunctionCallsRule implements Rule
{
    /** @var array */
    private array $disallowed;

    public function __construct(array $disallowed_functions)
    {
        $map = [];
        foreach ($disallowed_functions as $fn) {
            $map[strtolower($fn)] = true;
        }
        $this->disallowed = $map;
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $name = strtolower($node->name->toString());
        if (!isset($this->disallowed[$name])) {
            return [];
        }

        // Appended to the identifier to make it easier to ignore if needed.
        // Only allow alphanumeric and dot characters in the identifier.
        $id_name = preg_replace('/[^A-Za-z0-9.]/', '', $name) ?? '';
        if ($id_name === '' || !preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9.]*[A-Za-z0-9])?$/', $id_name)) {
            $id_name = 'function';
        }
        $identifier = sprintf('pderas.disallow.function.%s', $id_name);
        return [
            RuleErrorBuilder::message(sprintf('Disallowed function %s() used.', $name))
                ->identifier($identifier)
                ->build()
        ];
    }
}
