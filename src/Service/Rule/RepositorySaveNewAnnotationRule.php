<?php

namespace Wexample\SymfonyFilestate\Service\Rule;

/**
 * `saveNew{X}()` is served by AbstractRepository::__call, so no signature is
 * visible to an IDE: the docblock is where it gets declared.
 */
class RepositorySaveNewAnnotationRule extends AbstractRule
{
    public static function getName(): string
    {
        return 'repository_save_new_annotation';
    }

    public function rectify(
        string $path,
        string $content
    ): string {
        if (! preg_match(
            '/^(?:final\s+)?class\s+(\w+)\s+extends\s+AbstractRepository\b/m',
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            return $content;
        }

        $className = $matches[1][0];
        $classOffset = $matches[0][1];
        $entityShortName = $this->resolveEntityShortName($content, $className);

        if ($entityShortName === null) {
            return $content;
        }

        $arguments = $this->readCreateNewArguments($content, $entityShortName);

        if ($arguments === null) {
            return $content;
        }

        $annotation = ' * @method '.$entityShortName.' saveNew'.$entityShortName.'('.$arguments.')';

        return $this->writeAnnotation(
            $content,
            $classOffset,
            $entityShortName,
            $annotation
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

    private function readCreateNewArguments(
        string $content,
        string $entityShortName
    ): ?string {
        if (! preg_match(
            '/function\s+createNew'.preg_quote($entityShortName, '/').'\s*\((?<arguments>[^)]*)\)/s',
            $content,
            $matches
        )) {
            return null;
        }

        $arguments = preg_replace('/\s+/', ' ', $matches['arguments']);

        return rtrim(trim($arguments), ',');
    }

    private function writeAnnotation(
        string $content,
        int $classOffset,
        string $entityShortName,
        string $annotation
    ): string {
        $header = substr($content, 0, $classOffset);

        if (! preg_match('/\/\*\*[\s\S]*?\*\/\s*$/', $header, $matches, PREG_OFFSET_CAPTURE)) {
            return substr_replace(
                $content,
                "/**\n".$annotation."\n */\n",
                $classOffset,
                0
            );
        }

        $docblock = rtrim($matches[0][0]);
        $existingLine = '/^ \* @method\s+\S+\s+saveNew'.preg_quote($entityShortName, '/').'\(.*\)$/m';

        $updated = preg_match($existingLine, $docblock)
            ? preg_replace($existingLine, $annotation, $docblock, 1)
            : preg_replace('/\n \*\/$/', "\n".$annotation."\n */", $docblock, 1);

        return substr_replace(
            $content,
            $updated,
            $matches[0][1],
            strlen($docblock)
        );
    }
}
