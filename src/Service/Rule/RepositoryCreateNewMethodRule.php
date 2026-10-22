<?php

namespace Wexample\SymfonyFilestate\Service\Rule;

class RepositoryCreateNewMethodRule extends AbstractRule
{
    public static function getName(): string
    {
        return 'repository_create_new_method';
    }

    public function rectify(
        string $path,
        string $content
    ): string {
        if (! preg_match('/^\s*(?:final\s+)?class\s+(\w+)\s+extends\s+AbstractRepository\b/m', $content, $matches)) {
            return $content;
        }

        $entityShortName = $this->resolveEntityShortName($content, $matches[1]);

        if ($entityShortName === null) {
            return $content;
        }

        $methodName = 'createNew'.$entityShortName;

        if (preg_match('/function\s+'.preg_quote($methodName, '/').'\s*\(/', $content)) {
            return $content;
        }

        $content = $this->importEntity($content, $entityShortName);

        $method = <<<PHP
    public function {$methodName}(): {$entityShortName}
    {
        return new {$entityShortName}();
    }
PHP;

        // One blank line between members, none right after the class brace.
        $glue = preg_match('/\{\s*\n\s*\}\s*$/', $content) ? "\n" : "\n\n";

        return preg_replace('/\n}\s*$/', $glue.$method."\n}\n", $content, 1);
    }

    private function resolveEntityShortName(
        string $content,
        string $className
    ): ?string {
        if (preg_match('/getEntityClassName\(\)\s*:\s*string\s*\{\s*return\s+(\w+)::class/s', $content, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^(\w+)Repository$/', $className, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function importEntity(
        string $content,
        string $entityShortName
    ): string {
        if (preg_match('/^use\s+[^;]*\\\\'.preg_quote($entityShortName, '/').';$/m', $content)) {
            return $content;
        }

        if (! preg_match('/^namespace\s+(\S+);/m', $content, $matches)) {
            return $content;
        }

        // Entities live next to their repositories, one namespace aside.
        $entityNamespace = preg_replace('/Repository$/', 'Entity', $matches[1]);
        $importLine = 'use '.$entityNamespace.'\\'.$entityShortName.";\n";

        if (preg_match_all('/^use\s+[^;]+;\n/m', $content, $imports, PREG_OFFSET_CAPTURE)) {
            $lastImport = end($imports[0]);

            return substr_replace(
                $content,
                $importLine,
                $lastImport[1] + strlen($lastImport[0]),
                0
            );
        }

        return preg_replace(
            '/^namespace\s+[^;]+;\n/m',
            "$0\n".$importLine,
            $content,
            1
        );
    }
}
