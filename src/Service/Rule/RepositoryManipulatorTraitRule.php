<?php

namespace Wexample\SymfonyFilestate\Service\Rule;

class RepositoryManipulatorTraitRule extends AbstractRule
{
    public static function getName(): string
    {
        return 'repository_manipulator_trait';
    }

    public function rectify(
        string $path,
        string $content
    ): string {
        if (! preg_match('/^\s*(?:final\s+)?class\s+(\w+)\s+extends\s+AbstractRepository\b/m', $content, $matches)) {
            return $content;
        }

        $className = $matches[1];
        $entityShortName = $this->resolveEntityShortName($content, $className);

        // An abstract entity is a shared base: filestate scaffolds no trait for
        // it, so there is nothing to use here either.
        if ($entityShortName === null || str_starts_with($entityShortName, 'Abstract')) {
            return $content;
        }

        $traitShortName = $entityShortName.'EntityManipulatorTrait';
        $content = $this->importTrait($content, $traitShortName);

        // The import and the in-class use are checked apart: a file missing
        // only one of the two is repaired rather than left half wired.
        if (preg_match('/^\s*use\s+'.preg_quote($traitShortName, '/').';\s*$/m', $content)) {
            return $content;
        }

        // One blank line before the first member, none before a closing brace.
        $glue = preg_match('/\{\s*\n\s*\}\s*$/', $content) ? '' : "\n";

        return preg_replace(
            '/(class\s+'.preg_quote($className, '/').'\b[^{]*\{)\n/m',
            "$1\n    use ".$traitShortName.";\n".$glue,
            $content,
            1
        );
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

    private function importTrait(
        string $content,
        string $traitShortName
    ): string {
        if (! preg_match('/^namespace\s+(\S+);/m', $content, $matches)) {
            return $content;
        }

        // Manipulator traits live beside the entities, one namespace aside.
        $traitNamespace = preg_replace(
            '/Repository$/',
            'Entity\\Traits\\Manipulator',
            $matches[1]
        );
        $importLine = 'use '.$traitNamespace.'\\'.$traitShortName.";\n";

        if (str_contains($content, $importLine)) {
            return $content;
        }

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
