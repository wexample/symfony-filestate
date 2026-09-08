<?php

namespace Wexample\SymfonyFilestate\Service\Rule;

/**
 * A rule that computes the expected content of a file.
 *
 * Rules never touch the filesystem: filestate is the only writer.
 */
abstract class AbstractRule
{
    abstract public static function getName(): string;

    abstract public function rectify(
        string $path,
        string $content
    ): string;

    /**
     * Adds a `use` statement for a class the rectified content will name.
     *
     * The new line lands at the end of the import block rather than in
     * alphabetical order, which the php-cs-fixer pass on the same file then
     * puts right.
     */
    protected function importClass(
        string $content,
        string $className
    ): string {
        $importLine = 'use '.$className.";\n";

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
