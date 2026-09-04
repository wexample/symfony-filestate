<?php

namespace Wexample\SymfonyFilestate\Service\Rule;

class EntityExtendRule extends AbstractRule
{
    public static function getName(): string
    {
        return 'entity_extend';
    }

    public function rectify(
        string $path,
        string $content
    ): string {
        if (! preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)\b(?!\s+extends)/m', $content, $matches)) {
            return $content;
        }

        $className = $matches[1];

        if (! str_contains($content, 'use Wexample\\SymfonyHelpers\\Entity\\AbstractEntity;')) {
            $content = preg_replace(
                '/^namespace\s+[^;]+;\n/m',
                "$0\nuse Wexample\\SymfonyHelpers\\Entity\\AbstractEntity;\n",
                $content,
                1
            );
        }

        return preg_replace(
            '/\bclass\s+'.preg_quote($className, '/').'\b(?!\s+extends)(\s*(?:implements\s+[^{]+)?)\s*\{/m',
            'class '.$className.' extends AbstractEntity$1 {',
            $content,
            1
        );
    }
}
