<?php

namespace Wexample\SymfonyFilestate\Service\Rule;

class HasSecureIdTraitRule extends AbstractRule
{
    private const TRAIT_FQCN = 'Wexample\\SymfonyHelpers\\Entity\\Traits\\HasSecureIdTrait';

    public static function getName(): string
    {
        return 'has_secure_id_trait';
    }

    public function rectify(
        string $path,
        string $content
    ): string {
        if (! preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m', $content, $matches)) {
            return $content;
        }

        $className = $matches[1];

        if (! str_contains($content, 'use '.self::TRAIT_FQCN.';')) {
            $content = preg_replace(
                '/^namespace\s+[^;]+;\n/m',
                "$0\nuse ".self::TRAIT_FQCN.";\n",
                $content,
                1
            );
        }

        // The leading backslash is required: without it the pattern would also
        // match the top-level import, which is not a trait usage.
        $alreadyUsed = preg_match('/^\s*use\s+HasSecureIdTrait\s*;/m', $content)
            || preg_match('/^\s*use\s+\\\\'.preg_quote(self::TRAIT_FQCN, '/').'\s*;/m', $content);

        if (! $alreadyUsed) {
            $content = preg_replace(
                '/(class\s+'.preg_quote($className, '/').'\b[^{]*\{)\n/m',
                "$1\n    use HasSecureIdTrait;\n",
                $content,
                1
            );
        }

        return $content;
    }
}
