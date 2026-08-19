<?php

namespace Wexample\SymfonyFilestate\Service;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Wexample\SymfonyFilestate\Service\Rule\AbstractRule;

class RectifyService
{
    public function __construct(
        #[TaggedIterator('wexample.filestate.rule')]
        private readonly iterable $rules
    ) {
    }

    /**
     * @param string[] $ruleNames
     * @param string[] $paths
     *
     * @return array{files: array<string, string>, violations: string[]}
     */
    public function rectify(
        array $ruleNames,
        array $paths
    ): array {
        $rules = $this->resolveRules($ruleNames);
        $files = [];
        $violations = [];

        foreach ($paths as $path) {
            if (! is_file($path)) {
                $violations[] = 'missing_file: '.$path;
                continue;
            }

            $original = file_get_contents($path);
            $content = $original;

            foreach ($rules as $rule) {
                $rectified = $rule->rectify($path, $content);

                if ($rectified !== $content) {
                    $violations[] = $rule::getName().': '.$path;
                    $content = $rectified;
                }
            }

            if ($content !== $original) {
                $files[$path] = $content;
            }
        }

        return [
            'files' => $files,
            'violations' => $violations,
        ];
    }

    /**
     * @return array<string, AbstractRule>
     */
    public function getAvailableRules(): array
    {
        $available = [];

        foreach ($this->rules as $rule) {
            $available[$rule::getName()] = $rule;
        }

        return $available;
    }

    /**
     * @param string[] $ruleNames
     *
     * @return AbstractRule[]
     */
    private function resolveRules(array $ruleNames): array
    {
        $available = $this->getAvailableRules();
        $unknown = array_diff($ruleNames, array_keys($available));

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Unknown rule(s): '.implode(', ', $unknown)
                .'. Available: '.implode(', ', array_keys($available)).'.'
            );
        }

        return array_map(
            static fn (string $name): AbstractRule => $available[$name],
            $ruleNames
        );
    }
}
