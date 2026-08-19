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
}
