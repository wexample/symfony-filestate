<?php

namespace Wexample\SymfonyFilestate\Service\Rule;

class RemoveDefaultIdBlockRule extends AbstractRule
{
    public static function getName(): string
    {
        return 'remove_default_id_block';
    }

    public function rectify(
        string $path,
        string $content
    ): string {
        if (! preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m', $content, $matches)) {
            return $content;
        }

        $className = $matches[1];

        $pattern = '/(class\s+'.preg_quote($className, '/').'\b[^{]*\{)(?<body>[\s\S]*?)(^\})/m';
        if (! preg_match($pattern, $content, $blockMatches)) {
            return $content;
        }

        $normalizedBody = preg_replace('/\s+/', '', $blockMatches['body']);
        $normalizedLegacyIdBlock = preg_replace('/\s+/', '', <<<'PHP'
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column]
private ?int $id = null;

public function getId(): ?int
{
    return $this->id;
}
PHP);

        if ($normalizedBody !== $normalizedLegacyIdBlock) {
            return $content;
        }

        return preg_replace(
            $pattern,
            '$1'."\n".'$3',
            $content,
            1
        );
    }
}
